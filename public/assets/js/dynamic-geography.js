(function () {
    const API_BASE = 'https://countriesnow.space/api/v0.1/countries';

    let countriesCache = [];
    async function getCountries() {
        if (countriesCache.length > 0) return countriesCache;
        try {
            const response = await fetch(`${API_BASE}/iso`);
            const json = await response.json();
            if (json && !json.error) {
                countriesCache = json.data;
                return countriesCache;
            }
        } catch (e) {
            console.error('Failed to load countries', e);
        }
        return [];
    }

    async function getStates(countryName) {
        try {
            const response = await fetch(`${API_BASE}/states`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ country: countryName })
            });
            const json = await response.json();
            if (json && !json.error && json.data && json.data.states) {
                return json.data.states;
            }
        } catch (e) {
            console.error(`Failed to load states for ${countryName}`, e);
        }
        return [];
    }

    async function getCities(countryName, stateName) {
        try {
            const response = await fetch(`${API_BASE}/state/cities`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ country: countryName, state: stateName })
            });
            const json = await response.json();
            if (json && !json.error && json.data) {
                return json.data;
            }
        } catch (e) {
            console.error(`Failed to load cities for ${countryName}, ${stateName}`, e);
        }
        return [];
    }

    window.initSelect2 = function(element) {
        const $el = $(element);
        if (!$el.length) return;

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        const selectorType = $el.attr('data-select2-selector') || $el.data('select2-selector');
        const modal = $el.closest('.modal');
        const options = {
            theme: "bootstrap-5",
            width: "100%"
        };

        if (modal.length) {
            options.dropdownParent = modal.find('.modal-content');
        }

        if (selectorType === 'country') {
            options.templateResult = typeof countryformat !== 'undefined' ? countryformat : undefined;
            options.templateSelection = typeof countryformat !== 'undefined' ? countryformat : undefined;
        } else if (selectorType === 'tzone') {
            options.templateResult = typeof tzoneformat !== 'undefined' ? tzoneformat : undefined;
            options.templateSelection = typeof tzoneformat !== 'undefined' ? tzoneformat : undefined;
        }

        $el.select2(options);
    };

    window.setupTimezones = function(container) {
        const timezoneSelects = container.querySelectorAll('.geo-timezone');
        timezoneSelects.forEach(select => {
            let initialTz = select.dataset.initialValue || select.value || '';
            
            let timezones = [];
            try {
                timezones = Intl.supportedValuesOf('timeZone');
            } catch (e) {
                // Fallback standard timezones list
                timezones = [
                    "Africa/Cairo", "Africa/Johannesburg", "Africa/Lagos", "Africa/Nairobi",
                    "America/Anchorage", "America/Argentina/Buenos_Aires", "America/Bogota",
                    "America/Caracas", "America/Chicago", "America/Denver", "America/Halifax",
                    "America/Los_Angeles", "America/Mexico_City", "America/New_York", "America/Phoenix",
                    "America/Santiago", "America/Sao_Paulo", "America/St_Johns",
                    "Asia/Baku", "Asia/Bangkok", "Asia/Calcutta", "Asia/Colombo", "Asia/Dhaka",
                    "Asia/Dubai", "Asia/Hong_Kong", "Asia/Jakarta", "Asia/Jerusalem", "Asia/Kabul",
                    "Asia/Karachi", "Asia/Katmandu", "Asia/Kolkata", "Asia/Moscow", "Asia/Novosibirsk",
                    "Asia/Rangoon", "Asia/Seoul", "Asia/Singapore", "Asia/Tashkent", "Asia/Tbilisi",
                    "Asia/Tehran", "Asia/Tokyo", "Asia/Vladivostok", "Asia/Yakutsk", "Asia/Yekaterinburg",
                    "Atlantic/Azores", "Atlantic/Cape_Verde",
                    "Australia/Adelaide", "Australia/Darwin", "Australia/Eucla", "Australia/Lord_Howe",
                    "Australia/Melbourne", "Australia/Perth", "Australia/Sydney",
                    "Europe/Amsterdam", "Europe/Athens", "Europe/Belgrade", "Europe/Berlin", "Europe/Brussels",
                    "Europe/Copenhagen", "Europe/Dublin", "Europe/Kaliningrad", "Europe/Lisbon", "Europe/London",
                    "Europe/Madrid", "Europe/Moscow", "Europe/Paris", "Europe/Rome", "Europe/St_Petersburg",
                    "Europe/Vienna", "Europe/Warsaw", "Europe/Zurich",
                    "Pacific/Auckland", "Pacific/Fiji", "Pacific/Honolulu", "Pacific/Kwajalein",
                    "Pacific/Midway", "Pacific/Noumea", "Pacific/Pago_Pago", "Pacific/Port_Moresby"
                ];
            }

            select.innerHTML = '<option value="">Select Timezone</option>';
            timezones.forEach(tz => {
                const option = document.createElement('option');
                option.value = tz;
                
                // Get dynamic GMT offset
                let offsetStr = '';
                try {
                    const date = new Date();
                    const parts = new Intl.DateTimeFormat('en', {
                        timeZone: tz,
                        timeZoneName: 'shortOffset'
                    }).formatToParts(date);
                    const tzPart = parts.find(p => p.type === 'timeZoneName');
                    offsetStr = tzPart ? ` (${tzPart.value})` : '';
                } catch(e) {}

                // Make timezone labels clear and friendly for non-technical users
                let label = tz;
                if (tz === 'Asia/Kolkata' || tz === 'Asia/Calcutta') {
                    label = 'Asia/Kolkata (India Standard Time)';
                } else if (tz === 'America/New_York') {
                    label = 'America/New_York (US Eastern Time)';
                } else if (tz === 'Europe/London') {
                    label = 'Europe/London (London/UK Time)';
                } else if (tz === 'Asia/Dubai') {
                    label = 'Asia/Dubai (Gulf Standard Time)';
                }

                option.textContent = `${label}${offsetStr}`;
                
                // Map data-tzone attribute for sun/moon icons
                if (tz.startsWith('Asia') || tz.startsWith('Europe') || tz.startsWith('Africa') || tz.startsWith('Australia') || tz.startsWith('Indian')) {
                    option.setAttribute('data-tzone', 'feather-sun');
                } else {
                    option.setAttribute('data-tzone', 'feather-moon');
                }

                if (tz.toLowerCase() === initialTz.toLowerCase()) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            window.initSelect2(select);
        });
    };

    window.setupGeoFields = async function(container) {
        const countrySelect = container.querySelector('.geo-country');
        const stateSelect = container.querySelector('.geo-state');
        const citySelect = container.querySelector('.geo-city');

        if (!countrySelect) return;

        let initialCountry = countrySelect.dataset.initialValue || countrySelect.value || '';
        let initialState = stateSelect ? (stateSelect.dataset.initialValue || stateSelect.value || '') : '';
        let initialCity = citySelect ? (citySelect.dataset.initialValue || citySelect.value || '') : '';

        // Unbind existing handlers to prevent multiple bindings
        $(countrySelect).off('change.geo');
        if (stateSelect) $(stateSelect).off('change.geo');

        // Populate Countries
        const countries = await getCountries();
        countrySelect.innerHTML = '<option value="">Select Country</option>';
        countries.forEach(c => {
            const option = document.createElement('option');
            option.value = c.name;
            option.textContent = c.name;
            option.setAttribute('data-country', c.Iso2.toLowerCase());
            if (c.name.toLowerCase() === initialCountry.toLowerCase()) {
                option.selected = true;
            }
            countrySelect.appendChild(option);
        });

        window.initSelect2(countrySelect);

        async function updateStates() {
            if (!stateSelect) return;
            const selectedCountry = countrySelect.value;
            if (!selectedCountry) {
                stateSelect.innerHTML = '<option value="">Select State</option>';
                window.initSelect2(stateSelect);
                if (citySelect) {
                    citySelect.innerHTML = '<option value="">Select City</option>';
                    window.initSelect2(citySelect);
                }
                return;
            }

            // Auto-detect and select corresponding timezone for known countries
            const tzSelect = container.querySelector('.geo-timezone');
            if (tzSelect) {
                const countryLower = selectedCountry.toLowerCase();
                let matchedTz = '';
                if (countryLower === 'india') {
                    matchedTz = 'Asia/Kolkata';
                } else if (countryLower === 'united states') {
                    matchedTz = 'America/New_York';
                } else if (countryLower === 'united kingdom') {
                    matchedTz = 'Europe/London';
                } else if (countryLower === 'united arab emirates') {
                    matchedTz = 'Asia/Dubai';
                } else if (countryLower === 'bangladesh') {
                    matchedTz = 'Asia/Dhaka';
                } else if (countryLower === 'pakistan') {
                    matchedTz = 'Asia/Karachi';
                } else if (countryLower === 'singapore') {
                    matchedTz = 'Asia/Singapore';
                }

                if (matchedTz) {
                    tzSelect.value = matchedTz;
                    window.initSelect2(tzSelect);
                }
            }

            stateSelect.innerHTML = '<option value="">Loading...</option>';
            window.initSelect2(stateSelect);

            const states = await getStates(selectedCountry);
            stateSelect.innerHTML = '<option value="">Select State</option>';
            states.forEach(s => {
                const option = document.createElement('option');
                option.value = s.name;
                option.textContent = s.name;
                if (s.name.toLowerCase() === initialState.toLowerCase()) {
                    option.selected = true;
                }
                stateSelect.appendChild(option);
            });
            initialState = ''; // consume

            window.initSelect2(stateSelect);
            
            // Trigger change to update cities
            $(stateSelect).trigger('change.geo');
        }

        async function updateCities() {
            if (!citySelect) return;
            const selectedCountry = countrySelect.value;
            const selectedState = stateSelect ? stateSelect.value : '';
            if (!selectedCountry || !selectedState) {
                citySelect.innerHTML = '<option value="">Select City</option>';
                window.initSelect2(citySelect);
                return;
            }

            citySelect.innerHTML = '<option value="">Loading...</option>';
            window.initSelect2(citySelect);

            const cities = await getCities(selectedCountry, selectedState);
            citySelect.innerHTML = '<option value="">Select City</option>';
            cities.forEach(c => {
                const option = document.createElement('option');
                option.value = c;
                option.textContent = c;
                if (c.toLowerCase() === initialCity.toLowerCase()) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
            initialCity = ''; // consume

            window.initSelect2(citySelect);
        }

        // Bind events using jQuery to capture both Select2 UI changes and programmatic triggers
        $(countrySelect).on('change.geo', updateStates);
        if (stateSelect) {
            $(stateSelect).on('change.geo', updateCities);
        }

        // If there is a preselected country, trigger states loading
        if (countrySelect.value) {
            await updateStates();
        }
    };

    // Auto initialize forms on load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('.geo-country')) {
                window.setupGeoFields(form);
            }
            if (form.querySelector('.geo-timezone')) {
                window.setupTimezones(form);
            }
        });
    });

    // Auto initialize dynamic modals when they are shown
    $(document).on('shown.bs.modal', '.modal', function() {
        const modal = this;
        if (modal.querySelector('.geo-country')) {
            window.setupGeoFields(modal);
        }
        if (modal.querySelector('.geo-timezone')) {
            window.setupTimezones(modal);
        }
    });

    // Edit modal listeners
    $(document).on('click', '.btn-edit-company, .btn-edit-branch', function() {
        const targetBtn = this;
        setTimeout(() => {
            const modalId = targetBtn.dataset.bsTarget || targetBtn.getAttribute('data-bs-target');
            const modal = document.querySelector(modalId);
            if (modal) {
                const countrySelect = modal.querySelector('.geo-country');
                const stateSelect = modal.querySelector('.geo-state');
                const citySelect = modal.querySelector('.geo-city');
                const timezoneSelect = modal.querySelector('.geo-timezone');

                let dataObj = null;
                if (targetBtn.dataset.company) {
                    dataObj = JSON.parse(atob(targetBtn.dataset.company));
                } else if (targetBtn.dataset.branch) {
                    dataObj = JSON.parse(atob(targetBtn.dataset.branch));
                }

                if (dataObj) {
                    if (countrySelect) countrySelect.dataset.initialValue = dataObj.country || '';
                    if (stateSelect) stateSelect.dataset.initialValue = dataObj.state || '';
                    if (citySelect) citySelect.dataset.initialValue = dataObj.city || '';
                    if (timezoneSelect) timezoneSelect.dataset.initialValue = dataObj.timezone || '';

                    window.setupGeoFields(modal);
                    window.setupTimezones(modal);
                }
            }
        }, 150);
    });
})();
