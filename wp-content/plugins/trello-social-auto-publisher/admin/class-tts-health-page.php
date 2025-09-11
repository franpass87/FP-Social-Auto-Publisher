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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Enqueue health page assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'social-auto-publisher_page_tts-health' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tts-health',
            plugin_dir_url( __FILE__ ) . 'css/tts-health.css',
            array(),
            '1.0'
        );
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
        echo '<h1>' . esc_html__( 'Stato Sistema', 'trello-social-auto-publisher' ) . '</h1>';

        // Overall health status
        echo '<div class="tts-health-overview">';
        echo '<h2>' . esc_html__( 'Stato Generale', 'trello-social-auto-publisher' ) . '</h2>';
        $this->render_general_health();
        echo '</div>';

        // Token checks.
        echo '<div class="tts-health-section">';
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
            echo '<div class="tts-health-grid">';
            foreach ( $clients as $client_id ) {
                $result = TTS_Client::check_token( $client_id );
                $title  = get_the_title( $client_id );
                echo '<div class="tts-health-card">';
                echo '<h3>' . esc_html( $title ) . '</h3>';
                if ( is_wp_error( $result ) || ! $result ) {
                    $message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Errore', 'trello-social-auto-publisher' );
                    echo '<div class="tts-status-error"><span class="dashicons dashicons-warning"></span>' . esc_html( $message ) . '</div>';
                } else {
                    echo '<div class="tts-status-ok"><span class="dashicons dashicons-yes"></span>' . esc_html__( 'Token valido', 'trello-social-auto-publisher' ) . '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Nessun client configurato.', 'trello-social-auto-publisher' ) . '</p>';
        }
        echo '</div>';

        // System checks
        echo '<div class="tts-health-section">';
        echo '<h2>' . esc_html__( 'Controlli Sistema', 'trello-social-auto-publisher' ) . '</h2>';
        echo '<div class="tts-health-grid">';
        
        // Trello webhook check.
        echo '<div class="tts-health-card">';
        echo '<h3>' . esc_html__( 'Webhook Trello', 'trello-social-auto-publisher' ) . '</h3>';
        $webhook = TTS_Webhook::check_connection();
        if ( is_wp_error( $webhook ) || ! $webhook ) {
            $message = is_wp_error( $webhook ) ? $webhook->get_error_message() : __( 'Errore', 'trello-social-auto-publisher' );
            echo '<div class="tts-status-error"><span class="dashicons dashicons-warning"></span>' . esc_html( $message ) . '</div>';
        } else {
            echo '<div class="tts-status-ok"><span class="dashicons dashicons-yes"></span>' . esc_html__( 'Connessione attiva', 'trello-social-auto-publisher' ) . '</div>';
        }
        echo '</div>';

        // Action Scheduler queue check.
        echo '<div class="tts-health-card">';
        echo '<h3>' . esc_html__( 'Action Scheduler', 'trello-social-auto-publisher' ) . '</h3>';
        if ( is_callable( array( 'TTS_Scheduler', 'check_queue' ) ) ) {
            $queue = TTS_Scheduler::check_queue();
        } else {
            $queue = new WP_Error( 'missing_method', __( 'Metodo check_queue non disponibile.', 'trello-social-auto-publisher' ) );
        }
        if ( is_wp_error( $queue ) || ! $queue ) {
            $message = is_wp_error( $queue ) ? $queue->get_error_message() : __( 'Errore', 'trello-social-auto-publisher' );
            echo '<div class="tts-status-error"><span class="dashicons dashicons-warning"></span>' . esc_html( $message ) . '</div>';
        } else {
            echo '<div class="tts-status-ok"><span class="dashicons dashicons-yes"></span>' . esc_html( $queue ) . '</div>';
        }
        echo '</div>';

        // WordPress requirements
        echo '<div class="tts-health-card">';
        echo '<h3>' . esc_html__( 'Requisiti WordPress', 'trello-social-auto-publisher' ) . '</h3>';
        $this->render_wp_requirements();
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render general health overview
     */
    private function render_general_health() {
        $issues = 0;
        $total_checks = 0;
        
        // Count issues from all checks
        $clients = get_posts(array('post_type' => 'tts_client', 'posts_per_page' => -1, 'fields' => 'ids'));
        foreach ($clients as $client_id) {
            $total_checks++;
            $result = TTS_Client::check_token($client_id);
            if (is_wp_error($result) || !$result) $issues++;
        }
        
        $total_checks++; // Webhook check
        $webhook = TTS_Webhook::check_connection();
        if (is_wp_error($webhook) || !$webhook) $issues++;
        
        $total_checks++; // Scheduler check
        if (is_callable(array('TTS_Scheduler', 'check_queue'))) {
            $queue = TTS_Scheduler::check_queue();
            if (is_wp_error($queue) || !$queue) $issues++;
        } else {
            $issues++;
        }
        
        $health_percentage = $total_checks > 0 ? round((($total_checks - $issues) / $total_checks) * 100) : 100;
        
        echo '<div class="tts-health-meter">';
        echo '<div class="tts-health-circle">';
        echo '<span class="tts-health-percentage">' . $health_percentage . '%</span>';
        echo '</div>';
        echo '<div class="tts-health-description">';
        if ($issues === 0) {
            echo '<div class="tts-status-ok"><span class="dashicons dashicons-yes"></span>' . esc_html__('Tutti i sistemi funzionano correttamente', 'trello-social-auto-publisher') . '</div>';
        } else {
            echo '<div class="tts-status-warning"><span class="dashicons dashicons-warning"></span>' . sprintf(esc_html__('%d problemi rilevati su %d controlli', 'trello-social-auto-publisher'), $issues, $total_checks) . '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render WordPress requirements check
     */
    private function render_wp_requirements() {
        $requirements = array(
            array(
                'name' => __('WordPress Version', 'trello-social-auto-publisher'),
                'current' => get_bloginfo('version'),
                'required' => '5.0',
                'check' => version_compare(get_bloginfo('version'), '5.0', '>=')
            ),
            array(
                'name' => __('PHP Version', 'trello-social-auto-publisher'),
                'current' => PHP_VERSION,
                'required' => '7.4',
                'check' => version_compare(PHP_VERSION, '7.4', '>=')
            ),
            array(
                'name' => __('cURL Extension', 'trello-social-auto-publisher'),
                'current' => extension_loaded('curl') ? __('Enabled', 'trello-social-auto-publisher') : __('Disabled', 'trello-social-auto-publisher'),
                'required' => __('Required', 'trello-social-auto-publisher'),
                'check' => extension_loaded('curl')
            )
        );

        echo '<div class="tts-requirements-list">';
        foreach ($requirements as $req) {
            echo '<div class="tts-requirement-item">';
            echo '<span class="req-name">' . esc_html($req['name']) . '</span>';
            echo '<span class="req-current">' . esc_html($req['current']) . '</span>';
            if ($req['check']) {
                echo '<span class="dashicons dashicons-yes req-status-ok"></span>';
            } else {
                echo '<span class="dashicons dashicons-no req-status-error"></span>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
