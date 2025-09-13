<?php
/**
 * Advanced Error Recovery and Retry System
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TTS_Error_Recovery class for intelligent error handling and retry mechanisms
 */
class TTS_Error_Recovery {

    private $retry_queue_option = 'tts_retry_queue';
    private $max_retries = 5;
    private $base_delay = 2; // Base delay in seconds
    
    /**
     * Error severity levels
     */
    const SEVERITY_LOW = 1;
    const SEVERITY_MEDIUM = 2; 
    const SEVERITY_HIGH = 3;
    const SEVERITY_CRITICAL = 4;

    /**
     * Retry strategies
     */
    const STRATEGY_EXPONENTIAL = 'exponential';
    const STRATEGY_LINEAR = 'linear';
    const STRATEGY_FIXED = 'fixed';

    /**
     * Initialize error recovery system
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_retry_failed_operation', array( $this, 'ajax_retry_failed_operation' ) );
        add_action( 'wp_ajax_tts_get_error_analytics', array( $this, 'ajax_get_error_analytics' ) );
        add_action( 'wp_ajax_tts_clear_retry_queue', array( $this, 'ajax_clear_retry_queue' ) );
        add_action( 'wp_ajax_tts_get_retry_queue', array( $this, 'ajax_get_retry_queue' ) );
        
        // Schedule retry processor
        add_action( 'tts_process_retry_queue', array( $this, 'process_retry_queue' ) );
        if ( ! wp_next_scheduled( 'tts_process_retry_queue' ) ) {
            wp_schedule_event( time(), 'every_15_minutes', 'tts_process_retry_queue' );
        }
        
        // Add custom cron schedule
        add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
    }

    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules( $schedules ) {
        $schedules['every_15_minutes'] = array(
            'interval' => 15 * 60,
            'display' => __( 'Every 15 minutes', 'fp-publisher' )
        );
        return $schedules;
    }

    /**
     * Handle error with intelligent recovery
     *
     * @param Exception|WP_Error|array $error Error object or data
     * @param string $operation Operation that failed
     * @param array $context Additional context
     * @return array Recovery result
     */
    public function handle_error( $error, $operation, $context = array() ) {
        $error_data = $this->normalize_error( $error );
        $severity = $this->determine_severity( $error_data );
        $strategy = $this->determine_retry_strategy( $error_data, $operation );
        
        // Log error with context
        $this->log_error( $error_data, $operation, $context, $severity );
        
        // Determine if retry is appropriate
        if ( $this->should_retry( $error_data, $operation, $context ) ) {
            return $this->queue_for_retry( $error_data, $operation, $context, $strategy );
        }
        
        // Handle non-retryable errors
        return $this->handle_permanent_failure( $error_data, $operation, $context );
    }

    /**
     * Normalize error to standard format
     */
    private function normalize_error( $error ) {
        if ( is_wp_error( $error ) ) {
            return array(
                'type' => 'wp_error',
                'code' => $error->get_error_code(),
                'message' => $error->get_error_message(),
                'data' => $error->get_error_data()
            );
        }
        
        if ( $error instanceof Exception ) {
            return array(
                'type' => 'exception',
                'code' => $error->getCode(),
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString()
            );
        }
        
        if ( is_array( $error ) ) {
            return array_merge( array(
                'type' => 'custom',
                'code' => 'unknown',
                'message' => 'Unknown error'
            ), $error );
        }
        
        return array(
            'type' => 'string',
            'code' => 'unknown',
            'message' => (string) $error
        );
    }

    /**
     * Determine error severity
     */
    private function determine_severity( $error_data ) {
        $critical_codes = array( 'fatal_error', 'database_error', 'security_violation' );
        $high_codes = array( 'api_limit_exceeded', 'authentication_failed', 'permission_denied' );
        $medium_codes = array( 'network_error', 'timeout', 'rate_limit' );
        
        if ( in_array( $error_data['code'], $critical_codes ) ) {
            return self::SEVERITY_CRITICAL;
        }
        
        if ( in_array( $error_data['code'], $high_codes ) ) {
            return self::SEVERITY_HIGH;
        }
        
        if ( in_array( $error_data['code'], $medium_codes ) ) {
            return self::SEVERITY_MEDIUM;
        }
        
        return self::SEVERITY_LOW;
    }

    /**
     * Determine appropriate retry strategy
     */
    private function determine_retry_strategy( $error_data, $operation ) {
        // Rate limiting and throttling - use exponential backoff
        if ( in_array( $error_data['code'], array( 'rate_limit', 'throttling', 'too_many_requests' ) ) ) {
            return self::STRATEGY_EXPONENTIAL;
        }
        
        // Network errors - use linear backoff
        if ( in_array( $error_data['code'], array( 'network_error', 'timeout', 'connection_failed' ) ) ) {
            return self::STRATEGY_LINEAR;
        }
        
        // API operations - use fixed delays
        if ( strpos( $operation, 'api_' ) === 0 ) {
            return self::STRATEGY_FIXED;
        }
        
        return self::STRATEGY_EXPONENTIAL;
    }

    /**
     * Check if operation should be retried
     */
    private function should_retry( $error_data, $operation, $context ) {
        // Never retry critical security violations
        if ( $error_data['code'] === 'security_violation' ) {
            return false;
        }
        
        // Never retry authentication failures without manual intervention
        if ( $error_data['code'] === 'authentication_failed' ) {
            return false;
        }
        
        // Check if operation has already been retried too many times
        $retry_count = $context['retry_count'] ?? 0;
        if ( $retry_count >= $this->max_retries ) {
            return false;
        }
        
        // Check if error is retryable
        $retryable_codes = array(
            'rate_limit',
            'throttling', 
            'network_error',
            'timeout',
            'server_error',
            'service_unavailable',
            'too_many_requests',
            'connection_failed'
        );
        
        return in_array( $error_data['code'], $retryable_codes );
    }

    /**
     * Queue operation for retry
     */
    private function queue_for_retry( $error_data, $operation, $context, $strategy ) {
        $retry_count = ( $context['retry_count'] ?? 0 ) + 1;
        $next_attempt = $this->calculate_next_attempt( $retry_count, $strategy );
        
        $retry_item = array(
            'id' => uniqid( 'retry_' ),
            'operation' => $operation,
            'context' => $context,
            'error' => $error_data,
            'retry_count' => $retry_count,
            'strategy' => $strategy,
            'next_attempt' => $next_attempt,
            'created_at' => time(),
            'last_attempt' => time()
        );
        
        $this->add_to_retry_queue( $retry_item );
        
        TTS_Logger::log( 
            sprintf( 'Operation queued for retry: %s (attempt %d/%d)', 
                $operation, $retry_count, $this->max_retries 
            ),
            'info'
        );
        
        return array(
            'status' => 'queued_for_retry',
            'retry_count' => $retry_count,
            'next_attempt' => $next_attempt,
            'strategy' => $strategy
        );
    }

    /**
     * Calculate next retry attempt time
     */
    private function calculate_next_attempt( $retry_count, $strategy ) {
        $base_time = time();
        
        switch ( $strategy ) {
            case self::STRATEGY_EXPONENTIAL:
                $delay = $this->base_delay * pow( 2, $retry_count - 1 );
                break;
                
            case self::STRATEGY_LINEAR:
                $delay = $this->base_delay * $retry_count;
                break;
                
            case self::STRATEGY_FIXED:
            default:
                $delay = $this->base_delay * 5; // 10 second fixed delay
                break;
        }
        
        // Add jitter to prevent thundering herd
        $jitter = rand( 0, $delay * 0.1 );
        $delay += $jitter;
        
        // Cap maximum delay at 1 hour
        $delay = min( $delay, 3600 );
        
        return $base_time + $delay;
    }

    /**
     * Add item to retry queue
     */
    private function add_to_retry_queue( $retry_item ) {
        $queue = get_option( $this->retry_queue_option, array() );
        $queue[] = $retry_item;
        
        // Keep queue size manageable
        if ( count( $queue ) > 1000 ) {
            $queue = array_slice( $queue, -900 );
        }
        
        update_option( $this->retry_queue_option, $queue );
    }

    /**
     * Process retry queue
     */
    public function process_retry_queue() {
        $queue = get_option( $this->retry_queue_option, array() );
        $current_time = time();
        $processed = array();
        $remaining = array();
        
        foreach ( $queue as $item ) {
            if ( $current_time >= $item['next_attempt'] ) {
                $result = $this->execute_retry( $item );
                $processed[] = array(
                    'item' => $item,
                    'result' => $result
                );
                
                if ( $result['status'] === 'retry_failed' && $item['retry_count'] < $this->max_retries ) {
                    // Queue for another retry
                    $item['retry_count']++;
                    $item['next_attempt'] = $this->calculate_next_attempt( $item['retry_count'], $item['strategy'] );
                    $item['last_attempt'] = $current_time;
                    $remaining[] = $item;
                }
            } else {
                $remaining[] = $item;
            }
        }
        
        // Update queue
        update_option( $this->retry_queue_option, $remaining );
        
        // Log processing results
        if ( ! empty( $processed ) ) {
            TTS_Logger::log( 
                sprintf( 'Processed %d retry operations', count( $processed ) ),
                'info'
            );
        }
        
        return $processed;
    }

    /**
     * Execute retry operation
     */
    private function execute_retry( $item ) {
        try {
            $operation = $item['operation'];
            $context = $item['context'];
            $context['retry_count'] = $item['retry_count'];
            
            // Execute operation based on type
            $result = $this->execute_operation( $operation, $context );
            
            if ( $result['success'] ) {
                TTS_Logger::log( 
                    sprintf( 'Retry successful for operation: %s', $operation ),
                    'info'
                );
                
                return array(
                    'status' => 'retry_successful',
                    'result' => $result
                );
            } else {
                return array(
                    'status' => 'retry_failed',
                    'error' => $result['error'] ?? 'Unknown error'
                );
            }
            
        } catch ( Exception $e ) {
            TTS_Logger::log( 
                sprintf( 'Retry exception for operation %s: %s', $operation, $e->getMessage() ),
                'error'
            );
            
            return array(
                'status' => 'retry_failed',
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Execute operation based on type
     */
    private function execute_operation( $operation, $context ) {
        switch ( $operation ) {
            case 'api_publish_post':
                return $this->retry_api_publish( $context );
                
            case 'api_upload_media':
                return $this->retry_api_upload( $context );
                
            case 'webhook_delivery':
                return $this->retry_webhook_delivery( $context );
                
            case 'database_operation':
                return $this->retry_database_operation( $context );
                
            default:
                return array(
                    'success' => false,
                    'error' => 'Unknown operation type: ' . $operation
                );
        }
    }

    /**
     * Retry API publish operation
     */
    private function retry_api_publish( $context ) {
        $client_id = $context['client_id'] ?? 0;
        $post_data = $context['post_data'] ?? array();
        
        if ( ! $client_id || empty( $post_data ) ) {
            return array(
                'success' => false,
                'error' => 'Missing required context data'
            );
        }
        
        // Attempt to republish
        $client = new TTS_Client( $client_id );
        return $client->publish_post( $post_data );
    }

    /**
     * Retry API upload operation
     */
    private function retry_api_upload( $context ) {
        $platform = $context['platform'] ?? '';
        $media_path = $context['media_path'] ?? '';
        
        if ( ! $platform || ! $media_path ) {
            return array(
                'success' => false,
                'error' => 'Missing platform or media path'
            );
        }
        
        // Attempt to re-upload media
        $publisher_class = 'TTS_Publisher_' . ucfirst( $platform );
        if ( class_exists( $publisher_class ) ) {
            $publisher = new $publisher_class();
            return $publisher->upload_media( $media_path );
        }
        
        return array(
            'success' => false,
            'error' => 'Publisher not found: ' . $publisher_class
        );
    }

    /**
     * Retry webhook delivery
     */
    private function retry_webhook_delivery( $context ) {
        $webhook_url = $context['webhook_url'] ?? '';
        $payload = $context['payload'] ?? array();
        
        if ( ! $webhook_url ) {
            return array(
                'success' => false,
                'error' => 'Missing webhook URL'
            );
        }
        
        // Attempt webhook delivery
        $response = wp_remote_post( $webhook_url, array(
            'body' => wp_json_encode( $payload ),
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code >= 200 && $status_code < 300 ) {
            return array( 'success' => true );
        }
        
        return array(
            'success' => false,
            'error' => 'HTTP ' . $status_code
        );
    }

    /**
     * Retry database operation
     */
    private function retry_database_operation( $context ) {
        global $wpdb;
        
        $query = $context['query'] ?? '';
        $params = $context['params'] ?? array();
        
        if ( ! $query ) {
            return array(
                'success' => false,
                'error' => 'Missing database query'
            );
        }
        
        // Execute prepared query
        $prepared = empty( $params ) ? $query : $wpdb->prepare( $query, $params );
        $result = $wpdb->query( $prepared );
        
        if ( $result === false ) {
            return array(
                'success' => false,
                'error' => $wpdb->last_error
            );
        }
        
        return array(
            'success' => true,
            'affected_rows' => $result
        );
    }

    /**
     * Handle permanent failure
     */
    private function handle_permanent_failure( $error_data, $operation, $context ) {
        // Log permanent failure
        TTS_Logger::log( 
            sprintf( 'Permanent failure for operation: %s. Error: %s', 
                $operation, $error_data['message'] 
            ),
            'error'
        );
        
        // Send notification for critical failures
        if ( $this->determine_severity( $error_data ) >= self::SEVERITY_HIGH ) {
            $this->send_failure_notification( $error_data, $operation, $context );
        }
        
        return array(
            'status' => 'permanent_failure',
            'error' => $error_data,
            'operation' => $operation
        );
    }

    /**
     * Send failure notification
     */
    private function send_failure_notification( $error_data, $operation, $context ) {
        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );
        
        $subject = sprintf( 
            '[%s] Critical Operation Failure: %s', 
            $site_name, 
            $operation 
        );
        
        $message = sprintf(
            "A critical operation has failed permanently:\n\n" .
            "Operation: %s\n" .
            "Error Code: %s\n" .
            "Error Message: %s\n" .
            "Time: %s\n\n" .
            "Please review the system logs and take appropriate action.",
            $operation,
            $error_data['code'],
            $error_data['message'],
            current_time( 'mysql' )
        );
        
        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Log error with context
     */
    private function log_error( $error_data, $operation, $context, $severity ) {
        $severity_names = array(
            self::SEVERITY_LOW => 'low',
            self::SEVERITY_MEDIUM => 'medium',
            self::SEVERITY_HIGH => 'high',
            self::SEVERITY_CRITICAL => 'critical'
        );
        
        $log_entry = array(
            'timestamp' => time(),
            'operation' => $operation,
            'error' => $error_data,
            'context' => $context,
            'severity' => $severity_names[ $severity ] ?? 'unknown',
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        $error_logs = get_option( 'tts_error_logs', array() );
        $error_logs[] = $log_entry;
        
        // Keep only last 500 error logs
        if ( count( $error_logs ) > 500 ) {
            $error_logs = array_slice( $error_logs, -450 );
        }
        
        update_option( 'tts_error_logs', $error_logs );
    }

    /**
     * Get error analytics
     */
    public function get_error_analytics( $days = 7 ) {
        $error_logs = get_option( 'tts_error_logs', array() );
        $cutoff_time = time() - ( $days * DAY_IN_SECONDS );
        
        // Filter recent errors
        $recent_errors = array_filter( $error_logs, function( $log ) use ( $cutoff_time ) {
            return $log['timestamp'] >= $cutoff_time;
        });
        
        $total_errors = count( $recent_errors );
        $by_severity = array();
        $by_operation = array();
        $by_day = array();
        
        foreach ( $recent_errors as $error ) {
            // Group by severity
            $severity = $error['severity'];
            $by_severity[ $severity ] = ( $by_severity[ $severity ] ?? 0 ) + 1;
            
            // Group by operation
            $operation = $error['operation'];
            $by_operation[ $operation ] = ( $by_operation[ $operation ] ?? 0 ) + 1;
            
            // Group by day
            $day = date( 'Y-m-d', $error['timestamp'] );
            $by_day[ $day ] = ( $by_day[ $day ] ?? 0 ) + 1;
        }
        
        return array(
            'period_days' => $days,
            'total_errors' => $total_errors,
            'errors_per_day' => round( $total_errors / max( 1, $days ), 2 ),
            'by_severity' => $by_severity,
            'by_operation' => $by_operation,
            'by_day' => $by_day,
            'retry_queue_size' => count( get_option( $this->retry_queue_option, array() ) )
        );
    }

    /**
     * AJAX: Retry failed operation
     */
    public function ajax_retry_failed_operation() {
        check_ajax_referer( 'tts_error_recovery_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $operation_id = sanitize_text_field( $_POST['operation_id'] ?? '' );
        $result = $this->manual_retry( $operation_id );
        
        wp_send_json( $result );
    }

    /**
     * Manual retry of specific operation
     */
    public function manual_retry( $operation_id ) {
        $queue = get_option( $this->retry_queue_option, array() );
        
        foreach ( $queue as $index => $item ) {
            if ( $item['id'] === $operation_id ) {
                $result = $this->execute_retry( $item );
                
                if ( $result['status'] === 'retry_successful' ) {
                    // Remove from queue on success
                    unset( $queue[ $index ] );
                    update_option( $this->retry_queue_option, array_values( $queue ) );
                }
                
                return $result;
            }
        }
        
        return array(
            'status' => 'not_found',
            'error' => 'Operation not found in retry queue'
        );
    }

    /**
     * AJAX: Get error analytics
     */
    public function ajax_get_error_analytics() {
        check_ajax_referer( 'tts_error_recovery_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $days = intval( $_POST['days'] ?? 7 );
        $analytics = $this->get_error_analytics( $days );
        
        wp_send_json_success( $analytics );
    }

    /**
     * AJAX: Clear retry queue
     */
    public function ajax_clear_retry_queue() {
        check_ajax_referer( 'tts_error_recovery_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        delete_option( $this->retry_queue_option );
        
        wp_send_json_success( array(
            'message' => __( 'Retry queue cleared successfully', 'fp-publisher' )
        ));
    }

    /**
     * AJAX: Get retry queue
     */
    public function ajax_get_retry_queue() {
        check_ajax_referer( 'tts_error_recovery_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $queue = get_option( $this->retry_queue_option, array() );
        wp_send_json_success( $queue );
    }
}

// Initialize error recovery system
new TTS_Error_Recovery();