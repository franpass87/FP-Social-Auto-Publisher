<?php
/**
 * Admin page to display analytics charts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Analytics page controller.
 */
class TTS_Analytics_Page {

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the analytics menu.
     */
    public function register_menu() {
        add_submenu_page(
            'tts-main',
            __( 'Analytics', 'trello-social-auto-publisher' ),
            __( 'Analytics', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-analytics',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue Chart.js and custom scripts.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'social-auto-publisher_page_tts-analytics' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'css/tts-analytics.css',
            array(),
            '1.0'
        );

        wp_enqueue_script(
            'chart.js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '4.4.0',
            true
        );

        wp_enqueue_script(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'js/tts-analytics.js',
            array( 'chart.js' ),
            '1.0',
            true
        );
    }

    /**
     * Render the analytics page and output filters & chart container.
     */
    public function render_page() {
        $channel = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';
        $start   = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
        $end     = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';

        $data = $this->get_metrics_data( $channel, $start, $end );

        if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] ) {
            $this->export_csv( $data );
            exit;
        }

        wp_localize_script(
            'tts-analytics',
            'ttsAnalytics',
            array(
                'data' => $data,
            )
        );

        $channels = $this->get_available_channels();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Analytics', 'trello-social-auto-publisher' ) . '</h1>';
        
        // Summary stats
        $this->render_analytics_summary($data);
        
        echo '<div class="tts-analytics-content">';
        echo '<div class="tts-analytics-filters-section">';
        echo '<form method="get" class="tts-analytics-filters">';
        echo '<input type="hidden" name="page" value="tts-analytics" />';

        echo '<div class="filter-group">';
        echo '<label for="channel">' . esc_html__( 'Channel', 'trello-social-auto-publisher' ) . '</label>';
        echo '<select name="channel" id="channel">';
        echo '<option value="">' . esc_html__( 'All Channels', 'trello-social-auto-publisher' ) . '</option>';
        foreach ( $channels as $ch ) {
            printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $ch ), selected( $ch, $channel, false ) );
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="filter-group">';
        echo '<label for="start">' . esc_html__( 'From', 'trello-social-auto-publisher' ) . '</label>';
        printf( '<input type="date" name="start" id="start" value="%s" />', esc_attr( $start ) );
        echo '</div>';
        
        echo '<div class="filter-group">';
        echo '<label for="end">' . esc_html__( 'To', 'trello-social-auto-publisher' ) . '</label>';
        printf( '<input type="date" name="end" id="end" value="%s" />', esc_attr( $end ) );
        echo '</div>';

        echo '<div class="filter-actions">';
        submit_button( __( 'Filter', 'trello-social-auto-publisher' ), 'primary', '', false );

        $export_url = add_query_arg( array_merge( $_GET, array( 'export' => 'csv' ) ) );
        echo ' <a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV', 'trello-social-auto-publisher' ) . '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
        
        echo '<div class="tts-chart-container">';
        echo '<canvas id="tts-analytics-chart"></canvas>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render analytics summary section
     */
    private function render_analytics_summary($data) {
        $total_interactions = 0;
        $channel_totals = array();
        $date_range = array();
        
        foreach ($data as $date => $channels) {
            $date_range[] = $date;
            foreach ($channels as $channel => $interactions) {
                $total_interactions += $interactions;
                if (!isset($channel_totals[$channel])) {
                    $channel_totals[$channel] = 0;
                }
                $channel_totals[$channel] += $interactions;
            }
        }
        
        arsort($channel_totals);
        $top_channel = !empty($channel_totals) ? array_key_first($channel_totals) : null;
        $date_range_text = '';
        if (!empty($date_range)) {
            sort($date_range);
            $date_range_text = count($date_range) > 1 ? 
                sprintf('%s - %s', reset($date_range), end($date_range)) : 
                reset($date_range);
        }

        echo '<div class="tts-analytics-summary">';
        echo '<div class="tts-summary-cards">';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Total Interactions', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-summary-number">' . number_format($total_interactions) . '</span>';
        echo '</div>';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Active Channels', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-summary-number">' . count($channel_totals) . '</span>';
        echo '</div>';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Top Channel', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-summary-text">' . ($top_channel ? esc_html($top_channel) : esc_html__('N/A', 'trello-social-auto-publisher')) . '</span>';
        echo '</div>';
        
        echo '<div class="tts-summary-card">';
        echo '<h3>' . esc_html__('Date Range', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-summary-text">' . ($date_range_text ? esc_html($date_range_text) : esc_html__('No data', 'trello-social-auto-publisher')) . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Retrieve unique available channels.
     *
     * @return array
     */
    private function get_available_channels() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );
        $channels = array();
        foreach ( $posts as $post_id ) {
            $ch = get_post_meta( $post_id, '_tts_social_channel', true );
            if ( is_array( $ch ) ) {
                $channels = array_merge( $channels, $ch );
            } elseif ( $ch ) {
                $channels[] = $ch;
            }
        }
        return array_unique( $channels );
    }

    /**
     * Get metrics data filtered by channel and date range.
     *
     * @param string $channel Channel filter.
     * @param string $start   Start date (Y-m-d).
     * @param string $end     End date (Y-m-d).
     *
     * @return array
     */
    private function get_metrics_data( $channel, $start, $end ) {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'ASC',
            )
        );
        $data = array();

        foreach ( $posts as $post ) {
            $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );
            $date       = $publish_at ? substr( $publish_at, 0, 10 ) : get_the_date( 'Y-m-d', $post );

            if ( $start && strtotime( $date ) < strtotime( $start ) ) {
                continue;
            }
            if ( $end && strtotime( $date ) > strtotime( $end ) ) {
                continue;
            }

            $channels = get_post_meta( $post->ID, '_tts_social_channel', true );
            $channels = is_array( $channels ) ? $channels : array( $channels );
            if ( $channel && ! in_array( $channel, $channels, true ) ) {
                continue;
            }

            $metrics = get_post_meta( $post->ID, '_tts_metrics', true );
            if ( ! is_array( $metrics ) ) {
                continue;
            }

            foreach ( $metrics as $ch => $values ) {
                if ( $channel && $ch !== $channel ) {
                    continue;
                }
                $sum = $this->count_interactions( $values );
                if ( ! isset( $data[ $date ][ $ch ] ) ) {
                    $data[ $date ][ $ch ] = 0;
                }
                $data[ $date ][ $ch ] += $sum;
            }
        }

        ksort( $data );
        return $data;
    }

    /**
     * Recursively count numeric interactions in metrics array.
     *
     * @param array $data Metrics array.
     * @return int
     */
    private function count_interactions( $data ) {
        $sum = 0;
        foreach ( (array) $data as $value ) {
            if ( is_array( $value ) ) {
                $sum += $this->count_interactions( $value );
            } elseif ( is_numeric( $value ) ) {
                $sum += (int) $value;
            }
        }
        return $sum;
    }

    /**
     * Export data to CSV.
     *
     * @param array $data Metrics data.
     */
    private function export_csv( $data ) {
        nocache_headers();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="tts-analytics.csv"' );
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'date', 'channel', 'interactions' ) );
        foreach ( $data as $date => $channels ) {
            foreach ( $channels as $ch => $count ) {
                fputcsv( $output, array( $date, $ch, $count ) );
            }
        }
        fclose( $output );
    }
}

new TTS_Analytics_Page();
