<?php
/**
 * First address step.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="asc-step asc-step-active" data-asc-step="address">
    <p class="asc-progress">Passaggio 1 di 5</p>
    <h2 class="asc-title">Quanto può produrre il tuo tetto?</h2>
    <p class="asc-subtitle">Inserisci l'indirizzo dell'immobile che vuoi analizzare.</p>

    <form class="asc-address-form" data-asc-address-form novalidate>
        <label class="asc-field-label" for="asc-address-input">
            <span class="asc-field-label-text">Indirizzo dell'immobile</span>
        </label>
        <div class="asc-input-row">
            <input
                class="asc-address-input"
                id="asc-address-input"
                name="asc_address"
                type="text"
                placeholder="Via, numero civico, comune..."
                autocomplete="street-address"
                data-asc-address-input
            />
            <button class="asc-submit" type="submit">ANALIZZA IL MIO TETTO</button>
        </div>
        <p class="asc-error" data-asc-error hidden>Inserisci un indirizzo per continuare.</p>
    </form>

    <div class="asc-placeholder" data-asc-placeholder hidden>
        <p>Indirizzo acquisito. La verifica dell'immobile sarà disponibile nel prossimo step.</p>
    </div>
</div>
