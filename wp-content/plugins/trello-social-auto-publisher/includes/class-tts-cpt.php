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
}

new TTS_CPT();
