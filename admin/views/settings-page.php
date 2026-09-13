<?php
/**
 * Admin settings view.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

$option_name =
    Atlas_Solar_Configurator_Settings::OPTION_KEY;

$secret_option_name =
    Atlas_Solar_Configurator_Settings::SECRETS_OPTION_KEY;

$geoapify_source =
    Atlas_Solar_Configurator_Settings::get_geoapify_api_key_source();

$geoapify_source_labels = [
    'constant' => __('Server constant', 'atlas-solar-configurator'),
    'environment' => __('Server environment', 'atlas-solar-configurator'),
    'wordpress_admin' => __('WordPress admin (server-side)', 'atlas-solar-configurator'),
    'none' => __('Not configured', 'atlas-solar-configurator'),
];

$geoapify_source_label =
    $geoapify_source_labels[
        $geoapify_source
    ] ?? $geoapify_source_labels['none'];
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

    <h2><?php echo esc_html__('Boundary pubblico ATLAS', 'atlas-solar-configurator'); ?></h2>
    <p style="max-width: 820px;">
        <?php
        echo esc_html__(
            'PLUGIN-005 mantiene il contratto pubblico v1 e aggiunge la fondazione dell’adapter server-side verso ATLAS. In questa fase il boundary pubblico resta intenzionalmente scollegato: la normale esperienza browser continua a validare il contratto senza trasmetterlo ad ATLAS.',
            'atlas-solar-configurator'
        );
        ?>
    </p>
    <p style="max-width: 820px;">
        <code>POST /wp-json/atlas-solar-configurator/v1/assessment-contract</code>
    </p>
    <p style="max-width: 820px;">
        <?php
        echo esc_html__(
            'Le credenziali ATLAS non sono configurate in questa pagina e non vengono mai esposte al frontend. L’adapter legge ASC_ATLAS_BASE_URL e ASC_ATLAS_BEARER_TOKEN esclusivamente dalla configurazione server.',
            'atlas-solar-configurator'
        );
        ?>
    </p>

    <hr style="margin: 28px 0;" />

    <h2><?php echo esc_html__('Provider localizzazione', 'atlas-solar-configurator'); ?></h2>
    <p style="max-width: 820px;">
        <?php
        echo esc_html__(
            'La localizzazione usa un proxy WordPress per la geocodifica e un provider tile configurabile per la mappa. I valori predefiniti usano servizi OpenStreetMap/Nominatim e possono essere sostituiti senza aggiornare il plugin.',
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
                    <label for="asc-geoapify-api-key">
                        <?php echo esc_html__('Geoapify API key', 'atlas-solar-configurator'); ?>
                    </label>
                </th>
                <td>
                    <input
                        id="asc-geoapify-api-key"
                        class="regular-text code"
                        type="password"
                        autocomplete="new-password"
                        name="<?php echo esc_attr($secret_option_name); ?>[geoapify_api_key]"
                        value=""
                        placeholder="<?php echo esc_attr__('Incolla una nuova chiave per impostarla o sostituirla', 'atlas-solar-configurator'); ?>"
                    />

                    <p class="description">
                        <?php
                        echo esc_html(
                            sprintf(
                                __('Sorgente attuale: %s. La chiave resta esclusivamente server-side.', 'atlas-solar-configurator'),
                                $geoapify_source_label
                            )
                        );
                        ?>
                    </p>

                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($secret_option_name); ?>[geoapify_api_key_clear]"
                            value="1"
                        />
                        <?php echo esc_html__('Rimuovi la chiave Geoapify salvata in WordPress', 'atlas-solar-configurator'); ?>
                    </label>

                    <p class="description">
                        <?php
                        echo esc_html__(
                            'Lascia vuoto il campo per mantenere la chiave esistente. ASC_GEOAPIFY_API_KEY o la variabile ambiente, se presenti, hanno precedenza.',
                            'atlas-solar-configurator'
                        );
                        ?>
                    </p>
                </td>
            </tr>

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
