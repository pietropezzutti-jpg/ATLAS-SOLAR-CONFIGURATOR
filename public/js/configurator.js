(function () {
    'use strict';

    const config = window.ASC_CONFIG || {};
    const mapConfig = config.map || {};
    const storageKey = config.storageKey || 'atlas_solar_configurator_state';
    const root = document.querySelector('[data-asc-configurator]');
    if (!root) { return; }

    const emptyLocation = () => ({
        status: 'idle',
        provider: null,
        query: '',
        candidates: [],
        manualReference: null,
        selectedCandidateIndex: null,
        propertyPosition: { latitude: null, longitude: null, confirmed: false, source: null }
    });

    const defaultState = {
        sessionId: config.sessionId || root.getAttribute('data-session-id') || '',
        address: { raw: '', formatted: '', latitude: null, longitude: null, confirmed: false },
        location: emptyLocation(),
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

    const clone = value => JSON.parse(JSON.stringify(value));

    function merge(base, patch) {
        if (Array.isArray(patch)) { return patch.slice(); }
        if (!patch || typeof patch !== 'object') { return patch; }
        const out = Array.isArray(base) ? base.slice() : Object.assign({}, base || {});
        Object.keys(patch).forEach(key => {
            const value = patch[key];
            if (Array.isArray(value)) {
                out[key] = value.slice();
            } else if (
                value && typeof value === 'object' &&
                base && base[key] && typeof base[key] === 'object' &&
                !Array.isArray(base[key])
            ) {
                out[key] = merge(base[key], value);
            } else {
                out[key] = value;
            }
        });
        return out;
    }

    function finite(value) {
        return value !== null && value !== '' && Number.isFinite(Number(value));
    }

    function latitude(value) {
        const n = Number(value);
        return Number.isFinite(n) && n >= -90 && n <= 90 ? n : null;
    }

    function longitude(value) {
        const n = Number(value);
        return Number.isFinite(n) && n >= -180 && n <= 180 ? n : null;
    }

    function normalizeLocation(value) {
        const loc = merge(emptyLocation(), value || {});
        if (!Array.isArray(loc.candidates)) { loc.candidates = []; }
        if (loc.selectedCandidateIndex !== null && !Number.isInteger(loc.selectedCandidateIndex)) {
            loc.selectedCandidateIndex = null;
        }
        loc.manualReference = normalizeCandidate(loc.manualReference);
        const pos = loc.propertyPosition || {};
        loc.propertyPosition = {
            latitude: finite(pos.latitude) ? Number(pos.latitude) : null,
            longitude: finite(pos.longitude) ? Number(pos.longitude) : null,
            confirmed: Boolean(pos.confirmed),
            source: pos.source || null
        };
        return loc;
    }

    function migrate(stored) {
        const s = Object.assign({}, stored || {});
        if (s.currentStep === 'address' || s.currentStep === 'placeholder') { s.currentStep = 1; }
        if (!Number.isInteger(s.currentStep)) { s.currentStep = 1; }
        s.location = normalizeLocation(s.location);

        if (
            s.address && s.address.confirmed === true &&
            (!finite(s.address.latitude) || !finite(s.address.longitude))
        ) {
            s.address.confirmed = false;
        }

        if (
            s.address && s.address.confirmed === true &&
            finite(s.address.latitude) && finite(s.address.longitude)
        ) {
            s.location.status = 'confirmed';
            s.location.propertyPosition = {
                latitude: Number(s.address.latitude),
                longitude: Number(s.address.longitude),
                confirmed: true,
                source: s.location.propertyPosition.source || 'migrated_confirmed'
            };
        }

        if (
            Number(s.currentStep) > 1 &&
            (
                !s.address || s.address.confirmed !== true ||
                !finite(s.address.latitude) || !finite(s.address.longitude)
            )
        ) {
            s.currentStep = 1;
        }
        return s;
    }

    function attribution(existing) {
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
        const out = {};
        Object.keys(mapping).forEach(key => {
            if (params.has(key)) { out[mapping[key]] = params.get(key); }
        });
        if (!existing.referrer && document.referrer) { out.referrer = document.referrer; }
        if (!existing.landingUrl) { out.landingUrl = window.location.href; }
        return out;
    }

    function hydrateState() {
        let stored = {};
        try { stored = JSON.parse(localStorage.getItem(storageKey) || '{}'); } catch (_) {}
        const s = merge(clone(defaultState), migrate(stored));
        s.location = normalizeLocation(s.location);
        s.marketing = merge(s.marketing, attribution(s.marketing));
        return s;
    }

    let state = hydrateState();
    let map = null;
    let marker = null;
    let geocodeController = null;
    let searching = false;
    let hasStartedAddress = Boolean(state.address.raw);
    let hasStartedLead = Boolean(state.contact && (state.contact.firstName || state.contact.email));

    function persist() {
        try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (_) {}
    }

    function setState(patch) {
        state = merge(state, patch || {});
        state.location = normalizeLocation(state.location);
        persist();
        return getState();
    }

    function getState() { return clone(state); }

    function trackEvent(name, payload) {
        const event = { name, payload: payload || {}, timestamp: new Date().toISOString() };
        state.events.push(event);
        persist();
        root.dispatchEvent(new CustomEvent('asc:event', { detail: event }));
    }

    function showStep(step) {
        const n = Number(step);
        root.querySelectorAll('[data-asc-step]').forEach(node => {
            node.hidden = Number(node.getAttribute('data-asc-step')) !== n;
        });
        setState({ currentStep: n });
        if (n === 1) {
            renderLocation();
            if (map) { setTimeout(() => map.invalidateSize(), 0); }
        }
        const active = root.querySelector('[data-asc-step="' + n + '"]');
        const focus = active && active.querySelector('input, button, select, textarea');
        if (focus) { setTimeout(() => focus.focus(), 0); }
    }

    function nextStep() { showStep(Math.min(6, Number(state.currentStep) + 1)); }
    function previousStep() { showStep(Math.max(1, Number(state.currentStep) - 1)); }

    function reset() {
        if (geocodeController) { geocodeController.abort(); }
        geocodeController = null;
        searching = false;
        localStorage.removeItem(storageKey);
        state = merge(clone(defaultState), {
            sessionId: config.sessionId || root.getAttribute('data-session-id') || ''
        });
        state.location = emptyLocation();
        state.marketing = merge(state.marketing, attribution(state.marketing));
        hasStartedAddress = false;
        hasStartedLead = false;
        persist();
        hydrateInputs();
        clearMap();
        trackEvent('configurator_reset');
        showStep(1);
    }

    function selectedValue(name) {
        const input = root.querySelector('input[name="' + name + '"]:checked');
        return input ? input.value : null;
    }

    function setRadio(name, value) {
        root.querySelectorAll('input[name="' + name + '"]').forEach(input => {
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
        if (annual > 0) { result.annualKwh = Math.max(result.annualKwh, Math.round(annual * 1.05)); }
        return result;
    }

    function renderMockResult() {
        const result = state.solarAssessment || mockScenario(state.consumption.monthlyBillBand);
        const storage = state.batteryScenario || { mode: 'mock', capacityKwh: result.storageKwh };
        const format = new Intl.NumberFormat('it-IT');
        const values = {
            '[data-asc-result-kwp]': result.kwp.toFixed(1).replace('.', ',') + ' kWp',
            '[data-asc-result-kwh]': format.format(result.annualKwh) + ' kWh/anno',
            '[data-asc-result-storage]': storage.capacityKwh + ' kWh',
            '[data-asc-result-saving]': '€ ' + format.format(result.savingEur) + '/anno'
        };
        Object.keys(values).forEach(selector => {
            const node = root.querySelector(selector);
            if (node) { node.textContent = values[selector]; }
        });
    }

    function hydrateInputs() {
        const address = root.querySelector('[data-asc-address-input]');
        if (address) { address.value = state.address.raw || ''; }
        setRadio('asc_property_type', state.property.type);
        setRadio('asc_ownership', state.property.ownership);
        setRadio('asc_bill_band', state.consumption.monthlyBillBand);

        const annual = root.querySelector('[data-asc-annual-kwh]');
        if (annual) { annual.value = state.consumption.annualKwh || ''; }

        const checks = {
            asc_heat_pump: state.energyProfile.heatPump,
            asc_ev: state.energyProfile.electricVehicle,
            asc_induction: state.energyProfile.induction,
            asc_pool: state.energyProfile.pool,
            asc_privacy: state.contact.privacyAccepted
        };
        Object.keys(checks).forEach(name => {
            const input = root.querySelector('input[name="' + name + '"]');
            if (input) { input.checked = Boolean(checks[name]); }
        });

        const contacts = {
            asc_first_name: state.contact.firstName,
            asc_last_name: state.contact.lastName,
            asc_phone: state.contact.phone,
            asc_email: state.contact.email
        };
        Object.keys(contacts).forEach(name => {
            const input = root.querySelector('input[name="' + name + '"]');
            if (input) { input.value = contacts[name] || ''; }
        });

        if (state.solarAssessment) { renderMockResult(); }
        renderLocation();
    }

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function mapAttribution() {
        const label = mapConfig.tileAttributionLabel || '© OpenStreetMap contributors';
        const url = mapConfig.tileAttributionUrl || 'https://www.openstreetmap.org/copyright';
        return '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">' + esc(label) + '</a>';
    }

    function ensureMap() {
        const element = root.querySelector('[data-asc-map]');
        if (!element || !window.L || typeof window.L.map !== 'function') { return false; }
        if (map) { return true; }

        const center = Array.isArray(mapConfig.defaultCenter) ? mapConfig.defaultCenter : [42.5, 12.5];
        map = window.L.map(element, { zoomControl: true, scrollWheelZoom: true })
            .setView([Number(center[0]), Number(center[1])], Number(mapConfig.defaultZoom) || 5);

        window.L.tileLayer(
            mapConfig.tileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            { maxZoom: Number(mapConfig.maxZoom) || 19, attribution: mapAttribution() }
        ).addTo(map);

        map.on('click', event => {
            updatePropertyPosition(event.latlng.lat, event.latlng.lng, 'map_click', true);
        });
        return true;
    }

    function markerIcon() {
        return window.L.divIcon({
            className: 'asc-leaflet-div-icon',
            html: '<span class="asc-map-marker-pin" aria-hidden="true"></span>',
            iconSize: [30, 38],
            iconAnchor: [15, 36]
        });
    }

    function placeMarker(latValue, lonValue, animate) {
        if (!ensureMap()) { return false; }
        const lat = latitude(latValue);
        const lon = longitude(lonValue);
        if (lat === null || lon === null) { return false; }

        if (!marker) {
            marker = window.L.marker([lat, lon], {
                draggable: true,
                icon: markerIcon(),
                keyboard: true,
                title: 'Posizione dell’immobile'
            }).addTo(map);
            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                updatePropertyPosition(pos.lat, pos.lng, 'marker_drag', true);
            });
        } else {
            marker.setLatLng([lat, lon]);
        }

        map.setView([lat, lon], Number(mapConfig.propertyZoom) || 19, { animate: animate !== false });
        setTimeout(() => map.invalidateSize(), 0);
        return true;
    }

    function clearMap() {
        if (marker && map) { map.removeLayer(marker); }
        marker = null;
        const center = Array.isArray(mapConfig.defaultCenter) ? mapConfig.defaultCenter : [42.5, 12.5];
        if (map) { map.setView([Number(center[0]), Number(center[1])], Number(mapConfig.defaultZoom) || 5); }
    }

    function showManualReference(candidate) {
        if (!candidate || !ensureMap()) { return false; }

        const lat = latitude(candidate.latitude);
        const lon = longitude(candidate.longitude);
        if (lat === null || lon === null) { return false; }

        if (marker && map) {
            map.removeLayer(marker);
            marker = null;
        }

        const propertyZoom = Number(mapConfig.propertyZoom) || 19;
        const referenceZoom = Math.max(15, propertyZoom - 2);

        map.setView([lat, lon], referenceZoom, { animate: false });
        setTimeout(() => map.invalidateSize(), 0);
        return true;
    }

    function coords(lat, lon) { return Number(lat).toFixed(6) + ', ' + Number(lon).toFixed(6); }

    function renderCandidates() {
        const section = root.querySelector('[data-asc-candidate-section]');
        const list = root.querySelector('[data-asc-candidate-list]');
        if (!section || !list) { return; }
        list.textContent = '';
        const candidates = state.location.candidates || [];
        section.hidden = candidates.length <= 1;
        if (section.hidden) { return; }

        candidates.forEach((candidate, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'asc-candidate-button';
            if (state.location.selectedCandidateIndex === index) {
                button.classList.add('asc-candidate-button-selected');
            }
            button.textContent = candidate.displayName || ('Risultato ' + (index + 1));
            button.addEventListener('click', () => selectCandidate(index, true));
            list.appendChild(button);
        });
    }

    function statusMessage() {
        const messages = {
            searching: 'Sto cercando l’immobile…',
            ambiguous: 'Ho trovato più posizioni compatibili con l’indirizzo.',
            resolved: 'Controlla il punto sulla mappa prima di continuare.',
            candidate_selected: 'Controlla il punto sulla mappa prima di continuare.',
            manual_position_required: 'Il civico non è localizzato con precisione. Individua il tetto sulla mappa e fai clic sulla posizione corretta.',
            confirmed: 'Posizione dell’immobile confermata.',
            not_found: 'Non ho trovato una posizione affidabile. Verifica indirizzo, numero civico e comune.',
            error: 'Non riesco a localizzare l’indirizzo in questo momento. Riprova tra poco.'
        };
        return messages[state.location.status] || '';
    }

    function renderLocation() {
        const panel = root.querySelector('[data-asc-location-panel]');
        const status = root.querySelector('[data-asc-location-status]');
        const mapWrap = root.querySelector('[data-asc-map-wrap]');
        const summary = root.querySelector('[data-asc-position-summary]');
        const address = root.querySelector('[data-asc-selected-address]');
        const coordinateNode = root.querySelector('[data-asc-selected-coordinates]');
        const confirm = root.querySelector('[data-asc-confirm-position]');
        if (!panel || !status || !mapWrap || !summary || !address || !coordinateNode || !confirm) { return; }

        const candidates = state.location.candidates || [];
        const manualReference = state.location.manualReference || null;
        const pos = state.location.propertyPosition || {};
        const hasPosition = finite(pos.latitude) && finite(pos.longitude);
        panel.hidden = state.location.status === 'idle'
            && !candidates.length
            && !manualReference
            && !hasPosition;
        status.textContent = statusMessage();
        status.setAttribute('data-status', state.location.status || 'idle');
        renderCandidates();

        if (!hasPosition && manualReference) {
            mapWrap.hidden = false;
            summary.hidden = true;
            confirm.disabled = true;

            if (!showManualReference(manualReference)) {
                mapWrap.hidden = true;
                status.textContent = 'La mappa non è disponibile. Ricarica la pagina e riprova.';
                status.setAttribute('data-status', 'error');
            }
            return;
        }

        if (!hasPosition) {
            mapWrap.hidden = true;
            summary.hidden = true;
            confirm.disabled = true;
            if (['idle', 'searching', 'ambiguous', 'not_found', 'error'].includes(state.location.status)) {
                clearMap();
            }
            return;
        }

        mapWrap.hidden = false;
        summary.hidden = false;
        address.textContent = state.address.formatted || state.address.raw || 'Posizione selezionata';
        coordinateNode.textContent = coords(pos.latitude, pos.longitude);
        if (!placeMarker(pos.latitude, pos.longitude, false)) {
            status.textContent = 'La mappa non è disponibile. Ricarica la pagina prima di confermare la posizione.';
            status.setAttribute('data-status', 'error');
            confirm.disabled = true;
            return;
        }
        confirm.disabled = false;
        confirm.textContent = state.address.confirmed
            ? 'POSIZIONE CONFERMATA — CONTINUA'
            : 'CONFERMA POSIZIONE E CONTINUA';
    }

    function clearLocationForEdit() {
        if (state.location.status === 'idle' && !state.address.confirmed && !(state.location.candidates || []).length) {
            return;
        }
        setState({
            address: {
                raw: state.address.raw,
                formatted: state.address.raw,
                latitude: null,
                longitude: null,
                confirmed: false
            },
            location: emptyLocation(),
            roofAssessment: null
        });
        clearMap();
        renderLocation();
    }

    function setSearching(value) {
        searching = Boolean(value);
        const button = root.querySelector('[data-asc-address-submit]');
        if (button) {
            button.disabled = searching;
            button.textContent = searching ? 'RICERCA IN CORSO…' : 'TROVA L’IMMOBILE';
        }
    }

    function normalizeCandidate(candidate) {
        if (!candidate || typeof candidate !== 'object') { return null; }
        const lat = latitude(candidate.latitude);
        const lon = longitude(candidate.longitude);
        if (lat === null || lon === null) { return null; }

        const snap = candidate.buildingSnap && typeof candidate.buildingSnap === 'object'
            ? candidate.buildingSnap
            : {};

        return {
            id: candidate.id !== undefined ? String(candidate.id) : '',
            placeId: candidate.placeId !== undefined ? String(candidate.placeId) : '',
            displayName: String(candidate.displayName || '').trim(),
            latitude: lat,
            longitude: lon,
            originalLatitude: finite(candidate.originalLatitude) ? Number(candidate.originalLatitude) : lat,
            originalLongitude: finite(candidate.originalLongitude) ? Number(candidate.originalLongitude) : lon,
            type: String(candidate.type || ''),
            category: String(candidate.category || ''),
            confidence: finite(candidate.confidence) ? Number(candidate.confidence) : null,
            confidenceBuildingLevel: finite(candidate.confidenceBuildingLevel)
                ? Number(candidate.confidenceBuildingLevel)
                : null,
            matchType: String(candidate.matchType || ''),
            buildingSnap: {
                eligible: Boolean(snap.eligible),
                attempted: Boolean(snap.attempted),
                applied: Boolean(snap.applied),
                distanceMeters: finite(snap.distanceMeters) ? Number(snap.distanceMeters) : null,
                buildingFeatureCount: Number.isInteger(Number(snap.buildingFeatureCount))
                    ? Number(snap.buildingFeatureCount)
                    : null,
                source: snap.source ? String(snap.source) : null,
                reason: snap.reason ? String(snap.reason) : null
            }
        };
    }

    function selectCandidate(index, userInitiated) {
        const candidates = state.location.candidates || [];
        const candidate = candidates[index];
        if (!candidate) { return; }
        const snapApplied = Boolean(candidate.buildingSnap && candidate.buildingSnap.applied);
        const source = snapApplied ? 'geoapify_building_snap' : 'geocoder_candidate';

        setState({
            address: {
                raw: state.address.raw,
                formatted: candidate.displayName || state.address.raw,
                latitude: null,
                longitude: null,
                confirmed: false
            },
            location: {
                status: candidates.length > 1 ? 'candidate_selected' : 'resolved',
                provider: state.location.provider || 'nominatim-compatible',
                query: state.location.query || state.address.raw,
                candidates,
                manualReference: null,
                selectedCandidateIndex: index,
                propertyPosition: {
                    latitude: candidate.latitude,
                    longitude: candidate.longitude,
                    confirmed: false,
                    source
                }
            },
            roofAssessment: null
        });
        if (userInitiated) {
            trackEvent('address_candidate_selected', { candidateIndex: index, candidateCount: candidates.length });
        }
        if (snapApplied) {
            trackEvent('property_position_snapped', {
                source,
                distanceMeters: candidate.buildingSnap.distanceMeters,
                reason: candidate.buildingSnap.reason || null
            });
        }
        renderLocation();
    }

    function updatePropertyPosition(latValue, lonValue, source, userInitiated) {
        const lat = latitude(latValue);
        const lon = longitude(lonValue);
        if (lat === null || lon === null) { return; }

        setState({
            address: {
                raw: state.address.raw,
                formatted: state.address.formatted || state.address.raw,
                latitude: null,
                longitude: null,
                confirmed: false
            },
            location: {
                status: 'candidate_selected',
                provider: state.location.provider,
                query: state.location.query,
                candidates: state.location.candidates,
                manualReference: null,
                selectedCandidateIndex: state.location.selectedCandidateIndex,
                propertyPosition: {
                    latitude: lat,
                    longitude: lon,
                    confirmed: false,
                    source: source || 'map_adjusted'
                }
            },
            roofAssessment: null
        });
        if (userInitiated) { trackEvent('property_position_adjusted', { source: source || 'map_adjusted' }); }
        renderLocation();
    }

    function confirmPropertyPosition() {
        const pos = state.location.propertyPosition || {};
        const lat = latitude(pos.latitude);
        const lon = longitude(pos.longitude);
        if (lat === null || lon === null) { renderLocation(); return; }

        setState({
            address: {
                raw: state.address.raw,
                formatted: state.address.formatted || state.address.raw,
                latitude: lat,
                longitude: lon,
                confirmed: true
            },
            location: {
                status: 'confirmed',
                provider: state.location.provider,
                query: state.location.query,
                candidates: state.location.candidates,
                manualReference: null,
                selectedCandidateIndex: state.location.selectedCandidateIndex,
                propertyPosition: {
                    latitude: lat,
                    longitude: lon,
                    confirmed: true,
                    source: pos.source || 'geocoder_candidate'
                }
            }
        });
        trackEvent('property_position_confirmed', { source: state.location.propertyPosition.source || 'geocoder_candidate' });
        showStep(2);
    }

    async function searchAddress(rawAddress) {
        if (searching || !rawAddress) { return; }
        if (!mapConfig.geocodeUrl) {
            setState({ location: { status: 'error' } });
            renderLocation();
            return;
        }
        if (state.location.query === rawAddress && (state.location.candidates || []).length) {
            renderLocation();
            return;
        }

        if (geocodeController) { geocodeController.abort(); }
        geocodeController = new AbortController();
        const timeoutId = setTimeout(() => {
            if (geocodeController) { geocodeController.abort(); }
        }, 10000);

        setSearching(true);
        setState({
            address: { raw: rawAddress, formatted: rawAddress, latitude: null, longitude: null, confirmed: false },
            location: {
                status: 'searching',
                provider: null,
                query: rawAddress,
                candidates: [],
                manualReference: null,
                selectedCandidateIndex: null,
                propertyPosition: { latitude: null, longitude: null, confirmed: false, source: null }
            },
            roofAssessment: null
        });
        trackEvent('address_submitted', { hasAddress: true });
        trackEvent('address_geocode_started');
        renderLocation();

        try {
            const url = new URL(mapConfig.geocodeUrl, window.location.href);
            url.searchParams.set('q', rawAddress);
            const response = await window.fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: geocodeController.signal
            });

            let payload = null;
            try { payload = await response.json(); } catch (_) {}
            if (!response.ok) {
                throw new Error(payload && payload.message ? String(payload.message) : 'GEOCODER_HTTP_' + response.status);
            }

            const candidates = Array.isArray(payload && payload.candidates)
                ? payload.candidates.map(normalizeCandidate).filter(Boolean)
                : [];
            const manualReference = normalizeCandidate(payload && payload.manualFallback);
            const provider = payload && payload.provider ? String(payload.provider) : 'nominatim-compatible';

            if (!candidates.length) {
                if (manualReference) {
                    setState({
                        location: {
                            status: 'manual_position_required',
                            provider,
                            query: rawAddress,
                            candidates: [],
                            manualReference,
                            selectedCandidateIndex: null,
                            propertyPosition: { latitude: null, longitude: null, confirmed: false, source: null }
                        }
                    });
                    trackEvent('address_geocode_manual_fallback');
                } else {
                    setState({
                        location: {
                            status: 'not_found',
                            provider,
                            query: rawAddress,
                            candidates: [],
                            manualReference: null,
                            selectedCandidateIndex: null,
                            propertyPosition: { latitude: null, longitude: null, confirmed: false, source: null }
                        }
                    });
                    trackEvent('address_geocode_not_found');
                }
                renderLocation();
                return;
            }

            setState({
                location: {
                    status: candidates.length === 1 ? 'resolved' : 'ambiguous',
                    provider,
                    query: rawAddress,
                    candidates,
                    manualReference: null,
                    selectedCandidateIndex: null,
                    propertyPosition: { latitude: null, longitude: null, confirmed: false, source: null }
                }
            });

            if (candidates.length === 1) {
                trackEvent('address_geocode_resolved', { candidateCount: 1 });
                selectCandidate(0, false);
            } else {
                trackEvent('address_geocode_ambiguous', { candidateCount: candidates.length });
                renderLocation();
            }
        } catch (error) {
            setState({ location: { status: 'error' } });
            trackEvent('address_geocode_failed', {
                reason: error && error.message ? String(error.message).slice(0, 120) : 'unknown'
            });
            renderLocation();
        } finally {
            clearTimeout(timeoutId);
            geocodeController = null;
            setSearching(false);
        }
    }

    function bindAddressStep() {
        const form = root.querySelector('[data-asc-address-form]');
        const input = root.querySelector('[data-asc-address-input]');
        const error = root.querySelector('[data-asc-address-error]');
        const confirm = root.querySelector('[data-asc-confirm-position]');
        if (!form || !input || !error || !confirm) { return; }

        input.addEventListener('input', () => {
            if (!hasStartedAddress) {
                hasStartedAddress = true;
                trackEvent('address_started');
            }
            error.hidden = true;
            if (input.value.trim() !== String(state.address.raw || '').trim()) { clearLocationForEdit(); }
        });

        form.addEventListener('submit', event => {
            event.preventDefault();
            const raw = input.value.trim();
            if (!raw) {
                error.hidden = false;
                input.focus();
                return;
            }
            error.hidden = true;
            searchAddress(raw);
        });

        confirm.addEventListener('click', confirmPropertyPosition);
    }

    function bindPropertyStep() {
        const form = root.querySelector('[data-asc-property-form]');
        const error = root.querySelector('[data-asc-property-error]');
        if (!form || !error) { return; }

        form.addEventListener('submit', event => {
            event.preventDefault();
            if (
                state.address.confirmed !== true ||
                !finite(state.address.latitude) ||
                !finite(state.address.longitude)
            ) {
                showStep(1);
                return;
            }

            const type = selectedValue('asc_property_type');
            const ownership = selectedValue('asc_ownership');
            if (!type || !ownership) {
                error.hidden = false;
                const target = form.querySelector('input:not(:checked)') || form.querySelector('input');
                if (target) { target.focus(); }
                return;
            }
            error.hidden = true;
            setState({ property: { type, ownership } });
            trackEvent('property_completed');
            showStep(3);
        });
    }

    function bindConsumptionStep() {
        const form = root.querySelector('[data-asc-consumption-form]');
        const error = root.querySelector('[data-asc-consumption-error]');
        const annual = root.querySelector('[data-asc-annual-kwh]');
        if (!form || !error || !annual) { return; }

        form.addEventListener('submit', event => {
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
        if (button) { button.addEventListener('click', () => showStep(5)); }
    }

    function bindContactStep() {
        const form = root.querySelector('[data-asc-contact-form]');
        const error = root.querySelector('[data-asc-contact-error]');
        if (!form || !error) { return; }

        form.addEventListener('input', () => {
            if (!hasStartedLead) {
                hasStartedLead = true;
                trackEvent('lead_form_started');
            }
            error.hidden = true;
        });

        form.addEventListener('submit', event => {
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

            setState({ contact: { firstName, lastName, phone, email, privacyAccepted } });
            trackEvent('lead_demo_completed', { mode: 'demo', transmitted: false });
            showStep(6);
        });
    }

    function bindNavigation() {
        root.querySelectorAll('[data-asc-back]').forEach(button => {
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
    trackEvent('configurator_view', { version: config.version || '0.3.0' });

    window.AtlasSolarConfigurator = {
        nextStep,
        previousStep,
        goToStep: showStep,
        setState,
        getState,
        trackEvent,
        reset,
        searchAddress,
        confirmPropertyPosition
    };
}());
