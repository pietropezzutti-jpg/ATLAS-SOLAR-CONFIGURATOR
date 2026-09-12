(function () {
    'use strict';

    const imagery = window.ASC_IMAGERY_CONFIG || {};

    if (
        !imagery.enabled ||
        !imagery.wmsUrl ||
        !imagery.layers ||
        !window.L ||
        typeof window.L.map !== 'function' ||
        !window.L.tileLayer ||
        typeof window.L.tileLayer.wms !== 'function'
    ) {
        return;
    }

    const originalMapFactory = window.L.map;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function attributionHtml(provider) {
        const label = String(provider.attributionLabel || 'MASE / Geoportale Nazionale');
        const url = String(provider.attributionUrl || 'https://geodati.gov.it/geoportale/');

        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' +
            escapeHtml(label) + '</a>';
    }

    function defaultProvider() {
        return {
            id: 'national-fallback',
            label: String(imagery.label || 'Foto aerea'),
            wmsUrl: String(imagery.wmsUrl),
            layers: String(imagery.layers),
            version: String(imagery.version || '1.1.1'),
            format: String(imagery.format || 'image/png'),
            transparent: Boolean(imagery.transparent),
            attributionLabel: imagery.attributionLabel,
            attributionUrl: imagery.attributionUrl,
            bounds: null
        };
    }

    function configuredProviders() {
        const providers = Array.isArray(imagery.providers) ? imagery.providers : [];
        return providers.filter(function (provider) {
            return provider && provider.enabled !== false && provider.wmsUrl && provider.layers;
        });
    }

    function contains(provider, latlng) {
        const bounds = provider && provider.bounds;
        if (!bounds || !latlng) {
            return false;
        }

        const south = Number(bounds.south);
        const west = Number(bounds.west);
        const north = Number(bounds.north);
        const east = Number(bounds.east);

        if (![south, west, north, east].every(Number.isFinite)) {
            return false;
        }

        return Number(latlng.lat) >= south && Number(latlng.lat) <= north &&
            Number(latlng.lng) >= west && Number(latlng.lng) <= east;
    }

    function providerFor(latlng) {
        const providers = configuredProviders();

        for (let index = 0; index < providers.length; index += 1) {
            if (contains(providers[index], latlng)) {
                return providers[index];
            }
        }

        return defaultProvider();
    }

    function makeWmsLayer(provider) {
        return window.L.tileLayer.wms(
            String(provider.wmsUrl),
            {
                layers: String(provider.layers),
                format: String(provider.format || 'image/png'),
                transparent: Boolean(provider.transparent),
                version: String(provider.version || '1.1.1'),
                crs: window.L.CRS.EPSG4326,
                attribution: attributionHtml(provider),
                maxZoom: 20
            }
        );
    }

    function installAerialRegistry(map) {
        if (!map || map.__ascAerialRegistryInstalled) {
            return;
        }

        map.__ascAerialRegistryInstalled = true;

        window.setTimeout(function () {
            const baseLayers = [];

            map.eachLayer(function (layer) {
                if (
                    layer instanceof window.L.TileLayer &&
                    !(layer instanceof window.L.TileLayer.WMS)
                ) {
                    baseLayers.push(layer);
                }
            });

            const streetLayer = baseLayers.length ? baseLayers[0] : null;
            const aerialGroup = window.L.layerGroup();
            let activeProviderId = null;

            function refreshAerial() {
                const provider = providerFor(map.getCenter());
                const providerId = String(provider.id || 'provider');

                if (providerId === activeProviderId && aerialGroup.getLayers().length > 0) {
                    return;
                }

                aerialGroup.clearLayers();
                makeWmsLayer(provider).addTo(aerialGroup);
                activeProviderId = providerId;

                map.fire('asc:aerial-provider-changed', {
                    providerId: providerId,
                    providerLabel: String(provider.label || imagery.label || 'Foto aerea')
                });
            }

            refreshAerial();

            const layerChoices = {};
            if (streetLayer) {
                layerChoices.Mappa = streetLayer;
            }
            layerChoices[String(imagery.label || 'Foto aerea')] = aerialGroup;

            window.L.control.layers(layerChoices, null, {
                position: 'topright',
                collapsed: false
            }).addTo(map);

            function enableAerialIfNeeded() {
                refreshAerial();

                if (
                    imagery.autoEnableAtPropertyZoom &&
                    Number(map.getZoom()) >= 17 &&
                    !map.hasLayer(aerialGroup)
                ) {
                    if (streetLayer && map.hasLayer(streetLayer)) {
                        map.removeLayer(streetLayer);
                    }
                    aerialGroup.addTo(map);
                }
            }

            map.on('moveend', function () {
                if (map.hasLayer(aerialGroup)) {
                    refreshAerial();
                }
            });

            map.on('zoomend', enableAerialIfNeeded);
            map.on('baselayerchange', function (event) {
                if (event && event.layer === aerialGroup) {
                    refreshAerial();
                }
            });

            enableAerialIfNeeded();
        }, 0);
    }

    window.L.map = function () {
        const map = originalMapFactory.apply(window.L, arguments);
        installAerialRegistry(map);
        return map;
    };

    Object.keys(originalMapFactory).forEach(function (key) {
        try {
            window.L.map[key] = originalMapFactory[key];
        } catch (_) {}
    });
}());
