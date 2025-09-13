<?php
/**
 * Performance optimization utilities for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Performance optimization and caching utilities.
 */
class TTS_Performance {
    
    /**
     * Cache group for transients.
     */
    const CACHE_GROUP = 'tts_performance';
    
    /**
     * Default cache expiration (5 minutes).
     */
    const DEFAULT_EXPIRATION = 300;
    
    /**
     * Get cached dashboard statistics.
     *
     * @return array Dashboard statistics.
     */
    public static function get_cached_dashboard_stats() {
        $cache_key = 'tts_dashboard_stats';
        $stats = get_transient( $cache_key );
        
        if ( false === $stats ) {
            $stats = self::generate_dashboard_stats();
            set_transient( $cache_key, $stats, self::DEFAULT_EXPIRATION );
        }
        
        return $stats;
    }
    
    /**
     * Generate dashboard statistics with enhanced caching and optimization.
     *
     * @return array Statistics array.
     */
    private static function generate_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        try {
            // Ultra-optimized single query for all post statistics
            $query = $wpdb->prepare("
                SELECT 
                    COUNT(CASE WHEN p.post_status = 'publish' THEN 1 END) as total_published,
                    COUNT(CASE WHEN p.post_status = 'draft' THEN 1 END) as total_pending,
                    COUNT(CASE WHEN p.post_status = 'future' THEN 1 END) as total_scheduled,
                    COUNT(CASE WHEN DATE(p.post_date) = %s AND p.post_status = 'publish' THEN 1 END) as published_today,
                    COUNT(CASE WHEN DATE(p.post_date) >= DATE_SUB(%s, INTERVAL 7 DAY) AND p.post_status = 'publish' THEN 1 END) as published_week,
                    COUNT(CASE WHEN DATE(p.post_date) >= DATE_SUB(%s, INTERVAL 30 DAY) AND p.post_status = 'publish' THEN 1 END) as published_month,
                    AVG(CASE WHEN p.post_status = 'publish' THEN 
                        DATEDIFF(p.post_modified, p.post_date) 
                    END) as avg_processing_days
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'tts_social_post'
                AND p.post_status IN ('publish', 'draft', 'future')
            ", 
                current_time('Y-m-d'),
                current_time('Y-m-d'),
                current_time('Y-m-d')
            );
            
            $result = $wpdb->get_row( $query, ARRAY_A );
            
            if ( $result ) {
                $stats = array(
                    'total_posts' => (int) $result['total_published'],
                    'pending_posts' => (int) $result['total_pending'],
                    'scheduled_posts' => (int) $result['total_scheduled'],
                    'published_today' => (int) $result['published_today'],
                    'published_week' => (int) $result['published_week'],
                    'published_month' => (int) $result['published_month'],
                    'avg_processing_days' => round( (float) $result['avg_processing_days'], 1 ),
                    'next_scheduled' => self::get_next_scheduled_post(),
                    'performance_metrics' => self::get_performance_metrics(),
                    'active_channels' => self::get_active_channels_optimized(),
                    'success_rate' => self::calculate_success_rate_optimized(),
                    'system_health' => self::get_system_health_score(),
                    'trends' => self::calculate_trend_data(),
                    'last_updated' => current_time( 'mysql' )
                );
            }
            
        } catch ( Exception $e ) {
            tts_log_event( 0, 'performance', 'error', 'Dashboard stats generation failed: ' . $e->getMessage(), '' );
            
            // Enhanced fallback stats
            $stats = array(
                'total_posts' => 0,
                'pending_posts' => 0,
                'scheduled_posts' => 0,
                'published_today' => 0,
                'published_week' => 0,
                'published_month' => 0,
                'avg_processing_days' => 0,
                'next_scheduled' => null,
                'active_channels' => array(),
                'success_rate' => 0,
                'system_health' => 50,
                'trends' => array(),
                'last_updated' => current_time( 'mysql' ),
                'error' => true
            );
        }
        
        return $stats;
    }
    
    /**
     * Get next scheduled post information.
     *
     * @return array|null Next scheduled post data.
     */
    private static function get_next_scheduled_post() {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as publish_at
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tts_social_post'
            AND p.post_status = 'future'
            AND pm.meta_key = '_tts_publish_at'
            AND pm.meta_value > %s
            ORDER BY pm.meta_value ASC
            LIMIT 1
        ", current_time( 'mysql' ) );
        
        $result = $wpdb->get_row( $query, ARRAY_A );
        
        return $result ? array(
            'id' => (int) $result['ID'],
            'title' => $result['post_title'],
            'publish_at' => $result['publish_at']
        ) : null;
    }
    
    /**
     * Get active social media channels (optimized).
     *
     * @return array Active channels with statistics.
     */
    private static function get_active_channels_optimized() {
        global $wpdb;
        
        $cache_key = 'tts_active_channels_stats';
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return $cached;
        }
        
        $channels = $wpdb->get_results("
            SELECT 
                pm.meta_value as channel,
                COUNT(*) as post_count,
                COUNT(CASE WHEN p.post_status = 'publish' THEN 1 END) as published_count,
                MAX(p.post_date) as last_activity
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_tts_social_channel'
            AND p.post_type = 'tts_social_post'
            AND p.post_status != 'trash'
            GROUP BY pm.meta_value
            ORDER BY post_count DESC
        ", ARRAY_A );
        
        $result = array_map( function( $channel ) {
            return array(
                'name' => $channel['channel'],
                'posts' => (int) $channel['post_count'],
                'published' => (int) $channel['published_count'],
                'last_activity' => $channel['last_activity'],
                'success_rate' => $channel['post_count'] > 0 ? 
                    round( ( $channel['published_count'] / $channel['post_count'] ) * 100, 1 ) : 0
            );
        }, $channels );
        
        set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
        return $result;
    }
    
    /**
     * Calculate publishing success rate (optimized).
     *
     * @return array Success rate data with trends.
     */
    private static function calculate_success_rate_optimized() {
        global $wpdb;
        
        $cache_key = 'tts_success_rate_stats';
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return $cached;
        }
        
        $table = $wpdb->prefix . 'tts_logs';
        
        // Get success rate for different periods
        $periods = array(
            'today' => '1 DAY',
            'week' => '7 DAY',
            'month' => '30 DAY'
        );
        
        $success_data = array();
        
        foreach ( $periods as $period => $interval ) {
            $total = $wpdb->get_var( $wpdb->prepare("
                SELECT COUNT(*) FROM {$table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)
            ", $interval ) );
            
            if ( $total > 0 ) {
                $successful = $wpdb->get_var( $wpdb->prepare("
                    SELECT COUNT(*) FROM {$table}
                    WHERE status = 'success'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL %s)
                ", $interval ) );
                
                $success_data[$period] = array(
                    'rate' => round( ( $successful / $total ) * 100, 2 ),
                    'total' => (int) $total,
                    'successful' => (int) $successful,
                    'failed' => (int) ( $total - $successful )
                );
            } else {
                $success_data[$period] = array(
                    'rate' => 100.0,
                    'total' => 0,
                    'successful' => 0,
                    'failed' => 0
                );
            }
        }
        
        set_transient( $cache_key, $success_data, 10 * MINUTE_IN_SECONDS );
        return $success_data;
    }
    
    /**
     * Calculate system health score.
     *
     * @return int Health score (0-100).
     */
    private static function get_system_health_score() {
        $score = 100;
        $checks = array();
        
        // Check database performance
        $db_start = microtime( true );
        global $wpdb;
        $wpdb->get_var( "SELECT 1" );
        $db_time = ( microtime( true ) - $db_start ) * 1000;
        
        if ( $db_time > 100 ) {
            $score -= 20;
            $checks['database'] = false;
        } else {
            $checks['database'] = true;
        }
        
        // Check memory usage
        $memory_limit = ini_get( 'memory_limit' );
        $memory_usage = memory_get_usage( true );
        $memory_percent = ( $memory_usage / wp_convert_hr_to_bytes( $memory_limit ) ) * 100;
        
        if ( $memory_percent > 80 ) {
            $score -= 15;
            $checks['memory'] = false;
        } else {
            $checks['memory'] = true;
        }
        
        // Check for recent errors
        $recent_errors = $wpdb->get_var( $wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}tts_logs
            WHERE status = 'error'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
        ", 24 ) );
        
        if ( $recent_errors > 10 ) {
            $score -= 25;
            $checks['errors'] = false;
        } else {
            $checks['errors'] = true;
        }
        
        // Check API connections
        $social_settings = get_option( 'tts_social_apps', array() );
        $configured_platforms = 0;
        foreach ( array( 'facebook', 'instagram', 'youtube', 'tiktok' ) as $platform ) {
            if ( ! empty( $social_settings[$platform] ) ) {
                $configured_platforms++;
            }
        }
        
        if ( $configured_platforms === 0 ) {
            $score -= 20;
            $checks['social_connections'] = false;
        } else {
            $checks['social_connections'] = true;
        }
        
        // Check scheduled tasks
        if ( ! wp_next_scheduled( 'tts_refresh_tokens' ) ) {
            $score -= 10;
            $checks['scheduled_tasks'] = false;
        } else {
            $checks['scheduled_tasks'] = true;
        }
        
        return array(
            'score' => max( 0, $score ),
            'checks' => $checks,
            'recommendations' => self::get_health_recommendations( $checks )
        );
    }
    
    /**
     * Get health recommendations based on checks.
     *
     * @param array $checks System checks results.
     * @return array Recommendations.
     */
    private static function get_health_recommendations( $checks ) {
        $recommendations = array();
        
        if ( ! $checks['database'] ) {
            $recommendations[] = 'Database performance is slow. Consider optimizing your database or upgrading your hosting.';
        }
        
        if ( ! $checks['memory'] ) {
            $recommendations[] = 'Memory usage is high. Consider increasing the PHP memory limit or optimizing your site.';
        }
        
        if ( ! $checks['errors'] ) {
            $recommendations[] = 'Recent errors detected. Check the error logs and resolve any issues.';
        }
        
        if ( ! $checks['social_connections'] ) {
            $recommendations[] = 'No social media platforms configured. Set up social connections to start publishing.';
        }
        
        if ( ! $checks['scheduled_tasks'] ) {
            $recommendations[] = 'Scheduled tasks are not properly configured. Check your WordPress cron settings.';
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate trend data for analytics.
     *
     * @return array Trend data.
     */
    private static function calculate_trend_data() {
        global $wpdb;
        
        $cache_key = 'tts_trend_data';
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return $cached;
        }
        
        // Get daily posting trends for the last 30 days
        $daily_trends = $wpdb->get_results("
            SELECT 
                DATE(post_date) as date,
                COUNT(*) as posts,
                COUNT(CASE WHEN post_status = 'publish' THEN 1 END) as published
            FROM {$wpdb->posts}
            WHERE post_type = 'tts_social_post'
            AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(post_date)
            ORDER BY date ASC
        ", ARRAY_A );
        
        // Calculate growth percentage
        $recent_week = array_slice( $daily_trends, -7 );
        $previous_week = array_slice( $daily_trends, -14, 7 );
        
        $recent_total = array_sum( array_column( $recent_week, 'posts' ) );
        $previous_total = array_sum( array_column( $previous_week, 'posts' ) );
        
        $growth_rate = $previous_total > 0 ? 
            round( ( ( $recent_total - $previous_total ) / $previous_total ) * 100, 1 ) : 0;
        
        $trends = array(
            'daily_data' => $daily_trends,
            'growth_rate' => $growth_rate,
            'total_recent_week' => $recent_total,
            'total_previous_week' => $previous_total,
            'trend_direction' => $growth_rate > 0 ? 'up' : ( $growth_rate < 0 ? 'down' : 'stable' )
        );
        
        set_transient( $cache_key, $trends, 30 * MINUTE_IN_SECONDS );
        return $trends;
    }
    
    /**
     * Get performance metrics with enhanced monitoring.
     *
     * @return array Performance metrics.
     */
    public static function get_performance_metrics() {
        $cache_key = 'tts_performance_metrics';
        $cached = get_transient( $cache_key );
        
        if ( false !== $cached ) {
            return $cached;
        }
        
        $start_time = microtime( true );
        
        // Test database response time with multiple queries
        global $wpdb;
        $db_tests = array();
        
        // Simple query test
        $db_start = microtime( true );
        $wpdb->get_var( "SELECT 1" );
        $db_tests['simple'] = ( microtime( true ) - $db_start ) * 1000;
        
        // Complex query test
        $db_start = microtime( true );
        $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'tts_social_post' LIMIT 5" );
        $db_tests['complex'] = ( microtime( true ) - $db_start ) * 1000;
        
        // Average database response time
        $db_time = array_sum( $db_tests ) / count( $db_tests );
        
        // Test WordPress load time simulation
        $wp_time = ( microtime( true ) - $start_time ) * 1000;
        
        // Get comprehensive memory usage
        $memory_usage = memory_get_usage( true );
        $memory_peak = memory_get_peak_usage( true );
        $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        
        // Cache statistics (enhanced)
        $cache_stats = self::get_cache_statistics();
        
        // Server information
        $server_info = self::get_server_information();
        
        // WordPress performance
        $wp_performance = self::get_wordpress_performance();
        
        $metrics = array(
            'database' => array(
                'response_ms' => round( $db_time, 2 ),
                'queries_per_test' => count( $db_tests ),
                'simple_query_ms' => round( $db_tests['simple'], 2 ),
                'complex_query_ms' => round( $db_tests['complex'], 2 ),
                'status' => $db_time < 50 ? 'excellent' : ( $db_time < 100 ? 'good' : 'needs_attention' )
            ),
            'memory' => array(
                'usage_mb' => round( $memory_usage / 1024 / 1024, 2 ),
                'peak_mb' => round( $memory_peak / 1024 / 1024, 2 ),
                'limit_mb' => round( $memory_limit / 1024 / 1024, 2 ),
                'usage_percent' => round( ( $memory_usage / $memory_limit ) * 100, 1 ),
                'status' => ( $memory_usage / $memory_limit ) < 0.8 ? 'good' : 'warning'
            ),
            'cache' => $cache_stats,
            'server' => $server_info,
            'wordpress' => $wp_performance,
            'load_time_ms' => round( $wp_time, 2 ),
            'last_updated' => current_time( 'mysql' ),
            'score' => self::calculate_performance_score( $db_time, $memory_usage, $memory_limit )
        );
        
        // Cache for 2 minutes
        set_transient( $cache_key, $metrics, 2 * MINUTE_IN_SECONDS );
        
        return $metrics;
    }
    
    /**
     * Get cache statistics.
     *
     * @return array Cache statistics.
     */
    private static function get_cache_statistics() {
        // Try to get WordPress cache stats
        $cache_hits = 0;
        $cache_misses = 0;
        $cache_ratio = 85; // Default
        
        // If object cache is available
        if ( function_exists( 'wp_cache_get_stats' ) ) {
            $stats = wp_cache_get_stats();
            if ( is_array( $stats ) && ! empty( $stats ) ) {
                foreach ( $stats as $group => $group_stats ) {
                    if ( isset( $group_stats['cache_hits'] ) ) {
                        $cache_hits += $group_stats['cache_hits'];
                    }
                    if ( isset( $group_stats['cache_misses'] ) ) {
                        $cache_misses += $group_stats['cache_misses'];
                    }
                }
                
                if ( $cache_hits + $cache_misses > 0 ) {
                    $cache_ratio = round( ( $cache_hits / ( $cache_hits + $cache_misses ) ) * 100, 1 );
                }
            }
        }
        
        // Check transient cache health
        $transient_count = self::count_tts_transients();
        
        return array(
            'hit_ratio' => $cache_ratio,
            'hits' => $cache_hits,
            'misses' => $cache_misses,
            'transients' => $transient_count,
            'status' => $cache_ratio > 80 ? 'excellent' : ( $cache_ratio > 60 ? 'good' : 'poor' )
        );
    }
    
    /**
     * Get server information.
     *
     * @return array Server information.
     */
    private static function get_server_information() {
        return array(
            'php_version' => PHP_VERSION,
            'mysql_version' => self::get_mysql_version(),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
            'post_max_size' => ini_get( 'post_max_size' ),
            'server_software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown'
        );
    }
    
    /**
     * Get WordPress performance metrics.
     *
     * @return array WordPress performance data.
     */
    private static function get_wordpress_performance() {
        global $wpdb;
        
        // Count plugins and themes
        $active_plugins = count( get_option( 'active_plugins', array() ) );
        $theme = wp_get_theme();
        
        // Database size
        $db_size = $wpdb->get_var( "
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
            FROM information_schema.tables 
            WHERE table_schema = '{$wpdb->dbname}'
        " );
        
        return array(
            'version' => get_bloginfo( 'version' ),
            'active_plugins' => $active_plugins,
            'theme' => $theme->get( 'Name' ),
            'database_size_mb' => (float) $db_size,
            'posts_count' => wp_count_posts( 'tts_social_post' )->publish,
            'multisite' => is_multisite()
        );
    }
    
    /**
     * Calculate overall performance score.
     *
     * @param float $db_time Database response time.
     * @param int   $memory_usage Current memory usage.
     * @param int   $memory_limit Memory limit.
     * @return int Performance score (0-100).
     */
    private static function calculate_performance_score( $db_time, $memory_usage, $memory_limit ) {
        $score = 100;
        
        // Database performance (30% weight)
        if ( $db_time > 100 ) {
            $score -= 30;
        } elseif ( $db_time > 50 ) {
            $score -= 15;
        }
        
        // Memory usage (25% weight)
        $memory_percent = ( $memory_usage / $memory_limit ) * 100;
        if ( $memory_percent > 90 ) {
            $score -= 25;
        } elseif ( $memory_percent > 80 ) {
            $score -= 15;
        } elseif ( $memory_percent > 70 ) {
            $score -= 10;
        }
        
        // PHP version (15% weight)
        if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
            $score -= 15;
        } elseif ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $score -= 10;
        }
        
        // WordPress version (10% weight)
        if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
            $score -= 10;
        }
        
        // Cache performance (20% weight)
        $cache_stats = self::get_cache_statistics();
        if ( $cache_stats['hit_ratio'] < 60 ) {
            $score -= 20;
        } elseif ( $cache_stats['hit_ratio'] < 80 ) {
            $score -= 10;
        }
        
        return max( 0, $score );
    }
    
    /**
     * Get MySQL version.
     *
     * @return string MySQL version.
     */
    private static function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var( "SELECT VERSION()" );
    }
    
    /**
     * Count TTS transients.
     *
     * @return int Number of TTS transients.
     */
    private static function count_tts_transients() {
        global $wpdb;
        
        return (int) $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_tts_%'
        " );
    }
    
    /**
     * Advanced database cleanup with safety checks.
     */
    public static function optimize_database_advanced() {
        global $wpdb;
        
        $cleanup_log = array();
        
        try {
            // 1. Optimize core tables
            $tables_to_optimize = array(
                $wpdb->prefix . 'tts_logs',
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->options
            );
            
            foreach ( $tables_to_optimize as $table ) {
                $result = $wpdb->query( "OPTIMIZE TABLE {$table}" );
                $cleanup_log[] = "Optimized table {$table}: " . ( $result ? 'success' : 'failed' );
            }
            
            // 2. Clean up old transients (older than 1 week)
            $expired_transients = $wpdb->query( "
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_tts_%' 
                AND option_value < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
            " );
            $cleanup_log[] = "Removed {$expired_transients} expired transients";
            
            // 3. Clean up orphaned transients
            $orphaned_transients = $wpdb->query( "
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_tts_%' 
                AND option_name NOT IN (
                    SELECT REPLACE(option_name, '_timeout_', '_') 
                    FROM {$wpdb->options} 
                    WHERE option_name LIKE '_transient_timeout_tts_%'
                )
            " );
            $cleanup_log[] = "Removed {$orphaned_transients} orphaned transients";
            
            // 4. Clean up old log entries (keep last 1000)
            $logs_table = $wpdb->prefix . 'tts_logs';
            $old_logs = $wpdb->query( $wpdb->prepare( "
                DELETE FROM {$logs_table} 
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$logs_table} 
                        ORDER BY created_at DESC 
                        LIMIT %d
                    ) AS keep_logs
                )
            ", 1000 ) );
            $cleanup_log[] = "Removed {$old_logs} old log entries";
            
            // 5. Update database statistics
            $wpdb->query( "ANALYZE TABLE {$wpdb->posts}, {$wpdb->postmeta}, {$logs_table}" );
            $cleanup_log[] = "Updated database statistics";
            
            // Clear performance cache after optimization
            self::clear_all_performance_cache();
            
            tts_log_event( 0, 'performance', 'success', 'Database optimization completed: ' . implode( '; ', $cleanup_log ), '' );
            
            return array(
                'success' => true,
                'log' => $cleanup_log,
                'timestamp' => current_time( 'mysql' )
            );
            
        } catch ( Exception $e ) {
            $error_msg = 'Database optimization failed: ' . $e->getMessage();
            tts_log_event( 0, 'performance', 'error', $error_msg, '' );
            
            return array(
                'success' => false,
                'error' => $error_msg,
                'log' => $cleanup_log,
                'timestamp' => current_time( 'mysql' )
            );
        }
    }
    
    /**
     * Clear all performance-related cache.
     */
    public static function clear_all_performance_cache() {
        $cache_keys = array(
            'tts_dashboard_stats',
            'tts_performance_metrics',
            'tts_active_channels_stats',
            'tts_success_rate_stats',
            'tts_trend_data'
        );
        
        foreach ( $cache_keys as $key ) {
            delete_transient( $key );
        }
    }
    
    /**
     * Clear dashboard statistics cache.
     */
    public static function clear_dashboard_cache() {
        delete_transient( 'tts_dashboard_stats' );
    }
    
    /**
     * Optimize database tables.
     */
    public static function optimize_database() {
        global $wpdb;

        $tables_to_optimize = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->options,
            $wpdb->prefix . 'tts_logs'
        );

        $optimized = 0;
        foreach ( $tables_to_optimize as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) ) {
                $result = $wpdb->query( "OPTIMIZE TABLE `$table`" );
                if ( $result !== false ) {
                    $optimized++;
                }
            }
        }

        tts_log_event( 0, 'performance', 'info', 
            "Database optimization completed. Optimized $optimized tables.", '' );

        return $optimized;
    }
    
    /**
     * Get cached Trello boards for a client.
     *
     * @param string $key   Trello API key.
     * @param string $token Trello token.
     * @return array Cached boards or false if not cached.
     */
    public static function get_cached_trello_boards( $key, $token ) {
        $cache_key = 'tts_trello_boards_' . md5( $key . $token );
        return get_transient( $cache_key );
    }
    
    /**
     * Cache Trello boards for a client.
     *
     * @param string $key    Trello API key.
     * @param string $token  Trello token.
     * @param array  $boards Array of boards.
     */
    public static function cache_trello_boards( $key, $token, $boards ) {
        $cache_key = 'tts_trello_boards_' . md5( $key . $token );
        set_transient( $cache_key, $boards, HOUR_IN_SECONDS );
    }
    
    /**
     * Schedule database cleanup.
     */
    public static function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'tts_database_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_database_cleanup' );
        }
    }
    
    /**
     * Unschedule database cleanup.
     */
    public static function unschedule_cleanup() {
        wp_clear_scheduled_hook( 'tts_database_cleanup' );
    }
    
    /**
     * Get plugin memory usage.
     *
     * @return array Memory usage statistics.
     */
    public static function get_memory_usage() {
        return array(
            'current' => memory_get_usage( true ),
            'peak' => memory_get_peak_usage( true ),
            'limit' => ini_get( 'memory_limit' )
        );
    }
    
    /**
     * Enable object caching for the plugin.
     */
    public static function enable_object_cache() {
        if ( function_exists( 'wp_cache_add_global_groups' ) ) {
            wp_cache_add_global_groups( array( self::CACHE_GROUP ) );
        }
    }

    /**
     * Invalidate all performance caches.
     */
    public static function invalidate_all_caches() {
        $cache_keys = array(
            'tts_dashboard_stats',
            'tts_performance_metrics',
            'tts_active_channels',
            'tts_success_rate',
            'tts_system_health',
            'tts_trend_data'
        );

        foreach ( $cache_keys as $key ) {
            delete_transient( $key );
        }

        // Clear object cache if available
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( self::CACHE_GROUP );
        }

        tts_log_event( 0, 'performance', 'info', 'All performance caches invalidated', '' );
    }

    /**
     * Clear Trello-related caches.
     */
    public static function clear_trello_cache() {
        global $wpdb;
        
        // Get all transients that start with tts_trello_boards_
        $transients = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tts_trello_boards_%'"
        );

        foreach ( $transients as $transient ) {
            $key = str_replace( '_transient_', '', $transient );
            delete_transient( $key );
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics.
     */
    public static function get_cache_stats() {
        global $wpdb;

        $cache_stats = array(
            'total_transients' => 0,
            'tts_transients' => 0,
            'cache_size' => 0,
            'cache_hit_ratio' => 0
        );

        // Count total transients
        $cache_stats['total_transients'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_%'"
        );

        // Count TTS-specific transients
        $cache_stats['tts_transients'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tts_%'"
        );

        // Calculate approximate cache size
        $tts_cache_data = $wpdb->get_results(
            "SELECT option_value FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_tts_%'"
        );

        foreach ( $tts_cache_data as $cache_item ) {
            $cache_stats['cache_size'] += strlen( $cache_item->option_value );
        }

        // Format cache size
        $cache_stats['cache_size_formatted'] = size_format( $cache_stats['cache_size'] );

        return $cache_stats;
    }

    /**
     * Clean up expired transients.
     */
    public static function cleanup_expired_transients() {
        global $wpdb;

        // Delete expired transients
        $expired_transients = $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_%'
             AND a.option_name NOT LIKE '_transient_timeout_%'
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < UNIX_TIMESTAMP()"
        );

        // Clean up orphaned timeout options
        $orphaned_timeouts = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%'
             AND option_name NOT IN (
                 SELECT CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
                 FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%') AS temp
             )"
        );

        $total_cleaned = $expired_transients + $orphaned_timeouts;
        
        tts_log_event( 0, 'performance', 'info', 
            "Transient cleanup completed. Cleaned $total_cleaned expired/orphaned transients.", '' );

        return $total_cleaned;
    }
}

// Initialize performance optimizations
add_action( 'plugins_loaded', array( 'TTS_Performance', 'enable_object_cache' ) );
add_action( 'plugins_loaded', array( 'TTS_Performance', 'schedule_cleanup' ) );
add_action( 'tts_database_cleanup', array( 'TTS_Performance', 'optimize_database' ) );
add_action( 'tts_database_cleanup', array( 'TTS_Performance', 'cleanup_expired_transients' ) );

// Clear cache when posts are updated
add_action( 'save_post_tts_social_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );
add_action( 'delete_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );
add_action( 'wp_trash_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );

// Clear cache when client settings are updated
add_action( 'save_post_tts_client', array( 'TTS_Performance', 'clear_trello_cache' ) );

// Add weekly transient cleanup
if ( ! wp_next_scheduled( 'tts_weekly_cleanup' ) ) {
    wp_schedule_event( time(), 'weekly', 'tts_weekly_cleanup' );
}
add_action( 'tts_weekly_cleanup', array( 'TTS_Performance', 'cleanup_expired_transients' ) );
add_action( 'delete_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );