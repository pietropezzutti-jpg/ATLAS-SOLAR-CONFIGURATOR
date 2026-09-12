<?php
/**
 * Admin settings view.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

$option_name = Atlas_Solar_Configurator_Settings::OPTION_KEY;
?>
<div class="wrap">
    <h1><?php echo esc_html__('ATLAS Configurator', 'atlas-solar-configurator'); ?></h1>

    <h2><?php echo esc_html__('Stato plugin', 'atlas-solar-configurator'); ?></h2>
    <table class="widefat striped" style="max-width: 760px;">
        <tbody>
        <?php foreach ($summary as $label => $value) : ?>
            <tr>
                <th scope="row"><?php echo esc_html($label); ?></th>
                <td><?php echo esc_html($value); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <hr style="margin: 28px 0;" />

    <h2><?php echo esc_html__('Provider localizzazione', 'atlas-solar-configurator'); ?></h2>
    <p style="max-width: 820px;">
        <?php
        echo esc_html__(
            'PLUGIN-003 usa un proxy WordPress per la geocodifica e un provider tile configurabile per la mappa. I valori predefiniti usano servizi OpenStreetMap/Nominatim e possono essere sostituiti senza aggiornare il plugin.',
            'atlas-solar-configurator'
        );
        ?>
    </p>

    <form method="post" action="options.php" style="max-width: 820px;">
        <?php settings_fields(Atlas_Solar_Configurator_Settings::SETTINGS_GROUP); ?>

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="asc-geocoder-endpoint">
                        <?php echo esc_html__('Endpoint geocoder', 'atlas-solar-configurator'); ?>
                    </label>
                </th>
                <td>
                    <input
                        id="asc-geocoder-endpoint"
                        class="regular-text code"
                        type="url"
                        name="<?php echo esc_attr($option_name); ?>[geocoder_endpoint]"
                        value="<?php echo esc_attr($map_options['geocoder_endpoint']); ?>"
                    />
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Endpoint Nominatim-compatible. Il plugin esegue solo ricerche esplicite dell’utente, senza autocomplete.',
                            'atlas-solar-configurator'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="asc-tile-url">
                        <?php echo esc_html__('URL tile mappa', 'atlas-solar-configurator'); ?>
                    </label>
                </th>
                <td>
                    <input
                        id="asc-tile-url"
                        class="regular-text code"
                        type="text"
                        name="<?php echo esc_attr($option_name); ?>[tile_url]"
                        value="<?php echo esc_attr($map_options['tile_url']); ?>"
                    />
                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Deve contenere i placeholder {z}, {x} e {y}.',
                            'atlas-solar-configurator'
                        );
                        ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="asc-tile-attribution-label">
                        <?php echo esc_html__('Testo attribuzione', 'atlas-solar-configurator'); ?>
                    </label>
                </th>
                <td>
                    <input
                        id="asc-tile-attribution-label"
                        class="regular-text"
                        type="text"
                        name="<?php echo esc_attr($option_name); ?>[tile_attribution_label]"
                        value="<?php echo esc_attr($map_options['tile_attribution_label']); ?>"
                    />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="asc-tile-attribution-url">
                        <?php echo esc_html__('URL attribuzione', 'atlas-solar-configurator'); ?>
                    </label>
                </th>
                <td>
                    <input
                        id="asc-tile-attribution-url"
                        class="regular-text code"
                        type="url"
                        name="<?php echo esc_attr($option_name); ?>[tile_attribution_url]"
                        value="<?php echo esc_attr($map_options['tile_attribution_url']); ?>"
                    />
                </td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(__('Salva provider mappa', 'atlas-solar-configurator')); ?>
    </form>

    <p style="max-width: 820px; color: #50575e;">
        <?php echo esc_html__('Nota operativa:', 'atlas-solar-configurator'); ?>
        <?php
        echo esc_html__(
            'i servizi pubblici OpenStreetMap/Nominatim non offrono SLA e sono soggetti alle rispettive policy d’uso. Per traffico commerciale elevato configura provider dedicati o infrastruttura propria.',
            'atlas-solar-configurator'
        );
        ?>
        <a href="https://operations.osmfoundation.org/policies/nominatim/" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html__('Policy Nominatim', 'atlas-solar-configurator'); ?>
        </a>
        ·
        <a href="https://operations.osmfoundation.org/policies/tiles/" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html__('Policy tile OSM', 'atlas-solar-configurator'); ?>
        </a>
    </p>
</div>
