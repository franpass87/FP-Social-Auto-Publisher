<?php
/**
 * Settings page for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the plugin settings.
 */
class TTS_Settings {

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register the settings page.
     */
    public function add_menu() {
        add_options_page(
            __( 'Trello Social Settings', 'trello-social-auto-publisher' ),
            __( 'Trello Social', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting( 'tts_settings_group', 'tts_settings' );

        // Trello API credentials.
        add_settings_section(
            'tts_trello_api',
            __( 'Trello API Credentials', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'trello_api_key',
            __( 'API Key', 'trello-social-auto-publisher' ),
            array( $this, 'render_trello_api_key_field' ),
            'tts_settings',
            'tts_trello_api'
        );

        add_settings_field(
            'trello_api_token',
            __( 'API Token', 'trello-social-auto-publisher' ),
            array( $this, 'render_trello_api_token_field' ),
            'tts_settings',
            'tts_trello_api'
        );

        // Column mapping.
        add_settings_section(
            'tts_column_mapping',
            __( 'Trello Column Mapping', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'column_mapping',
            __( 'Column Mapping (JSON)', 'trello-social-auto-publisher' ),
            array( $this, 'render_column_mapping_field' ),
            'tts_settings',
            'tts_column_mapping'
        );

        // Social access token.
        add_settings_section(
            'tts_social_token',
            __( 'Social Access Token', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'social_access_token',
            __( 'Access Token', 'trello-social-auto-publisher' ),
            array( $this, 'render_social_access_token_field' ),
            'tts_settings',
            'tts_social_token'
        );

        // UTM options.
        add_settings_section(
            'tts_utm_options',
            __( 'UTM Options', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );
        $channels = array( 'facebook', 'instagram' );
        $params   = array( 'source', 'medium', 'campaign' );

        foreach ( $channels as $channel ) {
            foreach ( $params as $param ) {
                add_settings_field(
                    $channel . '_utm_' . $param,
                    sprintf( __( '%s UTM %s', 'trello-social-auto-publisher' ), ucfirst( $channel ), ucfirst( $param ) ),
                    array( $this, 'render_utm_field' ),
                    'tts_settings',
                    'tts_utm_options',
                    array(
                        'channel' => $channel,
                        'param'   => $param,
                    )
                );
            }
        }

        // Template options.
        add_settings_section(
            'tts_template_options',
            __( 'Template Options', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'facebook_template',
            __( 'Facebook Template', 'trello-social-auto-publisher' ),
            array( $this, 'render_facebook_template_field' ),
            'tts_settings',
            'tts_template_options'
        );

        add_settings_field(
            'instagram_template',
            __( 'Instagram Template', 'trello-social-auto-publisher' ),
            array( $this, 'render_instagram_template_field' ),
            'tts_settings',
            'tts_template_options'
        );

        // Logging options.
        add_settings_section(
            'tts_logging_options',
            __( 'Logging Options', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'log_retention_days',
            __( 'Log Retention (days)', 'trello-social-auto-publisher' ),
            array( $this, 'render_log_retention_days_field' ),
            'tts_settings',
            'tts_logging_options'
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Trello Social Settings', 'trello-social-auto-publisher' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'tts_settings_group' );
                do_settings_sections( 'tts_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render field for Trello API key.
     */
    public function render_trello_api_key_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['trello_api_key'] ) ? esc_attr( $options['trello_api_key'] ) : '';
        echo '<input type="text" name="tts_settings[trello_api_key]" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render field for Trello API token.
     */
    public function render_trello_api_token_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['trello_api_token'] ) ? esc_attr( $options['trello_api_token'] ) : '';
        echo '<input type="text" name="tts_settings[trello_api_token]" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render field for column mapping.
     */
    public function render_column_mapping_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['column_mapping'] ) ? esc_textarea( $options['column_mapping'] ) : '';
        echo '<textarea name="tts_settings[column_mapping]" rows="5" cols="50" class="large-text">' . $value . '</textarea>';
    }

    /**
     * Render field for social access token.
     */
    public function render_social_access_token_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['social_access_token'] ) ? esc_attr( $options['social_access_token'] ) : '';
        echo '<input type="password" name="tts_settings[social_access_token]" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render a UTM field for a given channel and parameter.
     *
     * @param array $args Field arguments.
     */
    public function render_utm_field( $args ) {
        $options = get_option( 'tts_settings', array() );
        $channel = isset( $args['channel'] ) ? $args['channel'] : '';
        $param   = isset( $args['param'] ) ? $args['param'] : '';
        $key     = $channel . '_utm_' . $param;
        $value   = isset( $options[ $key ] ) ? esc_attr( $options[ $key ] ) : '';
        echo '<input type="text" name="tts_settings[' . esc_attr( $key ) . ']" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render field for Facebook template.
     */
    public function render_facebook_template_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['facebook_template'] ) ? esc_attr( $options['facebook_template'] ) : '';
        echo '<input type="text" name="tts_settings[facebook_template]" value="' . $value . '" class="regular-text" placeholder="{title} {url}" />';
    }

    /**
     * Render field for Instagram template.
     */
    public function render_instagram_template_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['instagram_template'] ) ? esc_attr( $options['instagram_template'] ) : '';
        echo '<input type="text" name="tts_settings[instagram_template]" value="' . $value . '" class="regular-text" placeholder="{title} {url}" />';
    }

    /**
     * Render field for log retention period.
     */
    public function render_log_retention_days_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['log_retention_days'] ) ? intval( $options['log_retention_days'] ) : 30;
        echo '<input type="number" min="1" name="tts_settings[log_retention_days]" value="' . esc_attr( $value ) . '" class="small-text" />';
    }
}

/**
 * Initialize TTS_Settings on plugins_loaded.
 */
function tts_init_settings() {
    new TTS_Settings();
}
add_action( 'plugins_loaded', 'tts_init_settings' );
