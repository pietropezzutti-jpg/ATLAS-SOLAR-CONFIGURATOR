<?php
/**
 * Step 3: consumption profile.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="asc-step" data-asc-step="3" hidden>
    <p class="asc-progress">Passaggio 3 di 5</p>
    <h2 class="asc-title">Quanto consumi?</h2>
    <p class="asc-subtitle">Indicaci la spesa media e, se lo conosci, il consumo annuo.</p>

    <form class="asc-form" data-asc-consumption-form novalidate>
        <fieldset class="asc-fieldset">
            <legend class="asc-field-label-text">Quanto spendi mediamente di energia elettrica?</legend>
            <div class="asc-option-grid">
                <label class="asc-option-card"><input type="radio" name="asc_bill_band" value="lt_70" /> meno di 70 €/mese</label>
                <label class="asc-option-card"><input type="radio" name="asc_bill_band" value="70_120" /> 70–120 €/mese</label>
                <label class="asc-option-card"><input type="radio" name="asc_bill_band" value="120_180" /> 120–180 €/mese</label>
                <label class="asc-option-card"><input type="radio" name="asc_bill_band" value="180_300" /> 180–300 €/mese</label>
                <label class="asc-option-card"><input type="radio" name="asc_bill_band" value="gt_300" /> oltre 300 €/mese</label>
            </div>
        </fieldset>

        <label class="asc-field-label" for="asc-annual-kwh">
            <span class="asc-field-label-text">Conosci il tuo consumo annuo? (opzionale)</span>
        </label>
        <input class="asc-input" id="asc-annual-kwh" name="asc_annual_kwh" type="number" min="0" step="1" inputmode="numeric" placeholder="es. 5200 kWh/anno" data-asc-annual-kwh />

        <fieldset class="asc-fieldset">
            <legend class="asc-field-label-text">Hai o prevedi uno di questi consumi?</legend>
            <div class="asc-option-grid">
                <label class="asc-option-card"><input type="checkbox" name="asc_heat_pump" /> Pompa di calore</label>
                <label class="asc-option-card"><input type="checkbox" name="asc_ev" /> Auto elettrica</label>
                <label class="asc-option-card"><input type="checkbox" name="asc_induction" /> Piano a induzione</label>
                <label class="asc-option-card"><input type="checkbox" name="asc_pool" /> Piscina</label>
            </div>
        </fieldset>

        <p class="asc-error" data-asc-consumption-error hidden aria-live="polite">Seleziona una fascia di spesa valida.</p>
        <div class="asc-actions">
            <button class="asc-button asc-button-secondary" type="button" data-asc-back>INDIETRO</button>
            <button class="asc-button" type="submit">CALCOLA IL MIO POTENZIALE</button>
        </div>
    </form>
</div>
