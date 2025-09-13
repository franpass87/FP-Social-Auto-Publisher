<?php
/**
 * Notification handler for Trello Social Auto Publisher.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides methods to send notifications via Slack and email.
 */
class TTS_Notifier {

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'tts_post_approved', array( __CLASS__, 'notify_post_approved' ) );
    }

    /**
     * Send a notification when a post is approved.
     *
     * @param int $post_id Post ID.
     */
    public static function notify_post_approved( $post_id ) {
        $title   = get_the_title( $post_id );
        $message = sprintf( __( 'Post "%s" approvato', 'fp-publisher' ), $title );

        $notifier = new self();
        $notifier->notify_slack( $message );
        $notifier->notify_email( __( 'Post approvato', 'fp-publisher' ), $message );
    }

    /**
     * Send a message to Slack using the configured webhook.
     *
     * @param string $message Message to send.
     */
    public function notify_slack( $message ) {
        $options = get_option( 'tts_settings', array() );
        $webhook = isset( $options['slack_webhook'] ) ? esc_url_raw( $options['slack_webhook'] ) : '';
        if ( empty( $webhook ) ) {
            return;
        }

        wp_remote_post(
            $webhook,
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array( 'text' => $message ) ),
                'timeout' => 20,
            )
        );
    }

    /**
     * Send an email notification to configured recipients.
     *
     * @param string $subject Email subject.
     * @param string $body    Email body.
     */
    public function notify_email( $subject, $body ) {
        $options = get_option( 'tts_settings', array() );
        $emails  = isset( $options['notification_emails'] ) ? $options['notification_emails'] : '';
        if ( empty( $emails ) ) {
            return;
        }

        $recipients = array_map( 'sanitize_email', array_map( 'trim', explode( ',', $emails ) ) );
        $recipients = array_filter( $recipients );
        if ( empty( $recipients ) ) {
            return;
        }

        wp_mail( $recipients, $subject, $body );
    }
}

TTS_Notifier::init();
