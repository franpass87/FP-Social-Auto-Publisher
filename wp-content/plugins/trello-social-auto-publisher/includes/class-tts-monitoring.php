<?php
/**
 * Advanced monitoring and health check system for Trello Social Auto Publisher.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Advanced monitoring and health check system.
 */
class TTS_Monitoring {
    
    /**
     * Initialize monitoring system.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'schedule_health_checks' ) );
        add_action( 'tts_hourly_health_check', array( __CLASS__, 'perform_health_check' ) );
        add_action( 'tts_daily_system_report', array( __CLASS__, 'generate_daily_report' ) );
        add_action( 'admin_notices', array( __CLASS__, 'show_health_warnings' ) );
    }
    
    /**
     * Schedule health check tasks.
     */
    public static function schedule_health_checks() {
        // Hourly health checks
        if ( ! wp_next_scheduled( 'tts_hourly_health_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'tts_hourly_health_check' );
        }
        
        // Daily system reports
        if ( ! wp_next_scheduled( 'tts_daily_system_report' ) ) {
            wp_schedule_event( strtotime( 'tomorrow 6:00 AM' ), 'daily', 'tts_daily_system_report' );
        }
    }
    
    /**
     * Perform comprehensive health check.
     */
    public static function perform_health_check() {
        $health_data = array(
            'timestamp' => current_time( 'mysql' ),
            'checks' => array(),
            'alerts' => array(),
            'score' => 100
        );
        
        // Database health check
        $db_health = self::check_database_health();
        $health_data['checks']['database'] = $db_health;
        if ( ! $db_health['status'] ) {
            $health_data['score'] -= 20;
            $health_data['alerts'][] = array(
                'type' => 'database',
                'severity' => 'high',
                'message' => $db_health['message']
            );
        }
        
        // API connections health check
        $api_health = self::check_api_connections();
        $health_data['checks']['api_connections'] = $api_health;
        if ( $api_health['failed_connections'] > 0 ) {
            $health_data['score'] -= 15;
            $health_data['alerts'][] = array(
                'type' => 'api',
                'severity' => 'medium',
                'message' => 'Some API connections are failing'
            );
        }
        
        // System resources check
        $resource_health = self::check_system_resources();
        $health_data['checks']['resources'] = $resource_health;
        if ( ! $resource_health['status'] ) {
            $health_data['score'] -= 25;
            $health_data['alerts'][] = array(
                'type' => 'resources',
                'severity' => 'high',
                'message' => $resource_health['message']
            );
        }
        
        // Scheduled tasks check
        $scheduler_health = self::check_scheduled_tasks();
        $health_data['checks']['scheduler'] = $scheduler_health;
        if ( ! $scheduler_health['status'] ) {
            $health_data['score'] -= 10;
            $health_data['alerts'][] = array(
                'type' => 'scheduler',
                'severity' => 'medium',
                'message' => 'Some scheduled tasks are not running'
            );
        }
        
        // Error rate check
        $error_health = self::check_error_rates();
        $health_data['checks']['errors'] = $error_health;
        if ( $error_health['error_rate'] > 10 ) {
            $health_data['score'] -= 20;
            $health_data['alerts'][] = array(
                'type' => 'errors',
                'severity' => 'high',
                'message' => 'High error rate detected: ' . $error_health['error_rate'] . '%'
            );
        }
        
        // Store health data
        update_option( 'tts_last_health_check', $health_data );
        
        // Send alerts if necessary
        if ( count( $health_data['alerts'] ) > 0 ) {
            self::send_health_alerts( $health_data['alerts'] );
        }
        
        return $health_data;
    }
    
    /**
     * Check database health.
     */
    private static function check_database_health() {
        global $wpdb;
        
        $health = array(
            'status' => true,
            'message' => 'Database is healthy',
            'details' => array()
        );
        
        try {
            // Check database connectivity
            $start_time = microtime( true );
            $test_query = $wpdb->get_var( "SELECT 1" );
            $response_time = ( microtime( true ) - $start_time ) * 1000;
            
            $health['details']['response_time_ms'] = round( $response_time, 2 );
            
            if ( $response_time > 1000 ) { // 1 second
                $health['status'] = false;
                $health['message'] = 'Database response time is too slow: ' . round( $response_time, 2 ) . 'ms';
            }
            
            // Check table integrity
            $tables = array(
                $wpdb->prefix . 'tts_logs',
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->options
            );
            
            $table_status = array();
            foreach ( $tables as $table ) {
                $check_result = $wpdb->get_row( "CHECK TABLE {$table}", ARRAY_A );
                $table_status[ $table ] = $check_result ? $check_result['Msg_text'] : 'Unknown';
                
                if ( $check_result && $check_result['Msg_text'] !== 'OK' ) {
                    $health['status'] = false;
                    $health['message'] = "Table {$table} has issues: " . $check_result['Msg_text'];
                }
            }
            
            $health['details']['table_status'] = $table_status;
            
            // Check for deadlocks or long-running queries
            $long_queries = $wpdb->get_results( "
                SELECT TIME, STATE, INFO 
                FROM INFORMATION_SCHEMA.PROCESSLIST 
                WHERE TIME > 30 AND COMMAND != 'Sleep'
            ", ARRAY_A );
            
            if ( ! empty( $long_queries ) ) {
                $health['details']['long_running_queries'] = count( $long_queries );
                if ( count( $long_queries ) > 5 ) {
                    $health['status'] = false;
                    $health['message'] = 'Multiple long-running database queries detected';
                }
            }
            
        } catch ( Exception $e ) {
            $health['status'] = false;
            $health['message'] = 'Database error: ' . $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Check API connections health.
     */
    private static function check_api_connections() {
        $social_apps = get_option( 'tts_social_apps', array() );
        $platforms = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
        
        $health = array(
            'total_platforms' => count( $platforms ),
            'configured_platforms' => 0,
            'working_connections' => 0,
            'failed_connections' => 0,
            'platform_status' => array()
        );
        
        foreach ( $platforms as $platform ) {
            if ( ! empty( $social_apps[ $platform ] ) ) {
                $health['configured_platforms']++;
                
                // Test connection (simplified check)
                $test_result = self::test_platform_connection( $platform, $social_apps[ $platform ] );
                $health['platform_status'][ $platform ] = $test_result;
                
                if ( $test_result['success'] ) {
                    $health['working_connections']++;
                } else {
                    $health['failed_connections']++;
                }
            } else {
                $health['platform_status'][ $platform ] = array(
                    'success' => false,
                    'message' => 'Not configured'
                );
            }
        }
        
        return $health;
    }
    
    /**
     * Test platform connection.
     */
    private static function test_platform_connection( $platform, $credentials ) {
        // This is a simplified version - in production you'd test actual API endpoints
        $required_fields = array(
            'facebook' => array( 'app_id', 'app_secret' ),
            'instagram' => array( 'app_id', 'app_secret' ),
            'youtube' => array( 'client_id', 'client_secret' ),
            'tiktok' => array( 'client_key', 'client_secret' )
        );
        
        if ( ! isset( $required_fields[ $platform ] ) ) {
            return array( 'success' => false, 'message' => 'Unknown platform' );
        }
        
        foreach ( $required_fields[ $platform ] as $field ) {
            if ( empty( $credentials[ $field ] ) ) {
                return array( 'success' => false, 'message' => 'Missing credentials' );
            }
        }
        
        return array( 'success' => true, 'message' => 'Credentials configured' );
    }
    
    /**
     * Check system resources.
     */
    private static function check_system_resources() {
        $health = array(
            'status' => true,
            'message' => 'System resources are healthy',
            'details' => array()
        );
        
        // Memory usage check
        $memory_usage = memory_get_usage( true );
        $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        $memory_percent = ( $memory_usage / $memory_limit ) * 100;
        
        $health['details']['memory_usage_percent'] = round( $memory_percent, 1 );
        
        if ( $memory_percent > 90 ) {
            $health['status'] = false;
            $health['message'] = 'Memory usage is critically high: ' . round( $memory_percent, 1 ) . '%';
        } elseif ( $memory_percent > 80 ) {
            $health['message'] = 'Memory usage is high: ' . round( $memory_percent, 1 ) . '%';
        }
        
        // Disk space check (if possible)
        if ( function_exists( 'disk_free_space' ) ) {
            $upload_dir = wp_upload_dir();
            $free_space = disk_free_space( $upload_dir['basedir'] );
            $total_space = disk_total_space( $upload_dir['basedir'] );
            
            if ( $free_space && $total_space ) {
                $disk_usage_percent = ( ( $total_space - $free_space ) / $total_space ) * 100;
                $health['details']['disk_usage_percent'] = round( $disk_usage_percent, 1 );
                
                if ( $disk_usage_percent > 95 ) {
                    $health['status'] = false;
                    $health['message'] = 'Disk space is critically low';
                }
            }
        }
        
        // CPU load check (if available)
        if ( function_exists( 'sys_getloadavg' ) ) {
            $load = sys_getloadavg();
            $health['details']['cpu_load_1min'] = $load[0];
            
            if ( $load[0] > 5.0 ) {
                $health['status'] = false;
                $health['message'] = 'CPU load is very high: ' . $load[0];
            }
        }
        
        return $health;
    }
    
    /**
     * Check scheduled tasks.
     */
    private static function check_scheduled_tasks() {
        $required_tasks = array(
            'tts_refresh_tokens',
            'tts_fetch_metrics',
            'tts_check_links',
            'tts_hourly_health_check'
        );
        
        $health = array(
            'status' => true,
            'message' => 'All scheduled tasks are running',
            'details' => array()
        );
        
        foreach ( $required_tasks as $task ) {
            $next_run = wp_next_scheduled( $task );
            $health['details'][ $task ] = $next_run ? 'scheduled' : 'not_scheduled';
            
            if ( ! $next_run ) {
                $health['status'] = false;
                $health['message'] = 'Some scheduled tasks are not running';
            }
        }
        
        return $health;
    }
    
    /**
     * Check error rates.
     */
    private static function check_error_rates() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'tts_logs';
        
        // Count total and error logs in last 24 hours
        $total_logs = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        " );
        
        $error_logs = $wpdb->get_var( "
            SELECT COUNT(*) FROM {$logs_table}
            WHERE status = 'error'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        " );
        
        $error_rate = $total_logs > 0 ? ( $error_logs / $total_logs ) * 100 : 0;
        
        return array(
            'total_logs' => (int) $total_logs,
            'error_logs' => (int) $error_logs,
            'error_rate' => round( $error_rate, 2 )
        );
    }
    
    /**
     * Send health alerts.
     */
    private static function send_health_alerts( $alerts ) {
        $alert_settings = get_option( 'tts_alert_settings', array(
            'enabled' => false,
            'email' => get_option( 'admin_email' ),
            'severity_threshold' => 'medium'
        ) );
        
        if ( ! $alert_settings['enabled'] ) {
            return;
        }
        
        $high_priority_alerts = array_filter( $alerts, function( $alert ) {
            return $alert['severity'] === 'high';
        } );
        
        if ( empty( $high_priority_alerts ) && $alert_settings['severity_threshold'] === 'high' ) {
            return;
        }
        
        $subject = 'TTS Health Alert: ' . count( $alerts ) . ' issue(s) detected';
        $message = "Health check detected the following issues:\n\n";
        
        foreach ( $alerts as $alert ) {
            $message .= sprintf(
                "Type: %s\nSeverity: %s\nMessage: %s\n\n",
                ucfirst( $alert['type'] ),
                ucfirst( $alert['severity'] ),
                $alert['message']
            );
        }
        
        $message .= "Please check your Social Auto Publisher dashboard for more details.\n";
        $message .= "Dashboard: " . admin_url( 'admin.php?page=tts-main' );
        
        wp_mail( $alert_settings['email'], $subject, $message );
    }
    
    /**
     * Generate daily system report.
     */
    public static function generate_daily_report() {
        $report = TTS_Advanced_Utils::generate_system_report();
        $performance_metrics = TTS_Performance::get_performance_metrics();
        $health_data = get_option( 'tts_last_health_check', array() );
        
        $daily_report = array(
            'date' => current_time( 'Y-m-d' ),
            'system_report' => $report,
            'performance_metrics' => $performance_metrics,
            'health_status' => $health_data,
            'daily_stats' => self::get_daily_stats()
        );
        
        // Store report
        update_option( 'tts_daily_report_' . current_time( 'Y_m_d' ), $daily_report );
        
        // Clean up old reports (keep last 30 days)
        self::cleanup_old_reports();
        
        return $daily_report;
    }
    
    /**
     * Get daily statistics.
     */
    private static function get_daily_stats() {
        global $wpdb;
        
        $today = current_time( 'Y-m-d' );
        
        return array(
            'posts_created' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'tts_social_post'
                AND DATE(post_date) = %s
            ", $today ) ),
            'posts_published' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'tts_social_post'
                AND post_status = 'publish'
                AND DATE(post_modified) = %s
            ", $today ) ),
            'errors_logged' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->prefix}tts_logs
                WHERE status = 'error'
                AND DATE(created_at) = %s
            ", $today ) ),
            'api_calls' => $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(*) FROM {$wpdb->prefix}tts_logs
                WHERE event_type LIKE '%api%'
                AND DATE(created_at) = %s
            ", $today ) )
        );
    }
    
    /**
     * Clean up old reports.
     */
    private static function cleanup_old_reports() {
        global $wpdb;
        
        $cutoff_date = date( 'Y_m_d', strtotime( '-30 days' ) );
        
        $wpdb->query( $wpdb->prepare( "
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE 'tts_daily_report_%'
            AND option_name < %s
        ", 'tts_daily_report_' . $cutoff_date ) );
    }
    
    /**
     * Show health warnings in admin.
     */
    public static function show_health_warnings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $health_data = get_option( 'tts_last_health_check', array() );
        
        if ( empty( $health_data['alerts'] ) ) {
            return;
        }
        
        $high_priority_alerts = array_filter( $health_data['alerts'], function( $alert ) {
            return $alert['severity'] === 'high';
        } );
        
        if ( ! empty( $high_priority_alerts ) ) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Social Auto Publisher Health Alert:</strong> ' . count( $high_priority_alerts ) . ' critical issue(s) detected.</p>';
            echo '<p><a href="' . admin_url( 'admin.php?page=tts-main' ) . '">View Dashboard</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Get current health status.
     */
    public static function get_current_health_status() {
        $health_data = get_option( 'tts_last_health_check', array() );
        
        if ( empty( $health_data ) ) {
            return array(
                'score' => 0,
                'status' => 'unknown',
                'message' => 'No health data available'
            );
        }
        
        $score = isset( $health_data['score'] ) ? $health_data['score'] : 0;
        
        if ( $score >= 90 ) {
            $status = 'excellent';
            $message = 'System is running optimally';
        } elseif ( $score >= 70 ) {
            $status = 'good';
            $message = 'System is running well with minor issues';
        } elseif ( $score >= 50 ) {
            $status = 'warning';
            $message = 'System has some issues that need attention';
        } else {
            $status = 'critical';
            $message = 'System has serious issues requiring immediate attention';
        }
        
        return array(
            'score' => $score,
            'status' => $status,
            'message' => $message,
            'alerts' => isset( $health_data['alerts'] ) ? $health_data['alerts'] : array(),
            'last_check' => isset( $health_data['timestamp'] ) ? $health_data['timestamp'] : null
        );
    }
}

// Initialize monitoring system
TTS_Monitoring::init();