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

        $client_id = intval( get_post_meta( $post_id, '_tts_client_id', true ) );
        if ( ! $client_id ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing client ID', 'trello-social-auto-publisher' ), '' );
            return;
        }

        tts_log_event( $post_id, 'scheduler', 'start', __( 'Publishing social post', 'trello-social-auto-publisher' ), '' );

        $tokens = array(
            'facebook'  => get_post_meta( $client_id, '_tts_fb_token', true ),
            'instagram' => get_post_meta( $client_id, '_tts_ig_token', true ),
        );

        $options = get_option( 'tts_settings', array() );

        $channel = get_post_meta( $post_id, '_tts_social_channel', true );
        if ( empty( $channel ) ) {
            tts_log_event( $post_id, 'scheduler', 'error', __( 'Missing social channel', 'trello-social-auto-publisher' ), '' );
            return;
        }

        $channels = is_array( $channel ) ? $channel : array( $channel );
        $log      = array();

        foreach ( $channels as $ch ) {
            $class = 'TTS_Publisher_' . ucfirst( $ch );
            $file  = plugin_dir_path( __FILE__ ) . 'publishers/class-tts-publisher-' . $ch . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
                if ( class_exists( $class ) ) {
                    $publisher   = new $class();
                    $credentials = isset( $tokens[ $ch ] ) ? $tokens[ $ch ] : '';
                    $template    = isset( $options[ $ch . '_template' ] ) ? $options[ $ch . '_template' ] : '';
                    $message     = $template ? tts_apply_template( $template, $post_id ) : '';
                    $log[ $ch ]  = $publisher->publish( $post_id, $credentials, $message );
                    tts_notify_publication( $post_id, 'processed', $ch );
                }
            }
        }

        update_post_meta( $post_id, '_published_status', 'published' );
        update_post_meta( $post_id, '_tts_publish_log', $log );

        tts_log_event( $post_id, 'scheduler', 'complete', __( 'Publish process completed', 'trello-social-auto-publisher' ), $log );
    }
}

new TTS_Scheduler();
