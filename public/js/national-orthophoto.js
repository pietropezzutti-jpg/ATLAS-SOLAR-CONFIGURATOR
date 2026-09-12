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

    function attributionHtml() {
        const label = String(imagery.attributionLabel || 'MASE / Geoportale Nazionale');
        const url = String(imagery.attributionUrl || 'https://geodati.gov.it/geoportale/');

        const safeLabel = label
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const safeUrl = url
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        return '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer">' + safeLabel + '</a>';
    }

    function installNationalOrthophoto(map) {
        if (!map || map.__ascNationalOrthophotoInstalled) {
            return;
        }

        map.__ascNationalOrthophotoInstalled = true;

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

            const aerialLayer = window.L.tileLayer.wms(
                String(imagery.wmsUrl),
                {
                    layers: String(imagery.layers),
                    format: String(imagery.format || 'image/png'),
                    transparent: Boolean(imagery.transparent),
                    version: String(imagery.version || '1.1.1'),
                    crs: window.L.CRS.EPSG4326,
                    attribution: attributionHtml(),
                    maxZoom: 20
                }
            );

            const layerChoices = {};
            if (streetLayer) {
                layerChoices.Mappa = streetLayer;
            }
            layerChoices[String(imagery.label || 'Ortofoto nazionale')] = aerialLayer;

            window.L.control.layers(layerChoices, null, {
                position: 'topright',
                collapsed: false
            }).addTo(map);

            if (
                imagery.autoEnableAtPropertyZoom &&
                Number(map.getZoom()) >= 17
            ) {
                if (streetLayer && map.hasLayer(streetLayer)) {
                    map.removeLayer(streetLayer);
                }
                aerialLayer.addTo(map);
            }

            map.on('zoomend', function () {
                if (
                    imagery.autoEnableAtPropertyZoom &&
                    Number(map.getZoom()) >= 17 &&
                    streetLayer &&
                    map.hasLayer(streetLayer) &&
                    !map.hasLayer(aerialLayer)
                ) {
                    map.removeLayer(streetLayer);
                    aerialLayer.addTo(map);
                }
            });
        }, 0);
    }

    window.L.map = function () {
        const map = originalMapFactory.apply(window.L, arguments);
        installNationalOrthophoto(map);
        return map;
    };

    Object.keys(originalMapFactory).forEach(function (key) {
        try {
            window.L.map[key] = originalMapFactory[key];
        } catch (_) {}
    });
}());
