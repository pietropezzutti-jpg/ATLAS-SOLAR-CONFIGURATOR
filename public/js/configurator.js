(function () {
    'use strict';

    const config = window.ASC_CONFIG || {};
    const storageKey = config.storageKey || 'atlas_solar_configurator_state';
    const root = document.querySelector('[data-asc-configurator]');

    if (!root) {
        return;
    }

    const defaultState = {
        sessionId: config.sessionId || root.getAttribute('data-session-id') || '',
        address: {
            raw: '',
            formatted: '',
            latitude: null,
            longitude: null,
            confirmed: false
        },
        property: {
            type: null,
            ownership: null
        },
        consumption: {
            annualKwh: null,
            monthlyBillBand: null
        },
        energyProfile: {
            heatPump: null,
            electricVehicle: null,
            induction: null,
            pool: null
        },
        roofAssessment: null,
        solarAssessment: null,
        batteryScenario: null,
        contact: null,
        marketing: {
            utmSource: null,
            utmMedium: null,
            utmCampaign: null,
            utmContent: null,
            utmTerm: null,
            gclid: null,
            fbclid: null,
            referrer: document.referrer || '',
            landingUrl: window.location.href
        },
        currentStep: 'address',
        events: []
    };

    let state = hydrateState();
    let hasStartedAddress = Boolean(state.address.raw);

    function hydrateState() {
        let stored = {};

        try {
            stored = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
        } catch (error) {
            stored = {};
        }

        const merged = mergeState(defaultState, stored);
        merged.marketing = mergeState(merged.marketing, readAttribution(merged.marketing));

        return merged;
    }

    function mergeState(base, patch) {
        const output = Array.isArray(base) ? base.slice() : Object.assign({}, base);

        Object.keys(patch || {}).forEach(function (key) {
            const value = patch[key];

            if (value && typeof value === 'object' && !Array.isArray(value) && base[key]) {
                output[key] = mergeState(base[key], value);
                return;
            }

            output[key] = value;
        });

        return output;
    }

    function readAttribution(existingMarketing) {
        const params = new URLSearchParams(window.location.search);
        const mapping = {
            utm_source: 'utmSource',
            utm_medium: 'utmMedium',
            utm_campaign: 'utmCampaign',
            utm_content: 'utmContent',
            utm_term: 'utmTerm',
            gclid: 'gclid',
            fbclid: 'fbclid'
        };
        const attribution = {};

        Object.keys(mapping).forEach(function (queryKey) {
            if (params.has(queryKey)) {
                attribution[mapping[queryKey]] = params.get(queryKey);
            }
        });

        if (!existingMarketing.referrer && document.referrer) {
            attribution.referrer = document.referrer;
        }

        if (!existingMarketing.landingUrl) {
            attribution.landingUrl = window.location.href;
        }

        return attribution;
    }

    function persistState() {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            return;
        }
    }

    function setState(patch) {
        state = mergeState(state, patch || {});
        persistState();
        return getState();
    }

    function getState() {
        return JSON.parse(JSON.stringify(state));
    }

    function trackEvent(name, payload) {
        const event = {
            name: name,
            payload: payload || {},
            timestamp: new Date().toISOString()
        };

        state.events.push(event);
        persistState();
        root.dispatchEvent(new CustomEvent('asc:event', { detail: event }));
    }

    function nextStep() {
        setState({ currentStep: 'placeholder' });
        showPlaceholder();
    }

    function previousStep() {
        setState({ currentStep: 'address' });
        showAddressStep();
    }

    function showPlaceholder() {
        const form = root.querySelector('[data-asc-address-form]');
        const placeholder = root.querySelector('[data-asc-placeholder]');

        if (form) {
            form.hidden = true;
        }

        if (placeholder) {
            placeholder.hidden = false;
        }
    }

    function showAddressStep() {
        const form = root.querySelector('[data-asc-address-form]');
        const placeholder = root.querySelector('[data-asc-placeholder]');

        if (form) {
            form.hidden = false;
        }

        if (placeholder) {
            placeholder.hidden = true;
        }
    }

    function bindAddressStep() {
        const form = root.querySelector('[data-asc-address-form]');
        const input = root.querySelector('[data-asc-address-input]');
        const error = root.querySelector('[data-asc-error]');

        if (!form || !input || !error) {
            return;
        }

        input.value = state.address.raw || '';

        input.addEventListener('input', function () {
            if (!hasStartedAddress) {
                hasStartedAddress = true;
                trackEvent('address_started');
            }

            if (!error.hidden) {
                error.hidden = true;
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const rawAddress = input.value.trim();

            if (!rawAddress) {
                error.hidden = false;
                input.focus();
                return;
            }

            setState({
                address: {
                    raw: rawAddress,
                    formatted: rawAddress,
                    latitude: null,
                    longitude: null,
                    confirmed: true
                }
            });

            trackEvent('address_submitted', { hasAddress: true });
            nextStep();
        });
    }

    bindAddressStep();
    trackEvent('configurator_view', { version: config.version || '0.1.0' });

    if (state.currentStep === 'placeholder' && state.address.raw) {
        showPlaceholder();
    }

    window.AtlasSolarConfigurator = {
        nextStep: nextStep,
        previousStep: previousStep,
        setState: setState,
        getState: getState,
        trackEvent: trackEvent
    };
}());
