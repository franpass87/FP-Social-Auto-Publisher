<?php
/**
 * Publishing frequency monitoring and alert system for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Monitors publishing frequencies and sends alerts when needed.
 */
class TTS_Frequency_Monitor {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'schedule_frequency_check' ) );
        add_action( 'tts_check_publishing_frequencies', array( $this, 'check_all_clients' ) );
        add_action( 'tts_publish_social_post', array( $this, 'record_publication' ), 20 );
    }

    /**
     * Schedule the daily frequency check if not already scheduled.
     */
    public function schedule_frequency_check() {
        if ( ! wp_next_scheduled( 'tts_check_publishing_frequencies' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_check_publishing_frequencies' );
        }
    }

    /**
     * Check all clients for publishing frequency compliance.
     */
    public function check_all_clients() {
        $clients = get_posts( array(
            'post_type' => 'tts_client',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ) );

        foreach ( $clients as $client_id ) {
            $this->check_client_frequency( $client_id );
        }
    }

    /**
     * Check publishing frequency for a specific client.
     *
     * @param int $client_id Client post ID.
     */
    public function check_client_frequency( $client_id ) {
        $frequency_settings = get_post_meta( $client_id, '_tts_publishing_frequency', true );
        $alert_days_ahead = get_post_meta( $client_id, '_tts_alert_days_ahead', true );
        
        if ( ! is_array( $frequency_settings ) || empty( $frequency_settings ) ) {
            return; // No frequency settings configured
        }

        if ( ! $alert_days_ahead ) {
            $alert_days_ahead = 3; // Default to 3 days ahead
        }

        $client_title = get_the_title( $client_id );
        $notifier = new TTS_Notifier();
        $alerts = array();

        foreach ( $frequency_settings as $channel => $freq_data ) {
            $count = $freq_data['count'];
            $period = $freq_data['period'];

            if ( $count <= 0 ) {
                continue; // Skip disabled channels
            }

            // Calculate the time frame for checking
            $period_start = $this->get_period_start( $period );
            $period_end = time();
            $next_period_start = $this->get_next_period_start( $period );

            // Count published posts in current period
            $published_count = $this->count_published_posts( $client_id, $channel, $period_start, $period_end );
            
            // Calculate remaining publications needed
            $remaining_needed = $count - $published_count;

            if ( $remaining_needed > 0 ) {
                // Calculate days remaining in period
                $days_remaining = ceil( ( $next_period_start - time() ) / DAY_IN_SECONDS );
                
                // Check if we're behind schedule
                if ( $days_remaining <= 0 ) {
                    // Period has ended and we're behind
                    $alerts[] = array(
                        'type' => 'overdue',
                        'channel' => $channel,
                        'message' => sprintf(
                            __( 'OVERDUE: %1$s needs %2$d more %3$s posts for the current %4$s period (target: %5$d)', 'trello-social-auto-publisher' ),
                            $client_title,
                            $remaining_needed,
                            ucfirst( $channel ),
                            $period,
                            $count
                        )
                    );
                } elseif ( $days_remaining <= $alert_days_ahead ) {
                    // We're approaching the deadline
                    $alerts[] = array(
                        'type' => 'upcoming',
                        'channel' => $channel,
                        'message' => sprintf(
                            __( 'CONTENT NEEDED: %1$s needs %2$d more %3$s posts in the next %4$d days (target: %5$d per %6$s)', 'trello-social-auto-publisher' ),
                            $client_title,
                            $remaining_needed,
                            ucfirst( $channel ),
                            $days_remaining,
                            $count,
                            $period
                        )
                    );
                }
            }
        }

        // Send alerts if any were found
        if ( ! empty( $alerts ) ) {
            $this->send_frequency_alerts( $client_id, $alerts );
        }
    }

    /**
     * Record a publication when a post is published.
     *
     * @param array $args Action Scheduler arguments from publish action.
     */
    public function record_publication( $args ) {
        $post_id = isset( $args['post_id'] ) ? intval( $args['post_id'] ) : 0;
        $channel = isset( $args['channel'] ) ? sanitize_text_field( $args['channel'] ) : '';
        
        if ( ! $post_id ) {
            return;
        }

        $client_id = get_post_meta( $post_id, '_tts_client_id', true );
        if ( ! $client_id ) {
            return;
        }

        // If no specific channel, get all channels from the post
        if ( ! $channel ) {
            $channels = get_post_meta( $post_id, '_tts_social_channel', true );
            if ( ! is_array( $channels ) ) {
                $channels = array( $channels );
            }
        } else {
            $channels = array( $channel );
        }

        // Record publication for each channel
        foreach ( $channels as $ch ) {
            if ( empty( $ch ) ) {
                continue;
            }
            
            $publication_history = get_post_meta( $client_id, '_tts_publication_history', true );
            if ( ! is_array( $publication_history ) ) {
                $publication_history = array();
            }

            $publication_history[] = array(
                'post_id' => $post_id,
                'channel' => $ch,
                'published_at' => time(),
                'date' => current_time( 'Y-m-d H:i:s' )
            );

            // Keep only last 1000 records to prevent unlimited growth
            if ( count( $publication_history ) > 1000 ) {
                $publication_history = array_slice( $publication_history, -1000 );
            }

            update_post_meta( $client_id, '_tts_publication_history', $publication_history );
        }
    }

    /**
     * Count published posts for a client/channel in a given time period.
     *
     * @param int    $client_id   Client ID.
     * @param string $channel     Channel name.
     * @param int    $start_time  Period start timestamp.
     * @param int    $end_time    Period end timestamp.
     * @return int Number of published posts.
     */
    private function count_published_posts( $client_id, $channel, $start_time, $end_time ) {
        $publication_history = get_post_meta( $client_id, '_tts_publication_history', true );
        if ( ! is_array( $publication_history ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $publication_history as $record ) {
            if ( isset( $record['channel'], $record['published_at'] ) 
                && $record['channel'] === $channel 
                && $record['published_at'] >= $start_time 
                && $record['published_at'] <= $end_time ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the start timestamp for the current period.
     *
     * @param string $period Period type (daily, weekly, monthly).
     * @return int Start timestamp.
     */
    private function get_period_start( $period ) {
        $current_time = current_time( 'timestamp' );
        
        switch ( $period ) {
            case 'daily':
                return strtotime( 'today', $current_time );
            
            case 'weekly':
                // Start of this week (Monday)
                return strtotime( 'monday this week', $current_time );
            
            case 'monthly':
                // Start of this month
                return strtotime( 'first day of this month', $current_time );
            
            default:
                return strtotime( 'monday this week', $current_time );
        }
    }

    /**
     * Get the start timestamp for the next period.
     *
     * @param string $period Period type (daily, weekly, monthly).
     * @return int Next period start timestamp.
     */
    private function get_next_period_start( $period ) {
        $current_time = current_time( 'timestamp' );
        
        switch ( $period ) {
            case 'daily':
                return strtotime( 'tomorrow', $current_time );
            
            case 'weekly':
                // Start of next week (Monday)
                return strtotime( 'monday next week', $current_time );
            
            case 'monthly':
                // Start of next month
                return strtotime( 'first day of next month', $current_time );
            
            default:
                return strtotime( 'monday next week', $current_time );
        }
    }

    /**
     * Send frequency alerts for a client.
     *
     * @param int   $client_id Client ID.
     * @param array $alerts    Array of alert data.
     */
    private function send_frequency_alerts( $client_id, $alerts ) {
        $client_title = get_the_title( $client_id );
        $notifier = new TTS_Notifier();

        foreach ( $alerts as $alert ) {
            $message = $alert['message'];
            
            // Log the alert
            tts_log_event( 0, 'frequency_monitor', $alert['type'], $message, array( 'client_id' => $client_id ) );
            
            // Send notifications
            $notifier->notify_slack( $message );
            
            $subject = $alert['type'] === 'overdue' 
                ? __( 'Publishing Schedule Overdue', 'trello-social-auto-publisher' )
                : __( 'Content Needed Soon', 'trello-social-auto-publisher' );
                
            $notifier->notify_email( $subject, $message );
        }
    }

    /**
     * Get frequency status for a client.
     *
     * @param int $client_id Client ID.
     * @return array Status information for all channels.
     */
    public function get_client_frequency_status( $client_id ) {
        $frequency_settings = get_post_meta( $client_id, '_tts_publishing_frequency', true );
        
        if ( ! is_array( $frequency_settings ) || empty( $frequency_settings ) ) {
            return array();
        }

        $status = array();

        foreach ( $frequency_settings as $channel => $freq_data ) {
            $count = $freq_data['count'];
            $period = $freq_data['period'];

            if ( $count <= 0 ) {
                continue;
            }

            $period_start = $this->get_period_start( $period );
            $period_end = time();
            $next_period_start = $this->get_next_period_start( $period );

            $published_count = $this->count_published_posts( $client_id, $channel, $period_start, $period_end );
            $remaining_needed = $count - $published_count;
            $days_remaining = ceil( ( $next_period_start - time() ) / DAY_IN_SECONDS );

            $status[ $channel ] = array(
                'target' => $count,
                'period' => $period,
                'published' => $published_count,
                'remaining' => max( 0, $remaining_needed ),
                'days_remaining' => max( 0, $days_remaining ),
                'status' => $this->get_status_label( $remaining_needed, $days_remaining ),
                'percentage' => $count > 0 ? round( ( $published_count / $count ) * 100 ) : 0
            );
        }

        return $status;
    }

    /**
     * Get status label based on remaining posts and days.
     *
     * @param int $remaining_needed Posts still needed.
     * @param int $days_remaining   Days left in period.
     * @return string Status label.
     */
    private function get_status_label( $remaining_needed, $days_remaining ) {
        if ( $remaining_needed <= 0 ) {
            return 'completed';
        } elseif ( $days_remaining <= 0 ) {
            return 'overdue';
        } elseif ( $days_remaining <= 2 ) {
            return 'urgent';
        } elseif ( $days_remaining <= 5 ) {
            return 'warning';
        } else {
            return 'on_track';
        }
    }
}

new TTS_Frequency_Monitor();