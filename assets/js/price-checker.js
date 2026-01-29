/**
 * NewBook Price Checker - Frontend JavaScript
 * Vanilla JS - No jQuery dependency
 */

(function() {
    'use strict';

    // Initialize all widgets on the page
    document.addEventListener('DOMContentLoaded', function() {
        const widgets = document.querySelectorAll('.nbpc-widget');
        widgets.forEach(function(widget) {
            new NBPCWidget(widget);
        });
    });

    /**
     * Price Checker Widget Class
     */
    function NBPCWidget(container) {
        this.container = container;
        this.siteCode = container.dataset.site;
        this.debounceTimer = null;

        // Cache DOM elements
        this.elements = {
            arriveInput: container.querySelector('.nbpc-arrive'),
            nightsSelect: container.querySelector('.nbpc-nights'),
            adultsSelect: container.querySelector('.nbpc-adults'),
            childrenSelect: container.querySelector('.nbpc-children'),
            introSection: container.querySelector('.nbpc-intro'),
            loadingSection: container.querySelector('.nbpc-loading'),
            resultsSection: container.querySelector('.nbpc-results'),
            unavailableSection: container.querySelector('.nbpc-unavailable'),
            fallbackSection: container.querySelector('.nbpc-fallback'),
            errorSection: container.querySelector('.nbpc-error'),
            onlinePrice: container.querySelector('.nbpc-online-value'),
            onlineRoom: container.querySelector('.nbpc-online-room'),
            channelPrice: container.querySelector('.nbpc-channel-value'),
            channelRoom: container.querySelector('.nbpc-channel-room'),
            savings: container.querySelector('.nbpc-savings'),
            priceLink: container.querySelector('.nbpc-price-link'),
            fallbackList: container.querySelector('.nbpc-fallback-list'),
            errorMessage: container.querySelector('.nbpc-error-message'),
            bookingUrlBase: container.querySelector('.nbpc-booking-url-base'),
            showFallback: container.querySelector('.nbpc-show-fallback'),
        };

        this.init();
    }

    NBPCWidget.prototype.init = function() {
        // Set up event listeners
        this.elements.arriveInput.addEventListener('change', this.handleFieldChange.bind(this));
        this.elements.nightsSelect.addEventListener('change', this.handleFieldChange.bind(this));
        this.elements.adultsSelect.addEventListener('change', this.handleFieldChange.bind(this));
        this.elements.childrenSelect.addEventListener('change', this.handleFieldChange.bind(this));

        // Show intro section initially
        this.showSection('intro');
    };

    /**
     * Handle any field change
     */
    NBPCWidget.prototype.handleFieldChange = function() {
        this.checkAndFetch();
    };

    /**
     * Calculate departure date from arrival + nights
     */
    NBPCWidget.prototype.getDepartureDate = function() {
        const arriveDate = this.elements.arriveInput.value;
        const nights = parseInt(this.elements.nightsSelect.value, 10);

        if (!arriveDate || !nights) {
            return null;
        }

        const arrive = new Date(arriveDate);
        arrive.setDate(arrive.getDate() + nights);
        return this.formatDate(arrive);
    };

    /**
     * Check if all required fields are filled and fetch prices
     */
    NBPCWidget.prototype.checkAndFetch = function() {
        const arriveDate = this.elements.arriveInput.value;

        if (!arriveDate) {
            return;
        }

        // Debounce the API call
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(this.fetchPrices.bind(this), 300);
    };

    /**
     * Fetch prices from the server
     */
    NBPCWidget.prototype.fetchPrices = function() {
        const arriveDate = this.elements.arriveInput.value;
        const departDate = this.getDepartureDate();

        if (!arriveDate || !departDate) {
            return;
        }

        this.showSection('loading');

        const formData = new FormData();
        formData.append('action', 'nbpc_price_check');
        formData.append('nonce', nbpcData.nonce);
        formData.append('site', this.siteCode);
        formData.append('available_from', this.convertToAPIDate(arriveDate));
        formData.append('available_to', this.convertToAPIDate(departDate));
        formData.append('adults', this.elements.adultsSelect.value);
        formData.append('children', this.elements.childrenSelect.value);

        fetch(nbpcData.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function(response) {
            return response.json();
        })
        .then(this.handlePriceResponse.bind(this))
        .catch(this.handleError.bind(this));
    };

    /**
     * Handle price check response
     */
    NBPCWidget.prototype.handlePriceResponse = function(response) {
        // Debug: Log full response to console
        console.log('NBPC Response:', response);
        console.log('NBPC Data:', response.data);

        if (!response.success) {
            this.showError(response.data?.message || 'An error occurred');
            return;
        }

        const data = response.data;

        // Debug: Log availability details
        console.log('NBPC Online:', data.online);
        console.log('NBPC Channels:', data.channels);

        // Check if online rates are available
        if (data.online && data.online.available) {
            this.displayResults(data);
        } else {
            this.showUnavailable(data);
        }
    };

    /**
     * Display price results
     */
    NBPCWidget.prototype.displayResults = function(data) {
        const currency = nbpcData.currency;

        // Update online price
        this.elements.onlinePrice.textContent = currency + data.online.cheapest_price.toFixed(2);
        this.elements.onlineRoom.textContent = data.online.room_type;

        // Update channel price
        if (data.channels && data.channels.available && data.channels.cheapest_price > 0) {
            this.elements.channelPrice.textContent = currency + data.channels.cheapest_price.toFixed(2);
            this.elements.channelRoom.textContent = data.channels.room_type;

            // Calculate savings
            const savings = data.channels.cheapest_price - data.online.cheapest_price;
            if (savings > 0) {
                this.elements.savings.textContent = 'Save ' + currency + savings.toFixed(2);
                this.elements.savings.style.display = 'inline-block';
            } else {
                this.elements.savings.style.display = 'none';
            }
        } else {
            this.elements.channelPrice.innerHTML = '<span style="font-size: 14px;">Unavailable</span>';
            this.elements.channelRoom.textContent = '';
            this.elements.savings.style.display = 'none';
        }

        // Update booking URL on price link
        if (data.booking_url && this.elements.priceLink) {
            this.elements.priceLink.href = data.booking_url;
        }

        this.showSection('results');
    };

    /**
     * Show unavailable state and fetch fallback properties
     */
    NBPCWidget.prototype.showUnavailable = function(data) {
        this.showSection('unavailable');

        // Check if we should show fallback
        const showFallback = this.elements.showFallback && this.elements.showFallback.value === '1';

        if (showFallback && this.elements.fallbackSection) {
            this.fetchFallbackPrices();
        }
    };

    /**
     * Fetch fallback property prices
     */
    NBPCWidget.prototype.fetchFallbackPrices = function() {
        const arriveDate = this.elements.arriveInput.value;
        const departDate = this.getDepartureDate();

        // Show fallback section with loading state
        this.elements.fallbackSection.style.display = 'block';
        this.elements.fallbackList.innerHTML =
            '<div class="nbpc-fallback-loading">' +
            '<div class="nbpc-spinner"></div>' +
            '<span>Checking other properties...</span>' +
            '</div>';

        const formData = new FormData();
        formData.append('action', 'nbpc_fallback_check');
        formData.append('nonce', nbpcData.nonce);
        formData.append('exclude_site', this.siteCode);
        formData.append('available_from', this.convertToAPIDate(arriveDate));
        formData.append('available_to', this.convertToAPIDate(departDate));
        formData.append('adults', this.elements.adultsSelect.value);
        formData.append('children', this.elements.childrenSelect.value);

        fetch(nbpcData.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function(response) {
            return response.json();
        })
        .then(this.handleFallbackResponse.bind(this))
        .catch(function() {
            this.elements.fallbackSection.style.display = 'none';
        }.bind(this));
    };

    /**
     * Handle fallback response
     */
    NBPCWidget.prototype.handleFallbackResponse = function(response) {
        if (!response.success || !response.data.sites || response.data.sites.length === 0) {
            this.elements.fallbackSection.style.display = 'none';
            return;
        }

        const currency = nbpcData.currency;
        let html = '';

        response.data.sites.forEach(function(site) {
            html += '<div class="nbpc-fallback-item">' +
                '<div class="nbpc-fallback-item-info">' +
                '<div class="nbpc-fallback-item-name">' + this.escapeHtml(site.name) + '</div>' +
                '<div class="nbpc-fallback-item-price">From ' + currency + site.cheapest_price.toFixed(2) + '</div>' +
                '</div>' +
                '<a href="' + this.escapeHtml(site.booking_url) + '" class="nbpc-fallback-item-link" target="_blank" rel="noopener">View</a>' +
                '</div>';
        }.bind(this));

        this.elements.fallbackList.innerHTML = html;
    };

    /**
     * Show error message
     */
    NBPCWidget.prototype.showError = function(message) {
        this.elements.errorMessage.textContent = message;
        this.showSection('error');
    };

    /**
     * Handle fetch errors
     */
    NBPCWidget.prototype.handleError = function(error) {
        console.error('NBPC Error:', error);
        this.showError('Failed to check prices. Please try again.');
    };

    /**
     * Show a specific section, hide others
     */
    NBPCWidget.prototype.showSection = function(section) {
        const sections = ['intro', 'loading', 'results', 'unavailable', 'error'];

        sections.forEach(function(name) {
            const el = this.elements[name + 'Section'];
            if (el) {
                el.style.display = name === section ? 'block' : 'none';
            }
        }.bind(this));

        // Hide fallback when showing results
        if (section === 'results' && this.elements.fallbackSection) {
            this.elements.fallbackSection.style.display = 'none';
        }
    };

    /**
     * Format date as YYYY-MM-DD
     */
    NBPCWidget.prototype.formatDate = function(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    };

    /**
     * Convert HTML5 date (YYYY-MM-DD) to API format (DD-MM-YYYY)
     */
    NBPCWidget.prototype.convertToAPIDate = function(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    };

    /**
     * Escape HTML to prevent XSS
     */
    NBPCWidget.prototype.escapeHtml = function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

})();
