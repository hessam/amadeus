/**
 * Amadeus Flight Search Frontend JavaScript
 *
 * Handles form interactions, AJAX calls for flight search,
 * results display, and pre-filling an existing Gravity Form on the booking page.
 *
 * @version 2.0.7
 */
(function($) {
    'use strict';

    // --- NEW: Enhanced logger for detailed debugging ---
    const logger = {
        log: (message, data) => console.log(`%c[AFS LOG] ${message}`, 'color: #0073aa; font-weight: bold;', data !== undefined ? data : ''),
        error: (message, data) => console.error(`%c[AFS ERROR] ${message}`, 'color: #dc3232; font-weight: bold;', data !== undefined ? data : '')
    };
    
  // Global variable to store selected location names for easier access.
    let selectedLocationNames = { origin: '', destination: '' };

    // Early exit if the essential configuration object is missing.
    if (typeof amadeus_vars === 'undefined') {
        console.error('Amadeus Flight Search: Localized variables (amadeus_vars) not found.');
        return;
    }

    /**
     * Displays an error message in a specified container.
     * @param {string} message The error message to display.
     * @param {jQuery} $container The jQuery object of the container for the message.
     */
    function showErrorMessage(message, $container) {
        const $errorTarget = $container && $container.length ? $container : $('#afs-results-error-message');
        // Sanitize message before injecting as HTML
        const sanitizedMessage = $('<div/>').text(message).html();
        $errorTarget.html(`<p class="afs-error">${sanitizedMessage}</p>`).show();
        // Clear results only if the error is related to the results container
        if ($container !== $('#afs-results-error-message')) {
            $('#afs-flight-results-container').empty();
        }
    }

    /**
     * Displays a zero state UI when no results are found.
     * @param {string} message The message to display.
     */
    function showZeroState(message) {
        const $container = $('#afs-flight-results-container').empty().show();
        const zeroStateHtml = `
            <div class="afs-zero-state">
                <div class="afs-zero-state__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <h3 class="afs-zero-state__title">${amadeus_vars.text.zero_state_title || 'No Flights Found'}</h3>
                <p class="afs-zero-state__message">${message}</p>
                <div class="afs-zero-state__suggestions">
                    <p>${amadeus_vars.text.zero_state_suggestions || 'Try adjusting your search criteria:'}</p>
                    <ul>
                        <li>${amadeus_vars.text.zero_state_suggestion_1 || 'Check your departure and return dates'}</li>
                        <li>${amadeus_vars.text.zero_state_suggestion_2 || 'Try different airports or nearby cities'}</li>
                        <li>${amadeus_vars.text.zero_state_suggestion_3 || 'Consider flexible dates or different travel classes'}</li>
                    </ul>
                </div>
            </div>
        `;
        $container.html(zeroStateHtml);
        clearErrorMessage();
    }

    /**
     * Initializes the datepickers for departure and return dates.
     */
    function initDatepickers() {
        const $departureDate = $('#afs-departure-date');
        const $returnDate = $('#afs-return-date');
        $departureDate.datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0,
            onSelect: function(selectedDate) {
                $returnDate.datepicker('option', 'minDate', selectedDate);
                // Clear return date if it's earlier than the new departure date
                if ($returnDate.val() && new Date($returnDate.val()) < new Date(selectedDate)) {
                    $returnDate.val('');
                }
            }
        });
        $returnDate.datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0
        });
    }

    /**
     * Initializes location autocomplete with "Any Airport" functionality.
     */
    function initLocationAutocomplete() {
        const config = {
            minLength: 2,
        };

        $('.afs-location-input').each(function() {
            const $inputField = $(this);

            $inputField.autocomplete({
                minLength: config.minLength,
                source: function(request, responseCallback) {
                    const searchTerm = request.term.toLowerCase();

                    if (!amadeus_vars.airports || amadeus_vars.airports.length === 0) {
                        console.error("Amadeus Debug: Airport data from amadeus_vars is missing or empty.");
                        return responseCallback([]);
                    }

                    const matches = amadeus_vars.airports.filter(item =>
                        item.aliases.some(alias => alias.startsWith(searchTerm))
                    );

                    matches.sort((a, b) => a.rank - b.rank);

                    const results = matches.slice(0, 7).map(item => {
                        // --- NEW LOGIC TO HANDLE CITY VS AIRPORT DISPLAY ---
                        let itemLabel = '';
                        if (item.type === 'city') {
                            // For cities, show a clear label like "Milan, İtalya (Any Airport - MIL)"
                            itemLabel = `${item.city}, ${item.country} (Tüm Havaalanları - ${item.iata})`;
                        } else {
                            // For airports, show the specific airport name
                            itemLabel = `${item.name}, ${item.city} (${item.iata})`;
                        }
                        
                        return {
                            label: itemLabel,
                            value: itemLabel, // The text that fills the input on select
                            iata: item.iata,
                            name: item.name,
                            city: item.city
                        };
                    });
                    
                    responseCallback(results);
                },
                
                select: function(event, ui) {
                    event.preventDefault(); 
                    $(this).val(ui.item.label);

                    const $codeField = $('#' + $(this).attr('id').replace('-text', '-code'));
                    $codeField.val(ui.item.iata);

                    const inputId = $(this).attr('id');
                    if (inputId.includes('origin')) {
                        selectedLocationNames.origin = ui.item.city;
                    } else if (inputId.includes('destination')) {
                        selectedLocationNames.destination = ui.item.city;
                    }
                }
            }).autocomplete("instance")._renderItem = function(ul, item) {
                return $("<li>").append($("<div>").text(item.label)).appendTo(ul);
            };
        });
    }

    /**
     * Handles the change event for the trip type radio buttons.
     */
    function handleTripTypeChange() {
        $('input[name="afs_trip_type"]').on('change', function() {
            if ($(this).val() === 'round_trip') {
                $('.afs-return-date-group').slideDown();
                $('#afs-return-date').prop('required', true);
            } else {
                $('.afs-return-date-group').slideUp();
                $('#afs-return-date').val('').prop('required', false);
            }
        }).filter(':checked').trigger('change');
    }

    /**
     * Initializes the passenger and travel class selection panel.
     */
    function initPassengerClassPanel() {
        const $panel = $('#afs-passenger-class-panel'),
            $summaryInput = $('#afs-passengers-summary'),
            $adultsStepper = $('#afs-adults-stepper'),
            $childrenStepper = $('#afs-children-stepper'),
            $infantsStepper = $('#afs-infants-stepper'),
            $travelClassSelect = $('#afs-travel-class-select'),
            $hiddenAdults = $('#afs-adults'),
            $hiddenChildren = $('#afs-children'),
            $hiddenInfants = $('#afs-infants'),
            $hiddenTravelClass = $('#afs-travel-class');

        $summaryInput.on('click', (e) => {
            e.stopPropagation();
            $panel.slideToggle();
        });

        $(document).on('click', (e) => {
            if ($panel.is(':visible') && !$(e.target).closest('#afs-passenger-class-panel').length && !$(e.target).is($summaryInput)) {
                $panel.slideUp();
            }
        });

        $('.afs-stepper-btn').on('click', function() {
            const $btn = $(this);
            const $input = $btn.siblings('.afs-stepper-input');
            let val = parseInt($input.val(), 10);
            const min = parseInt($input.attr('min'), 10);
            const max = parseInt($input.attr('max'), 10);

            if ($btn.hasClass('afs-stepper-plus') && val < max) {
                val++;
            } else if ($btn.hasClass('afs-stepper-minus') && val > min) {
                val--;
            }
            $input.val(val).trigger('change'); // Trigger change for any listeners
        });

        // Update summary when steppers or dropdown change
        $adultsStepper.on('change', updatePassengerSummary);
        $childrenStepper.on('change', updatePassengerSummary);
        $infantsStepper.on('change', updatePassengerSummary);
        $travelClassSelect.on('change', updatePassengerSummary);

        $('#afs-confirm-passengers-class').on('click', () => {
            $panel.slideUp();
        });

        function updatePassengerSummary() {
            const adults = parseInt($adultsStepper.val(), 10);
            const children = parseInt($childrenStepper.val(), 10);
            const infants = parseInt($infantsStepper.val(), 10);
            const travelClassText = $travelClassSelect.find('option:selected').text();
            const travelClassVal = $travelClassSelect.val();

            let summary_parts = [];
            summary_parts.push(`${adults} ${adults !== 1 ? amadeus_vars.text.adults : amadeus_vars.text.adult}`);
            if (children > 0) summary_parts.push(`${children} ${children !== 1 ? amadeus_vars.text.children : amadeus_vars.text.child}`);
            if (infants > 0) summary_parts.push(`${infants} ${infants !== 1 ? amadeus_vars.text.infants : amadeus_vars.text.infant}`);
            summary_parts.push(travelClassText);
            let summary = summary_parts.join(', ');

            $summaryInput.val(summary);
            $hiddenAdults.val(adults);
            $hiddenChildren.val(children);
            $hiddenInfants.val(infants);
            $hiddenTravelClass.val(travelClassVal);

            if (infants > adults && adults > 0) {
                console.warn("Number of infants exceeds the number of adults.");
                // Optionally show a user-facing warning
            }
        }
        updatePassengerSummary(); // Initial call
    }

    /**
     * Handles the flight search form submission.
     */
    function handleFlightSearchForm() {
        $('#afs-flight-search-form').on('submit', function(e) {
            e.preventDefault();
            clearErrorMessage($('#afs-results-error-message'));
            $('#afs-flight-results-container').empty().hide();

            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $spinner = $submitBtn.find('.afs-spinner');
            const $overlay = $('#afs-loading-overlay');
            
            $submitBtn.prop('disabled', true);
            $spinner.show();
            $overlay.show();

            // Serialize form data into a neat object
            const params = {
                originLocationCode: $('#afs-origin-location-code').val(),
                destinationLocationCode: $('#afs-destination-location-code').val(),
                departureDate: $('#afs-departure-date').val(),
                returnDate: $('input[name="afs_trip_type"]:checked').val() === 'round_trip' ? $('#afs-return-date').val() : '',
                adults: parseInt($('#afs-adults').val(), 10),
                children: parseInt($('#afs-children').val(), 10),
                infants: parseInt($('#afs-infants').val(), 10),
                travelClass: $('#afs-travel-class').val(),
                nonStop: $('#afs-nonstop').is(':checked')
            };

            // Basic validation
            if (!params.originLocationCode || !params.destinationLocationCode || !params.departureDate) {
                showErrorMessage(amadeus_vars.text.error_generic || 'Please fill all required fields.', $('#afs-results-error-message'));
                $submitBtn.prop('disabled', false);
                $spinner.hide();
                $overlay.hide();
                return;
            }

            $.ajax({
                url: amadeus_vars.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'afs_search_flights',
                    nonce: amadeus_vars.nonce,
                    params: params
                },
                success: function(response) {
                    if (response.success && response.data && response.data.data && response.data.data.length > 0) {
                        displayFlightResults(response.data.data, response.data.dictionaries || {});
                        $('#afs-flight-results-container').show();
                    } else if (response.success && response.data && response.data.data && response.data.data.length === 0) {
                        // Zero state: No flights found
                        const message = response.data.message || amadeus_vars.text.zero_state_message || 'We couldn\'t find any flights matching your search criteria.';
                        showZeroState(message);
                    } else {
                        // Handle API errors with specific messages
                        let errorMessage = amadeus_vars.text.error_no_flights_found || 'No flights found.';
                        
                        if (response.data && response.data.message) {
                            const apiMessage = response.data.message.toLowerCase();
                            
                            // Check for specific API error types and use appropriate user-friendly messages
                            if (apiMessage.includes('temporarily unavailable') || 
                                apiMessage.includes('system error') || 
                                apiMessage.includes('server error') ||
                                apiMessage.includes('service is experiencing')) {
                                errorMessage = amadeus_vars.text.error_api_temporary || response.data.message;
                            } else if (apiMessage.includes('invalid') || 
                                      apiMessage.includes('parameter') ||
                                      apiMessage.includes('check your')) {
                                errorMessage = amadeus_vars.text.error_api_invalid || response.data.message;
                            } else {
                                errorMessage = response.data.message;
                            }
                        }
                        
                        showErrorMessage(errorMessage, $('#afs-results-error-message'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    logger.error('Search AJAX error:', textStatus, errorThrown);
                    
                    let errorMessage = amadeus_vars.text.error_generic || 'A network error occurred. Please try again.';
                    
                    // Provide specific error messages based on the type of network error
                    if (textStatus === 'timeout') {
                        errorMessage = amadeus_vars.text.error_api_temporary || 'Request timed out. Please try again.';
                    } else if (textStatus === 'error' && jqXHR.status === 0) {
                        errorMessage = amadeus_vars.text.error_network || 'Network error. Please check your internet connection.';
                    } else if (jqXHR.status >= 500) {
                        errorMessage = amadeus_vars.text.error_api_temporary || 'Server error. Please try again in a few minutes.';
                    }
                    
                    showErrorMessage(errorMessage, $('#afs-results-error-message'));
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                    $spinner.hide();
                    $overlay.hide();
                    $('html, body').animate({
                        scrollTop: $("#afs-results-wrapper").offset().top - 100
                    }, 500);
                }
            });
        });
    }

    /**
     * Displays the flight search results using the new Skyscanner-style layout.
     * @param {Array} flightOffers - The array of flight offers.
     * @param {Object} dictionaries - The dictionaries for carriers, aircraft, etc.
     */
    function displayFlightResults(flightOffers, dictionaries) {
        const $container = $('#afs-flight-results-container').empty().show();
        const fixedPrice = amadeus_vars.settings.fixed_dummy_price;
        const currency = amadeus_vars.settings.currency_code || 'USD';

        flightOffers.forEach((offer) => {
            const priceToShow = (fixedPrice && !isNaN(parseFloat(fixedPrice))) ? parseFloat(fixedPrice).toFixed(2) : offer.price.grandTotal;
            const currencyToShow = (fixedPrice && !isNaN(parseFloat(fixedPrice))) ? currency : offer.price.currency;
            let priceHtml = `<div class="afs-price-amount">${priceToShow} ${currencyToShow}</div>`;
            if (fixedPrice && !isNaN(parseFloat(fixedPrice))) {
                priceHtml += `<div class="afs-price-original"><del>${offer.price.grandTotal} ${offer.price.currency}</del></div>`;
            }

            let legsHtml = '';
            offer.itineraries.forEach(itinerary => {
                const firstSegment = itinerary.segments[0];
                const lastSegment = itinerary.segments[itinerary.segments.length - 1];
                const airlineName = dictionaries.carriers?.[firstSegment.marketing?.carrierCode] || firstSegment.marketing?.carrierCode || firstSegment.carrierCode;
                const logoUrl = `https://dummyticket247.com/airline-logo?logo=${firstSegment.marketing?.carrierCode || firstSegment.carrierCode}.png&v=2025`;


                
                let stopsLabel, tooltipContent = '';
                if (itinerary.segments.length === 1) {
                    stopsLabel = amadeus_vars.text.stops_direct || 'Non-stop';
                } else {
                    const stopCount = itinerary.segments.length - 1;
                    stopsLabel = (stopCount === 1) 
                        ? (amadeus_vars.text.stops_one || '1 stop')
                        : (amadeus_vars.text.stops_multi || '%d stops').replace('%d', stopCount);
                    
                    // Build tooltip content
                    let layovers = [];
                    for (let i = 0; i < stopCount; i++) {
                        const layoverAirport = itinerary.segments[i].arrival.iataCode;
                        const layoverDuration = calculateLayover(itinerary.segments[i].arrival.at, itinerary.segments[i+1].departure.at);
                        layovers.push(`${amadeus_vars.text.layover_in || 'Layover in'} ${layoverAirport} (${formatDuration(layoverDuration)})`);
                    }
                    tooltipContent = layovers.join('<br>');
                }

                const stopsHtml = `
                    <div class="afs-leg__stops ${tooltipContent ? 'afs-tooltip-container' : ''}">
                        <div class="afs-leg__duration">${formatDuration(itinerary.duration)}</div>
                        <div class="afs-leg__stops-line"></div>
                        <div class="afs-leg__stops-label">${stopsLabel}</div>
                        ${tooltipContent ? `<span class="afs-tooltip-text">${tooltipContent}</span>` : ''}
                    </div>
                `;

                legsHtml += `
                    <div class="afs-ticket__leg">
                        <div class="afs-leg__logo"><img src="${logoUrl}" alt="${airlineName}"></div>
                        <div class="afs-leg__details">
                            <div class="afs-leg__route-part">
                                <div class="afs-leg__route-time">${formatTime(firstSegment.departure.at)}</div>
                                <div class="afs-leg__route-station">${firstSegment.departure.iataCode}</div>
                            </div>
                            ${stopsHtml}
                            <div class="afs-leg__route-part">
                                <div class="afs-leg__route-time">${formatTime(lastSegment.arrival.at)}</div>
                                <div class="afs-leg__route-station">${lastSegment.arrival.iataCode}</div>
                            </div>
                        </div>
                    </div>
                `;
            });

            const cardHtml = `
                <div class="afs-ticket">
                    <div class="afs-ticket__main">${legsHtml}</div>
                    <div class="afs-ticket__stub">
                        <div class="afs-stub__price">${priceHtml}</div>
                        <button type="button" class="afs-stub__button afs-select-flight-button">
                            ${amadeus_vars.text.select_offer || 'Select'}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1rem" height="1rem" fill="currentColor"><path d="M3 12a1.5 1.5 0 0 0 1.5 1.5h11.379l-4.94 4.94a1.5 1.5 0 0 0 2.122 2.12l7.5-7.5a1.5 1.5 0 0 0 0-2.12l-7.5-7.5a1.5 1.5 0 0 0-2.122 2.12l4.94 4.94H4.5A1.5 1.5 0 0 0 3 12" clip-rule="evenodd"></path></svg>
                        </button>
                    </div>
                </div>
            `;
            
            const $offerCard = $(cardHtml);
            $offerCard.data('flightOfferData', offer); 
            $container.append($offerCard);
        });
    }


    /**
     * Loads images for elements with a data-src attribute.
     */
    function loadDynamicLogos() {
        $('.afs-logo-dynamic').each(function() {
            const $logo = $(this);
            const logoUrl = $logo.data('src');
            if (logoUrl) {
                $logo.attr('src', logoUrl);
                $logo.removeClass('afs-logo-dynamic');
            }
        });
    }

    /**
     * Handles the click event for the 'Select Flight' button.
     * This version uses sessionStorage for a more reliable data transfer.
     */
    function handleSelectFlight() {
        $('#afs-flight-results-container').on('click', '.afs-select-flight-button', function() {
            const $button = $(this);
            const flightOfferData = $button.closest('.afs-ticket').data('flightOfferData'); 

            if (!flightOfferData) {
                console.error('No flight offer data found for selection.');
                showErrorMessage(amadeus_vars.text.error_generic, $('#afs-results-error-message'));
                return;
            }

            flightOfferData.originLocationName = selectedLocationNames.origin || '';
            flightOfferData.destinationLocationName = selectedLocationNames.destination || '';
            
            console.log('Final check before sending to server:', flightOfferData);
            $button.prop('disabled', true).text(amadeus_vars.text.loading || 'Loading...');

            $.ajax({
                url: amadeus_vars.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'afs_select_flight',
                    nonce: amadeus_vars.nonce,
                    flightOffer: JSON.stringify(flightOfferData)
                },
                // New Code
                success: function(response) {
                    logger.log('Server response from select_flight:', response);
                    if (response.success && response.data.redirectUrl && response.data.flightOffer) {
                        // KEY CHANGE: Store the data in the browser's session storage
                        try {
                            sessionStorage.setItem('afs_selected_flight', JSON.stringify(response.data.flightOffer));
                            logger.log('Flight data stored in sessionStorage. Redirecting...');
                            window.location.href = response.data.redirectUrl;
                        } catch (e) {
                            logger.error('Could not write to sessionStorage. Prefill may fail.', e);
                            window.location.href = response.data.redirectUrl; // Redirect anyway
                        }
                    } else {
                        logger.error('Failed to set transient or get redirect URL.', response.data?.message);
                        showErrorMessage((response.data && response.data.message) || amadeus_vars.text.error_generic, $('#afs-results-error-message'));
                        $button.prop('disabled', false).text(amadeus_vars.text.select_flight || 'Select Flight');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Select flight AJAX error:', textStatus, errorThrown);
                    showErrorMessage(amadeus_vars.text.error_generic, $('#afs-results-error-message'));
                    $button.prop('disabled', false).text(amadeus_vars.text.select_flight || 'Select Flight');
                }
            });
        });
    }

    /**
     * Prefills the Gravity Form on the booking page with the selected flight data.
     * This version uses a robust setInterval to find the form, preventing race conditions,
     * and hooks into sessionStorage for reliable data transfer.
     */
    function prefillGravityForm() {
        let flightData = null;
        // Prioritize reading from sessionStorage for reliability.
        if (typeof Storage !== 'undefined') {
            try {
                const storedData = sessionStorage.getItem('afs_selected_flight');
                if (storedData) {
                    flightData = JSON.parse(storedData);
                    // Clean up immediately after reading.
                    sessionStorage.removeItem('afs_selected_flight'); 
                }
            } catch (e) {
                console.error('Amadeus Debug: Could not read from sessionStorage.', e);
            }
        }

        // Fallback to the old method if sessionStorage fails.
        if (!flightData) {
            flightData = amadeus_vars?.selected_flight_data;
        }

        // If there is no flight data from any source, do nothing.
        if (!flightData) {
            return;
        }

        // Now that we have data, populate the summary.
        populateFlightSummary(flightData);

        // This is the robust retry loop. It will check for the form every half-second.
        const attemptPrefill = () => {
            const $gravityForm = $('form[id^="gform_"]');
            // If the form isn't on the page yet, return false to try again.
            if ($gravityForm.length === 0) {
                return false;
            }

            console.log('Amadeus Debug: Gravity Form found. Attempting to prefill.');
            const formId = parseInt($gravityForm.first().attr('id').split('_')[1], 10);
            const gf_mappings = amadeus_vars.settings.gf_mappings || {};
            const outboundItinerary = flightData.itineraries?.[0];

            if (outboundItinerary?.segments?.length > 0) {
                const firstSegment = outboundItinerary.segments[0];
                const lastSegment = outboundItinerary.segments[outboundItinerary.segments.length - 1];

                const setFieldValue = (fieldKey, value) => {
                    if (gf_mappings[fieldKey]) {
                        const fieldId = `#input_${formId}_${gf_mappings[fieldKey]}`;
                        $(fieldId).val(value).trigger('change');
                    }
                };

                setFieldValue('flight_number', `${firstSegment.carrierCode}${firstSegment.number}`);
                setFieldValue('departure_airport', firstSegment.departure.iataCode);
                setFieldValue('departure_time', formatDateTimeForInput(firstSegment.departure.at));
                setFieldValue('arrival_airport', lastSegment.arrival.iataCode);
                setFieldValue('arrival_time', formatDateTimeForInput(lastSegment.arrival.at));
                setFieldValue('origin_airport_name', flightData.originLocationName);
                setFieldValue('destination_airport_name', flightData.destinationLocationName);

                const returnItinerary = flightData.itineraries?.[1];
                if (returnItinerary) {
                    const returnFirstSegment = returnItinerary.segments[0];
                    setFieldValue('return_date', formatDateTimeForInput(returnFirstSegment.departure.at));
                    setFieldValue('return_origin_airport_name', flightData.destinationLocationName);
                    setFieldValue('return_destination_airport_name', flightData.originLocationName);
                }
            }
            
            // Explicitly tell Gravity Forms to re-run its conditional logic.
            setTimeout(() => {
                if (typeof gf_apply_rules === 'function') {
                    console.log('Amadeus Debug: Re-applying Gravity Forms conditional logic.');
                    gf_apply_rules(formId, []);
                }
                $(document).trigger('gform_post_render', [formId, 1]);
            }, 250);

            // Clean up the server-side transient.
            $.post(amadeus_vars.ajax_url, {
                action: 'afs_clear_flight_transient',
                nonce: amadeus_vars.nonce
            });
            
            // Return true to stop the retry loop.
            return true;
        };

        // Start the retry mechanism.
        let attempts = 0;
        const maxAttempts = 10;
        const retryInterval = 500;
        const prefillInterval = setInterval(() => {
            if (attemptPrefill() || ++attempts >= maxAttempts) {
                clearInterval(prefillInterval);
                if (attempts >= maxAttempts) {
                    console.error('Amadeus Debug: Gravity Form not found after multiple attempts.');
                }
            }
        }, retryInterval);
    }


    
    /**
     * Populates the flight summary on the booking page.
     * This version accepts flightData as a parameter to ensure it uses the correct data.
     */
    function populateFlightSummary(flightData) {
        // This function now receives flightData directly from prefillGravityForm
        const $container = $('#afs-selected-flight-summary-container');
        
        if (flightData && $container.length) {
            const $summaryContent = $('#afs-flight-summary-content');
            let summaryHtml = '<ul>';
            
            const outbound = flightData.itineraries?.[0];
            if (outbound?.segments?.[0]) {
                summaryHtml += `<li><strong>Outbound:</strong> ${outbound.segments[0].carrierCode}${outbound.segments[0].number} from ${outbound.segments[0].departure.iataCode} to ${outbound.segments[outbound.segments.length - 1].arrival.iataCode}</li>`;
                summaryHtml += `<li><strong>Departure:</strong> ${formatDateTimeForDisplay(outbound.segments[0].departure.at)}</li>`;
            }
            
            const inbound = flightData.itineraries?.[1];
            if (inbound?.segments?.[0]) {
                summaryHtml += `<li><strong>Return:</strong> ${inbound.segments[0].carrierCode}${inbound.segments[0].number} from ${inbound.segments[0].departure.iataCode} to ${inbound.segments[inbound.segments.length - 1].arrival.iataCode}</li>`;
                summaryHtml += `<li><strong>Return Departure:</strong> ${formatDateTimeForDisplay(inbound.segments[0].departure.at)}</li>`;
            }

            if (flightData.price) {
                summaryHtml += `<li><strong>Total Price:</strong> ${flightData.price.grandTotal} ${flightData.price.currency}</li>`;
            }
            if (flightData.travelerPricings) {
                summaryHtml += `<li><strong>Passengers:</strong> ${flightData.travelerPricings.length}</li>`;
            }
            
            summaryHtml += '</ul>';
            $summaryContent.html(summaryHtml);
            $container.show();
        } else {
            $container.hide();
        }
    }


    // --- UTILITY FUNCTIONS ---
    function formatDuration(isoDuration) {
        if (!isoDuration || typeof isoDuration !== 'string') return 'N/A';
        const matches = isoDuration.match(/PT(?:(\d+)H)?(?:(\d+)M)?/);
        if (!matches) return isoDuration;
        const hours = matches[1] ? parseInt(matches[1], 10) : 0;
        const minutes = matches[2] ? parseInt(matches[2], 10) : 0;
        let formatted = '';
        if (hours > 0) formatted += `${hours}h `;
        if (minutes > 0) formatted += `${minutes}m`;
        return formatted.trim() || '0m';
    }

    function formatTime(isoDateTime) {
        try {
            return new Date(isoDateTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
        } catch (e) {
            return isoDateTime;
        }
    }

    function formatDate(isoDateTime) {
        try {
            return new Date(isoDateTime).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
        } catch (e) {
            return isoDateTime;
        }
    }
    
    function formatDateTimeForInput(isoDateTime) {
         try {
            const dt = new Date(isoDateTime);
            // Format to "dd/mm/yyyy HH:MM"
            return dt.toLocaleDateString('en-GB') + ' ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
        } catch (e) {
            return isoDateTime;
        }
    }
    
     function formatDateTimeForDisplay(isoDateTime) {
        try {
            return new Date(isoDateTime).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short'});
        } catch (e) {
            return isoDateTime;
        }
    }

    function calculateLayover(arrivalISO, departureISO) {
        try {
            const arrivalTime = new Date(arrivalISO).getTime();
            const departureTime = new Date(departureISO).getTime();
            if (isNaN(arrivalTime) || isNaN(departureTime) || departureTime < arrivalTime) return null;
            let diffMillis = departureTime - arrivalTime;
            const hours = Math.floor(diffMillis / 3600000);
            diffMillis -= hours * 3600000;
            const minutes = Math.floor(diffMillis / 60000);
            if (hours === 0 && minutes === 0) return null;
            return `PT${hours > 0 ? hours + 'H' : ''}${minutes > 0 ? minutes + 'M' : ''}`;
        } catch (e) {
            console.error("Error calculating layover:", e);
            return null;
        }
    }


    // New Code
    $(function() {
        logger.log('Amadeus Flight Search script loaded and ready.');
        if ($('#afs-flight-search-form').length) {
            // These functions run on the search page
            initDatepickers();
            initLocationAutocomplete();
            handleTripTypeChange();
            initPassengerClassPanel();
            handleFlightSearchForm();
            handleSelectFlight();
        } else {
            // This function now runs on any other page (like the booking page)
            // It will check for data internally and only run if it finds it.
            prefillGravityForm();
        }
    });

})(jQuery);