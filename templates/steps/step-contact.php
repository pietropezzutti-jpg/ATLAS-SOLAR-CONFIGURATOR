<?php
/**
 * Step 5: contact capture (demo only).
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="asc-step" data-asc-step="5" hidden>
    <p class="asc-progress">Passaggio 5 di 5</p>
    <h2 class="asc-title">Ricevi la tua analisi</h2>
    <p class="asc-subtitle">Lascia i tuoi recapiti per completare la demo del configuratore.</p>

    <form class="asc-form" data-asc-contact-form novalidate>
        <div class="asc-field-grid">
            <label class="asc-field-label" for="asc-first-name"><span class="asc-field-label-text">Nome</span><input class="asc-input" id="asc-first-name" name="asc_first_name" type="text" autocomplete="given-name" /></label>
            <label class="asc-field-label" for="asc-last-name"><span class="asc-field-label-text">Cognome</span><input class="asc-input" id="asc-last-name" name="asc_last_name" type="text" autocomplete="family-name" /></label>
            <label class="asc-field-label" for="asc-phone"><span class="asc-field-label-text">Telefono</span><input class="asc-input" id="asc-phone" name="asc_phone" type="tel" autocomplete="tel" /></label>
            <label class="asc-field-label" for="asc-email"><span class="asc-field-label-text">Email</span><input class="asc-input" id="asc-email" name="asc_email" type="email" autocomplete="email" /></label>
        </div>

        <label class="asc-checkbox"><input type="checkbox" name="asc_privacy" /> <span>Acconsento al trattamento dei dati secondo la Privacy Policy.</span></label>
        <p class="asc-error" data-asc-contact-error hidden aria-live="polite">Compila tutti i campi e accetta la privacy per continuare.</p>

        <div class="asc-actions">
            <button class="asc-button asc-button-secondary" type="button" data-asc-back>INDIETRO</button>
            <button class="asc-button" type="submit">COMPLETA LA DEMO</button>
        </div>
    </form>
</div>
