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

        // Security check: only process if this is a legitimate post save with proper nonce
        if ( isset( $_POST['_tts_approved'] ) ) {
            // Verify nonce if processing form data
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $post_id ) ) {
                return;
            }
            
            // Check user capabilities for this specific post
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

        as_unschedule_all_actions( 'tts_publish_social_post', array( 'post_id' => $post_id ) );

        $approved  = isset( $_POST['_tts_approved'] ) ? (bool) sanitize_text_field( $_POST['_tts_approved'] ) : (bool) get_post_meta( $post_id, '_tts_approved', true );
        if ( ! $approved ) {
            return;
        }

        $publish_at = isset( $_POST['_tts_publish_at'] ) ? sanitize_text_field( $_POST['_tts_publish_at'] ) : get_post_meta( $post_id, '_tts_publish_at', true );
        $channels   = isset( $_POST['_tts_social_channel'] ) && is_array( $_POST['_tts_social_channel'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['_tts_social_channel'] ) ) : get_post_meta( $post_id, '_tts_social_channel', true );

        if ( ! empty( $publish_at ) ) {
            $timestamp = strtotime( $publish_at );
            if ( $timestamp ) {
                if ( ! empty( $channels ) ) {
                    $options = get_option( 'tts_settings', array() );
                    foreach ( $channels as $channel ) {
                        $offset = isset( $options[ $channel . '_offset' ] ) ? intval( $options[ $channel . '_offset' ] ) : 0;
                        $when   = $timestamp + $offset * MINUTE_IN_SECONDS;
                        as_schedule_single_action( $when, 'tts_publish_social_post', array( 'post_id' => $post_id, 'channel' => $channel ) );
                    }
                } else {
                    as_schedule_single_action( $timestamp, 'tts_publish_social_post', array( 'post_id' => $post_id ) );
                }
            }
        }
    }

    /**
     * Publish the social post to configured networks.
     *
     * @param array $args Action Scheduler arguments.
     */
    public function publish_social_post( $args ) {
        $post_id       = isset( $args['post_id'] ) ? intval( $args['post_id'] ) : 0;
        $forced_channel = isset( $args['channel'] ) ? sanitize_text_field( $args['channel'] ) : '';
        if ( ! $post_id ) {
            return;
        }

        $notifier = new TTS_Notifier();

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
            // Added support for YouTube uploads.
            'youtube'   => get_post_meta( $client_id, '_tts_yt_token', true ),
            // Added support for TikTok uploads.
            'tiktok'    => get_post_meta( $client_id, '_tts_tt_token', true ),
        );

        $options  = get_option( 'tts_settings', array() );
        $channels = $forced_channel ? array( $forced_channel ) : get_post_meta( $post_id, '_tts_social_channel', true );

        if ( empty( $channels ) && ! $forced_channel ) {
            $mapped_channel = '';
            $id_list        = get_post_meta( $post_id, '_trello_idList', true );
            $board_id      = get_post_meta( $post_id, '_trello_board_id', true );
            if ( empty( $id_list ) || empty( $board_id ) ) {
                $card_id     = get_post_meta( $post_id, '_trello_card_id', true );
                $trello_key   = get_post_meta( $client_id, '_tts_trello_key', true );
                $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
                if ( $card_id && $trello_key && $trello_token ) {
                    $url      = 'https://api.trello.com/1/cards/' . rawurlencode( $card_id ) . '?fields=idList,idBoard&key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token );
                    $response = wp_remote_get( $url, array( 'timeout' => 20 ) );
                    if ( ! is_wp_error( $response ) ) {
                        $body = json_decode( wp_remote_retrieve_body( $response ), true );
                        if ( isset( $body['idList'] ) ) {
                            $id_list = $body['idList'];
                        }
                        if ( isset( $body['idBoard'] ) ) {
                            $board_id = $body['idBoard'];
                        }
                    }
                }
            }
            if ( $board_id ) {
                update_post_meta( $post_id, '_trello_board_id', $board_id );
            }
            if ( $id_list ) {
                $mapping = get_post_meta( $client_id, '_tts_trello_map', true );
                if ( is_array( $mapping ) ) {
                    foreach ( $mapping as $row ) {
                        if ( isset( $row['idList'], $row['canale_social'] ) && $row['idList'] === $id_list ) {
                            $mapped_channel = $row['canale_social'];
                            break;
                        }
                    }
                }
            }
            if ( $mapped_channel ) {
                $channels = array( $mapped_channel );
            }
        }

        if ( empty( $channels ) ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing social channel', 'trello-social-auto-publisher' ), '' );
            return;
        }

        $channels = is_array( $channels ) ? $channels : array( $channels );
        $log      = array();

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $manual_id      = (int) get_post_meta( $post_id, '_tts_manual_media', true );
        if ( $manual_id ) {
            $attachment_ids[] = $manual_id;
        }

        $processor = new TTS_Image_Processor();

        $error = false;

        foreach ( $channels as $ch ) {
            if ( $attachment_ids ) {
                $resized = array();
                foreach ( $attachment_ids as $att_id ) {
                    $url = $processor->resize_for_channel( $att_id, $ch );
                    if ( $url ) {
                        $resized[ $att_id ] = $url;
                    }
                }
                if ( $resized ) {
                    update_post_meta( $post_id, '_tts_resized_' . $ch, $resized );
                }
            }
            $class = 'TTS_Publisher_' . ucfirst( $ch );
            $file  = plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-' . $ch . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
                if ( class_exists( $class ) ) {
                    $publisher   = new $class();
                    $credentials = isset( $tokens[ $ch ] ) ? $tokens[ $ch ] : '';
                    $template    = isset( $options[ $ch . '_template' ] ) ? $options[ $ch . '_template' ] : '';
                    $custom_message = get_post_meta( $post_id, '_tts_message_' . $ch, true );
                    if ( $custom_message ) {
                        $message = $custom_message;
                    } else {
                        $message = $template ? tts_apply_template( $template, $post_id, $ch ) : '';
                    }

                    try {
                        $log[ $ch ] = $publisher->publish( $post_id, $credentials, $message );
                        if ( is_wp_error( $log[ $ch ] ) ) {
                            $error = true;
                        } elseif ( 'instagram' === $ch ) {
                            $first_comment = get_post_meta( $post_id, '_tts_instagram_first_comment', true );
                            if ( $first_comment && is_array( $log[ $ch ] ) && isset( $log[ $ch ]['id'] ) ) {
                                $comment_res = $publisher->post_comment( $log[ $ch ]['id'], $first_comment );
                                if ( is_wp_error( $comment_res ) ) {
                                    $error = true;
                                    $log['instagram_comment'] = $comment_res;
                                } else {
                                    $log['instagram_comment'] = $comment_res;
                                }
                            }
                        }
                    } catch ( \Exception $e ) {
                        $error       = true;
                        $log[ $ch ]  = $e->getMessage();
                        tts_log_event( $post_id, $ch, 'error', $e->getMessage(), '' );
                    }
                }
            }
        }

        $publish_story = (bool) get_post_meta( $post_id, '_tts_publish_story', true );
        if ( $publish_story ) {
            $story_id  = (int) get_post_meta( $post_id, '_tts_story_media', true );
            $media_url = $story_id ? wp_get_attachment_url( $story_id ) : '';
            if ( $media_url ) {
                $story_channels = array( 'facebook', 'instagram' );
                foreach ( $story_channels as $story_channel ) {
                    $class = 'TTS_Publisher_' . ucfirst( $story_channel ) . '_Story';
                    $file  = plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-' . $story_channel . '-story.php';
                    if ( file_exists( $file ) ) {
                        require_once $file;
                        if ( class_exists( $class ) ) {
                            $publisher   = new $class();
                            $credentials = isset( $tokens[ $story_channel ] ) ? $tokens[ $story_channel ] : '';
                            try {
                                $key             = $story_channel . '_story';
                                $log[ $key ]     = $publisher->publish_story( $post_id, $credentials, $media_url );
                                if ( is_wp_error( $log[ $key ] ) ) {
                                    $error = true;
                                }
                            } catch ( \Exception $e ) {
                                $error = true;
                                $log[ $story_channel . '_story' ] = $e->getMessage();
                                tts_log_event( $post_id, $story_channel . '_story', 'error', $e->getMessage(), '' );
                            }
                        }
                    }
                }
            } else {
                tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing Story media', 'trello-social-auto-publisher' ), '' );
                $error = true;
            }
        }

        if ( $error ) {
            if ( $attempt >= $max_attempts ) {
                tts_log_event( $post_id, 'scheduler', 'error', __( 'Maximum retry attempts reached', 'trello-social-auto-publisher' ), '' );
                $log_url = admin_url( 'admin.php?page=tts-log&post_id=' . $post_id );
                $message = sprintf( __( 'Publishing failed for post %1$s. Log: %2$s', 'trello-social-auto-publisher' ), get_the_title( $post_id ), $log_url );
                $notifier->notify_slack( $message );
                $notifier->notify_email( __( 'Social publishing failed', 'trello-social-auto-publisher' ), $message );
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

        $card_id       = get_post_meta( $post_id, '_trello_card_id', true );
        $trello_key    = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token  = get_post_meta( $client_id, '_tts_trello_token', true );
        $published_list = get_post_meta( $client_id, '_tts_trello_published_list', true );

        if ( $card_id && $trello_key && $trello_token && $published_list ) {
            $first_url = '';
            $links     = array();
            foreach ( $log as $channel => $entry ) {
                $link = '';
                if ( is_string( $entry ) && preg_match( '/https?:\/\/[^\s]+/', $entry, $match ) ) {
                    $link = $match[0];
                } elseif ( is_array( $entry ) ) {
                    if ( isset( $entry['url'] ) ) {
                        $link = $entry['url'];
                    } else {
                        foreach ( $entry as $val ) {
                            if ( is_string( $val ) && preg_match( '/https?:\/\/[^\s]+/', $val, $match ) ) {
                                $link = $match[0];
                                break;
                            }
                        }
                    }
                }
                if ( $link ) {
                    if ( empty( $first_url ) ) {
                        $first_url = $link;
                    }
                    $links[] = ucfirst( $channel ) . ': ' . $link;
                }
            }

            $base = 'https://api.trello.com/1/cards/' . rawurlencode( $card_id );
            $move_response = wp_remote_request(
                $base . '?key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token ),
                array(
                    'method'  => 'PUT',
                    'body'    => array( 'idList' => $published_list ),
                    'timeout' => 20,
                )
            );
            if ( is_wp_error( $move_response ) ) {
                tts_log_event( $post_id, 'trello', 'error', $move_response->get_error_message(), '' );
            } else {
                $comment_url = sprintf(
                    'https://api.trello.com/1/cards/%s/actions/comments?key=%s&token=%s',
                    rawurlencode( $card_id ),
                    rawurlencode( $trello_key ),
                    rawurlencode( $trello_token )
                );

                if ( $first_url ) {
                    $comment_response = wp_remote_post(
                        $comment_url,
                        array(
                            'body'    => array( 'text' => $first_url ),
                            'timeout' => 20,
                        )
                    );
                    if ( is_wp_error( $comment_response ) ) {
                        tts_log_event( $post_id, 'trello', 'error', $comment_response->get_error_message(), '' );
                    }
                }

                if ( $links ) {
                    $comment_response2 = wp_remote_post(
                        $comment_url,
                        array(
                            'body'    => array( 'text' => implode( "\n", $links ) ),
                            'timeout' => 20,
                        )
                    );
                    if ( is_wp_error( $comment_response2 ) ) {
                        tts_log_event( $post_id, 'trello', 'error', $comment_response2->get_error_message(), '' );
                    }
                }
            }
        }

        tts_log_event( $post_id, 'scheduler', 'complete', __( 'Publish process completed', 'trello-social-auto-publisher' ), $log );

        $log_url = admin_url( 'admin.php?page=tts-log&post_id=' . $post_id );
        $message = sprintf( __( 'Publishing completed for post %1$s. Log: %2$s', 'trello-social-auto-publisher' ), get_the_title( $post_id ), $log_url );
        $notifier->notify_slack( $message );
        $notifier->notify_email( __( 'Social publishing completed', 'trello-social-auto-publisher' ), $message );
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
