<?php
/**
 * Advanced API Rate Limiting and Quota Management System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TTS_Rate_Limiter class for sophisticated API rate limiting and quota management
 */
class TTS_Rate_Limiter {

    private $cache_prefix = 'tts_rate_limit_';
    private $quota_prefix = 'tts_quota_';

    /**
     * Rate limits for different platforms (requests per window)
     */
    private $rate_limits = array(
        'facebook' => array(
            'requests_per_hour' => 200,
            'requests_per_day' => 5000,
            'burst_limit' => 50,
            'reset_window' => 3600
        ),
        'instagram' => array(
            'requests_per_hour' => 200,
            'requests_per_day' => 4800,
            'burst_limit' => 40,
            'reset_window' => 3600
        ),
        'youtube' => array(
            'requests_per_hour' => 100,
            'requests_per_day' => 10000,
            'burst_limit' => 25,
            'reset_window' => 3600
        ),
        'tiktok' => array(
            'requests_per_hour' => 100,
            'requests_per_day' => 1000,
            'burst_limit' => 20,
            'reset_window' => 3600
        )
    );

    /**
     * Initialize rate limiter
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_check_rate_limits', array( $this, 'ajax_check_rate_limits' ) );
        add_action( 'wp_ajax_tts_reset_rate_limits', array( $this, 'ajax_reset_rate_limits' ) );
        add_action( 'wp_ajax_tts_get_quota_status', array( $this, 'ajax_get_quota_status' ) );
        
        // Schedule hourly cleanup
        add_action( 'tts_hourly_rate_limit_cleanup', array( $this, 'cleanup_expired_limits' ) );
        if ( ! wp_next_scheduled( 'tts_hourly_rate_limit_cleanup' ) ) {
            wp_schedule_event( time(), 'hourly', 'tts_hourly_rate_limit_cleanup' );
        }
    }

    /**
     * Check if request is allowed for platform
     *
     * @param string $platform Platform name
     * @param string $endpoint Specific endpoint (optional)
     * @return array Result with allowed status and details
     */
    public function is_request_allowed( $platform, $endpoint = 'default' ) {
        $platform = strtolower( $platform );
        
        if ( ! isset( $this->rate_limits[ $platform ] ) ) {
            return array(
                'allowed' => true,
                'reason' => 'Platform not rate limited',
                'retry_after' => 0
            );
        }

        $limits = $this->rate_limits[ $platform ];
        $current_time = time();
        
        // Check hourly limit
        $hourly_key = $this->cache_prefix . $platform . '_hourly_' . floor( $current_time / 3600 );
        $hourly_count = get_transient( $hourly_key ) ?: 0;
        
        if ( $hourly_count >= $limits['requests_per_hour'] ) {
            $retry_after = 3600 - ( $current_time % 3600 );
            return array(
                'allowed' => false,
                'reason' => 'Hourly limit exceeded',
                'limit' => $limits['requests_per_hour'],
                'used' => $hourly_count,
                'retry_after' => $retry_after,
                'reset_time' => $current_time + $retry_after
            );
        }
        
        // Check daily limit
        $daily_key = $this->cache_prefix . $platform . '_daily_' . date( 'Y-m-d' );
        $daily_count = get_transient( $daily_key ) ?: 0;
        
        if ( $daily_count >= $limits['requests_per_day'] ) {
            $retry_after = strtotime( 'tomorrow' ) - $current_time;
            return array(
                'allowed' => false,
                'reason' => 'Daily limit exceeded',
                'limit' => $limits['requests_per_day'],
                'used' => $daily_count,
                'retry_after' => $retry_after,
                'reset_time' => strtotime( 'tomorrow' )
            );
        }
        
        // Check burst limit (requests in last 5 minutes)
        $burst_key = $this->cache_prefix . $platform . '_burst_' . floor( $current_time / 300 );
        $burst_count = get_transient( $burst_key ) ?: 0;
        
        if ( $burst_count >= $limits['burst_limit'] ) {
            $retry_after = 300 - ( $current_time % 300 );
            return array(
                'allowed' => false,
                'reason' => 'Burst limit exceeded',
                'limit' => $limits['burst_limit'],
                'used' => $burst_count,
                'retry_after' => $retry_after,
                'reset_time' => $current_time + $retry_after
            );
        }

        return array(
            'allowed' => true,
            'hourly_used' => $hourly_count,
            'hourly_limit' => $limits['requests_per_hour'],
            'daily_used' => $daily_count,
            'daily_limit' => $limits['requests_per_day'],
            'burst_used' => $burst_count,
            'burst_limit' => $limits['burst_limit']
        );
    }

    /**
     * Record API request
     *
     * @param string $platform Platform name
     * @param string $endpoint Endpoint name
     * @param array $response_data Response data from API
     */
    public function record_request( $platform, $endpoint = 'default', $response_data = array() ) {
        $platform = strtolower( $platform );
        $current_time = time();
        
        // Update counters
        $this->increment_counter( $platform . '_hourly_' . floor( $current_time / 3600 ), 3600 );
        $this->increment_counter( $platform . '_daily_' . date( 'Y-m-d' ), DAY_IN_SECONDS );
        $this->increment_counter( $platform . '_burst_' . floor( $current_time / 300 ), 300 );
        
        // Record request details
        $request_log = array(
            'platform' => $platform,
            'endpoint' => $endpoint,
            'timestamp' => $current_time,
            'response_time' => $response_data['response_time'] ?? 0,
            'status_code' => $response_data['status_code'] ?? 200,
            'error' => $response_data['error'] ?? null
        );
        
        $this->log_request( $request_log );
        
        // Update platform-specific quota tracking
        $this->update_platform_quota( $platform, $response_data );
    }

    /**
     * Increment counter with expiration
     */
    private function increment_counter( $key, $expiration ) {
        $full_key = $this->cache_prefix . $key;
        $current = get_transient( $full_key ) ?: 0;
        set_transient( $full_key, $current + 1, $expiration );
    }

    /**
     * Update platform-specific quota information
     */
    private function update_platform_quota( $platform, $response_data ) {
        $quota_key = $this->quota_prefix . $platform;
        $quota_data = get_option( $quota_key, array() );
        
        // Update quota based on response headers
        $headers = $response_data['headers'] ?? array();
        
        switch ( $platform ) {
            case 'facebook':
                $quota_data = $this->update_facebook_quota( $quota_data, $headers );
                break;
            case 'instagram':
                $quota_data = $this->update_instagram_quota( $quota_data, $headers );
                break;
            case 'youtube':
                $quota_data = $this->update_youtube_quota( $quota_data, $headers );
                break;
            case 'tiktok':
                $quota_data = $this->update_tiktok_quota( $quota_data, $headers );
                break;
        }
        
        $quota_data['last_updated'] = time();
        update_option( $quota_key, $quota_data );
    }

    /**
     * Update Facebook quota information
     */
    private function update_facebook_quota( $quota_data, $headers ) {
        // Facebook uses X-App-Usage header
        if ( isset( $headers['X-App-Usage'] ) ) {
            $usage = json_decode( $headers['X-App-Usage'], true );
            $quota_data['app_usage'] = $usage;
        }
        
        if ( isset( $headers['X-Business-Use-Case-Usage'] ) ) {
            $business_usage = json_decode( $headers['X-Business-Use-Case-Usage'], true );
            $quota_data['business_usage'] = $business_usage;
        }
        
        return $quota_data;
    }

    /**
     * Update Instagram quota information
     */
    private function update_instagram_quota( $quota_data, $headers ) {
        // Instagram uses same headers as Facebook
        return $this->update_facebook_quota( $quota_data, $headers );
    }

    /**
     * Update YouTube quota information
     */
    private function update_youtube_quota( $quota_data, $headers ) {
        // YouTube quota is tracked differently - estimate based on operation type
        $quota_data['estimated_daily_usage'] = ( $quota_data['estimated_daily_usage'] ?? 0 ) + 1;
        $quota_data['estimated_quota_cost'] = ( $quota_data['estimated_quota_cost'] ?? 0 ) + $this->get_youtube_quota_cost();
        
        return $quota_data;
    }

    /**
     * Update TikTok quota information
     */
    private function update_tiktok_quota( $quota_data, $headers ) {
        // TikTok rate limiting info from headers
        if ( isset( $headers['X-RateLimit-Remaining'] ) ) {
            $quota_data['remaining'] = intval( $headers['X-RateLimit-Remaining'] );
        }
        
        if ( isset( $headers['X-RateLimit-Reset'] ) ) {
            $quota_data['reset_time'] = intval( $headers['X-RateLimit-Reset'] );
        }
        
        return $quota_data;
    }

    /**
     * Get estimated YouTube quota cost
     */
    private function get_youtube_quota_cost() {
        // Typical YouTube API costs
        return 1; // Most read operations cost 1 unit
    }

    /**
     * Get comprehensive rate limit status
     *
     * @param string $platform Platform name
     * @return array Rate limit status
     */
    public function get_rate_limit_status( $platform ) {
        $platform = strtolower( $platform );
        $current_time = time();
        
        if ( ! isset( $this->rate_limits[ $platform ] ) ) {
            return array( 'error' => 'Platform not supported' );
        }
        
        $limits = $this->rate_limits[ $platform ];
        
        // Get current usage
        $hourly_key = $this->cache_prefix . $platform . '_hourly_' . floor( $current_time / 3600 );
        $daily_key = $this->cache_prefix . $platform . '_daily_' . date( 'Y-m-d' );
        $burst_key = $this->cache_prefix . $platform . '_burst_' . floor( $current_time / 300 );
        
        $hourly_used = get_transient( $hourly_key ) ?: 0;
        $daily_used = get_transient( $daily_key ) ?: 0;
        $burst_used = get_transient( $burst_key ) ?: 0;
        
        // Get quota information
        $quota_data = get_option( $this->quota_prefix . $platform, array() );
        
        return array(
            'platform' => $platform,
            'hourly' => array(
                'used' => $hourly_used,
                'limit' => $limits['requests_per_hour'],
                'remaining' => max( 0, $limits['requests_per_hour'] - $hourly_used ),
                'reset_time' => ceil( $current_time / 3600 ) * 3600
            ),
            'daily' => array(
                'used' => $daily_used,
                'limit' => $limits['requests_per_day'],
                'remaining' => max( 0, $limits['requests_per_day'] - $daily_used ),
                'reset_time' => strtotime( 'tomorrow' )
            ),
            'burst' => array(
                'used' => $burst_used,
                'limit' => $limits['burst_limit'],
                'remaining' => max( 0, $limits['burst_limit'] - $burst_used ),
                'reset_time' => ceil( $current_time / 300 ) * 300
            ),
            'quota' => $quota_data,
            'health_score' => $this->calculate_health_score( $platform, $hourly_used, $daily_used, $limits )
        );
    }

    /**
     * Calculate health score for platform
     */
    private function calculate_health_score( $platform, $hourly_used, $daily_used, $limits ) {
        $hourly_percentage = ( $hourly_used / $limits['requests_per_hour'] ) * 100;
        $daily_percentage = ( $daily_used / $limits['requests_per_day'] ) * 100;
        
        // Calculate weighted score (daily has more weight)
        $score = 100 - ( ( $hourly_percentage * 0.3 ) + ( $daily_percentage * 0.7 ) );
        
        return array(
            'score' => max( 0, round( $score ) ),
            'status' => $this->get_health_status( $score ),
            'hourly_usage_percent' => round( $hourly_percentage, 2 ),
            'daily_usage_percent' => round( $daily_percentage, 2 )
        );
    }

    /**
     * Get health status based on score
     */
    private function get_health_status( $score ) {
        if ( $score >= 80 ) return 'excellent';
        if ( $score >= 60 ) return 'good';
        if ( $score >= 40 ) return 'warning';
        if ( $score >= 20 ) return 'critical';
        return 'emergency';
    }

    /**
     * Log API request for analytics
     */
    private function log_request( $request_log ) {
        $logs = get_option( 'tts_api_request_logs', array() );
        
        // Keep only last 1000 requests
        if ( count( $logs ) >= 1000 ) {
            $logs = array_slice( $logs, -900 );
        }
        
        $logs[] = $request_log;
        update_option( 'tts_api_request_logs', $logs );
    }

    /**
     * Get rate limit analytics
     */
    public function get_rate_limit_analytics( $platform = null, $days = 7 ) {
        $logs = get_option( 'tts_api_request_logs', array() );
        $cutoff_time = time() - ( $days * DAY_IN_SECONDS );
        
        // Filter logs
        $filtered_logs = array_filter( $logs, function( $log ) use ( $platform, $cutoff_time ) {
            $platform_match = ! $platform || $log['platform'] === $platform;
            $time_match = $log['timestamp'] >= $cutoff_time;
            return $platform_match && $time_match;
        });
        
        // Calculate analytics
        $total_requests = count( $filtered_logs );
        $error_count = count( array_filter( $filtered_logs, function( $log ) {
            return ! empty( $log['error'] ) || $log['status_code'] >= 400;
        }));
        
        $avg_response_time = 0;
        if ( $total_requests > 0 ) {
            $response_times = array_column( $filtered_logs, 'response_time' );
            $avg_response_time = array_sum( $response_times ) / count( $response_times );
        }
        
        // Group by platform
        $by_platform = array();
        foreach ( $filtered_logs as $log ) {
            $platform_name = $log['platform'];
            if ( ! isset( $by_platform[ $platform_name ] ) ) {
                $by_platform[ $platform_name ] = array(
                    'requests' => 0,
                    'errors' => 0,
                    'avg_response_time' => 0
                );
            }
            $by_platform[ $platform_name ]['requests']++;
            if ( ! empty( $log['error'] ) || $log['status_code'] >= 400 ) {
                $by_platform[ $platform_name ]['errors']++;
            }
        }
        
        return array(
            'period_days' => $days,
            'total_requests' => $total_requests,
            'error_count' => $error_count,
            'error_rate' => $total_requests > 0 ? round( ( $error_count / $total_requests ) * 100, 2 ) : 0,
            'avg_response_time' => round( $avg_response_time, 2 ),
            'by_platform' => $by_platform,
            'requests_per_day' => round( $total_requests / max( 1, $days ), 2 )
        );
    }

    /**
     * AJAX: Check rate limits
     */
    public function ajax_check_rate_limits() {
        check_ajax_referer( 'tts_rate_limit_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $platforms = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
        $status = array();
        
        foreach ( $platforms as $platform ) {
            $status[ $platform ] = $this->get_rate_limit_status( $platform );
        }
        
        wp_send_json_success( $status );
    }

    /**
     * AJAX: Reset rate limits
     */
    public function ajax_reset_rate_limits() {
        check_ajax_referer( 'tts_rate_limit_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $platform = sanitize_text_field( $_POST['platform'] ?? '' );
        $result = $this->reset_rate_limits( $platform );
        
        wp_send_json( $result );
    }

    /**
     * Reset rate limits for platform
     */
    public function reset_rate_limits( $platform ) {
        $platform = strtolower( $platform );
        $current_time = time();
        
        // Delete all rate limit counters for platform
        $keys_to_delete = array(
            $this->cache_prefix . $platform . '_hourly_' . floor( $current_time / 3600 ),
            $this->cache_prefix . $platform . '_daily_' . date( 'Y-m-d' ),
            $this->cache_prefix . $platform . '_burst_' . floor( $current_time / 300 )
        );
        
        foreach ( $keys_to_delete as $key ) {
            delete_transient( $key );
        }
        
        TTS_Logger::log( "Rate limits reset for platform: {$platform}" );
        
        return array(
            'success' => true,
            'message' => sprintf( __( 'Rate limits reset for %s', 'trello-social-auto-publisher' ), $platform )
        );
    }

    /**
     * AJAX: Get quota status
     */
    public function ajax_get_quota_status() {
        check_ajax_referer( 'tts_rate_limit_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $analytics = $this->get_rate_limit_analytics();
        wp_send_json_success( $analytics );
    }

    /**
     * Cleanup expired rate limit data
     */
    public function cleanup_expired_limits() {
        global $wpdb;
        
        // Clean up expired transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < %d",
                '_transient_timeout_' . $this->cache_prefix . '%',
                time()
            )
        );
        
        // Clean up old request logs (keep only last 30 days)
        $logs = get_option( 'tts_api_request_logs', array() );
        $cutoff_time = time() - ( 30 * DAY_IN_SECONDS );
        
        $filtered_logs = array_filter( $logs, function( $log ) use ( $cutoff_time ) {
            return $log['timestamp'] >= $cutoff_time;
        });
        
        if ( count( $filtered_logs ) !== count( $logs ) ) {
            update_option( 'tts_api_request_logs', array_values( $filtered_logs ) );
        }
        
        TTS_Logger::log( 'Rate limit cleanup completed' );
    }

    /**
     * Get wait time before next request is allowed
     */
    public function get_wait_time( $platform ) {
        $check = $this->is_request_allowed( $platform );
        return $check['allowed'] ? 0 : $check['retry_after'];
    }

    /**
     * Smart delay before API request
     */
    public function smart_delay( $platform ) {
        $status = $this->get_rate_limit_status( $platform );
        $health_score = $status['health_score']['score'];
        
        // Implement smart delays based on usage
        if ( $health_score < 20 ) {
            sleep( 5 ); // Emergency throttling
        } elseif ( $health_score < 40 ) {
            sleep( 2 ); // Critical throttling
        } elseif ( $health_score < 60 ) {
            sleep( 1 ); // Warning throttling
        }
        // No delay for good/excellent health scores
    }
}

// Initialize rate limiter
new TTS_Rate_Limiter();