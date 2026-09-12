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
        address: { raw: '', formatted: '', latitude: null, longitude: null, confirmed: false },
        property: { type: null, ownership: null },
        consumption: { annualKwh: null, monthlyBillBand: null },
        energyProfile: { heatPump: false, electricVehicle: false, induction: false, pool: false },
        roofAssessment: null,
        solarAssessment: null,
        batteryScenario: null,
        contact: { firstName: '', lastName: '', phone: '', email: '', privacyAccepted: false },
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
        currentStep: 1,
        events: []
    };

    let state = hydrateState();
    let hasStartedAddress = Boolean(state.address.raw);
    let hasStartedLead = Boolean(state.contact && (state.contact.firstName || state.contact.email));

    function mergeState(base, patch) {
        const output = Array.isArray(base) ? base.slice() : Object.assign({}, base);
        Object.keys(patch || {}).forEach(function (key) {
            const value = patch[key];
            if (value && typeof value === 'object' && !Array.isArray(value) && base[key] && typeof base[key] === 'object') {
                output[key] = mergeState(base[key], value);
            } else {
                output[key] = value;
            }
        });
        return output;
    }

    function migrateState(stored) {
        const migrated = Object.assign({}, stored || {});
        if (migrated.currentStep === 'address') {
            migrated.currentStep = 1;
        }
        if (migrated.currentStep === 'placeholder') {
            migrated.currentStep = migrated.address && migrated.address.raw ? 2 : 1;
        }
        if (!Number.isInteger(migrated.currentStep)) {
            migrated.currentStep = 1;
        }
        if (migrated.address && migrated.address.confirmed === true && !migrated.address.latitude && !migrated.address.longitude) {
            migrated.address.confirmed = false;
        }
        return migrated;
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

    function hydrateState() {
        let stored = {};
        try {
            stored = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
        } catch (error) {
            stored = {};
        }
        stored = migrateState(stored);
        const merged = mergeState(defaultState, stored);
        merged.marketing = mergeState(merged.marketing, readAttribution(merged.marketing));
        return merged;
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
        const event = { name: name, payload: payload || {}, timestamp: new Date().toISOString() };
        state.events.push(event);
        persistState();
        root.dispatchEvent(new CustomEvent('asc:event', { detail: event }));
    }

    function showStep(step) {
        root.querySelectorAll('[data-asc-step]').forEach(function (node) {
            node.hidden = Number(node.getAttribute('data-asc-step')) !== Number(step);
        });
        setState({ currentStep: Number(step) });
        const active = root.querySelector('[data-asc-step="' + Number(step) + '"]');
        if (active) {
            const focusTarget = active.querySelector('input, button, select, textarea');
            if (focusTarget) {
                window.setTimeout(function () { focusTarget.focus(); }, 0);
            }
        }
    }

    function nextStep() {
        showStep(Math.min(6, Number(state.currentStep) + 1));
    }

    function previousStep() {
        showStep(Math.max(1, Number(state.currentStep) - 1));
    }

    function reset() {
        window.localStorage.removeItem(storageKey);
        state = mergeState(defaultState, { sessionId: config.sessionId || root.getAttribute('data-session-id') || '' });
        state.marketing = mergeState(state.marketing, readAttribution(state.marketing));
        hasStartedAddress = false;
        hasStartedLead = false;
        persistState();
        hydrateInputs();
        trackEvent('configurator_reset');
        showStep(1);
    }

    function selectedValue(name) {
        const checked = root.querySelector('input[name="' + name + '"]:checked');
        return checked ? checked.value : null;
    }

    function setRadio(name, value) {
        root.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
            input.checked = input.value === value;
        });
    }

    function mockScenario(band) {
        const scenarios = {
            lt_70: { kwp: 3.0, annualKwh: 3600, storageKwh: 5, savingEur: 650 },
            '70_120': { kwp: 4.5, annualKwh: 5400, storageKwh: 5, savingEur: 900 },
            '120_180': { kwp: 6.0, annualKwh: 7200, storageKwh: 10, savingEur: 1300 },
            '180_300': { kwp: 8.0, annualKwh: 9600, storageKwh: 10, savingEur: 1800 },
            gt_300: { kwp: 10.0, annualKwh: 12000, storageKwh: 15, savingEur: 2400 }
        };
        const result = Object.assign({ mode: 'mock' }, scenarios[band] || scenarios['120_180']);
        const annual = Number(state.consumption.annualKwh || 0);
        if (annual > 0) {
            result.annualKwh = Math.max(result.annualKwh, Math.round(annual * 1.05));
        }
        return result;
    }

    function renderMockResult() {
        const result = state.solarAssessment || mockScenario(state.consumption.monthlyBillBand);
        const storage = state.batteryScenario || { mode: 'mock', capacityKwh: result.storageKwh };
        const formatNumber = new Intl.NumberFormat('it-IT');
        const kwp = root.querySelector('[data-asc-result-kwp]');
        const kwh = root.querySelector('[data-asc-result-kwh]');
        const battery = root.querySelector('[data-asc-result-storage]');
        const saving = root.querySelector('[data-asc-result-saving]');
        if (kwp) { kwp.textContent = result.kwp.toFixed(1).replace('.', ',') + ' kWp'; }
        if (kwh) { kwh.textContent = formatNumber.format(result.annualKwh) + ' kWh/anno'; }
        if (battery) { battery.textContent = storage.capacityKwh + ' kWh'; }
        if (saving) { saving.textContent = '€ ' + formatNumber.format(result.savingEur) + '/anno'; }
    }

    function hydrateInputs() {
        const address = root.querySelector('[data-asc-address-input]');
        if (address) { address.value = state.address.raw || ''; }
        setRadio('asc_property_type', state.property.type);
        setRadio('asc_ownership', state.property.ownership);
        setRadio('asc_bill_band', state.consumption.monthlyBillBand);
        const annual = root.querySelector('[data-asc-annual-kwh]');
        if (annual) { annual.value = state.consumption.annualKwh || ''; }
        const mapChecks = {
            asc_heat_pump: state.energyProfile.heatPump,
            asc_ev: state.energyProfile.electricVehicle,
            asc_induction: state.energyProfile.induction,
            asc_pool: state.energyProfile.pool,
            asc_privacy: state.contact.privacyAccepted
        };
        Object.keys(mapChecks).forEach(function (name) {
            const input = root.querySelector('input[name="' + name + '"]');
            if (input) { input.checked = Boolean(mapChecks[name]); }
        });
        const contactMap = {
            asc_first_name: state.contact.firstName,
            asc_last_name: state.contact.lastName,
            asc_phone: state.contact.phone,
            asc_email: state.contact.email
        };
        Object.keys(contactMap).forEach(function (name) {
            const input = root.querySelector('input[name="' + name + '"]');
            if (input) { input.value = contactMap[name] || ''; }
        });
        if (state.solarAssessment) { renderMockResult(); }
    }

    function bindAddressStep() {
        const form = root.querySelector('[data-asc-address-form]');
        const input = root.querySelector('[data-asc-address-input]');
        const error = root.querySelector('[data-asc-address-error]');
        if (!form || !input || !error) { return; }
        input.addEventListener('input', function () {
            if (!hasStartedAddress) {
                hasStartedAddress = true;
                trackEvent('address_started');
            }
            error.hidden = true;
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const rawAddress = input.value.trim();
            if (!rawAddress) {
                error.hidden = false;
                input.focus();
                return;
            }
            setState({ address: { raw: rawAddress, formatted: rawAddress, latitude: null, longitude: null, confirmed: false } });
            trackEvent('address_submitted', { hasAddress: true });
            showStep(2);
        });
    }

    function bindPropertyStep() {
        const form = root.querySelector('[data-asc-property-form]');
        const error = root.querySelector('[data-asc-property-error]');
        if (!form || !error) { return; }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const type = selectedValue('asc_property_type');
            const ownership = selectedValue('asc_ownership');
            if (!type || !ownership) {
                error.hidden = false;
                const target = form.querySelector('input:not(:checked)') || form.querySelector('input');
                if (target) { target.focus(); }
                return;
            }
            error.hidden = true;
            setState({ property: { type: type, ownership: ownership } });
            trackEvent('property_completed');
            showStep(3);
        });
    }

    function bindConsumptionStep() {
        const form = root.querySelector('[data-asc-consumption-form]');
        const error = root.querySelector('[data-asc-consumption-error]');
        const annual = root.querySelector('[data-asc-annual-kwh]');
        if (!form || !error || !annual) { return; }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const band = selectedValue('asc_bill_band');
            const annualValue = annual.value.trim() === '' ? null : Number(annual.value);
            if (!band || (annualValue !== null && (!Number.isFinite(annualValue) || annualValue < 0))) {
                error.hidden = false;
                return;
            }
            error.hidden = true;
            setState({
                consumption: { monthlyBillBand: band, annualKwh: annualValue },
                energyProfile: {
                    heatPump: Boolean(root.querySelector('input[name="asc_heat_pump"]:checked')),
                    electricVehicle: Boolean(root.querySelector('input[name="asc_ev"]:checked')),
                    induction: Boolean(root.querySelector('input[name="asc_induction"]:checked')),
                    pool: Boolean(root.querySelector('input[name="asc_pool"]:checked'))
                }
            });
            const scenario = mockScenario(band);
            setState({
                solarAssessment: scenario,
                batteryScenario: { mode: 'mock', capacityKwh: scenario.storageKwh }
            });
            renderMockResult();
            trackEvent('consumption_completed');
            trackEvent('mock_result_viewed', { mode: 'mock' });
            showStep(4);
        });
    }

    function bindResultStep() {
        const button = root.querySelector('[data-asc-result-next]');
        if (!button) { return; }
        button.addEventListener('click', function () {
            showStep(5);
        });
    }

    function bindContactStep() {
        const form = root.querySelector('[data-asc-contact-form]');
        const error = root.querySelector('[data-asc-contact-error]');
        if (!form || !error) { return; }
        form.addEventListener('input', function () {
            if (!hasStartedLead) {
                hasStartedLead = true;
                trackEvent('lead_form_started');
            }
            error.hidden = true;
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const firstName = form.elements.asc_first_name.value.trim();
            const lastName = form.elements.asc_last_name.value.trim();
            const phone = form.elements.asc_phone.value.trim();
            const email = form.elements.asc_email.value.trim();
            const privacyAccepted = Boolean(form.elements.asc_privacy.checked);
            const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            if (!firstName || !lastName || !phone || !validEmail || !privacyAccepted) {
                error.hidden = false;
                return;
            }
            setState({ contact: { firstName: firstName, lastName: lastName, phone: phone, email: email, privacyAccepted: privacyAccepted } });
            trackEvent('lead_demo_completed', { mode: 'demo', transmitted: false });
            showStep(6);
        });
    }

    function bindNavigation() {
        root.querySelectorAll('[data-asc-back]').forEach(function (button) {
            button.addEventListener('click', previousStep);
        });
        const resetButton = root.querySelector('[data-asc-reset]');
        if (resetButton) { resetButton.addEventListener('click', reset); }
    }

    bindAddressStep();
    bindPropertyStep();
    bindConsumptionStep();
    bindResultStep();
    bindContactStep();
    bindNavigation();
    hydrateInputs();
    showStep(state.currentStep || 1);
    trackEvent('configurator_view', { version: config.version || '0.2.0' });

    window.AtlasSolarConfigurator = {
        nextStep: nextStep,
        previousStep: previousStep,
        goToStep: showStep,
        setState: setState,
        getState: getState,
        trackEvent: trackEvent,
        reset: reset
    };
}());
