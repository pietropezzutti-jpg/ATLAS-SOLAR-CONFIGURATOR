<?php
/**
 * Step 4: mock preliminary result.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="asc-step" data-asc-step="4" hidden>
    <p class="asc-progress">Passaggio 4 di 5</p>
    <div class="asc-mock-badge">DEMO / STIMA DIMOSTRATIVA</div>
    <h2 class="asc-title">Ecco una prima simulazione</h2>
    <p class="asc-subtitle">Questi valori servono solo a provare il funnel. Non sono ancora calcoli tecnici reali del tuo tetto.</p>

    <div class="asc-result-grid">
        <div class="asc-result-card"><span>Impianto indicativo</span><strong data-asc-result-kwp>—</strong></div>
        <div class="asc-result-card"><span>Produzione indicativa</span><strong data-asc-result-kwh>—</strong></div>
        <div class="asc-result-card"><span>Accumulo suggerito</span><strong data-asc-result-storage>—</strong></div>
        <div class="asc-result-card"><span>Risparmio indicativo</span><strong data-asc-result-saving>—</strong></div>
    </div>

    <p class="asc-disclaimer">Questa è una simulazione dimostrativa del configuratore. Non rappresenta ancora una valutazione tecnica reale del tetto. Superficie, orientamento, irraggiamento e producibilità saranno calcolati nelle versioni integrate con i servizi ATLAS.</p>

    <div class="asc-actions">
        <button class="asc-button asc-button-secondary" type="button" data-asc-back>INDIETRO</button>
        <button class="asc-button" type="button" data-asc-result-next>RICEVI LA MIA ANALISI</button>
    </div>
</div>
