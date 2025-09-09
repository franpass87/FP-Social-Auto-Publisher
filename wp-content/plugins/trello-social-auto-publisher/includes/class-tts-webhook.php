<?php
/**
 * Trello webhook endpoint.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Trello webhook requests.
 */
class TTS_Webhook {

    /**
     * Initialize hooks.
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
            '/trello-webhook',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'handle_trello_webhook' ),
            )
        );
    }

    /**
     * Handle incoming Trello webhook requests.
     *
     * @param WP_REST_Request $request The request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_trello_webhook( WP_REST_Request $request ) {
        $options       = get_option( 'tts_settings', array() );
        $expected_token = isset( $options['trello_api_token'] ) ? $options['trello_api_token'] : '';

        $provided_token = $request->get_param( 'token' );
        if ( empty( $expected_token ) || $provided_token !== $expected_token ) {
            return new WP_Error( 'invalid_token', __( 'Invalid token.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
        }

        $signature_header = $request->get_header( 'x-trello-webhook' );
        if ( $signature_header && $expected_token ) {
            $callback_url = rest_url( 'tts/v1/trello-webhook' );
            $content      = $request->get_body();
            $computed     = base64_encode( hash_hmac( 'sha1', $content . $callback_url, $expected_token, true ) );

            if ( ! hash_equals( $signature_header, $computed ) ) {
                return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
            }
        }

        // Verify Action Scheduler plugin is active before proceeding.
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if ( ! function_exists( 'as_schedule_single_action' ) || ! is_plugin_active( 'action-scheduler/action-scheduler.php' ) ) {
            return new WP_Error(
                'missing_action_scheduler',
                __( 'Action Scheduler plugin is required.', 'trello-social-auto-publisher' ),
                array( 'status' => 500 )
            );
        }

        $data  = $request->get_json_params();
        $card  = isset( $data['action']['data']['card'] ) ? $data['action']['data']['card'] : array();
        $result = array(
            'idCard'      => isset( $card['id'] ) ? $card['id'] : '',
            'name'        => isset( $card['name'] ) ? $card['name'] : '',
            'desc'        => isset( $card['desc'] ) ? $card['desc'] : '',
            'labels'      => isset( $card['labels'] ) ? $card['labels'] : array(),
            'attachments' => isset( $card['attachments'] ) ? $card['attachments'] : array(),
            'due'         => isset( $card['due'] ) ? $card['due'] : '',
            'idList'      => isset( $card['idList'] ) ? $card['idList'] : '',
        );

        // Create the custom post and schedule its publication.
        $post_id = wp_insert_post(
            array(
                'post_type'   => 'tts_social_post',
                'post_title'  => $result['name'],
                'post_content' => $result['desc'],
                'post_status' => 'publish',
            )
        );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return new WP_Error(
                'post_insert_failed',
                __( 'Failed to create social post.', 'trello-social-auto-publisher' ),
                array( 'status' => 500 )
            );
        }
        if ( empty( $result['due'] ) ) {
            $timestamp = time();
        } else {
            $timestamp = strtotime( $result['due'] );
            if ( $timestamp === false ) {
                $timestamp = time();
            }
        }
        as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( $post_id ) );

        return rest_ensure_response( $result );
    }
}

new TTS_Webhook();
