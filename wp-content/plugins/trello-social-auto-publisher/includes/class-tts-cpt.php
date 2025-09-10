<?php
/**
 * Custom post type for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the custom post type used by the plugin.
 */
class TTS_CPT {

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_meta_fields' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_schedule_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_preview_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_channel_metabox' ) );
        add_action( 'save_post_tts_social_post', array( $this, 'save_schedule_metabox' ), 5, 3 );
    }

    /**
     * Register the custom post type.
     */
    public function register_post_type() {
        $args = array(
            'public'       => true,
            'supports'     => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
            'show_in_rest' => true,
            'label'        => __( 'Social Posts', 'trello-social-auto-publisher' ),
        );

        register_post_type( 'tts_social_post', $args );
    }

    /**
     * Register custom meta fields.
     */
    public function register_meta_fields() {
        register_post_meta(
            'tts_social_post',
            '_tts_client_id',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_social_channel',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'array',
                'items'        => array(
                    'type' => 'string',
                ),
                'default'      => array(),
            )
        );
    }

    /**
     * Register the scheduling meta box.
     */
    public function add_schedule_metabox() {
        add_meta_box(
            'tts_programmazione',
            __( 'Programmazione', 'trello-social-auto-publisher' ),
            array( $this, 'render_schedule_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the preview meta box.
     */
    public function add_preview_metabox() {
        add_meta_box(
            'tts_anteprima',
            __( 'Preview', 'trello-social-auto-publisher' ),
            array( $this, 'render_preview_metabox' ),
            'tts_social_post',
            'normal'
        );
    }

    /**
     * Register the social channels meta box.
     */
    public function add_channel_metabox() {
        add_meta_box(
            'tts_social_channel',
            __( 'Channels', 'trello-social-auto-publisher' ),
            array( $this, 'render_channel_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Render the scheduling meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_schedule_metabox( $post ) {
        wp_nonce_field( 'tts_schedule_metabox', 'tts_schedule_nonce' );
        $value     = get_post_meta( $post->ID, '_tts_publish_at', true );
        $formatted = $value ? date( 'Y-m-d\\TH:i', strtotime( $value ) ) : '';
        echo '<input type="datetime-local" name="_tts_publish_at" value="' . esc_attr( $formatted ) . '" class="widefat" />';
    }

    /**
     * Render the preview meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_preview_metabox( $post ) {
        $options   = get_option( 'tts_settings', array() );
        $templates = array(
            'facebook'  => isset( $options['facebook_template'] ) ? $options['facebook_template'] : '',
            'instagram' => isset( $options['instagram_template'] ) ? $options['instagram_template'] : '',
            'youtube'   => isset( $options['youtube_template'] ) ? $options['youtube_template'] : '',
        );

        echo '<div class="tts-preview">';
        foreach ( $templates as $network => $template ) {
            if ( empty( $template ) ) {
                continue;
            }
            $preview = tts_apply_template( $template, $post->ID, $network );
            echo '<p><strong>' . esc_html( ucfirst( $network ) ) . ':</strong> ' . esc_html( $preview ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render the channels meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_channel_metabox( $post ) {
        wp_nonce_field( 'tts_channel_metabox', 'tts_channel_nonce' );
        $value    = get_post_meta( $post->ID, '_tts_social_channel', true );
        $value    = is_array( $value ) ? $value : array();
        $channels = array(
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        );

        foreach ( $channels as $key => $label ) {
            printf(
                '<p><label><input type="checkbox" name="_tts_social_channel[]" value="%1$s" %2$s /> %3$s</label></p>',
                esc_attr( $key ),
                checked( in_array( $key, $value, true ), true, false ),
                esc_html( $label )
            );
        }
    }

    /**
     * Save scheduling meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_schedule_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_schedule_nonce'] ) && wp_verify_nonce( $_POST['tts_schedule_nonce'], 'tts_schedule_metabox' ) ) {
            if ( isset( $_POST['_tts_publish_at'] ) && '' !== $_POST['_tts_publish_at'] ) {
                update_post_meta( $post_id, '_tts_publish_at', sanitize_text_field( wp_unslash( $_POST['_tts_publish_at'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_publish_at' );
            }
        }

        if ( isset( $_POST['tts_channel_nonce'] ) && wp_verify_nonce( $_POST['tts_channel_nonce'], 'tts_channel_metabox' ) ) {
            if ( isset( $_POST['_tts_social_channel'] ) && is_array( $_POST['_tts_social_channel'] ) ) {
                $channels = array_map( 'sanitize_text_field', wp_unslash( $_POST['_tts_social_channel'] ) );
                update_post_meta( $post_id, '_tts_social_channel', $channels );
            } else {
                delete_post_meta( $post_id, '_tts_social_channel' );
            }
        }
    }
}

new TTS_CPT();
