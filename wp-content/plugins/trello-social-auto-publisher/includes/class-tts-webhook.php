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
            'idBoard'     => isset( $card['idBoard'] ) ? $card['idBoard'] : '',
        );

        $client_id = 0;
        $board_id  = $result['idBoard'];
        if ( ! $board_id && isset( $data['action']['data']['board']['id'] ) ) {
            $board_id = $data['action']['data']['board']['id'];
        }
        if ( $board_id ) {
            $client_query = get_posts(
                array(
                    'post_type'   => 'tts_client',
                    'post_status' => 'any',
                    'meta_query'  => array(
                        array(
                            'key'   => '_tts_trello_board',
                            'value' => $board_id,
                        ),
                    ),
                    'fields'      => 'ids',
                    'numberposts' => 1,
                )
            );
            if ( ! empty( $client_query ) ) {
                $client_id = (int) $client_query[0];
            }
        }
        if ( ! $client_id && $result['idList'] ) {
            // Get all tts_client posts with the _tts_trello_map meta key
            $client_query = get_posts(
                array(
                    'post_type'   => 'tts_client',
                    'post_status' => 'any',
                    'meta_query'  => array(
                        array(
                            'key' => '_tts_trello_map',
                            'compare' => 'EXISTS',
                        ),
                    ),
                    'fields'      => 'ids',
                    'numberposts' => -1,
                )
            );
            foreach ( $client_query as $client_post_id ) {
                $map = get_post_meta( $client_post_id, '_tts_trello_map', true );
                $map = maybe_unserialize( $map );
                if ( is_array( $map ) ) {
                    if ( in_array( $result['idList'], $map, true ) ) {
                        $client_id = (int) $client_post_id;
                        break;
                    }
                } elseif ( is_string( $map ) ) {
                    // If stored as comma-separated or single value
                    $ids = array_map( 'trim', explode( ',', $map ) );
                    if ( in_array( $result['idList'], $ids, true ) ) {
                        $client_id = (int) $client_post_id;
                        break;
                    }
                }
            }
        }

        if ( ! $client_id ) {
            return new WP_Error( 'client_not_found', __( 'Client not found.', 'trello-social-auto-publisher' ), array( 'status' => 404 ) );
        }

        // Load Trello credentials for the resolved client.
        $client_token  = get_post_meta( $client_id, '_tts_trello_token', true );
        $client_secret = get_post_meta( $client_id, '_tts_trello_secret', true );

        $provided_token = $request->get_param( 'token' );
        if ( empty( $client_token ) || $provided_token !== $client_token ) {
            return new WP_Error( 'invalid_token', __( 'Invalid token.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
        }

        $signature_header = $request->get_header( 'x-trello-webhook' );
        $hmac_param       = $request->get_param( 'hmac' );
        $content          = $request->get_body();

        if ( empty( $client_secret ) ) {
            return new WP_Error( 'missing_secret', __( 'Client secret not configured.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
        }

        if ( $signature_header ) {
            $callback_url = rest_url( 'tts/v1/trello-webhook' );
            $expected     = base64_encode( hash_hmac( 'sha1', $content . $callback_url, $client_secret, true ) );
            if ( ! hash_equals( $signature_header, $expected ) ) {
                return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
            }
        } elseif ( $hmac_param ) {
            $expected = hash_hmac( 'sha256', $content, $client_secret );
            if ( ! hash_equals( $hmac_param, $expected ) ) {
                return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
            }
        } else {
            return new WP_Error( 'invalid_signature', __( 'Missing signature.', 'trello-social-auto-publisher' ), array( 'status' => 403 ) );
        }

        $mapping_json = get_post_meta( $client_id, '_tts_column_mapping', true );
        $mapping = ! empty( $mapping_json ) ? json_decode( $mapping_json, true ) : array();
        if ( empty( $result['idList'] ) || ! is_array( $mapping ) || ! array_key_exists( $result['idList'], $mapping ) ) {
            return rest_ensure_response( array( 'message' => __( 'Unmapped list.', 'trello-social-auto-publisher' ) ) );
        }

        $post_id = wp_insert_post(
            array(
                'post_title'   => sanitize_text_field( $result['name'] ),
                'post_content' => wp_kses_post( $result['desc'] ),
                'post_type'    => 'tts_social_post',
                'post_status'  => 'publish',
                'meta_input'   => array( '_tts_client_id' => $client_id ),
            ),
            true
        );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_trello_labels', $result['labels'] );
            update_post_meta( $post_id, '_trello_attachments', $result['attachments'] );
            update_post_meta( $post_id, '_trello_due', $result['due'] );

            $media_ids = array();
            if ( ! empty( $result['attachments'] ) && is_array( $result['attachments'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                foreach ( $result['attachments'] as $attachment ) {
                    if ( empty( $attachment['isUpload'] ) || empty( $attachment['url'] ) ) {
                        continue;
                    }

                    $response = wp_remote_get( $attachment['url'] );
                    if ( is_wp_error( $response ) ) {
                        continue;
                    }

                    $body = wp_remote_retrieve_body( $response );
                    $tmp  = wp_tempnam( $attachment['url'] );
                    if ( ! $tmp ) {
                        continue;
                    }

                    file_put_contents( $tmp, $body );

                    $file_array = array(
                        'name'     => sanitize_file_name( basename( wp_parse_url( $attachment['url'], PHP_URL_PATH ) ) ),
                        'tmp_name' => $tmp,
                    );

                    $media_id = media_handle_sideload( $file_array, $post_id );
                    @unlink( $tmp );

                    if ( ! is_wp_error( $media_id ) ) {
                        $media_ids[] = (int) $media_id;
                    }
                }

                if ( ! empty( $media_ids ) ) {
                    set_post_thumbnail( $post_id, $media_ids[0] );
                    update_post_meta( $post_id, '_trello_media_ids', $media_ids );
                }
            }

            $result['post_id']   = $post_id;
            $result['client_id'] = $client_id;
        }

        return rest_ensure_response( $result );
    }
}

new TTS_Webhook();
