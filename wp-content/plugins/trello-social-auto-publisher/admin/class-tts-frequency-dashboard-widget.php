<?php
/**
 * Dashboard widget for publishing frequency status overview.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Publishing frequency dashboard widget.
 */
class TTS_Frequency_Dashboard_Widget {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
        add_action( 'wp_ajax_tts_frequency_widget_refresh', array( $this, 'ajax_refresh_widget' ) );
    }

    /**
     * Register the dashboard widget.
     */
    public function register_widget() {
        if ( current_user_can( 'manage_options' ) ) {
            wp_add_dashboard_widget(
                'tts_frequency_status_widget',
                __( 'Publishing Frequency Status', 'trello-social-auto-publisher' ),
                array( $this, 'render_widget' ),
                array( $this, 'render_widget_config' )
            );
        }
    }

    /**
     * Render the widget content.
     */
    public function render_widget() {
        $monitor = new TTS_Frequency_Monitor();
        $clients = get_posts( array(
            'post_type' => 'tts_client',
            'post_status' => 'publish',
            'posts_per_page' => 5, // Limit to top 5 for dashboard
            'orderby' => 'title',
            'order' => 'ASC'
        ) );

        $urgent_count = 0;
        $warning_count = 0;
        $completed_count = 0;
        $total_clients = 0;

        ?>
        <div class="tts-frequency-widget">
            <?php if ( empty( $clients ) ) : ?>
                <p><?php esc_html_e( 'No clients with frequency settings found.', 'trello-social-auto-publisher' ); ?></p>
                <p><a href="<?php echo admin_url( 'edit.php?post_type=tts_client' ); ?>" class="button">
                    <?php esc_html_e( 'Configure Clients', 'trello-social-auto-publisher' ); ?>
                </a></p>
            <?php else : ?>
                <div class="tts-widget-summary">
                    <?php
                    foreach ( $clients as $client ) {
                        $status = $monitor->get_client_frequency_status( $client->ID );
                        if ( empty( $status ) ) {
                            continue;
                        }
                        
                        $total_clients++;
                        $client_urgent = false;
                        $client_warning = false;
                        $client_completed = true;

                        foreach ( $status as $channel_status ) {
                            if ( $channel_status['status'] === 'overdue' || $channel_status['status'] === 'urgent' ) {
                                $client_urgent = true;
                                $client_completed = false;
                            } elseif ( $channel_status['status'] === 'warning' ) {
                                $client_warning = true;
                                $client_completed = false;
                            } elseif ( $channel_status['status'] !== 'completed' ) {
                                $client_completed = false;
                            }
                        }

                        if ( $client_urgent ) {
                            $urgent_count++;
                        } elseif ( $client_warning ) {
                            $warning_count++;
                        } elseif ( $client_completed ) {
                            $completed_count++;
                        }
                    }
                    ?>
                    
                    <div class="tts-summary-stats">
                        <div class="tts-stat urgent">
                            <span class="count"><?php echo esc_html( $urgent_count ); ?></span>
                            <span class="label"><?php esc_html_e( 'Urgent', 'trello-social-auto-publisher' ); ?></span>
                        </div>
                        <div class="tts-stat warning">
                            <span class="count"><?php echo esc_html( $warning_count ); ?></span>
                            <span class="label"><?php esc_html_e( 'Warning', 'trello-social-auto-publisher' ); ?></span>
                        </div>
                        <div class="tts-stat completed">
                            <span class="count"><?php echo esc_html( $completed_count ); ?></span>
                            <span class="label"><?php esc_html_e( 'On Track', 'trello-social-auto-publisher' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="tts-widget-clients">
                    <?php
                    $displayed = 0;
                    foreach ( $clients as $client ) {
                        if ( $displayed >= 3 ) break; // Show max 3 clients in widget
                        
                        $status = $monitor->get_client_frequency_status( $client->ID );
                        if ( empty( $status ) ) {
                            continue;
                        }
                        
                        $displayed++;
                        $this->render_client_summary( $client, $status );
                    }
                    ?>
                </div>

                <div class="tts-widget-actions">
                    <a href="<?php echo admin_url( 'admin.php?page=tts-frequency-status' ); ?>" class="button button-primary">
                        <?php esc_html_e( 'View Full Status', 'trello-social-auto-publisher' ); ?>
                    </a>
                    <button type="button" class="button button-secondary" id="tts-widget-refresh">
                        <?php esc_html_e( 'Refresh', 'trello-social-auto-publisher' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <style>
        .tts-frequency-widget .tts-summary-stats {
            display: flex;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .tts-stat {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .tts-stat.urgent {
            background: #fef2f2;
            color: #b91c1c;
        }
        
        .tts-stat.warning {
            background: #fefbf2;
            color: #92400e;
        }
        
        .tts-stat.completed {
            background: #f0fdf4;
            color: #166534;
        }
        
        .tts-stat .count {
            display: block;
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
        }
        
        .tts-stat .label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .tts-client-summary {
            margin-bottom: 10px;
            padding: 8px;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
            font-size: 12px;
        }
        
        .tts-client-summary.has-urgent {
            border-left-color: #dc2626;
        }
        
        .tts-client-summary.has-warning {
            border-left-color: #d97706;
        }
        
        .tts-client-summary.completed {
            border-left-color: #059669;
        }
        
        .tts-client-summary h4 {
            margin: 0 0 5px 0;
            font-size: 13px;
        }
        
        .tts-channel-status {
            display: inline-block;
            margin-right: 8px;
            margin-bottom: 2px;
        }
        
        .tts-widget-actions {
            margin-top: 15px;
            text-align: center;
        }
        
        .tts-widget-actions .button {
            margin: 0 5px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#tts-widget-refresh').on('click', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Refreshing...');
                
                $.post(ajaxurl, {
                    action: 'tts_frequency_widget_refresh',
                    nonce: '<?php echo wp_create_nonce( 'tts_frequency_widget' ); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload(); // Simple refresh for widget
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('Refresh');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render client summary for widget.
     *
     * @param WP_Post $client Client post.
     * @param array   $status Status data.
     */
    private function render_client_summary( $client, $status ) {
        $has_urgent = false;
        $has_warning = false;
        $all_completed = true;

        foreach ( $status as $channel_status ) {
            if ( $channel_status['status'] === 'overdue' || $channel_status['status'] === 'urgent' ) {
                $has_urgent = true;
                $all_completed = false;
            } elseif ( $channel_status['status'] === 'warning' ) {
                $has_warning = true;
                $all_completed = false;
            } elseif ( $channel_status['status'] !== 'completed' ) {
                $all_completed = false;
            }
        }

        $css_class = 'tts-client-summary';
        if ( $has_urgent ) {
            $css_class .= ' has-urgent';
        } elseif ( $has_warning ) {
            $css_class .= ' has-warning';
        } elseif ( $all_completed ) {
            $css_class .= ' completed';
        }
        ?>
        
        <div class="<?php echo esc_attr( $css_class ); ?>">
            <h4><?php echo esc_html( $client->post_title ); ?></h4>
            <div class="tts-channels">
                <?php foreach ( $status as $channel => $data ) : ?>
                    <span class="tts-channel-status">
                        <strong><?php echo esc_html( ucfirst( $channel ) ); ?>:</strong>
                        <?php echo esc_html( $data['published'] ); ?>/<?php echo esc_html( $data['target'] ); ?>
                        <?php if ( $data['remaining'] > 0 ) : ?>
                            (<?php echo esc_html( $data['remaining'] ); ?> <?php esc_html_e( 'left', 'trello-social-auto-publisher' ); ?>)
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Widget configuration (currently empty).
     */
    public function render_widget_config() {
        // No configuration needed for now
    }

    /**
     * AJAX handler for widget refresh.
     */
    public function ajax_refresh_widget() {
        check_ajax_referer( 'tts_frequency_widget', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        // Trigger a quick frequency check
        $monitor = new TTS_Frequency_Monitor();
        $monitor->check_all_clients();

        wp_send_json_success();
    }
}

new TTS_Frequency_Dashboard_Widget();