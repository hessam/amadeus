(function($) {
    'use strict';

    if (typeof amadeus_hotel_vars === 'undefined') {
        console.error('Amadeus Hotel Search: Localized variables (amadeus_hotel_vars) not found.');
        return;
    }

    const logger = {
        log: (message, data) => console.log(`%c[AHS LOG] ${message}`, 'color: #0073aa; font-weight: bold;', data !== undefined ? data : ''),
        error: (message, data) => console.error(`%c[AHS ERROR] ${message}`, 'color: #dc3232; font-weight: bold;', data !== undefined ? data : ''),
        info: (message, data) => console.info(`%c[AHS INFO] ${message}`, 'color: #3498db;', data !== undefined ? data : '')
    };

    function showErrorMessage(message) {
        const $errorContainer = $('#ahs-results-error-message');
        const sanitizedMessage = $('<div/>').text(message).html();
        $errorContainer.html(`<p class="afs-error">${sanitizedMessage}</p>`).show();
        $('#ahs-hotel-results-container').empty().hide();
    }

    function clearErrorMessage() {
        $('#ahs-results-error-message').empty().hide();
    }

    function initDatepickers() {
        const $checkin = $('#ahs-checkin-date');
        const $checkout = $('#ahs-checkout-date');
        $checkin.datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0,
            onSelect: function(selectedDate) {
                const nextDay = new Date(selectedDate);
                nextDay.setDate(nextDay.getDate() + 1);
                $checkout.datepicker('option', 'minDate', nextDay);
                if ($checkout.val() && new Date($checkout.val()) <= new Date(selectedDate)) {
                    $checkout.val('');
                }
            }
        });
        $checkout.datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 1,
        });
    }

    function initLocationAutocomplete() {
        $('#ahs-city-text').autocomplete({
            minLength: 2,
            source: function(request, responseCallback) {
                $.ajax({
                    url: amadeus_hotel_vars.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ahs_search_hotel_locations',
                        nonce: amadeus_hotel_vars.nonce,
                        keyword: request.term
                    },
                    success: function(response) {
                        responseCallback(response.success ? response.data : []);
                    }
                });
            },
            select: function(event, ui) {
                event.preventDefault();
                $(this).val(ui.item.value);
                $('#ahs-city-code').val(ui.item.iataCode);
            }
        });
    }

    function handleHotelSearchForm() {
        $('#ahs-hotel-search-form').on('submit', function(e) {
            e.preventDefault();
            clearErrorMessage();
            $('#ahs-hotel-results-container').empty().hide();
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $spinner = $submitBtn.find('.afs-spinner');
            const $overlay = $('#ahs-loading-overlay');
            const params = {
                cityCode: $('#ahs-city-code').val(),
                cityName: $('#ahs-city-text').val(),
                checkInDate: $('#ahs-checkin-date').val(),
                checkOutDate: $('#ahs-checkout-date').val(),
                adults: parseInt($('#ahs-adults').val(), 10) || 1,
            };
            if (!params.cityCode || !params.checkInDate || !params.checkOutDate) {
                showErrorMessage('Please fill all required fields.');
                return;
            }
            $submitBtn.prop('disabled', true);
            $spinner.show();
            $overlay.show();
            $.ajax({
                url: amadeus_hotel_vars.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'ahs_search_hotels',
                    nonce: amadeus_hotel_vars.nonce,
                    params: params
                },
                success: function(response) {
                    if (response.success && response.data && response.data.offers && response.data.offers.length > 0) {
                        displayHotelResults(response.data.offers, params.cityName);
                    } else {
                        // Handle API errors with specific messages
                        let errorMessage = amadeus_hotel_vars.text.error_no_hotels_found || 'No hotels found.';
                        
                        if (response.data && response.data.message) {
                            const apiMessage = response.data.message.toLowerCase();
                            
                            // Check for specific API error types and use appropriate user-friendly messages
                            if (apiMessage.includes('temporarily unavailable') || 
                                apiMessage.includes('system error') || 
                                apiMessage.includes('server error') ||
                                apiMessage.includes('service is experiencing')) {
                                errorMessage = amadeus_hotel_vars.text.error_api_temporary || response.data.message;
                            } else if (apiMessage.includes('invalid') || 
                                      apiMessage.includes('parameter') ||
                                      apiMessage.includes('check your')) {
                                errorMessage = amadeus_hotel_vars.text.error_api_invalid || response.data.message;
                            } else {
                                errorMessage = response.data.message;
                            }
                        }
                        
                        showErrorMessage(errorMessage);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    logger.error('Hotel Search AJAX Error:', jqXHR);
                    
                    let errorMessage = amadeus_hotel_vars.text.error_generic || 'An unexpected error occurred.';
                    
                    // Provide specific error messages based on the type of network error
                    if (textStatus === 'timeout') {
                        errorMessage = amadeus_hotel_vars.text.error_api_temporary || 'Request timed out. Please try again.';
                    } else if (textStatus === 'error' && jqXHR.status === 0) {
                        errorMessage = amadeus_hotel_vars.text.error_network || 'Network error. Please check your internet connection.';
                    } else if (jqXHR.status >= 500) {
                        errorMessage = amadeus_hotel_vars.text.error_api_temporary || 'Server error. Please try again in a few minutes.';
                    }
                    
                    showErrorMessage(errorMessage);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                    $spinner.hide();
                    $overlay.hide();
                    $('html, body').animate({
                        scrollTop: $("#ahs-results-wrapper").offset().top - 100
                    }, 500);
                }
            });
        });
    }

    function displayHotelResults(hotelOffers, cityName) {
        const $container = $('#ahs-hotel-results-container').empty().show();
        const fixedPrice = amadeus_hotel_vars.settings.fixed_dummy_price;
        const currency = amadeus_hotel_vars.settings.currency_code || 'USD';
        hotelOffers.forEach(offer => {
            if (!offer.hotel || !offer.offers || !offer.offers[0]) return;
            const hotel = offer.hotel;
            const mainOffer = offer.offers[0];
            const originalPrice = parseFloat(mainOffer.price.total).toFixed(2);
            const priceToShow = (fixedPrice && !isNaN(parseFloat(fixedPrice))) ?
                parseFloat(fixedPrice).toFixed(2) :
                originalPrice;
            let priceHtml = `<div class="afs-price-amount">${priceToShow} ${currency}</div>`;
            if (fixedPrice && !isNaN(parseFloat(fixedPrice))) {
                priceHtml += `<div class="afs-price-original" style="text-decoration: line-through;">${originalPrice} ${mainOffer.price.currency}</div>`;
            }
            priceHtml += `<small>${amadeus_hotel_vars.text.per_night || 'per night'}</small>`;
            const stars = hotel.rating ? '⭐'.repeat(parseInt(hotel.rating, 10)) : '';
            const country = hotel.address?.countryCode || '';
            const locationName = `${cityName || hotel.cityCode}, ${country}`;
            const cardHtml = `
                <div class="afs-ticket">
                    <div class="afs-ticket__main">
                        <div class="ahs-hotel-details">
                            <h3>${hotel.name || 'Hotel Name Not Available'} <span class="ahs-stars">${stars}</span></h3>
                            <p>${locationName}</p>
                        </div>
                    </div>
                    <div class="afs-ticket__stub">
                        <div class="afs-stub__price">${priceHtml}</div>
                        <button type="button" class="afs-stub__button ahs-select-hotel-button">
                            ${amadeus_hotel_vars.text.select_hotel || 'Select'}
                        </button>
                    </div>
                </div>`;
            const $offerCard = $(cardHtml);
            $offerCard.data('hotelOfferData', offer);
            $container.append($offerCard);
        });
    }

    function handleSelectHotel() {
        $('#ahs-hotel-results-container').on('click', '.ahs-select-hotel-button', function() {
            const $button = $(this);
            const hotelOfferData = $button.closest('.afs-ticket').data('hotelOfferData');
            const bookingUrl = amadeus_hotel_vars.settings.booking_page_url;
            if (!hotelOfferData) {
                logger.error('No hotel offer data found on button click.');
                return;
            }
            if (!bookingUrl) {
                showErrorMessage('Hotel booking page URL is not configured in plugin settings.');
                return;
            }
            hotelOfferData.searchParams = {
                checkIn: $('#ahs-checkin-date').val(),
                checkOut: $('#ahs-checkout-date').val(),
                guests: $('#ahs-adults').val(),
                cityName: $('#ahs-city-text').val()
            };
            $button.prop('disabled', true).text(amadeus_hotel_vars.text.loading);
            $.ajax({
                url: amadeus_hotel_vars.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'ahs_select_hotel',
                    nonce: amadeus_hotel_vars.nonce,
                    hotelOffer: JSON.stringify(hotelOfferData),
                },
                success: function(response) {
                    if (response.success) {
                        try {
                            sessionStorage.setItem('ahs_selected_hotel', JSON.stringify(response.data.hotelData));
                            logger.log('Hotel data stored in sessionStorage. Redirecting...', { url: bookingUrl });
                            window.location.href = bookingUrl;
                        } catch (e) {
                            logger.error('Could not write to sessionStorage. Prefill may fail.', e);
                            window.location.href = bookingUrl;
                        }
                    } else {
                        showErrorMessage(response.data.message || 'Could not select hotel.');
                        $button.prop('disabled', false).text(amadeus_hotel_vars.text.select_hotel);
                    }
                },
                error: function() {
                    showErrorMessage(amadeus_hotel_vars.text.error_generic);
                    $button.prop('disabled', false).text(amadeus_hotel_vars.text.select_hotel);
                }
            });
        });
    }

    function prefillGravityForm() {
        logger.log('Prefill function started.');
        let hotelData = null;
        
        // 1. Primary Method: Try sessionStorage
        try {
            const storedData = sessionStorage.getItem('ahs_selected_hotel');
            if (storedData) {
                logger.info('Found data in sessionStorage.');
                hotelData = JSON.parse(storedData);
                sessionStorage.removeItem('ahs_selected_hotel');
            } else {
                logger.info('No data found in sessionStorage.');
            }
        } catch (e) {
            logger.error('Could not read from sessionStorage.', e);
        }

        // 2. Fallback Method: Use data passed from the server transient
        if (!hotelData && amadeus_hotel_vars.selected_hotel_data) {
            logger.info('Using fallback data from server variable.', amadeus_hotel_vars.selected_hotel_data);
            hotelData = amadeus_hotel_vars.selected_hotel_data;
        }

        if (!hotelData) {
            logger.log('No hotel data found from any source. Prefill aborted.');
            return;
        }
        
        logger.log('Hotel data successfully retrieved for prefill:', hotelData);

        const attemptPrefill = () => {
            const mappings = amadeus_hotel_vars.settings.gf_mappings;
            const formId = mappings.form_id;

            logger.log('Attempting to find Gravity Form with ID:', formId);
            logger.log('Using mappings:', mappings);

            if (!formId) {
                logger.error('Gravity Form ID for hotels is not set in plugin settings.');
                return true; // Stop trying
            }

            const $gravityForm = $(`form#gform_${formId}`);

            if ($gravityForm.length === 0) {
                return false; // Form not ready
            }

            logger.info('Gravity Form found! Starting to populate fields.');
            
            const setFieldValue = (fieldKey, value) => {
                const fieldId = mappings[fieldKey];
                if (fieldId && value) {
                    const selector = `#input_${formId}_${fieldId}`;
                    const $field = $(selector);
                    if ($field.length) {
                        $field.val(value).trigger('change');
                        logger.log(`SUCCESS: Set field '${fieldKey}' (Selector: ${selector}) with value:`, value);
                    } else {
                        logger.error(`FAIL: Field for '${fieldKey}' not found with selector:`, selector);
                    }
                } else {
                     logger.info(`SKIP: No mapping or value for field '${fieldKey}'.`);
                }
            };

            setFieldValue('hotel_name', hotelData.hotel?.name);
            setFieldValue('hotel_city', hotelData.searchParams?.cityName);
            setFieldValue('check_in', hotelData.searchParams?.checkIn);
            setFieldValue('check_out', hotelData.searchParams?.checkOut);
            setFieldValue('guests', hotelData.searchParams?.guests);
            
            if (typeof gf_apply_rules === 'function') {
                logger.log('Applying Gravity Forms conditional logic.');
                gf_apply_rules(formId, []);
            }
            $(document).trigger('gform_post_render', [formId, 1]);

            return true; // Success
        };
        
        let attempts = 0;
        const maxAttempts = 20;
        const retryInterval = 500;
        const prefillInterval = setInterval(() => {
            logger.info(`Prefill attempt #${attempts + 1}`);
            if (attemptPrefill() || ++attempts >= maxAttempts) {
                clearInterval(prefillInterval);
                if (attempts >= maxAttempts) {
                    logger.error('Gravity Form not found after multiple attempts.');
                }
            }
        }, retryInterval);
    }

    // --- Document Ready ---
    $(function() {
        if ($('#ahs-hotel-search-form').length) {
            logger.log('Hotel search page detected. Initializing search form scripts.');
            initDatepickers();
            initLocationAutocomplete();
            handleHotelSearchForm();
            handleSelectHotel();
        } else {
            logger.log('Non-search page detected. Checking for prefill data.');
            prefillGravityForm();
        }
    });

})(jQuery);