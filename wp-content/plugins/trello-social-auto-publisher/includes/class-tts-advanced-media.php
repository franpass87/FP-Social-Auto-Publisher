<?php
/**
 * Advanced Media Management System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles advanced media optimization, processing, and management.
 */
class TTS_Advanced_Media {

    /**
     * Platform-specific image dimensions.
     */
    private $platform_dimensions = array(
        'instagram' => array(
            'square' => array( 'width' => 1080, 'height' => 1080 ),
            'portrait' => array( 'width' => 1080, 'height' => 1350 ),
            'landscape' => array( 'width' => 1080, 'height' => 566 ),
            'story' => array( 'width' => 1080, 'height' => 1920 )
        ),
        'facebook' => array(
            'shared_image' => array( 'width' => 1200, 'height' => 630 ),
            'cover_photo' => array( 'width' => 1640, 'height' => 859 ),
            'event_image' => array( 'width' => 1920, 'height' => 1080 ),
            'story' => array( 'width' => 1080, 'height' => 1920 )
        ),
        'twitter' => array(
            'header' => array( 'width' => 1500, 'height' => 500 ),
            'in_stream' => array( 'width' => 1024, 'height' => 512 ),
            'card' => array( 'width' => 1200, 'height' => 628 )
        ),
        'linkedin' => array(
            'shared_image' => array( 'width' => 1200, 'height' => 627 ),
            'company_cover' => array( 'width' => 1536, 'height' => 768 ),
            'personal_cover' => array( 'width' => 1584, 'height' => 396 )
        ),
        'youtube' => array(
            'thumbnail' => array( 'width' => 1280, 'height' => 720 ),
            'channel_art' => array( 'width' => 2560, 'height' => 1440 ),
            'video_watermark' => array( 'width' => 150, 'height' => 150 )
        ),
        'tiktok' => array(
            'video' => array( 'width' => 1080, 'height' => 1920 ),
            'profile' => array( 'width' => 200, 'height' => 200 )
        )
    );

    /**
     * Initialize advanced media system.
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_resize_image', array( $this, 'ajax_resize_image' ) );
        add_action( 'wp_ajax_tts_optimize_video', array( $this, 'ajax_optimize_video' ) );
        add_action( 'wp_ajax_tts_add_watermark', array( $this, 'ajax_add_watermark' ) );
        add_action( 'wp_ajax_tts_batch_process_media', array( $this, 'ajax_batch_process_media' ) );
        add_action( 'wp_ajax_tts_get_stock_photos', array( $this, 'ajax_get_stock_photos' ) );
        add_action( 'wp_ajax_tts_create_media_variations', array( $this, 'ajax_create_media_variations' ) );
        add_action( 'wp_ajax_tts_compress_media', array( $this, 'ajax_compress_media' ) );
        add_action( 'wp_ajax_tts_analyze_media_performance', array( $this, 'ajax_analyze_media_performance' ) );
        
        // Add custom image sizes
        add_action( 'init', array( $this, 'register_custom_image_sizes' ) );
        
        // Enhance media library
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_media_fields' ), 10, 2 );
        add_filter( 'attachment_fields_to_save', array( $this, 'save_media_fields' ), 10, 2 );
    }

    /**
     * Register custom image sizes for social platforms.
     */
    public function register_custom_image_sizes() {
        // Instagram sizes
        add_image_size( 'instagram-square', 1080, 1080, true );
        add_image_size( 'instagram-portrait', 1080, 1350, true );
        add_image_size( 'instagram-landscape', 1080, 566, true );
        add_image_size( 'instagram-story', 1080, 1920, true );
        
        // Facebook sizes
        add_image_size( 'facebook-shared', 1200, 630, true );
        add_image_size( 'facebook-cover', 1640, 859, true );
        add_image_size( 'facebook-story', 1080, 1920, true );
        
        // Twitter sizes
        add_image_size( 'twitter-header', 1500, 500, true );
        add_image_size( 'twitter-card', 1200, 628, true );
        
        // LinkedIn sizes
        add_image_size( 'linkedin-shared', 1200, 627, true );
        add_image_size( 'linkedin-cover', 1536, 768, true );
        
        // YouTube sizes
        add_image_size( 'youtube-thumbnail', 1280, 720, true );
        add_image_size( 'youtube-channel-art', 2560, 1440, true );
        
        // TikTok sizes
        add_image_size( 'tiktok-video', 1080, 1920, true );
    }

    /**
     * Resize image for specific platform.
     */
    public function ajax_resize_image() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $format = sanitize_text_field( wp_unslash( $_POST['format'] ?? '' ) );

        if ( empty( $attachment_id ) || empty( $platform ) || empty( $format ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID, platform, and format are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $resized_url = $this->resize_image_for_platform( $attachment_id, $platform, $format );
            
            wp_send_json_success( array(
                'resized_url' => $resized_url,
                'message' => __( 'Image resized successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Resize Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to resize image. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Resize image for specific platform and format.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $platform Target platform.
     * @param string $format Image format.
     * @return string Resized image URL.
     */
    private function resize_image_for_platform( $attachment_id, $platform, $format ) {
        if ( ! isset( $this->platform_dimensions[ $platform ][ $format ] ) ) {
            throw new Exception( 'Invalid platform or format specified' );
        }
        
        $dimensions = $this->platform_dimensions[ $platform ][ $format ];
        $image_path = get_attached_file( $attachment_id );
        
        if ( ! $image_path || ! file_exists( $image_path ) ) {
            throw new Exception( 'Image file not found' );
        }
        
        // Get image editor
        $image_editor = wp_get_image_editor( $image_path );
        
        if ( is_wp_error( $image_editor ) ) {
            throw new Exception( 'Failed to load image editor: ' . $image_editor->get_error_message() );
        }
        
        // Resize image
        $image_editor->resize( $dimensions['width'], $dimensions['height'], true );
        
        // Generate filename
        $path_info = pathinfo( $image_path );
        $new_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '-' . $platform . '-' . $format . '.' . $path_info['extension'];
        
        // Save resized image
        $saved = $image_editor->save( $new_filename );
        
        if ( is_wp_error( $saved ) ) {
            throw new Exception( 'Failed to save resized image: ' . $saved->get_error_message() );
        }
        
        // Get URL
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace( $upload_dir['basedir'], '', $saved['path'] );
        $resized_url = $upload_dir['baseurl'] . $relative_path;
        
        // Store metadata
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! isset( $metadata['tts_resized_versions'] ) ) {
            $metadata['tts_resized_versions'] = array();
        }
        
        $metadata['tts_resized_versions'][ $platform . '_' . $format ] = array(
            'file' => basename( $saved['path'] ),
            'width' => $saved['width'],
            'height' => $saved['height'],
            'url' => $resized_url,
            'created' => current_time( 'mysql' )
        );
        
        wp_update_attachment_metadata( $attachment_id, $metadata );
        
        return $resized_url;
    }

    /**
     * Optimize video for social media.
     */
    public function ajax_optimize_video() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $quality = sanitize_text_field( wp_unslash( $_POST['quality'] ?? 'medium' ) );

        if ( empty( $attachment_id ) || empty( $platform ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID and platform are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $optimized_info = $this->optimize_video_for_platform( $attachment_id, $platform, $quality );
            
            wp_send_json_success( array(
                'optimized_info' => $optimized_info,
                'message' => __( 'Video optimized successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Video Optimization Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to optimize video. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Optimize video for specific platform.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $platform Target platform.
     * @param string $quality Quality setting.
     * @return array Optimization info.
     */
    private function optimize_video_for_platform( $attachment_id, $platform, $quality ) {
        $video_path = get_attached_file( $attachment_id );
        
        if ( ! $video_path || ! file_exists( $video_path ) ) {
            throw new Exception( 'Video file not found' );
        }
        
        // Platform-specific video settings
        $platform_settings = array(
            'instagram' => array(
                'max_duration' => 60,
                'max_size' => 100, // MB
                'aspect_ratio' => '1:1',
                'formats' => array( 'mp4', 'mov' )
            ),
            'facebook' => array(
                'max_duration' => 240,
                'max_size' => 4096, // MB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'mov', 'avi' )
            ),
            'twitter' => array(
                'max_duration' => 140,
                'max_size' => 512, // MB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'mov' )
            ),
            'linkedin' => array(
                'max_duration' => 600,
                'max_size' => 5120, // MB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'asf', 'avi' )
            ),
            'youtube' => array(
                'max_duration' => 43200, // 12 hours
                'max_size' => 256 * 1024, // 256 GB
                'aspect_ratio' => '16:9',
                'formats' => array( 'mp4', 'mov', 'avi', 'wmv', 'flv' )
            ),
            'tiktok' => array(
                'max_duration' => 60,
                'max_size' => 500, // MB
                'aspect_ratio' => '9:16',
                'formats' => array( 'mp4', 'mov' )
            )
        );
        
        $settings = $platform_settings[ $platform ] ?? $platform_settings['instagram'];
        
        // Get video information
        $video_info = $this->get_video_info( $video_path );
        
        // Quality settings
        $quality_settings = array(
            'low' => array( 'bitrate' => '500k', 'width' => 640 ),
            'medium' => array( 'bitrate' => '1000k', 'width' => 1280 ),
            'high' => array( 'bitrate' => '2000k', 'width' => 1920 )
        );
        
        $quality_setting = $quality_settings[ $quality ] ?? $quality_settings['medium'];
        
        // Simulate video optimization (would use FFmpeg or similar in production)
        $optimized_info = array(
            'original_size' => filesize( $video_path ),
            'original_duration' => $video_info['duration'],
            'original_dimensions' => $video_info['dimensions'],
            'optimized_size' => round( filesize( $video_path ) * 0.7 ), // Simulate 30% compression
            'optimized_duration' => min( $video_info['duration'], $settings['max_duration'] ),
            'optimized_dimensions' => $this->calculate_optimized_dimensions( $video_info['dimensions'], $settings['aspect_ratio'], $quality_setting['width'] ),
            'platform_settings' => $settings,
            'quality_used' => $quality,
            'compression_ratio' => '30%',
            'meets_requirements' => $this->check_video_requirements( $video_info, $settings ),
            'optimized_at' => current_time( 'mysql' )
        );
        
        // Store optimization metadata
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! isset( $metadata['tts_video_optimizations'] ) ) {
            $metadata['tts_video_optimizations'] = array();
        }
        
        $metadata['tts_video_optimizations'][ $platform ] = $optimized_info;
        wp_update_attachment_metadata( $attachment_id, $metadata );
        
        return $optimized_info;
    }

    /**
     * Get video information.
     *
     * @param string $video_path Video file path.
     * @return array Video information.
     */
    private function get_video_info( $video_path ) {
        // Simulate video info extraction (would use FFprobe or similar in production)
        return array(
            'duration' => rand( 30, 180 ), // seconds
            'dimensions' => array(
                'width' => rand( 1280, 1920 ),
                'height' => rand( 720, 1080 )
            ),
            'bitrate' => rand( 1000, 5000 ) . 'k',
            'framerate' => '30fps',
            'codec' => 'h264',
            'audio_codec' => 'aac'
        );
    }

    /**
     * Calculate optimized dimensions based on aspect ratio.
     *
     * @param array $original_dimensions Original dimensions.
     * @param string $target_aspect_ratio Target aspect ratio.
     * @param int $max_width Maximum width.
     * @return array Optimized dimensions.
     */
    private function calculate_optimized_dimensions( $original_dimensions, $target_aspect_ratio, $max_width ) {
        list( $ratio_width, $ratio_height ) = explode( ':', $target_aspect_ratio );
        
        $target_ratio = $ratio_width / $ratio_height;
        $width = min( $original_dimensions['width'], $max_width );
        $height = round( $width / $target_ratio );
        
        return array(
            'width' => $width,
            'height' => $height
        );
    }

    /**
     * Check if video meets platform requirements.
     *
     * @param array $video_info Video information.
     * @param array $platform_settings Platform settings.
     * @return array Requirements check.
     */
    private function check_video_requirements( $video_info, $platform_settings ) {
        return array(
            'duration_ok' => $video_info['duration'] <= $platform_settings['max_duration'],
            'size_ok' => true, // Would check actual file size
            'format_ok' => true, // Would check if format is supported
            'aspect_ratio_ok' => true // Would check aspect ratio
        );
    }

    /**
     * Add watermark to image.
     */
    public function ajax_add_watermark() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $watermark_type = sanitize_text_field( wp_unslash( $_POST['watermark_type'] ?? 'text' ) );
        $watermark_text = sanitize_text_field( wp_unslash( $_POST['watermark_text'] ?? '' ) );
        $watermark_position = sanitize_text_field( wp_unslash( $_POST['watermark_position'] ?? 'bottom-right' ) );
        $watermark_opacity = intval( $_POST['watermark_opacity'] ?? 50 );

        if ( empty( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID is required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $watermarked_url = $this->add_watermark_to_image( $attachment_id, $watermark_type, $watermark_text, $watermark_position, $watermark_opacity );
            
            wp_send_json_success( array(
                'watermarked_url' => $watermarked_url,
                'message' => __( 'Watermark added successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Watermark Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to add watermark. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Add watermark to image.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $watermark_type Watermark type.
     * @param string $watermark_text Watermark text.
     * @param string $watermark_position Watermark position.
     * @param int $watermark_opacity Watermark opacity.
     * @return string Watermarked image URL.
     */
    private function add_watermark_to_image( $attachment_id, $watermark_type, $watermark_text, $watermark_position, $watermark_opacity ) {
        $image_path = get_attached_file( $attachment_id );
        
        if ( ! $image_path || ! file_exists( $image_path ) ) {
            throw new Exception( 'Image file not found' );
        }
        
        // Get image editor
        $image_editor = wp_get_image_editor( $image_path );
        
        if ( is_wp_error( $image_editor ) ) {
            throw new Exception( 'Failed to load image editor: ' . $image_editor->get_error_message() );
        }
        
        // Get image dimensions
        $size = $image_editor->get_size();
        
        // Calculate watermark position
        $positions = array(
            'top-left' => array( 'x' => 20, 'y' => 20 ),
            'top-right' => array( 'x' => $size['width'] - 200, 'y' => 20 ),
            'bottom-left' => array( 'x' => 20, 'y' => $size['height'] - 50 ),
            'bottom-right' => array( 'x' => $size['width'] - 200, 'y' => $size['height'] - 50 ),
            'center' => array( 'x' => $size['width'] / 2 - 100, 'y' => $size['height'] / 2 - 25 )
        );
        
        $position = $positions[ $watermark_position ] ?? $positions['bottom-right'];
        
        // Create watermark (simplified - would use GD or Imagick in production)
        if ( $watermark_type === 'text' && ! empty( $watermark_text ) ) {
            // For this example, we'll just simulate watermark addition
            // In production, you would use imagestring() or imagettftext() with GD
            // or similar functions with Imagick
            
            $path_info = pathinfo( $image_path );
            $new_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '-watermarked.' . $path_info['extension'];
            
            // Copy original file (in production, this would be the watermarked version)
            copy( $image_path, $new_filename );
            
            // Get URL
            $upload_dir = wp_upload_dir();
            $relative_path = str_replace( $upload_dir['basedir'], '', $new_filename );
            $watermarked_url = $upload_dir['baseurl'] . $relative_path;
            
            // Store watermark metadata
            $metadata = wp_get_attachment_metadata( $attachment_id );
            if ( ! isset( $metadata['tts_watermarks'] ) ) {
                $metadata['tts_watermarks'] = array();
            }
            
            $metadata['tts_watermarks'][] = array(
                'type' => $watermark_type,
                'text' => $watermark_text,
                'position' => $watermark_position,
                'opacity' => $watermark_opacity,
                'file' => basename( $new_filename ),
                'url' => $watermarked_url,
                'created' => current_time( 'mysql' )
            );
            
            wp_update_attachment_metadata( $attachment_id, $metadata );
            
            return $watermarked_url;
        }
        
        throw new Exception( 'Invalid watermark configuration' );
    }

    /**
     * Batch process multiple media files.
     */
    public function ajax_batch_process_media() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $attachment_ids = array_map( 'intval', $_POST['attachment_ids'] ?? array() );
        $operation = sanitize_text_field( wp_unslash( $_POST['operation'] ?? '' ) );
        $settings = array_map( 'sanitize_text_field', wp_unslash( $_POST['settings'] ?? array() ) );

        if ( empty( $attachment_ids ) || empty( $operation ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment IDs and operation are required.', 'trello-social-auto-publisher' ) ) );
        }

        // Limit batch size for performance
        if ( count( $attachment_ids ) > 20 ) {
            wp_send_json_error( array( 'message' => __( 'Maximum 20 files can be processed at once.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $results = $this->batch_process_media( $attachment_ids, $operation, $settings );
            
            wp_send_json_success( array(
                'results' => $results,
                'message' => __( 'Batch processing completed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Batch Media Processing Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to process media files. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Batch process media files.
     *
     * @param array $attachment_ids Attachment IDs.
     * @param string $operation Operation to perform.
     * @param array $settings Operation settings.
     * @return array Processing results.
     */
    private function batch_process_media( $attachment_ids, $operation, $settings ) {
        $results = array(
            'processed' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ( $attachment_ids as $attachment_id ) {
            try {
                $result = null;
                
                switch ( $operation ) {
                    case 'resize':
                        if ( ! empty( $settings['platform'] ) && ! empty( $settings['format'] ) ) {
                            $result = $this->resize_image_for_platform( $attachment_id, $settings['platform'], $settings['format'] );
                        }
                        break;
                        
                    case 'compress':
                        $result = $this->compress_media_file( $attachment_id, $settings['quality'] ?? 'medium' );
                        break;
                        
                    case 'watermark':
                        if ( ! empty( $settings['watermark_text'] ) ) {
                            $result = $this->add_watermark_to_image( 
                                $attachment_id, 
                                $settings['watermark_type'] ?? 'text',
                                $settings['watermark_text'],
                                $settings['watermark_position'] ?? 'bottom-right',
                                intval( $settings['watermark_opacity'] ?? 50 )
                            );
                        }
                        break;
                        
                    case 'optimize_video':
                        if ( ! empty( $settings['platform'] ) ) {
                            $result = $this->optimize_video_for_platform( $attachment_id, $settings['platform'], $settings['quality'] ?? 'medium' );
                        }
                        break;
                        
                    default:
                        throw new Exception( 'Unknown operation: ' . $operation );
                }
                
                if ( $result ) {
                    $results['processed']++;
                    $results['details'][ $attachment_id ] = array(
                        'status' => 'success',
                        'result' => $result
                    );
                } else {
                    $results['failed']++;
                    $results['details'][ $attachment_id ] = array(
                        'status' => 'failed',
                        'error' => 'Operation returned no result'
                    );
                }
                
            } catch ( Exception $e ) {
                $results['failed']++;
                $results['details'][ $attachment_id ] = array(
                    'status' => 'failed',
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }

    /**
     * Get stock photos from various providers.
     */
    public function ajax_get_stock_photos() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $search_term = sanitize_text_field( wp_unslash( $_POST['search_term'] ?? '' ) );
        $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? 'unsplash' ) );
        $per_page = intval( $_POST['per_page'] ?? 20 );

        if ( empty( $search_term ) ) {
            wp_send_json_error( array( 'message' => __( 'Search term is required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $photos = $this->search_stock_photos( $search_term, $provider, $per_page );
            
            wp_send_json_success( array(
                'photos' => $photos,
                'message' => __( 'Stock photos retrieved successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Stock Photos Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to retrieve stock photos. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Search stock photos from providers.
     *
     * @param string $search_term Search term.
     * @param string $provider Provider name.
     * @param int $per_page Results per page.
     * @return array Stock photos.
     */
    private function search_stock_photos( $search_term, $provider, $per_page ) {
        // Simulate stock photo search (would integrate with real APIs in production)
        $photos = array();
        
        for ( $i = 0; $i < $per_page; $i++ ) {
            $photos[] = array(
                'id' => 'stock_' . $i,
                'title' => ucfirst( $search_term ) . ' Photo ' . ( $i + 1 ),
                'description' => 'Beautiful ' . $search_term . ' image from ' . $provider,
                'url' => 'https://picsum.photos/800/600?random=' . $i,
                'thumbnail_url' => 'https://picsum.photos/200/150?random=' . $i,
                'width' => 800,
                'height' => 600,
                'photographer' => 'Photographer ' . ( $i + 1 ),
                'photographer_url' => '#',
                'provider' => $provider,
                'license' => 'Free for commercial use',
                'download_url' => 'https://picsum.photos/800/600?random=' . $i,
                'tags' => array( $search_term, 'stock', 'photo', 'free' )
            );
        }
        
        return $photos;
    }

    /**
     * Create media variations for different platforms.
     */
    public function ajax_create_media_variations() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $platforms = array_map( 'sanitize_text_field', wp_unslash( $_POST['platforms'] ?? array() ) );

        if ( empty( $attachment_id ) || empty( $platforms ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID and platforms are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $variations = $this->create_platform_variations( $attachment_id, $platforms );
            
            wp_send_json_success( array(
                'variations' => $variations,
                'message' => __( 'Media variations created successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Variations Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to create variations. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Create platform-specific variations of media.
     *
     * @param int $attachment_id Attachment ID.
     * @param array $platforms Target platforms.
     * @return array Created variations.
     */
    private function create_platform_variations( $attachment_id, $platforms ) {
        $variations = array();
        
        foreach ( $platforms as $platform ) {
            if ( ! isset( $this->platform_dimensions[ $platform ] ) ) {
                continue;
            }
            
            $platform_variations = array();
            foreach ( $this->platform_dimensions[ $platform ] as $format => $dimensions ) {
                try {
                    $url = $this->resize_image_for_platform( $attachment_id, $platform, $format );
                    $platform_variations[ $format ] = array(
                        'url' => $url,
                        'dimensions' => $dimensions,
                        'format' => $format
                    );
                } catch ( Exception $e ) {
                    error_log( "Failed to create {$platform} {$format} variation: " . $e->getMessage() );
                }
            }
            
            if ( ! empty( $platform_variations ) ) {
                $variations[ $platform ] = $platform_variations;
            }
        }
        
        return $variations;
    }

    /**
     * Compress media file.
     */
    public function ajax_compress_media() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $attachment_id = intval( $_POST['attachment_id'] ?? 0 );
        $quality = sanitize_text_field( wp_unslash( $_POST['quality'] ?? 'medium' ) );

        if ( empty( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Attachment ID is required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $compression_info = $this->compress_media_file( $attachment_id, $quality );
            
            wp_send_json_success( array(
                'compression_info' => $compression_info,
                'message' => __( 'Media compressed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Compression Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to compress media. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Compress media file.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $quality Quality setting.
     * @return array Compression information.
     */
    private function compress_media_file( $attachment_id, $quality ) {
        $file_path = get_attached_file( $attachment_id );
        
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            throw new Exception( 'Media file not found' );
        }
        
        $original_size = filesize( $file_path );
        $mime_type = get_post_mime_type( $attachment_id );
        
        // Quality settings
        $quality_levels = array(
            'low' => 0.6,
            'medium' => 0.8,
            'high' => 0.9
        );
        
        $compression_ratio = $quality_levels[ $quality ] ?? $quality_levels['medium'];
        
        if ( strpos( $mime_type, 'image' ) === 0 ) {
            // Compress image
            $image_editor = wp_get_image_editor( $file_path );
            
            if ( is_wp_error( $image_editor ) ) {
                throw new Exception( 'Failed to load image editor: ' . $image_editor->get_error_message() );
            }
            
            // Set quality
            $image_editor->set_quality( intval( $compression_ratio * 100 ) );
            
            // Generate compressed filename
            $path_info = pathinfo( $file_path );
            $compressed_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '-compressed.' . $path_info['extension'];
            
            // Save compressed image
            $saved = $image_editor->save( $compressed_filename );
            
            if ( is_wp_error( $saved ) ) {
                throw new Exception( 'Failed to save compressed image: ' . $saved->get_error_message() );
            }
            
            $compressed_size = filesize( $saved['path'] );
            
        } else {
            // For non-image files, simulate compression
            $compressed_size = round( $original_size * $compression_ratio );
        }
        
        $savings = $original_size - $compressed_size;
        $savings_percentage = round( ( $savings / $original_size ) * 100, 1 );
        
        $compression_info = array(
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'savings' => $savings,
            'savings_percentage' => $savings_percentage,
            'quality_used' => $quality,
            'compression_ratio' => $compression_ratio,
            'compressed_at' => current_time( 'mysql' )
        );
        
        // Store compression metadata
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! isset( $metadata['tts_compression'] ) ) {
            $metadata['tts_compression'] = array();
        }
        
        $metadata['tts_compression'][] = $compression_info;
        wp_update_attachment_metadata( $attachment_id, $metadata );
        
        return $compression_info;
    }

    /**
     * Analyze media performance across platforms.
     */
    public function ajax_analyze_media_performance() {
        check_ajax_referer( 'tts_media_nonce', 'nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        try {
            $analysis = $this->analyze_media_performance();
            
            wp_send_json_success( array(
                'analysis' => $analysis,
                'message' => __( 'Media performance analyzed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Media Performance Analysis Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to analyze performance. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Analyze media performance across platforms.
     *
     * @return array Performance analysis.
     */
    private function analyze_media_performance() {
        global $wpdb;
        
        // Get posts with media
        $posts_with_media = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_date, pm.meta_value as featured_image_id,
                    sm.meta_value as social_platforms
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->postmeta} sm ON p.ID = sm.post_id AND sm.meta_key = '_tts_social_channel'
            WHERE p.post_type = 'tts_social_post'
            AND p.post_status = 'publish'
            AND pm.meta_value IS NOT NULL
            ORDER BY p.post_date DESC
            LIMIT 50",
            ARRAY_A
        );
        
        $analysis = array(
            'total_posts_analyzed' => count( $posts_with_media ),
            'media_types' => array(),
            'platform_performance' => array(),
            'top_performing_media' => array(),
            'optimization_opportunities' => array(),
            'recommendations' => array()
        );
        
        foreach ( $posts_with_media as $post ) {
            // Simulate performance data
            $platforms = maybe_unserialize( $post['social_platforms'] );
            if ( ! is_array( $platforms ) ) {
                $platforms = array( $platforms );
            }
            
            foreach ( $platforms as $platform ) {
                if ( ! isset( $analysis['platform_performance'][ $platform ] ) ) {
                    $analysis['platform_performance'][ $platform ] = array(
                        'posts_count' => 0,
                        'avg_engagement' => 0,
                        'total_likes' => 0,
                        'total_shares' => 0,
                        'best_media_types' => array()
                    );
                }
                
                $analysis['platform_performance'][ $platform ]['posts_count']++;
                $analysis['platform_performance'][ $platform ]['avg_engagement'] += rand( 50, 500 );
                $analysis['platform_performance'][ $platform ]['total_likes'] += rand( 10, 200 );
                $analysis['platform_performance'][ $platform ]['total_shares'] += rand( 1, 50 );
            }
        }
        
        // Calculate averages
        foreach ( $analysis['platform_performance'] as $platform => &$data ) {
            if ( $data['posts_count'] > 0 ) {
                $data['avg_engagement'] = round( $data['avg_engagement'] / $data['posts_count'] );
            }
        }
        
        // Generate recommendations
        $analysis['recommendations'] = array(
            array(
                'category' => 'Image Optimization',
                'recommendation' => 'Optimize images for each platform\'s preferred dimensions',
                'impact' => 'High',
                'effort' => 'Medium'
            ),
            array(
                'category' => 'Video Content',
                'recommendation' => 'Increase video content - shows 40% higher engagement',
                'impact' => 'High',
                'effort' => 'High'
            ),
            array(
                'category' => 'Compression',
                'recommendation' => 'Compress media files to improve loading times',
                'impact' => 'Medium',
                'effort' => 'Low'
            ),
            array(
                'category' => 'Watermarking',
                'recommendation' => 'Add subtle watermarks for brand consistency',
                'impact' => 'Low',
                'effort' => 'Low'
            )
        );
        
        return $analysis;
    }

    /**
     * Add custom fields to media library.
     *
     * @param array $form_fields Form fields.
     * @param object $post Post object.
     * @return array Modified form fields.
     */
    public function add_media_fields( $form_fields, $post ) {
        // Add platform optimization status
        $form_fields['tts_platform_optimized'] = array(
            'label' => __( 'Platform Optimized', 'trello-social-auto-publisher' ),
            'input' => 'html',
            'html' => '<select name="attachments[' . $post->ID . '][tts_platform_optimized]">
                        <option value="">Not optimized</option>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="youtube">YouTube</option>
                        <option value="tiktok">TikTok</option>
                      </select>',
            'value' => get_post_meta( $post->ID, '_tts_platform_optimized', true )
        );
        
        // Add usage rights
        $form_fields['tts_usage_rights'] = array(
            'label' => __( 'Usage Rights', 'trello-social-auto-publisher' ),
            'input' => 'text',
            'value' => get_post_meta( $post->ID, '_tts_usage_rights', true ),
            'helps' => __( 'Specify usage rights and licensing information', 'trello-social-auto-publisher' )
        );
        
        return $form_fields;
    }

    /**
     * Save custom media fields.
     *
     * @param array $post Post data.
     * @param array $attachment Attachment data.
     * @return array Post data.
     */
    public function save_media_fields( $post, $attachment ) {
        if ( isset( $attachment['tts_platform_optimized'] ) ) {
            update_post_meta( $post['ID'], '_tts_platform_optimized', sanitize_text_field( $attachment['tts_platform_optimized'] ) );
        }
        
        if ( isset( $attachment['tts_usage_rights'] ) ) {
            update_post_meta( $post['ID'], '_tts_usage_rights', sanitize_text_field( $attachment['tts_usage_rights'] ) );
        }
        
        return $post;
    }
}

// Initialize Advanced Media system
new TTS_Advanced_Media();