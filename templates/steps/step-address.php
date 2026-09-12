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
            Puoi scegliere tra mappa stradale e foto aerea dal controllo in alto a destra.
            Il configuratore seleziona automaticamente la fonte fotografica pubblica più recente tra quelle verificate per l'area; dove non è disponibile usa l'ortofoto nazionale MASE / Geoportale Nazionale 2009-2012 come fallback.
            La ricerca indirizzi può usare <a href="https://www.geoapify.com/" target="_blank" rel="noopener noreferrer">Geoapify</a> quando configurato, con fallback ai provider open data già presenti.
            La fotografia aerea può avere una data diversa a seconda della zona e serve per riconoscere visivamente il tetto.
            La posizione dell'immobile diventa valida solo dopo la conferma sulla mappa.
        </p>
    </div>
</div>
