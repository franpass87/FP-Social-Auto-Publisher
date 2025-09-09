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

        return rest_ensure_response( $result );
    }
}

new TTS_Webhook();
