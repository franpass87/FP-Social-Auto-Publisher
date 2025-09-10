<?php
/**
 * REST endpoints for manual publish and status checks.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles custom REST API routes.
 */
class TTS_REST {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            'tts/v1',
            '/post/(?P<id>\d+)/publish',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'publish' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );

        register_rest_route(
            'tts/v1',
            '/post/(?P<id>\d+)/status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'status' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            )
        );
    }

    /**
     * Check permissions for routes.
     *
     * @param WP_REST_Request $request The current request.
     *
     * @return bool
     */
    public function permissions_check( WP_REST_Request $request ) {
        $id = intval( $request['id'] );
        return current_user_can( 'edit_post', $id );
    }

    /**
     * Publish the social post immediately.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function publish( WP_REST_Request $request ) {
        $id = intval( $request['id'] );

        // Trigger publish via scheduler.
        TTS_Scheduler::publish_social_post( array( 'post_id' => $id ) );

        return rest_ensure_response( array( 'post_id' => $id ) );
    }

    /**
     * Get publication status and logs for a post.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function status( WP_REST_Request $request ) {
        global $wpdb;

        $id   = intval( $request['id'] );
        $post = get_post( $id );
        if ( ! $post ) {
            return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'trello-social-auto-publisher' ), array( 'status' => 404 ) );
        }

        $post_status      = get_post_status( $id );
        $published_status = get_post_meta( $id, '_published_status', true );

        $table = $wpdb->prefix . 'tts_logs';
        $logs  = $wpdb->get_results( $wpdb->prepare( "SELECT channel, status, message, response, created_at FROM {$table} WHERE post_id = %d ORDER BY id DESC", $id ), ARRAY_A );

        return rest_ensure_response(
            array(
                'post_status'       => $post_status,
                '_published_status' => $published_status,
                'logs'              => $logs,
            )
        );
    }
}

new TTS_REST();
