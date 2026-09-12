<?php
/**
 * Admin settings view.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('ATLAS Configurator', 'atlas-solar-configurator'); ?></h1>
    <table class="widefat striped" style="max-width: 720px;">
        <tbody>
        <?php foreach ($summary as $label => $value) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($label); ?></th>
                <td><?php echo esc_html($value); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
