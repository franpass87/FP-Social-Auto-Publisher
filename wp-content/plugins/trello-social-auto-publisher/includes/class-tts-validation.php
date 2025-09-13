<?php
/**
 * Enhanced validation utilities for Trello Social Auto Publisher.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Form validation and error handling utilities.
 */
class TTS_Validation {
    
    /**
     * Validation errors collection.
     *
     * @var array
     */
    private static $errors = array();
    
    /**
     * Validate Trello credentials.
     *
     * @param string $key   Trello API key.
     * @param string $token Trello token.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_trello_credentials( $key, $token ) {
        if ( empty( $key ) || empty( $token ) ) {
            self::add_error( __( 'Trello API key and token are required.', 'fp-publisher' ) );
            return false;
        }
        
        if ( strlen( $key ) !== 32 ) {
            self::add_error( __( 'Invalid Trello API key format.', 'fp-publisher' ) );
            return false;
        }
        
        if ( strlen( $token ) !== 64 ) {
            self::add_error( __( 'Invalid Trello token format.', 'fp-publisher' ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate social media channel settings.
     *
     * @param array $channels Selected channels.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_social_channels( $channels ) {
        if ( empty( $channels ) || ! is_array( $channels ) ) {
            self::add_error( __( 'At least one social media channel must be selected.', 'fp-publisher' ) );
            return false;
        }
        
        $allowed_channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
        foreach ( $channels as $channel ) {
            if ( ! in_array( $channel, $allowed_channels, true ) ) {
                self::add_error( sprintf( __( 'Invalid social media channel: %s', 'fp-publisher' ), esc_html( $channel ) ) );
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate publish date and time.
     *
     * @param string $datetime DateTime string.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_publish_datetime( $datetime ) {
        if ( empty( $datetime ) ) {
            return true; // Empty is allowed (immediate publish)
        }
        
        $timestamp = strtotime( $datetime );
        if ( false === $timestamp ) {
            self::add_error( __( 'Invalid date and time format.', 'fp-publisher' ) );
            return false;
        }
        
        // Check if the date is in the past
        if ( $timestamp < current_time( 'timestamp' ) ) {
            self::add_error( __( 'Publish date cannot be in the past.', 'fp-publisher' ) );
            return false;
        }
        
        // Check if the date is too far in the future (max 1 year)
        if ( $timestamp > current_time( 'timestamp' ) + YEAR_IN_SECONDS ) {
            self::add_error( __( 'Publish date cannot be more than 1 year in the future.', 'fp-publisher' ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Add validation error.
     *
     * @param string $message Error message.
     */
    public static function add_error( $message ) {
        self::$errors[] = sanitize_text_field( $message );
    }
    
    /**
     * Get all validation errors.
     *
     * @return array Array of error messages.
     */
    public static function get_errors() {
        return self::$errors;
    }
    
    /**
     * Check if there are any validation errors.
     *
     * @return bool True if errors exist, false otherwise.
     */
    public static function has_errors() {
        return ! empty( self::$errors );
    }
    
    /**
     * Clear all validation errors.
     */
    public static function clear_errors() {
        self::$errors = array();
    }
    
    /**
     * Display validation errors as admin notices.
     */
    public static function display_errors() {
        if ( self::has_errors() ) {
            foreach ( self::$errors as $error ) {
                echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
            }
            self::clear_errors();
        }
    }
    
    /**
     * Validate file upload for media.
     *
     * @param array $file Upload file array.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_media_upload( $file ) {
        if ( empty( $file['tmp_name'] ) ) {
            self::add_error( __( 'No file uploaded.', 'fp-publisher' ) );
            return false;
        }
        
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/avi' );
        $file_type = wp_check_filetype( $file['name'] );
        
        if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
            self::add_error( __( 'Unsupported file type. Please upload JPG, PNG, GIF, MP4, or AVI files.', 'fp-publisher' ) );
            return false;
        }
        
        // Check file size (max 100MB)
        $max_size = 100 * 1024 * 1024; // 100MB
        if ( $file['size'] > $max_size ) {
            self::add_error( __( 'File size too large. Maximum allowed size is 100MB.', 'fp-publisher' ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate client input data.
     *
     * @param array $data Input data array.
     * @return array Sanitized data.
     */
    public static function sanitize_client_data( $data ) {
        $sanitized = array();
        
        if ( isset( $data['trello_key'] ) ) {
            $sanitized['trello_key'] = sanitize_text_field( $data['trello_key'] );
        }
        
        if ( isset( $data['trello_token'] ) ) {
            $sanitized['trello_token'] = sanitize_text_field( $data['trello_token'] );
        }
        
        if ( isset( $data['trello_board'] ) ) {
            $sanitized['trello_board'] = sanitize_text_field( $data['trello_board'] );
        }
        
        if ( isset( $data['client_name'] ) ) {
            $sanitized['client_name'] = sanitize_text_field( $data['client_name'] );
        }
        
        if ( isset( $data['channels'] ) && is_array( $data['channels'] ) ) {
            $sanitized['channels'] = array_map( 'sanitize_text_field', $data['channels'] );
        }
        
        return $sanitized;
    }
    
    /**
     * Validate bulk action input.
     *
     * @param string $action Bulk action.
     * @param array  $post_ids Array of post IDs.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_bulk_action( $action, $post_ids ) {
        $allowed_actions = array( 'approve', 'delete', 'schedule' );
        
        if ( ! in_array( $action, $allowed_actions, true ) ) {
            self::add_error( __( 'Invalid bulk action specified.', 'fp-publisher' ) );
            return false;
        }
        
        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            self::add_error( __( 'No posts selected for bulk action.', 'fp-publisher' ) );
            return false;
        }
        
        // Limit bulk actions to prevent performance issues
        if ( count( $post_ids ) > 100 ) {
            self::add_error( __( 'Too many posts selected. Maximum 100 posts allowed for bulk actions.', 'fp-publisher' ) );
            return false;
        }
        
        // Validate all post IDs are numeric
        foreach ( $post_ids as $post_id ) {
            if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
                self::add_error( __( 'Invalid post ID in selection.', 'fp-publisher' ) );
                return false;
            }
        }
        
        return true;
    }
}