<?php
/**
 * Configurator shell template.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="asc-configurator" data-asc-configurator data-session-id="<?php echo $session_id; ?>">
    <div class="asc-shell">
        <?php include ASC_PLUGIN_DIR . 'templates/steps/step-address.php'; ?>
        <?php include ASC_PLUGIN_DIR . 'templates/steps/step-property.php'; ?>
        <?php include ASC_PLUGIN_DIR . 'templates/steps/step-consumption.php'; ?>
        <?php include ASC_PLUGIN_DIR . 'templates/steps/step-result.php'; ?>
        <?php include ASC_PLUGIN_DIR . 'templates/steps/step-contact.php'; ?>
        <?php include ASC_PLUGIN_DIR . 'templates/steps/step-confirmation.php'; ?>
    </div>
</section>
