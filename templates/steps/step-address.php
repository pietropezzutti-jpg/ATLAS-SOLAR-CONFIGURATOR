<?php
/**
 * Step 1: address.
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
    <p class="asc-subtitle">Inserisci l'indirizzo dell'immobile che vuoi analizzare.</p>

    <form class="asc-form" data-asc-address-form novalidate>
        <label class="asc-field-label" for="asc-address-input">
            <span class="asc-field-label-text">Indirizzo dell'immobile</span>
        </label>
        <div class="asc-input-row">
            <input class="asc-input" id="asc-address-input" name="asc_address" type="text" placeholder="Via, numero civico, comune..." autocomplete="street-address" data-asc-address-input />
            <button class="asc-button" type="submit">ANALIZZA IL MIO TETTO</button>
        </div>
        <p class="asc-error" data-asc-address-error hidden aria-live="polite">Inserisci un indirizzo per continuare.</p>
    </form>
</div>
