<?php
/**
 * Admin page displaying plugin health status.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Health status page controller.
 */
class TTS_Health_Page {

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Register the health status menu page.
     */
    public function register_menu() {
        add_submenu_page(
            'tts-main',
            __( 'Stato', 'trello-social-auto-publisher' ),
            __( 'Stato', 'trello-social-auto-publisher' ),
            'manage_tts_posts',
            'tts-health',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the health status page.
     */
    public function render_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Stato', 'trello-social-auto-publisher' ) . '</h1>';

        // Token checks.
        echo '<h2>' . esc_html__( 'Token', 'trello-social-auto-publisher' ) . '</h2>';
        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );
        if ( $clients ) {
            echo '<ul>';
            foreach ( $clients as $client_id ) {
                $result = TTS_Client::check_token( $client_id );
                $title  = get_the_title( $client_id );
                if ( is_wp_error( $result ) || ! $result ) {
                    $message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Errore', 'trello-social-auto-publisher' );
                    printf( '<li style="color:red;">%1$s: %2$s</li>', esc_html( $title ), esc_html( $message ) );
                } else {
                    printf( '<li style="color:green;">%1$s: %2$s</li>', esc_html( $title ), esc_html__( 'OK', 'trello-social-auto-publisher' ) );
                }
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'Nessun client configurato.', 'trello-social-auto-publisher' ) . '</p>';
        }

        // Trello webhook check.
        echo '<h2>' . esc_html__( 'Webhook Trello', 'trello-social-auto-publisher' ) . '</h2>';
        $webhook = TTS_Webhook::check_connection();
        if ( is_wp_error( $webhook ) || ! $webhook ) {
            $message = is_wp_error( $webhook ) ? $webhook->get_error_message() : __( 'Errore', 'trello-social-auto-publisher' );
            echo '<p style="color:red;">' . esc_html( $message ) . '</p>';
        } else {
            echo '<p style="color:green;">' . esc_html__( 'OK', 'trello-social-auto-publisher' ) . '</p>';
        }

        // Action Scheduler queue check.
        echo '<h2>' . esc_html__( 'Action Scheduler', 'trello-social-auto-publisher' ) . '</h2>';
        if ( is_callable( array( 'TTS_Scheduler', 'check_queue' ) ) ) {
            $queue = TTS_Scheduler::check_queue();
        } else {
            $queue = new WP_Error( 'missing_method', __( 'Metodo check_queue non disponibile.', 'trello-social-auto-publisher' ) );
        }
        if ( is_wp_error( $queue ) || ! $queue ) {
            $message = is_wp_error( $queue ) ? $queue->get_error_message() : __( 'Errore', 'trello-social-auto-publisher' );
            echo '<p style="color:red;">' . esc_html( $message ) . '</p>';
        } else {
            echo '<p style="color:green;">' . esc_html( $queue ) . '</p>';
        }

        echo '</div>';
    }
}
