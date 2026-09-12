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
    </div>
</section>
