<?php
/**
 * Publishing frequency status page for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Publishing frequency status admin page.
 */
class TTS_Frequency_Status_Page {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_tts_refresh_frequency_status', array( $this, 'ajax_refresh_status' ) );
        add_action( 'wp_ajax_tts_check_all_frequencies', array( $this, 'ajax_check_all_frequencies' ) );
    }

    /**
     * Add the menu page.
     */
    public function add_menu_page() {
        add_submenu_page(
            'tts-main',
            __( 'Publishing Status', 'trello-social-auto-publisher' ),
            __( 'Publishing Status', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-frequency-status',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue page assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'social-auto-publisher_page_tts-frequency-status' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tts-frequency-status',
            plugin_dir_url( __FILE__ ) . '../admin/css/tts-frequency-status.css',
            array(),
            '1.0'
        );

        wp_enqueue_script(
            'tts-frequency-status',
            plugin_dir_url( __FILE__ ) . '../admin/js/tts-frequency-status.js',
            array( 'jquery' ),
            '1.0',
            true
        );

        wp_localize_script(
            'tts-frequency-status',
            'ttsFrequencyStatus',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_frequency_status' ),
                'strings' => array(
                    'refreshing' => __( 'Refreshing...', 'trello-social-auto-publisher' ),
                    'error'      => __( 'Error refreshing status', 'trello-social-auto-publisher' ),
                )
            )
        );
    }

    /**
     * Render the page content.
     */
    public function render_page() {
        $monitor = new TTS_Frequency_Monitor();
        $clients = get_posts( array(
            'post_type' => 'tts_client',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Publishing Frequency Status', 'trello-social-auto-publisher' ); ?></h1>
            
            <div class="tts-frequency-header">
                <p><?php esc_html_e( 'Monitor publishing frequency compliance for all clients. This page shows progress towards publishing goals and alerts for upcoming content needs.', 'trello-social-auto-publisher' ); ?></p>
                
                <div class="tts-frequency-actions">
                    <button type="button" id="tts-refresh-status" class="button button-secondary">
                        <?php esc_html_e( 'Refresh Status', 'trello-social-auto-publisher' ); ?>
                    </button>
                    <button type="button" id="tts-check-now" class="button button-primary">
                        <?php esc_html_e( 'Check All Clients Now', 'trello-social-auto-publisher' ); ?>
                    </button>
                </div>
            </div>

            <div id="tts-frequency-status-container">
                <?php $this->render_status_tables( $clients, $monitor ); ?>
            </div>

            <div class="tts-frequency-legend">
                <h3><?php esc_html_e( 'Status Legend', 'trello-social-auto-publisher' ); ?></h3>
                <div class="tts-legend-items">
                    <span class="tts-status-badge status-completed"><?php esc_html_e( 'Completed', 'trello-social-auto-publisher' ); ?></span>
                    <span class="tts-status-badge status-on_track"><?php esc_html_e( 'On Track', 'trello-social-auto-publisher' ); ?></span>
                    <span class="tts-status-badge status-warning"><?php esc_html_e( 'Warning (≤5 days)', 'trello-social-auto-publisher' ); ?></span>
                    <span class="tts-status-badge status-urgent"><?php esc_html_e( 'Urgent (≤2 days)', 'trello-social-auto-publisher' ); ?></span>
                    <span class="tts-status-badge status-overdue"><?php esc_html_e( 'Overdue', 'trello-social-auto-publisher' ); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render status tables for all clients.
     *
     * @param array                 $clients All client posts.
     * @param TTS_Frequency_Monitor $monitor Monitor instance.
     */
    private function render_status_tables( $clients, $monitor ) {
        if ( empty( $clients ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No clients found.', 'trello-social-auto-publisher' ) . '</p></div>';
            return;
        }

        foreach ( $clients as $client ) {
            $status = $monitor->get_client_frequency_status( $client->ID );
            
            if ( empty( $status ) ) {
                continue; // Skip clients without frequency settings
            }

            $this->render_client_status( $client, $status );
        }
    }

    /**
     * Render status for a single client.
     *
     * @param WP_Post $client Client post object.
     * @param array   $status Status data.
     */
    private function render_client_status( $client, $status ) {
        ?>
        <div class="tts-client-status-card">
            <h2><?php echo esc_html( $client->post_title ); ?></h2>
            
            <div class="tts-client-status-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Channel', 'trello-social-auto-publisher' ); ?></th>
                            <th><?php esc_html_e( 'Target', 'trello-social-auto-publisher' ); ?></th>
                            <th><?php esc_html_e( 'Published', 'trello-social-auto-publisher' ); ?></th>
                            <th><?php esc_html_e( 'Remaining', 'trello-social-auto-publisher' ); ?></th>
                            <th><?php esc_html_e( 'Days Left', 'trello-social-auto-publisher' ); ?></th>
                            <th><?php esc_html_e( 'Progress', 'trello-social-auto-publisher' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'trello-social-auto-publisher' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $status as $channel => $data ) : ?>
                            <tr class="tts-channel-row status-<?php echo esc_attr( $data['status'] ); ?>">
                                <td><strong><?php echo esc_html( ucfirst( $channel ) ); ?></strong></td>
                                <td>
                                    <?php echo esc_html( $data['target'] ); ?> / 
                                    <?php echo esc_html( $data['period'] ); ?>
                                </td>
                                <td><?php echo esc_html( $data['published'] ); ?></td>
                                <td><?php echo esc_html( $data['remaining'] ); ?></td>
                                <td><?php echo esc_html( $data['days_remaining'] ); ?></td>
                                <td>
                                    <div class="tts-progress-bar">
                                        <div class="tts-progress-fill" style="width: <?php echo esc_attr( $data['percentage'] ); ?>%"></div>
                                        <span class="tts-progress-text"><?php echo esc_html( $data['percentage'] ); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="tts-status-badge status-<?php echo esc_attr( $data['status'] ); ?>">
                                        <?php echo esc_html( $this->get_status_label( $data['status'] ) ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get human readable status label.
     *
     * @param string $status Status key.
     * @return string Human readable label.
     */
    private function get_status_label( $status ) {
        $labels = array(
            'completed' => __( 'Completed', 'trello-social-auto-publisher' ),
            'on_track'  => __( 'On Track', 'trello-social-auto-publisher' ),
            'warning'   => __( 'Warning', 'trello-social-auto-publisher' ),
            'urgent'    => __( 'Urgent', 'trello-social-auto-publisher' ),
            'overdue'   => __( 'Overdue', 'trello-social-auto-publisher' ),
        );

        return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
    }

    /**
     * AJAX handler to refresh status.
     */
    public function ajax_refresh_status() {
        check_ajax_referer( 'tts_frequency_status', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $monitor = new TTS_Frequency_Monitor();
        $clients = get_posts( array(
            'post_type' => 'tts_client',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ) );

        ob_start();
        $this->render_status_tables( $clients, $monitor );
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX handler to manually check all clients now.
     */
    public function ajax_check_all_frequencies() {
        check_ajax_referer( 'tts_frequency_status', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $monitor = new TTS_Frequency_Monitor();
        $monitor->check_all_clients();

        wp_send_json_success( array( 'message' => __( 'Frequency check completed', 'trello-social-auto-publisher' ) ) );
    }
}

new TTS_Frequency_Status_Page();