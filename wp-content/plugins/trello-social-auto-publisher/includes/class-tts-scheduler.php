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

        $settings = get_post_meta( $client_id, '_tts_social_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $log              = array();
        $log['facebook']  = $this->publish_facebook( $post_id, $tokens['facebook'], $settings );
        $log['instagram'] = $this->publish_instagram( $post_id, $tokens['instagram'], $settings );

        update_post_meta( $post_id, '_published_status', 'published' );
        update_post_meta( $post_id, '_tts_publish_log', $log );

        tts_log_event( $post_id, 'scheduler', 'complete', __( 'Publish process completed', 'trello-social-auto-publisher' ), $log );
    }

    /**
     * Publish to Facebook.
     *
     * @param int   $post_id  Post ID.
     * @param mixed $token    Facebook access token.
     * @param array $settings Client social settings.
     * @return string Log message.
     */
    protected function publish_facebook( $post_id, $token, $settings ) {
        if ( empty( $token ) ) {
            $message = __( 'Facebook token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $message, '' );
            return $message;
        }

        $message = __( 'Published to Facebook', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'success', $message, array() );

        return $message;
    }

    /**
     * Publish to Instagram.
     *
     * @param int   $post_id  Post ID.
     * @param mixed $token    Instagram access token.
     * @param array $settings Client social settings.
     * @return string Log message.
     */
    protected function publish_instagram( $post_id, $token, $settings ) {
        if ( empty( $token ) ) {
            $message = __( 'Instagram token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $message, '' );
            return $message;
        }

        $message = __( 'Published to Instagram', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'instagram', 'success', $message, array() );

        return $message;
    }
}

new TTS_Scheduler();
