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
        // We no longer add a separate settings page since it's now integrated
        // into the main plugin menu structure
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting(
            'tts_settings_group',
            'tts_settings',
            array(
                'sanitize_callback' => 'tts_sanitize_settings',
            )
        );
        
        $channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );

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

        // Scheduling options.
        add_settings_section(
            'tts_scheduling_options',
            __( 'Scheduling Options', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        foreach ( $channels as $channel ) {
            add_settings_field(
                $channel . '_offset',
                sprintf( __( '%s Offset (minutes)', 'trello-social-auto-publisher' ), ucfirst( $channel ) ),
                array( $this, 'render_offset_field' ),
                'tts_settings',
                'tts_scheduling_options',
                array(
                    'channel' => $channel,
                )
            );
        }

        // Default location options.
        add_settings_section(
            'tts_location_options',
            __( 'Default Location', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'default_lat',
            __( 'Default Latitude', 'trello-social-auto-publisher' ),
            array( $this, 'render_default_lat_field' ),
            'tts_settings',
            'tts_location_options'
        );

        add_settings_field(
            'default_lng',
            __( 'Default Longitude', 'trello-social-auto-publisher' ),
            array( $this, 'render_default_lng_field' ),
            'tts_settings',
            'tts_location_options'
        );

        // Image size options.
        add_settings_section(
            'tts_image_sizes',
            __( 'Image Sizes', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        foreach ( $channels as $channel ) {
            add_settings_field(
                $channel . '_size',
                sprintf( __( '%s Size (WxH)', 'trello-social-auto-publisher' ), ucfirst( $channel ) ),
                array( $this, 'render_image_size_field' ),
                'tts_settings',
                'tts_image_sizes',
                array(
                    'channel' => $channel,
                )
            );
        }

        // UTM options.
        add_settings_section(
            'tts_utm_options',
            __( 'UTM Options', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );
        $params = array( 'utm_source', 'utm_medium', 'utm_campaign' );

        foreach ( $channels as $channel ) {
            foreach ( $params as $param ) {
                add_settings_field(
                    $channel . '_' . $param,
                    sprintf( __( '%s UTM %s', 'trello-social-auto-publisher' ), ucfirst( $channel ), ucfirst( str_replace( 'utm_', '', $param ) ) ),
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

        add_settings_field(
            'youtube_template',
            __( 'YouTube Template', 'trello-social-auto-publisher' ),
            array( $this, 'render_youtube_template_field' ),
            'tts_settings',
            'tts_template_options'
        );

        add_settings_field(
            'tiktok_template',
            __( 'TikTok Template', 'trello-social-auto-publisher' ),
            array( $this, 'render_tiktok_template_field' ),
            'tts_settings',
            'tts_template_options'
        );

        add_settings_field(
            'labels_as_hashtags',
            __( 'Labels as Hashtags', 'trello-social-auto-publisher' ),
            array( $this, 'render_labels_as_hashtags_field' ),
            'tts_settings',
            'tts_template_options'
        );

        // URL shortener options.
        add_settings_section(
            'tts_url_shortener',
            __( 'URL Shortener', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'url_shortener',
            __( 'URL Shortener', 'trello-social-auto-publisher' ),
            array( $this, 'render_url_shortener_field' ),
            'tts_settings',
            'tts_url_shortener'
        );

        add_settings_field(
            'bitly_token',
            __( 'Bitly Token', 'trello-social-auto-publisher' ),
            array( $this, 'render_bitly_token_field' ),
            'tts_settings',
            'tts_url_shortener'
        );

        // Notification options.
        add_settings_section(
            'tts_notification_options',
            __( 'Notification Options', 'trello-social-auto-publisher' ),
            '__return_false',
            'tts_settings'
        );

        add_settings_field(
            'slack_webhook',
            __( 'Slack Webhook', 'trello-social-auto-publisher' ),
            array( $this, 'render_slack_webhook_field' ),
            'tts_settings',
            'tts_notification_options'
        );

        add_settings_field(
            'notification_emails',
            __( 'Notification Emails', 'trello-social-auto-publisher' ),
            array( $this, 'render_notification_emails_field' ),
            'tts_settings',
            'tts_notification_options'
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
     * Render scheduling offset field for a channel.
     *
     * @param array $args Field arguments.
     */
    public function render_offset_field( $args ) {
        $options = get_option( 'tts_settings', array() );
        $channel = isset( $args['channel'] ) ? $args['channel'] : '';
        $key     = $channel . '_offset';
        $value   = isset( $options[ $key ] ) ? intval( $options[ $key ] ) : 0;
        echo '<input type="number" min="0" name="tts_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="small-text" />';
    }

    /**
     * Render field for default latitude.
     */
    public function render_default_lat_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['default_lat'] ) ? esc_attr( $options['default_lat'] ) : '';
        echo '<input type="text" name="tts_settings[default_lat]" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render field for default longitude.
     */
    public function render_default_lng_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['default_lng'] ) ? esc_attr( $options['default_lng'] ) : '';
        echo '<input type="text" name="tts_settings[default_lng]" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render image size field for a channel.
     *
     * @param array $args Field arguments.
     */
    public function render_image_size_field( $args ) {
        $options  = get_option( 'tts_settings', array() );
        $channel  = isset( $args['channel'] ) ? $args['channel'] : '';
        $key      = $channel . '_size';
        $defaults = array(
            'facebook'  => '1080x1350',
            'instagram' => '1080x1350',
            'youtube'   => '1080x1920',
            'tiktok'    => '1080x1920',
        );
        $value = isset( $options[ $key ] ) ? esc_attr( $options[ $key ] ) : ( isset( $defaults[ $channel ] ) ? $defaults[ $channel ] : '' );
        echo '<input type="text" name="tts_settings[' . esc_attr( $key ) . ']" value="' . $value . '" class="regular-text" placeholder="' . esc_attr( $defaults[ $channel ] ) . '" />';
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
        $value   = isset( $options['utm'][ $channel ][ $param ] ) ? esc_attr( $options['utm'][ $channel ][ $param ] ) : '';
        echo '<input type="text" name="tts_settings[utm][' . esc_attr( $channel ) . '][' . esc_attr( $param ) . ']" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render field for Facebook template.
     */
    public function render_facebook_template_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['facebook_template'] ) ? esc_attr( $options['facebook_template'] ) : '';
        echo '<input type="text" name="tts_settings[facebook_template]" value="' . $value . '" class="regular-text" placeholder="{title} {url} {due}" />';
        echo '<p class="description">' . esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'trello-social-auto-publisher' ) . '</p>';
    }

    /**
     * Render field for Instagram template.
     */
    public function render_instagram_template_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['instagram_template'] ) ? esc_attr( $options['instagram_template'] ) : '';
        echo '<input type="text" name="tts_settings[instagram_template]" value="' . $value . '" class="regular-text" placeholder="{title} {url} {due}" />';
        echo '<p class="description">' . esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'trello-social-auto-publisher' ) . '</p>';
    }

    /**
     * Render field for YouTube template.
     */
    public function render_youtube_template_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['youtube_template'] ) ? esc_attr( $options['youtube_template'] ) : '';
        echo '<input type="text" name="tts_settings[youtube_template]" value="' . $value . '" class="regular-text" placeholder="{title} {url} {due}" />';
        echo '<p class="description">' . esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'trello-social-auto-publisher' ) . '</p>';
    }

    /**
     * Render field for TikTok template.
     */
    public function render_tiktok_template_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['tiktok_template'] ) ? esc_attr( $options['tiktok_template'] ) : '';
        echo '<input type="text" name="tts_settings[tiktok_template]" value="' . $value . '" class="regular-text" placeholder="{title} {url} {due}" />';
        echo '<p class="description">' . esc_html__( 'Available placeholders: {title}, {url}, {due}, {labels}, {client_name}', 'trello-social-auto-publisher' ) . '</p>';
    }

    /**
     * Render field for labels-as-hashtags option.
     */
    public function render_labels_as_hashtags_field() {
        $options = get_option( 'tts_settings', array() );
        $checked = ! empty( $options['labels_as_hashtags'] );
        echo '<label><input type="checkbox" name="tts_settings[labels_as_hashtags]" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Append Trello labels as hashtags', 'trello-social-auto-publisher' ) . '</label>';
    }

    /**
     * Render the URL shortener select field.
     */
    public function render_url_shortener_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['url_shortener'] ) ? $options['url_shortener'] : 'none';

        $choices = array(
            'none'  => __( 'None', 'trello-social-auto-publisher' ),
            'wp'    => __( 'WordPress', 'trello-social-auto-publisher' ),
            'bitly' => __( 'Bitly', 'trello-social-auto-publisher' ),
        );

        echo '<select name="tts_settings[url_shortener]">';
        foreach ( $choices as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Render field for Bitly token.
     */
    public function render_bitly_token_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['bitly_token'] ) ? esc_attr( $options['bitly_token'] ) : '';
        echo '<input type="text" name="tts_settings[bitly_token]" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Required for Bitly shortening.', 'trello-social-auto-publisher' ) . '</p>';
    }

    /**
     * Render field for Slack webhook.
     */
    public function render_slack_webhook_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['slack_webhook'] ) ? esc_url( $options['slack_webhook'] ) : '';
        echo '<input type="url" name="tts_settings[slack_webhook]" value="' . $value . '" class="regular-text" />';
    }

    /**
     * Render field for notification emails.
     */
    public function render_notification_emails_field() {
        $options = get_option( 'tts_settings', array() );
        $value   = isset( $options['notification_emails'] ) ? esc_attr( $options['notification_emails'] ) : '';
        echo '<input type="text" name="tts_settings[notification_emails]" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Comma-separated list of email addresses.', 'trello-social-auto-publisher' ) . '</p>';
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
 * Sanitize settings values.
 *
 * @param array $input Raw settings input.
 * @return array Sanitized settings.
 */
function tts_sanitize_settings( $input ) {
    $output = array();

    if ( ! is_array( $input ) ) {
        return $output;
    }

    $text_keys = array( 'trello_api_key', 'trello_api_token', 'social_access_token', 'bitly_token' );

    foreach ( $text_keys as $key ) {
        if ( isset( $input[ $key ] ) ) {
            $output[ $key ] = sanitize_text_field( $input[ $key ] );
        }
    }

    if ( isset( $input['column_mapping'] ) ) {
        $decoded = json_decode( $input['column_mapping'], true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $output['column_mapping'] = wp_json_encode( $decoded );
        } else {
            $output['column_mapping'] = array();
        }
    }

    if ( isset( $input['log_retention_days'] ) ) {
        $output['log_retention_days'] = absint( $input['log_retention_days'] );
    }

    $offset_keys = array( 'facebook_offset', 'instagram_offset', 'youtube_offset', 'tiktok_offset' );
    foreach ( $offset_keys as $key ) {
        if ( isset( $input[ $key ] ) ) {
            $output[ $key ] = absint( $input[ $key ] );
        }
    }

    $size_keys = array( 'facebook_size', 'instagram_size', 'youtube_size', 'tiktok_size' );
    foreach ( $size_keys as $key ) {
        if ( isset( $input[ $key ] ) ) {
            $value = strtolower( sanitize_text_field( $input[ $key ] ) );
            if ( preg_match( '/^\d+x\d+$/', $value ) ) {
                $output[ $key ] = $value;
            }
        }
    }

    if ( isset( $input['default_lat'] ) ) {
        $output['default_lat'] = sanitize_text_field( $input['default_lat'] );
    }

    if ( isset( $input['default_lng'] ) ) {
        $output['default_lng'] = sanitize_text_field( $input['default_lng'] );
    }

    if ( isset( $input['url_shortener'] ) && in_array( $input['url_shortener'], array( 'none', 'wp', 'bitly' ), true ) ) {
        $output['url_shortener'] = $input['url_shortener'];
    } else {
        $output['url_shortener'] = 'none';
    }

    $output['labels_as_hashtags'] = ! empty( $input['labels_as_hashtags'] ) ? 1 : 0;

    if ( isset( $input['utm'] ) && is_array( $input['utm'] ) ) {
        foreach ( $input['utm'] as $channel => $params ) {
            if ( ! is_array( $params ) ) {
                continue;
            }
            foreach ( $params as $param_key => $param_value ) {
                $output['utm'][ $channel ][ $param_key ] = sanitize_text_field( $param_value );
            }
        }
    }

    foreach ( $input as $key => $value ) {
        if ( 'utm' === $key ) {
            continue;
        } elseif ( substr( $key, -9 ) === '_template' ) {
            $output[ $key ] = sanitize_text_field( $value );
        } elseif ( substr( $key, -4 ) === '_url' ) {
            $output[ $key ] = esc_url_raw( $value );
        }
    }

    if ( isset( $input['slack_webhook'] ) ) {
        $output['slack_webhook'] = esc_url_raw( $input['slack_webhook'] );
    }

    if ( isset( $input['notification_emails'] ) ) {
        $emails = array_map( 'sanitize_email', array_map( 'trim', explode( ',', $input['notification_emails'] ) ) );
        $emails = array_filter( $emails );
        $output['notification_emails'] = implode( ',', $emails );
    }

    return $output;
}

/**
 * Initialize TTS_Settings on plugins_loaded.
 */
function tts_init_settings() {
    new TTS_Settings();
}
add_action( 'plugins_loaded', 'tts_init_settings' );
