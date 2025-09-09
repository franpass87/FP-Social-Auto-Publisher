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
        if ( ! isset( $_POST['tts_schedule_nonce'] ) || ! wp_verify_nonce( $_POST['tts_schedule_nonce'], 'tts_schedule_metabox' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( isset( $_POST['_tts_publish_at'] ) && '' !== $_POST['_tts_publish_at'] ) {
            update_post_meta( $post_id, '_tts_publish_at', sanitize_text_field( $_POST['_tts_publish_at'] ) );
        } else {
            delete_post_meta( $post_id, '_tts_publish_at' );
        }
    }
}

new TTS_CPT();
