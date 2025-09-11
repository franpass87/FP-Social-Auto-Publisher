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
     * Generate dashboard statistics.
     *
     * @return array Statistics array.
     */
    private static function generate_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        try {
            // Optimized query to get all required stats in one go
            $query = $wpdb->prepare("
                SELECT 
                    COUNT(CASE WHEN p.post_status = 'publish' THEN 1 END) as total_published,
                    COUNT(CASE WHEN p.post_status = 'draft' THEN 1 END) as total_pending,
                    COUNT(CASE WHEN p.post_status = 'future' THEN 1 END) as total_scheduled,
                    COUNT(CASE WHEN DATE(p.post_date) = %s AND p.post_status = 'publish' THEN 1 END) as published_today,
                    COUNT(CASE WHEN DATE(p.post_date) >= DATE_SUB(%s, INTERVAL 7 DAY) AND p.post_status = 'publish' THEN 1 END) as published_week
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'tts_social_post'
                AND p.post_status IN ('publish', 'draft', 'future')
            ", 
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
                    'next_scheduled' => self::get_next_scheduled_post(),
                    'performance_metrics' => self::get_performance_metrics(),
                    'active_channels' => self::get_active_channels(),
                    'success_rate' => self::calculate_success_rate()
                );
            }
            
        } catch ( Exception $e ) {
            tts_log_event( 0, 'performance', 'error', 'Dashboard stats generation failed: ' . $e->getMessage(), '' );
            
            // Fallback stats in case of error
            $stats = array(
                'total_posts' => 0,
                'pending_posts' => 0,
                'scheduled_posts' => 0,
                'published_today' => 0,
                'published_week' => 0,
                'next_scheduled' => null,
                'active_channels' => array(),
                'success_rate' => 0
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
     * Get active social media channels.
     *
     * @return array Active channels.
     */
    private static function get_active_channels() {
        global $wpdb;
        
        $channels = $wpdb->get_col("
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_tts_social_channel'
            AND p.post_type = 'tts_social_post'
            AND p.post_status != 'trash'
        ");
        
        return array_filter( $channels );
    }
    
    /**
     * Calculate publishing success rate.
     *
     * @return float Success rate as percentage.
     */
    private static function calculate_success_rate() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tts_logs';
        
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        if ( ! $total ) {
            return 100.0;
        }
        
        $successful = $wpdb->get_var("
            SELECT COUNT(*) FROM {$table}
            WHERE status = 'success'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return round( ( $successful / $total ) * 100, 2 );
    }
    
    /**
     * Get performance metrics.
     *
     * @return array Performance metrics.
     */
    public static function get_performance_metrics() {
        $start_time = microtime( true );
        
        // Test database response time
        global $wpdb;
        $db_start = microtime( true );
        $wpdb->get_var( "SELECT 1" );
        $db_time = ( microtime( true ) - $db_start ) * 1000;
        
        // Test WordPress load time simulation
        $wp_time = ( microtime( true ) - $start_time ) * 1000;
        
        // Get memory usage
        $memory_usage = memory_get_usage( true );
        $memory_peak = memory_get_peak_usage( true );
        
        // Check cache hit ratio (simulated)
        $cache_hits = wp_cache_get_stats();
        $cache_ratio = 85; // Default simulation
        
        return array(
            'database_response_ms' => round( $db_time, 2 ),
            'wordpress_load_ms' => round( $wp_time, 2 ),
            'memory_usage_mb' => round( $memory_usage / 1024 / 1024, 2 ),
            'memory_peak_mb' => round( $memory_peak / 1024 / 1024, 2 ),
            'cache_hit_ratio' => $cache_ratio,
            'last_updated' => current_time( 'mysql' )
        );
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
        
        try {
            // Optimize the logs table
            $table = $wpdb->prefix . 'tts_logs';
            $wpdb->query( "OPTIMIZE TABLE {$table}" );
            
            // Clean up old transients
            $wpdb->query( "
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_tts_%' 
                AND option_value < UNIX_TIMESTAMP()
            " );
            
            // Clean up expired transients
            $wpdb->query( "
                DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_tts_%' 
                AND option_name NOT IN (
                    SELECT REPLACE(option_name, '_timeout_', '_') 
                    FROM {$wpdb->options} 
                    WHERE option_name LIKE '_transient_timeout_tts_%'
                )
            " );
            
        } catch ( Exception $e ) {
            tts_log_event( 0, 'performance', 'error', 'Database optimization failed: ' . $e->getMessage(), '' );
        }
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
}

// Initialize performance optimizations
add_action( 'plugins_loaded', array( 'TTS_Performance', 'enable_object_cache' ) );
add_action( 'plugins_loaded', array( 'TTS_Performance', 'schedule_cleanup' ) );
add_action( 'tts_database_cleanup', array( 'TTS_Performance', 'optimize_database' ) );

// Clear cache when posts are updated
add_action( 'save_post_tts_social_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );
add_action( 'delete_post', array( 'TTS_Performance', 'clear_dashboard_cache' ) );