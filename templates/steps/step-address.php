<?php
/**
 * Step 1: address resolution and property-position confirmation.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="asc-step asc-step-active" data-asc-step="1">
    <p class="asc-progress">Passaggio 1 di 5</p>
    <h2 class="asc-title">Quanto può produrre il tuo tetto?</h2>
    <p class="asc-subtitle">Inserisci l'indirizzo, controlla il punto sulla mappa e conferma la posizione dell'immobile.</p>

    <form class="asc-form" data-asc-address-form novalidate>
        <label class="asc-field-label" for="asc-address-input">
            <span class="asc-field-label-text">Indirizzo dell'immobile</span>
        </label>

        <div class="asc-input-row">
            <input
                class="asc-input"
                id="asc-address-input"
                name="asc_address"
                type="text"
                placeholder="Via, numero civico, comune..."
                autocomplete="street-address"
                data-asc-address-input
            />
            <button class="asc-button" type="submit" data-asc-address-submit>TROVA L'IMMOBILE</button>
        </div>

        <p class="asc-error" data-asc-address-error hidden aria-live="polite">
            Inserisci un indirizzo per continuare.
        </p>
    </form>

    <div class="asc-location-panel" data-asc-location-panel hidden>
        <div class="asc-location-status" data-asc-location-status aria-live="polite"></div>

        <div class="asc-candidate-section" data-asc-candidate-section hidden>
            <p class="asc-location-help">Abbiamo trovato più risultati. Seleziona quello corretto.</p>
            <div class="asc-candidate-list" data-asc-candidate-list role="list"></div>
        </div>

        <div class="asc-map-wrap" data-asc-map-wrap hidden>
            <div
                class="asc-map"
                data-asc-map
                role="application"
                aria-label="Mappa per confermare la posizione dell'immobile"
            ></div>
        </div>

        <div class="asc-position-summary" data-asc-position-summary hidden>
            <p class="asc-position-label">Posizione proposta</p>
            <strong data-asc-selected-address></strong>
            <span class="asc-position-coordinates" data-asc-selected-coordinates></span>
            <p class="asc-location-help">
                Se il punto non coincide con l'immobile, trascina il marker oppure tocca la posizione corretta sulla mappa.
            </p>

            <button class="asc-button" type="button" data-asc-confirm-position disabled>
                CONFERMA POSIZIONE E CONTINUA
            </button>
        </div>

        <p class="asc-map-attribution">
            Mappa e dati geografici:
            <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a>.
            Ricerca primaria tramite servizio Nominatim-compatible; per civici non risolti può essere usato un fallback server-side su dati open data ANNCSU tramite mirror community configurabile. La posizione dell'immobile diventa valida solo dopo la conferma sulla mappa.
        </p>
    </div>
</div>
