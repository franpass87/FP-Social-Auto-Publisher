<?php
/**
 * Advanced Multi-Level Caching System
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TTS_Cache_Manager class for sophisticated multi-level caching
 */
class TTS_Cache_Manager {

    private $cache_prefix = 'tts_cache_';
    private $cache_groups = array();
    private $stats = array();

    /**
     * Cache levels
     */
    const LEVEL_MEMORY = 1;     // In-memory (object cache)
    const LEVEL_TRANSIENT = 2;  // WordPress transients
    const LEVEL_FILE = 3;       // File-based cache
    const LEVEL_DATABASE = 4;   // Database cache

    /**
     * Cache TTL presets
     */
    const TTL_MINUTE = 60;
    const TTL_HOUR = 3600;
    const TTL_DAY = 86400;
    const TTL_WEEK = 604800;
    const TTL_MONTH = 2592000;

    /**
     * Initialize cache manager
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_clear_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_tts_get_cache_stats', array( $this, 'ajax_get_cache_stats' ) );
        add_action( 'wp_ajax_tts_optimize_cache', array( $this, 'ajax_optimize_cache' ) );
        
        // Initialize cache groups
        $this->init_cache_groups();
        
        // Schedule cache cleanup
        add_action( 'tts_hourly_cache_cleanup', array( $this, 'cleanup_expired_cache' ) );
        if ( ! wp_next_scheduled( 'tts_hourly_cache_cleanup' ) ) {
            wp_schedule_event( time(), 'hourly', 'tts_hourly_cache_cleanup' );
        }
        
        // Initialize stats
        $this->stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        );
    }

    /**
     * Initialize cache groups with configurations
     */
    private function init_cache_groups() {
        $this->cache_groups = array(
            'api_responses' => array(
                'ttl' => self::TTL_HOUR,
                'levels' => array( self::LEVEL_MEMORY, self::LEVEL_TRANSIENT ),
                'compress' => true,
                'serialize' => true
            ),
            'user_data' => array(
                'ttl' => self::TTL_DAY,
                'levels' => array( self::LEVEL_MEMORY, self::LEVEL_DATABASE ),
                'compress' => false,
                'serialize' => true
            ),
            'settings' => array(
                'ttl' => self::TTL_WEEK,
                'levels' => array( self::LEVEL_MEMORY, self::LEVEL_TRANSIENT, self::LEVEL_FILE ),
                'compress' => false,
                'serialize' => true
            ),
            'analytics' => array(
                'ttl' => self::TTL_HOUR * 6,
                'levels' => array( self::LEVEL_TRANSIENT, self::LEVEL_FILE ),
                'compress' => true,
                'serialize' => true
            ),
            'media_metadata' => array(
                'ttl' => self::TTL_DAY * 7,
                'levels' => array( self::LEVEL_MEMORY, self::LEVEL_FILE ),
                'compress' => true,
                'serialize' => true
            ),
            'templates' => array(
                'ttl' => self::TTL_DAY,
                'levels' => array( self::LEVEL_MEMORY, self::LEVEL_FILE ),
                'compress' => false,
                'serialize' => false
            )
        );
    }

    /**
     * Get cached data with multi-level fallback
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false Cached data or false if not found
     */
    public function get( $key, $group = 'default' ) {
        $full_key = $this->get_full_key( $key, $group );
        $config = $this->get_group_config( $group );
        
        // Try each cache level in order
        foreach ( $config['levels'] as $level ) {
            $data = $this->get_from_level( $full_key, $level );
            
            if ( $data !== false ) {
                $this->stats['hits']++;
                
                // Backfill higher priority levels
                $this->backfill_cache_levels( $full_key, $data, $level, $config );
                
                return $this->unprocess_data( $data, $config );
            }
        }
        
        $this->stats['misses']++;
        return false;
    }

    /**
     * Set cached data across multiple levels
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $group Cache group
     * @param int|null $ttl TTL override
     * @return bool Success status
     */
    public function set( $key, $data, $group = 'default', $ttl = null ) {
        $full_key = $this->get_full_key( $key, $group );
        $config = $this->get_group_config( $group );
        $processed_data = $this->process_data( $data, $config );
        $cache_ttl = $ttl ?: $config['ttl'];
        
        $success = true;
        
        // Set data in all configured levels
        foreach ( $config['levels'] as $level ) {
            if ( ! $this->set_to_level( $full_key, $processed_data, $level, $cache_ttl ) ) {
                $success = false;
            }
        }
        
        if ( $success ) {
            $this->stats['sets']++;
        }
        
        return $success;
    }

    /**
     * Delete cached data from all levels
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success status
     */
    public function delete( $key, $group = 'default' ) {
        $full_key = $this->get_full_key( $key, $group );
        $config = $this->get_group_config( $group );
        
        $success = true;
        
        foreach ( $config['levels'] as $level ) {
            if ( ! $this->delete_from_level( $full_key, $level ) ) {
                $success = false;
            }
        }
        
        if ( $success ) {
            $this->stats['deletes']++;
        }
        
        return $success;
    }

    /**
     * Get data from specific cache level
     */
    private function get_from_level( $key, $level ) {
        switch ( $level ) {
            case self::LEVEL_MEMORY:
                return wp_cache_get( $key, 'tts_cache' );
                
            case self::LEVEL_TRANSIENT:
                return get_transient( $key );
                
            case self::LEVEL_FILE:
                return $this->get_from_file_cache( $key );
                
            case self::LEVEL_DATABASE:
                return $this->get_from_database_cache( $key );
                
            default:
                return false;
        }
    }

    /**
     * Set data to specific cache level
     */
    private function set_to_level( $key, $data, $level, $ttl ) {
        switch ( $level ) {
            case self::LEVEL_MEMORY:
                return wp_cache_set( $key, $data, 'tts_cache', $ttl );
                
            case self::LEVEL_TRANSIENT:
                return set_transient( $key, $data, $ttl );
                
            case self::LEVEL_FILE:
                return $this->set_to_file_cache( $key, $data, $ttl );
                
            case self::LEVEL_DATABASE:
                return $this->set_to_database_cache( $key, $data, $ttl );
                
            default:
                return false;
        }
    }

    /**
     * Delete data from specific cache level
     */
    private function delete_from_level( $key, $level ) {
        switch ( $level ) {
            case self::LEVEL_MEMORY:
                return wp_cache_delete( $key, 'tts_cache' );
                
            case self::LEVEL_TRANSIENT:
                return delete_transient( $key );
                
            case self::LEVEL_FILE:
                return $this->delete_from_file_cache( $key );
                
            case self::LEVEL_DATABASE:
                return $this->delete_from_database_cache( $key );
                
            default:
                return false;
        }
    }

    /**
     * File cache operations
     */
    private function get_cache_directory() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/tts-cache/';
        
        if ( ! file_exists( $cache_dir ) ) {
            wp_mkdir_p( $cache_dir );
            
            // Add security files
            file_put_contents( $cache_dir . '.htaccess', 'deny from all' );
            file_put_contents( $cache_dir . 'index.php', '<?php // Silence is golden' );
        }
        
        return $cache_dir;
    }

    private function get_from_file_cache( $key ) {
        $file_path = $this->get_cache_directory() . md5( $key ) . '.cache';
        
        if ( ! file_exists( $file_path ) ) {
            return false;
        }
        
        $cache_data = json_decode( file_get_contents( $file_path ), true );
        
        if ( ! $cache_data || $cache_data['expires'] < time() ) {
            unlink( $file_path );
            return false;
        }
        
        return $cache_data['data'];
    }

    private function set_to_file_cache( $key, $data, $ttl ) {
        $file_path = $this->get_cache_directory() . md5( $key ) . '.cache';
        
        $cache_data = array(
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        );
        
        return file_put_contents( $file_path, wp_json_encode( $cache_data ) ) !== false;
    }

    private function delete_from_file_cache( $key ) {
        $file_path = $this->get_cache_directory() . md5( $key ) . '.cache';
        
        if ( file_exists( $file_path ) ) {
            return unlink( $file_path );
        }
        
        return true;
    }

    /**
     * Database cache operations
     */
    private function get_from_database_cache( $key ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT cache_value, expires_at FROM $table_name WHERE cache_key = %s",
                $key
            )
        );
        
        if ( ! $result || strtotime( $result->expires_at ) < time() ) {
            // Clean up expired entry
            if ( $result ) {
                $wpdb->delete( $table_name, array( 'cache_key' => $key ) );
            }
            return false;
        }
        
        return maybe_unserialize( $result->cache_value );
    }

    private function set_to_database_cache( $key, $data, $ttl ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        
        // Ensure table exists
        $this->ensure_cache_table();
        
        $expires_at = date( 'Y-m-d H:i:s', time() + $ttl );
        
        return $wpdb->replace(
            $table_name,
            array(
                'cache_key' => $key,
                'cache_value' => maybe_serialize( $data ),
                'expires_at' => $expires_at,
                'created_at' => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%s', '%s' )
        ) !== false;
    }

    private function delete_from_database_cache( $key ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        
        return $wpdb->delete(
            $table_name,
            array( 'cache_key' => $key ),
            array( '%s' )
        ) !== false;
    }

    /**
     * Ensure cache table exists
     */
    private function ensure_cache_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cache_key),
            INDEX idx_expires_at (expires_at)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Backfill higher priority cache levels
     */
    private function backfill_cache_levels( $key, $data, $found_level, $config ) {
        $backfill_levels = array();
        
        foreach ( $config['levels'] as $level ) {
            if ( $level === $found_level ) {
                break;
            }
            $backfill_levels[] = $level;
        }
        
        foreach ( $backfill_levels as $level ) {
            $this->set_to_level( $key, $data, $level, $config['ttl'] );
        }
    }

    /**
     * Process data before caching
     */
    private function process_data( $data, $config ) {
        $processed = $data;
        
        if ( $config['serialize'] ) {
            $processed = maybe_serialize( $processed );
        }
        
        if ( $config['compress'] && function_exists( 'gzcompress' ) ) {
            $processed = gzcompress( $processed );
        }
        
        return $processed;
    }

    /**
     * Unprocess data after retrieving from cache
     */
    private function unprocess_data( $data, $config ) {
        $processed = $data;
        
        if ( $config['compress'] && function_exists( 'gzuncompress' ) ) {
            $processed = gzuncompress( $processed );
        }
        
        if ( $config['serialize'] ) {
            $processed = maybe_unserialize( $processed );
        }
        
        return $processed;
    }

    /**
     * Get full cache key
     */
    private function get_full_key( $key, $group ) {
        return $this->cache_prefix . $group . '_' . $key;
    }

    /**
     * Get cache group configuration
     */
    private function get_group_config( $group ) {
        if ( isset( $this->cache_groups[ $group ] ) ) {
            return $this->cache_groups[ $group ];
        }
        
        // Default configuration
        return array(
            'ttl' => self::TTL_HOUR,
            'levels' => array( self::LEVEL_MEMORY, self::LEVEL_TRANSIENT ),
            'compress' => false,
            'serialize' => true
        );
    }

    /**
     * Clear cache by group or pattern
     *
     * @param string|null $group Cache group to clear (null for all)
     * @param string|null $pattern Key pattern to match
     * @return int Number of keys cleared
     */
    public function clear_cache( $group = null, $pattern = null ) {
        $cleared = 0;
        
        if ( $group ) {
            $config = $this->get_group_config( $group );
            
            foreach ( $config['levels'] as $level ) {
                $cleared += $this->clear_level_by_group( $level, $group, $pattern );
            }
        } else {
            // Clear all groups
            foreach ( $this->cache_groups as $group_name => $config ) {
                foreach ( $config['levels'] as $level ) {
                    $cleared += $this->clear_level_by_group( $level, $group_name, $pattern );
                }
            }
        }
        
        return $cleared;
    }

    /**
     * Clear cache level by group
     */
    private function clear_level_by_group( $level, $group, $pattern = null ) {
        $prefix = $this->cache_prefix . $group . '_';
        
        switch ( $level ) {
            case self::LEVEL_MEMORY:
                return $this->clear_memory_cache( $prefix, $pattern );
                
            case self::LEVEL_TRANSIENT:
                return $this->clear_transient_cache( $prefix, $pattern );
                
            case self::LEVEL_FILE:
                return $this->clear_file_cache( $prefix, $pattern );
                
            case self::LEVEL_DATABASE:
                return $this->clear_database_cache( $prefix, $pattern );
                
            default:
                return 0;
        }
    }

    private function clear_memory_cache( $prefix, $pattern ) {
        // WordPress object cache doesn't provide pattern clearing
        // This would need to be implemented with specific cache backends
        return 0;
    }

    private function clear_transient_cache( $prefix, $pattern ) {
        global $wpdb;
        
        $search_pattern = $pattern ? $prefix . $pattern : $prefix . '%';
        
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . $search_pattern
            )
        );
        
        $cleared = 0;
        foreach ( $transients as $transient ) {
            $key = str_replace( '_transient_', '', $transient );
            if ( delete_transient( $key ) ) {
                $cleared++;
            }
        }
        
        return $cleared;
    }

    private function clear_file_cache( $prefix, $pattern ) {
        $cache_dir = $this->get_cache_directory();
        $files = glob( $cache_dir . '*.cache' );
        $cleared = 0;
        
        foreach ( $files as $file ) {
            $cache_data = json_decode( file_get_contents( $file ), true );
            if ( $cache_data && isset( $cache_data['key'] ) ) {
                $key = $cache_data['key'];
                
                if ( strpos( $key, $prefix ) === 0 ) {
                    if ( ! $pattern || strpos( $key, $pattern ) !== false ) {
                        unlink( $file );
                        $cleared++;
                    }
                }
            }
        }
        
        return $cleared;
    }

    private function clear_database_cache( $prefix, $pattern ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        $search_pattern = $pattern ? $prefix . '%' . $pattern . '%' : $prefix . '%';
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE cache_key LIKE %s",
                $search_pattern
            )
        );
        
        return $result ?: 0;
    }

    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        $hit_rate = $this->stats['hits'] + $this->stats['misses'] > 0 
            ? round( ( $this->stats['hits'] / ( $this->stats['hits'] + $this->stats['misses'] ) ) * 100, 2 )
            : 0;
        
        $stats = array(
            'hit_rate' => $hit_rate,
            'operations' => $this->stats,
            'groups' => array(),
            'levels' => array(),
            'memory_usage' => $this->get_memory_usage(),
            'file_cache_size' => $this->get_file_cache_size(),
            'database_cache_size' => $this->get_database_cache_size()
        );
        
        // Get stats by group
        foreach ( $this->cache_groups as $group => $config ) {
            $stats['groups'][ $group ] = array(
                'ttl' => $config['ttl'],
                'levels' => $config['levels'],
                'entries' => $this->count_group_entries( $group )
            );
        }
        
        // Get stats by level
        $stats['levels'] = array(
            'memory' => array(
                'available' => $this->is_object_cache_available(),
                'entries' => 0 // Hard to count object cache entries
            ),
            'transient' => array(
                'entries' => $this->count_transient_entries()
            ),
            'file' => array(
                'entries' => $this->count_file_entries(),
                'size_mb' => round( $this->get_file_cache_size() / 1024 / 1024, 2 )
            ),
            'database' => array(
                'entries' => $this->count_database_entries(),
                'size_mb' => round( $this->get_database_cache_size() / 1024 / 1024, 2 )
            )
        );
        
        return $stats;
    }

    /**
     * Count entries by group
     */
    private function count_group_entries( $group ) {
        $config = $this->get_group_config( $group );
        $prefix = $this->cache_prefix . $group . '_';
        $total = 0;
        
        foreach ( $config['levels'] as $level ) {
            switch ( $level ) {
                case self::LEVEL_TRANSIENT:
                    $total += $this->count_transient_entries( $prefix );
                    break;
                case self::LEVEL_FILE:
                    $total += $this->count_file_entries( $prefix );
                    break;
                case self::LEVEL_DATABASE:
                    $total += $this->count_database_entries( $prefix );
                    break;
            }
        }
        
        return $total;
    }

    private function count_transient_entries( $prefix = null ) {
        global $wpdb;
        
        $pattern = $prefix ? '_transient_' . $prefix . '%' : '_transient_' . $this->cache_prefix . '%';
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
    }

    private function count_file_entries( $prefix = null ) {
        $cache_dir = $this->get_cache_directory();
        $files = glob( $cache_dir . '*.cache' );
        
        if ( ! $prefix ) {
            return count( $files );
        }
        
        $count = 0;
        foreach ( $files as $file ) {
            $cache_data = json_decode( file_get_contents( $file ), true );
            if ( $cache_data && isset( $cache_data['key'] ) ) {
                if ( strpos( $cache_data['key'], $prefix ) === 0 ) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    private function count_database_entries( $prefix = null ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        
        if ( $prefix ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE cache_key LIKE %s",
                    $prefix . '%'
                )
            );
        }
        
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
    }

    /**
     * Get memory usage info
     */
    private function get_memory_usage() {
        return array(
            'current' => memory_get_usage( true ),
            'peak' => memory_get_peak_usage( true ),
            'limit' => $this->get_memory_limit()
        );
    }

    private function get_memory_limit() {
        $limit = ini_get( 'memory_limit' );
        
        if ( $limit === '-1' ) {
            return -1;
        }
        
        $unit = strtolower( substr( $limit, -1 ) );
        $value = intval( $limit );
        
        switch ( $unit ) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    private function get_file_cache_size() {
        $cache_dir = $this->get_cache_directory();
        $files = glob( $cache_dir . '*.cache' );
        $total_size = 0;
        
        foreach ( $files as $file ) {
            $total_size += filesize( $file );
        }
        
        return $total_size;
    }

    private function get_database_cache_size() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_cache';
        
        $result = $wpdb->get_row(
            "SELECT 
                SUM(LENGTH(cache_key) + LENGTH(cache_value) + 32) as total_size
             FROM $table_name"
        );
        
        return $result ? (int) $result->total_size : 0;
    }

    private function is_object_cache_available() {
        return function_exists( 'wp_cache_set' ) && wp_using_ext_object_cache();
    }

    /**
     * Optimize cache performance
     */
    public function optimize_cache() {
        $results = array();
        
        // Clean expired entries
        $results['expired_cleaned'] = $this->cleanup_expired_cache();
        
        // Compress file cache
        $results['files_compressed'] = $this->compress_file_cache();
        
        // Optimize database cache
        $results['database_optimized'] = $this->optimize_database_cache();
        
        // Clear empty cache groups
        $results['empty_groups_cleared'] = $this->clear_empty_cache_groups();
        
        return $results;
    }

    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        $cleaned = 0;
        
        // Clean file cache
        $cache_dir = $this->get_cache_directory();
        $files = glob( $cache_dir . '*.cache' );
        
        foreach ( $files as $file ) {
            $cache_data = json_decode( file_get_contents( $file ), true );
            
            if ( ! $cache_data || $cache_data['expires'] < time() ) {
                unlink( $file );
                $cleaned++;
            }
        }
        
        // Clean database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'tts_cache';
        
        $db_cleaned = $wpdb->query(
            "DELETE FROM $table_name WHERE expires_at < NOW()"
        );
        
        $cleaned += $db_cleaned ?: 0;
        
        return $cleaned;
    }

    private function compress_file_cache() {
        // Implementation for compressing file cache
        return 0;
    }

    private function optimize_database_cache() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tts_cache';
        
        // Optimize table
        $wpdb->query( "OPTIMIZE TABLE $table_name" );
        
        return true;
    }

    private function clear_empty_cache_groups() {
        // Implementation for clearing empty groups
        return 0;
    }

    /**
     * AJAX handlers
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'tts_cache_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $group = sanitize_text_field( $_POST['group'] ?? '' );
        $pattern = sanitize_text_field( $_POST['pattern'] ?? '' );
        
        $cleared = $this->clear_cache( $group ?: null, $pattern ?: null );
        
        wp_send_json_success( array(
            'message' => sprintf( __( 'Cleared %d cache entries', 'fp-publisher' ), $cleared ),
            'cleared' => $cleared
        ));
    }

    public function ajax_get_cache_stats() {
        check_ajax_referer( 'tts_cache_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $stats = $this->get_cache_stats();
        wp_send_json_success( $stats );
    }

    public function ajax_optimize_cache() {
        check_ajax_referer( 'tts_cache_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $results = $this->optimize_cache();
        
        wp_send_json_success( array(
            'message' => __( 'Cache optimization completed', 'fp-publisher' ),
            'results' => $results
        ));
    }
}

// Initialize cache manager
$GLOBALS['tts_cache'] = new TTS_Cache_Manager();