<?php
/**
 * Handles scheduling and publishing of social posts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Scheduler for social posts.
 */
class TTS_Scheduler {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'save_post_tts_social_post', array( $this, 'schedule_post' ), 10, 3 );
        add_action( 'tts_publish_social_post', array( $this, 'publish_social_post' ) );
    }

    /**
     * Schedule post publication via Action Scheduler.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function schedule_post( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        as_unschedule_all_actions( 'tts_publish_social_post', array( 'post_id' => $post_id ) );

        $publish_at = isset( $_POST['_tts_publish_at'] ) ? sanitize_text_field( $_POST['_tts_publish_at'] ) : '';

        if ( ! empty( $publish_at ) ) {
            $timestamp = strtotime( $publish_at );
            if ( $timestamp ) {
                as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( 'post_id' => $post_id ) );
            }
        }
    }

    /**
     * Publish the social post to configured networks.
     *
     * @param array $args Action Scheduler arguments.
     */
    public function publish_social_post( $args ) {
        $post_id = isset( $args['post_id'] ) ? intval( $args['post_id'] ) : 0;
        if ( ! $post_id ) {
            return;
        }

        $attempt      = (int) get_post_meta( $post_id, '_tts_retry_count', true );
        $max_attempts = 5;

        $client_id = intval( get_post_meta( $post_id, '_tts_client_id', true ) );
        if ( ! $client_id ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing client ID', 'trello-social-auto-publisher' ), '' );
            return;
        }

        tts_log_event( $post_id, 'scheduler', 'start', __( 'Publishing social post', 'trello-social-auto-publisher' ), '' );

        $tokens = array(
            'facebook'  => get_post_meta( $client_id, '_tts_fb_token', true ),
            'instagram' => get_post_meta( $client_id, '_tts_ig_token', true ),
            'youtube'   => get_post_meta( $client_id, '_tts_yt_token', true ),
            'tiktok'    => get_post_meta( $client_id, '_tts_tt_token', true ),
        );

        $options = get_option( 'tts_settings', array() );
        $channel = get_post_meta( $post_id, '_tts_social_channel', true );
        if ( empty( $channel ) ) {
            $id_list = get_post_meta( $post_id, '_trello_idList', true );
            if ( empty( $id_list ) ) {
                $card_id     = get_post_meta( $post_id, '_trello_card_id', true );
                $trello_key   = get_post_meta( $client_id, '_tts_trello_key', true );
                $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
                if ( $card_id && $trello_key && $trello_token ) {
                    $url      = 'https://api.trello.com/1/cards/' . rawurlencode( $card_id ) . '?fields=idList&key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token );
                    $response = wp_remote_get( $url );
                    if ( ! is_wp_error( $response ) ) {
                        $body = json_decode( wp_remote_retrieve_body( $response ), true );
                        if ( isset( $body['idList'] ) ) {
                            $id_list = $body['idList'];
                        }
                    }
                }
            }
            if ( $id_list ) {
                $mapping = get_post_meta( $client_id, '_tts_trello_map', true );
                if ( is_array( $mapping ) ) {
                    foreach ( $mapping as $row ) {
                        if ( isset( $row['idList'], $row['canale_social'] ) && $row['idList'] === $id_list ) {
                            $channel = $row['canale_social'];
                            break;
                        }
                    }
                }
            }
        }
        if ( empty( $channel ) ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing social channel', 'trello-social-auto-publisher' ), '' );
            return;
        }

        $channels = is_array( $channel ) ? $channel : array( $channel );
        $log      = array();

        $error = false;

        foreach ( $channels as $ch ) {
            $class = 'TTS_Publisher_' . ucfirst( $ch );
            $file  = plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-' . $ch . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
                if ( class_exists( $class ) ) {
                    $publisher   = new $class();
                    $credentials = isset( $tokens[ $ch ] ) ? $tokens[ $ch ] : '';
                    $template    = isset( $options[ $ch . '_template' ] ) ? $options[ $ch . '_template' ] : '';
                    $message     = $template ? tts_apply_template( $template, $post_id, $ch ) : '';

                    try {
                        $log[ $ch ] = $publisher->publish( $post_id, $credentials, $message );
                        if ( is_wp_error( $log[ $ch ] ) ) {
                            $error = true;
                        }
                    } catch ( \Exception $e ) {
                        $error       = true;
                        $log[ $ch ]  = $e->getMessage();
                        tts_log_event( $post_id, $ch, 'error', $e->getMessage(), '' );
                    }

                    tts_notify_publication( $post_id, 'processed', $ch );
                }
            }
        }

        if ( $error ) {
            if ( $attempt >= $max_attempts ) {
                tts_log_event( $post_id, 'scheduler', 'error', __( 'Maximum retry attempts reached', 'trello-social-auto-publisher' ), '' );
                tts_notify_publication( $post_id, 'error', 'scheduler' );
                return;
            }

            $attempt++;
            update_post_meta( $post_id, '_tts_retry_count', $attempt );

            $delay     = $this->calculate_backoff_delay( $attempt );
            $timestamp = time() + $delay * MINUTE_IN_SECONDS;
            as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( 'post_id' => $post_id ) );

            tts_log_event( $post_id, 'scheduler', 'retry', sprintf( __( 'Retry #%1$d scheduled in %2$d minutes', 'trello-social-auto-publisher' ), $attempt, $delay ), '' );
            return;
        }

        delete_post_meta( $post_id, '_tts_retry_count' );
        update_post_meta( $post_id, '_published_status', 'published' );
        update_post_meta( $post_id, '_tts_publish_log', $log );

        tts_log_event( $post_id, 'scheduler', 'complete', __( 'Publish process completed', 'trello-social-auto-publisher' ), $log );
    }

    /**
     * Calculate delay for retry attempts in minutes.
     *
     * @param int $attempt Current attempt number.
     * @return int Delay in minutes.
     */
    private function calculate_backoff_delay( $attempt ) {
        $delays = array( 1, 5, 15, 30, 60 );

        if ( $attempt <= 0 ) {
            return 1;
        }

        return isset( $delays[ $attempt - 1 ] ) ? $delays[ $attempt - 1 ] : end( $delays );
    }
}

new TTS_Scheduler();
