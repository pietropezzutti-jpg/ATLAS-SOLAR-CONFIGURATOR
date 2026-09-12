<?php
/**
 * Step 2: property profile.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="asc-step" data-asc-step="2" hidden>
    <p class="asc-progress">Passaggio 2 di 5</p>
    <h2 class="asc-title">Parlaci dell'immobile</h2>
    <p class="asc-subtitle">Ci bastano due informazioni per qualificare meglio la simulazione.</p>

    <form class="asc-form" data-asc-property-form novalidate>
        <fieldset class="asc-fieldset">
            <legend class="asc-field-label-text">Che tipo di immobile è?</legend>
            <div class="asc-option-grid">
                <label class="asc-option-card"><input type="radio" name="asc_property_type" value="independent_house" /> Villa / Casa indipendente</label>
                <label class="asc-option-card"><input type="radio" name="asc_property_type" value="semi_detached" /> Bifamiliare</label>
                <label class="asc-option-card"><input type="radio" name="asc_property_type" value="condominium" /> Condominio</label>
                <label class="asc-option-card"><input type="radio" name="asc_property_type" value="business" /> Azienda / Attività</label>
                <label class="asc-option-card"><input type="radio" name="asc_property_type" value="other" /> Altro</label>
            </div>
        </fieldset>

        <fieldset class="asc-fieldset">
            <legend class="asc-field-label-text">Sei proprietario dell'immobile?</legend>
            <div class="asc-option-grid asc-option-grid-compact">
                <label class="asc-option-card"><input type="radio" name="asc_ownership" value="owner" /> Sì, sono proprietario</label>
                <label class="asc-option-card"><input type="radio" name="asc_ownership" value="non_owner" /> No</label>
            </div>
        </fieldset>

        <p class="asc-error" data-asc-property-error hidden aria-live="polite">Seleziona il tipo di immobile e indica se sei proprietario.</p>
        <div class="asc-actions">
            <button class="asc-button asc-button-secondary" type="button" data-asc-back>INDIETRO</button>
            <button class="asc-button" type="submit">CONTINUA</button>
        </div>
    </form>
</div>
