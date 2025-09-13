<?php
/**
 * Trello webhook endpoint.
 *
 * @package FPPublisher
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
        // Legacy Trello webhook (kept for compatibility)
        register_rest_route(
            'tts/v1',
            '/trello-webhook',
            array(
                'methods'             => array( 'POST', 'GET', 'HEAD' ),
                'callback'            => array( $this, 'handle_trello_webhook' ),
                'permission_callback' => '__return_true',
            )
        );

        // Generic content webhook for multiple sources
        register_rest_route(
            'tts/v1',
            '/content-webhook/(?P<source>\w+)',
            array(
                'methods'             => array( 'POST', 'GET', 'HEAD' ),
                'callback'            => array( $this, 'handle_content_webhook' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'tts/v1',
            '/client/(?P<id>\d+)/register-webhooks',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'register_client_webhooks' ),
                'permission_callback' => function( $request ) {
                    $id = isset( $request['id'] ) ? (int) $request['id'] : 0;
                    return current_user_can( 'edit_post', $id );
                },
            )
        );
    }

    /**
     * Handle generic content webhook from multiple sources.
     *
     * @param WP_REST_Request $request The request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_content_webhook( WP_REST_Request $request ) {
        $source = $request->get_param( 'source' );
        
        if ( 'POST' !== $request->get_method() ) {
            return rest_ensure_response( 'OK' );
        }

        switch ( $source ) {
            case 'trello':
                return $this->handle_trello_webhook( $request );
            case 'dropbox':
                return $this->handle_dropbox_webhook( $request );
            case 'google_drive':
                return $this->handle_google_drive_webhook( $request );
            default:
                return new WP_Error( 
                    'invalid_source', 
                    __( 'Invalid content source', 'fp-publisher' ), 
                    array( 'status' => 400 ) 
                );
        }
    }

    /**
     * Handle Dropbox webhook notifications.
     *
     * @param WP_REST_Request $request The request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_dropbox_webhook( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        
        // Validate Dropbox webhook signature if needed
        // TODO: Implement signature validation
        
        // Process Dropbox file changes
        if ( isset( $data['list_folder'] ) && isset( $data['list_folder']['accounts'] ) ) {
            foreach ( $data['list_folder']['accounts'] as $account ) {
                $this->process_dropbox_changes( $account );
            }
        }
        
        return rest_ensure_response( array( 'status' => 'processed' ) );
    }

    /**
     * Handle Google Drive webhook notifications.
     *
     * @param WP_REST_Request $request The request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_google_drive_webhook( WP_REST_Request $request ) {
        $headers = $request->get_headers();
        
        // Google Drive sends notifications via headers
        $resource_id = isset( $headers['x_goog_resource_id'] ) ? $headers['x_goog_resource_id'][0] : '';
        $resource_state = isset( $headers['x_goog_resource_state'] ) ? $headers['x_goog_resource_state'][0] : '';
        
        if ( $resource_state === 'update' ) {
            $this->process_google_drive_changes( $resource_id );
        }
        
        return rest_ensure_response( array( 'status' => 'processed' ) );
    }

    /**
     * Process Dropbox file changes.
     *
     * @param array $account_data Dropbox account data.
     */
    private function process_dropbox_changes( $account_data ) {
        // Find clients with matching Dropbox configuration
        $clients = get_posts( array(
            'post_type' => 'tts_client',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_tts_dropbox_token',
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ) );

        foreach ( $clients as $client ) {
            $dropbox_token = get_post_meta( $client->ID, '_tts_dropbox_token', true );
            $dropbox_folder = get_post_meta( $client->ID, '_tts_dropbox_folder', true ) ?: '/Social Content';
            
            // Schedule a background task to sync Dropbox content for this client
            as_schedule_single_action(
                time() + 60, // Delay to avoid rate limits
                'tts_sync_dropbox_content',
                array( $client->ID ),
                'tts_content_sync'
            );
        }
    }

    /**
     * Process Google Drive file changes.
     *
     * @param string $resource_id Google Drive resource ID.
     */
    private function process_google_drive_changes( $resource_id ) {
        // Find clients with matching Google Drive configuration
        $clients = get_posts( array(
            'post_type' => 'tts_client',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_tts_google_drive_token',
                    'value' => '',
                    'compare' => '!=',
                ),
            ),
        ) );

        foreach ( $clients as $client ) {
            $gdrive_token = get_post_meta( $client->ID, '_tts_google_drive_token', true );
            $gdrive_folder = get_post_meta( $client->ID, '_tts_google_drive_folder', true ) ?: 'Social Content';
            
            // Schedule a background task to sync Google Drive content for this client
            as_schedule_single_action(
                time() + 60, // Delay to avoid rate limits
                'tts_sync_google_drive_content',
                array( $client->ID ),
                'tts_content_sync'
            );
        }
    }

    /**
     * Handle incoming Trello webhook requests.
     *
     * @param WP_REST_Request $request The request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_trello_webhook( WP_REST_Request $request ) {
        if ( 'POST' !== $request->get_method() ) {
            return rest_ensure_response( 'OK' );
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
            'idBoard'     => isset( $card['idBoard'] ) ? $card['idBoard'] : '',
            'canale_social' => '',
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
                            'key'     => '_tts_trello_boards',
                            'value'   => $board_id,
                            'compare' => 'LIKE',
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
                    foreach ( $map as $row ) {
                        if ( isset( $row['idList'] ) && $row['idList'] === $result['idList'] ) {
                            $client_id               = (int) $client_post_id;
                            $result['canale_social'] = isset( $row['canale_social'] ) ? $row['canale_social'] : '';
                            break 2;
                        }
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
            return new WP_Error( 'client_not_found', __( 'Client not found.', 'fp-publisher' ), array( 'status' => 404 ) );
        }

        // Load Trello credentials for the resolved client.
        $client_token  = get_post_meta( $client_id, '_tts_trello_token', true );
        $client_secret = get_post_meta( $client_id, '_tts_trello_secret', true );

        $provided_token = $request->get_param( 'token' );
        if ( empty( $client_token ) || $provided_token !== $client_token ) {
            return new WP_Error( 'invalid_token', __( 'Invalid token.', 'fp-publisher' ), array( 'status' => 403 ) );
        }

        $signature_header = $request->get_header( 'x-trello-webhook' );
        $hmac_param       = $request->get_param( 'hmac' );
        $content          = $request->get_body();

        if ( empty( $client_secret ) ) {
            return new WP_Error( 'missing_secret', __( 'Client secret not configured.', 'fp-publisher' ), array( 'status' => 403 ) );
        }

        if ( $signature_header ) {
            $callback_url = rest_url( 'tts/v1/trello-webhook' );
            $expected     = base64_encode( hash_hmac( 'sha1', $content . $callback_url, $client_secret, true ) );
            if ( ! hash_equals( $signature_header, $expected ) ) {
                return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'fp-publisher' ), array( 'status' => 403 ) );
            }
        } elseif ( $hmac_param ) {
            $expected = hash_hmac( 'sha256', $content, $client_secret );
            if ( ! hash_equals( $hmac_param, $expected ) ) {
                return new WP_Error( 'invalid_signature', __( 'Invalid signature.', 'fp-publisher' ), array( 'status' => 403 ) );
            }
        } else {
            return new WP_Error( 'invalid_signature', __( 'Missing signature.', 'fp-publisher' ), array( 'status' => 403 ) );
        }

        $mapping_json = get_post_meta( $client_id, '_tts_column_mapping', true );
        $mapping = ! empty( $mapping_json ) ? json_decode( $mapping_json, true ) : array();
        if ( empty( $result['idList'] ) || ! is_array( $mapping ) || ! array_key_exists( $result['idList'], $mapping ) ) {
            return rest_ensure_response( array( 'message' => __( 'Unmapped list.', 'fp-publisher' ) ) );
        }
        $existing_post = get_posts(
            array(
                'post_type'   => 'tts_social_post',
                'post_status' => 'any',
                'meta_query'  => array(
                    array(
                        'key'   => '_trello_card_id',
                        'value' => $result['idCard'],
                    ),
                ),
                'fields'      => 'ids',
                'numberposts' => 1,
            )
        );

        if ( ! empty( $existing_post ) ) {
            tts_log_event( $existing_post[0], 'webhook', 'skip', 'Trello card already processed', '' );
            return rest_ensure_response( array( 'message' => __( 'Card already processed.', 'fp-publisher' ) ) );
        }

        $post_id = wp_insert_post(
            array(
                'post_title'   => sanitize_text_field( $result['name'] ),
                'post_content' => wp_kses_post( $result['desc'] ),
                'post_type'    => 'tts_social_post',
                'post_status'  => 'draft',
                'meta_input'   => array( '_tts_client_id' => $client_id ),
            ),
            true
        );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_trello_card_id', $result['idCard'] );
            update_post_meta( $post_id, '_trello_labels', $result['labels'] );
            update_post_meta( $post_id, '_trello_attachments', $result['attachments'] );
            update_post_meta( $post_id, '_trello_due', $result['due'] );
            update_post_meta( $post_id, '_trello_board_id', $result['idBoard'] );

            $result['post_id']   = $post_id;
            $result['client_id'] = $client_id;

            if ( empty( $result['attachments'] ) ) {
                $manual_url = '';
                $pattern    = '/https?:\\/\\/\S+\.mp4/i';

                if ( ! empty( $result['desc'] ) && preg_match( $pattern, $result['desc'], $matches ) ) {
                    $manual_url = $matches[0];
                } elseif ( isset( $data['action']['data']['text'] ) && preg_match( $pattern, $data['action']['data']['text'], $matches ) ) {
                    $manual_url = $matches[0];
                }

                if ( $manual_url ) {
                    $importer  = new TTS_Media_Importer();
                    $media_id  = $importer->import_from_url( $manual_url );

                    if ( is_wp_error( $media_id ) ) {
                        tts_log_event( $post_id, 'webhook', 'error', $media_id->get_error_message(), $manual_url );
                        return rest_ensure_response( $result );
                    }

                    update_post_meta( $post_id, '_tts_manual_media', (int) $media_id );
                    tts_log_event( $post_id, 'webhook', 'success', __( 'Manual media imported', 'fp-publisher' ), $manual_url );
                } else {
                    tts_log_event( $post_id, 'webhook', 'warning', __( 'No attachments provided', 'fp-publisher' ), '' );
                    return rest_ensure_response( $result );
                }
            }

            if ( ! empty( $result['due'] ) ) {
                $publish_at = sanitize_text_field( $result['due'] );
                update_post_meta( $post_id, '_tts_publish_at', $publish_at );
                $timestamp = strtotime( $publish_at );
                if ( $timestamp ) {
                    as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( 'post_id' => $post_id ) );
                    tts_log_event(
                        $post_id,
                        'webhook',
                        'scheduled',
                        sprintf( __( 'Publish scheduled for %s', 'fp-publisher' ), $publish_at ),
                        ''
                    );
                }
            }

            $media_ids = array();
            if ( ! empty( $result['attachments'] ) && is_array( $result['attachments'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                foreach ( $result['attachments'] as $attachment ) {
                    if ( empty( $attachment['isUpload'] ) || empty( $attachment['url'] ) ) {
                        continue;
                    }

                    $response = wp_remote_get(
                        $attachment['url'],
                        array(
                            'timeout' => 20,
                        )
                    );
                    if ( is_wp_error( $response ) ) {
                        tts_log_event( $post_id, 'webhook', 'error', __( 'Failed to retrieve attachment.', 'fp-publisher' ), $attachment['url'] );
                        continue;
                    }

                    $code = wp_remote_retrieve_response_code( $response );
                    if ( 200 !== (int) $code ) {
                        tts_log_event( $post_id, 'webhook', 'error', sprintf( __( 'Unexpected HTTP response code: %d', 'fp-publisher' ), $code ), $attachment['url'] );
                        continue;
                    }

                    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
                    $filetype     = wp_check_filetype( basename( wp_parse_url( $attachment['url'], PHP_URL_PATH ) ) );

                    if ( empty( $content_type ) || empty( $filetype['type'] ) || ( 0 !== strpos( $content_type, 'image/' ) && 0 !== strpos( $content_type, 'video/' ) ) ) {
                        tts_log_event( $post_id, 'webhook', 'error', sprintf( __( 'Unsupported MIME type: %s', 'fp-publisher' ), $content_type ), $attachment['url'] );
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
                    update_post_meta( $post_id, '_tts_attachment_ids', $media_ids );
                }
            }
        }

        return rest_ensure_response( $result );
    }

    /**
     * Register Trello webhooks for all boards of a client.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function register_client_webhooks( WP_REST_Request $request ) {
        $client_id = (int) $request['id'];

        $key    = get_post_meta( $client_id, '_tts_trello_key', true );
        $token  = get_post_meta( $client_id, '_tts_trello_token', true );
        $boards = get_post_meta( $client_id, '_tts_trello_boards', true );

        if ( empty( $key ) || empty( $token ) || empty( $boards ) || ! is_array( $boards ) ) {
            return new WP_Error( 'missing_data', __( 'Missing Trello credentials or boards.', 'fp-publisher' ), array( 'status' => 400 ) );
        }

        $callback = rest_url( 'tts/v1/trello-webhook' );
        $results  = array();

        foreach ( $boards as $board_id ) {
            $response = wp_remote_post(
                sprintf( 'https://api.trello.com/1/webhooks/?key=%s&token=%s', rawurlencode( $key ), rawurlencode( $token ) ),
                array(
                    'body' => array(
                        'idModel'     => $board_id,
                        'callbackURL' => $callback,
                        'description' => get_bloginfo( 'name' ) . ' TTS',
                    ),
                    'timeout' => 20,
                )
            );

            if ( is_wp_error( $response ) ) {
                $results[ $board_id ] = $response->get_error_message();
            } else {
                $results[ $board_id ] = wp_remote_retrieve_response_code( $response );
            }
        }

        return rest_ensure_response( $results );
    }
}

new TTS_Webhook();
