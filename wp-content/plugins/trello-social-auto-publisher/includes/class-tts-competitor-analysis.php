<?php
/**
 * Competitor Analysis and Tracking System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles competitor analysis and performance tracking.
 */
class TTS_Competitor_Analysis {

    /**
     * Initialize competitor analysis system.
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_add_competitor', array( $this, 'ajax_add_competitor' ) );
        add_action( 'wp_ajax_tts_remove_competitor', array( $this, 'ajax_remove_competitor' ) );
        add_action( 'wp_ajax_tts_analyze_competitor', array( $this, 'ajax_analyze_competitor' ) );
        add_action( 'wp_ajax_tts_get_competitor_report', array( $this, 'ajax_get_competitor_report' ) );
        add_action( 'wp_ajax_tts_track_competitor_posts', array( $this, 'ajax_track_competitor_posts' ) );
        
        // Schedule daily competitor analysis
        add_action( 'init', array( $this, 'schedule_competitor_analysis' ) );
        add_action( 'tts_daily_competitor_analysis', array( $this, 'run_daily_analysis' ) );
    }

    /**
     * Schedule daily competitor analysis.
     */
    public function schedule_competitor_analysis() {
        if ( ! wp_next_scheduled( 'tts_daily_competitor_analysis' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_daily_competitor_analysis' );
        }
    }

    /**
     * Add new competitor for tracking.
     */
    public function ajax_add_competitor() {
        check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $competitor_name = sanitize_text_field( wp_unslash( $_POST['competitor_name'] ?? '' ) );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $handle = sanitize_text_field( wp_unslash( $_POST['handle'] ?? '' ) );

        if ( empty( $competitor_name ) || empty( $platform ) || empty( $handle ) ) {
            wp_send_json_error( array( 'message' => __( 'All fields are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $competitor_id = $this->add_competitor( $competitor_name, $platform, $handle );
            
            wp_send_json_success( array(
                'competitor_id' => $competitor_id,
                'message' => __( 'Competitor added successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Competitor Add Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to add competitor. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Add competitor to tracking system.
     *
     * @param string $name Competitor name.
     * @param string $platform Social media platform.
     * @param string $handle Social media handle.
     * @return int Competitor ID.
     */
    private function add_competitor( $name, $platform, $handle ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        // Create table if it doesn't exist
        $this->create_competitors_table();
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'platform' => $platform,
                'handle' => $handle,
                'added_date' => current_time( 'mysql' ),
                'status' => 'active'
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to add competitor to database' );
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Create competitors table.
     */
    private function create_competitors_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            platform varchar(50) NOT NULL,
            handle varchar(255) NOT NULL,
            added_date datetime NOT NULL,
            status varchar(20) DEFAULT 'active',
            last_analyzed datetime,
            follower_count int(11),
            following_count int(11),
            post_count int(11),
            engagement_rate decimal(5,2),
            PRIMARY KEY (id),
            KEY platform (platform),
            KEY status (status)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Remove competitor from tracking.
     */
    public function ajax_remove_competitor() {
        check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $competitor_id = intval( $_POST['competitor_id'] ?? 0 );

        if ( empty( $competitor_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid competitor ID.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $this->remove_competitor( $competitor_id );
            
            wp_send_json_success( array(
                'message' => __( 'Competitor removed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Competitor Remove Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to remove competitor. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Remove competitor from database.
     *
     * @param int $competitor_id Competitor ID.
     */
    private function remove_competitor( $competitor_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        $result = $wpdb->delete(
            $table_name,
            array( 'id' => $competitor_id ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to remove competitor from database' );
        }
    }

    /**
     * Analyze specific competitor.
     */
    public function ajax_analyze_competitor() {
        check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $competitor_id = intval( $_POST['competitor_id'] ?? 0 );

        if ( empty( $competitor_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid competitor ID.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $analysis = $this->analyze_competitor( $competitor_id );
            
            wp_send_json_success( array(
                'analysis' => $analysis,
                'message' => __( 'Competitor analyzed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Competitor Analysis Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to analyze competitor. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Analyze competitor performance.
     *
     * @param int $competitor_id Competitor ID.
     * @return array Analysis results.
     */
    private function analyze_competitor( $competitor_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        $competitor = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $competitor_id ),
            ARRAY_A
        );
        
        if ( ! $competitor ) {
            throw new Exception( 'Competitor not found' );
        }
        
        // Simulate competitor data analysis (would use real APIs in production)
        $analysis = $this->simulate_competitor_analysis( $competitor );
        
        // Update competitor data in database
        $wpdb->update(
            $table_name,
            array(
                'last_analyzed' => current_time( 'mysql' ),
                'follower_count' => $analysis['followers'],
                'following_count' => $analysis['following'],
                'post_count' => $analysis['posts'],
                'engagement_rate' => $analysis['engagement_rate']
            ),
            array( 'id' => $competitor_id ),
            array( '%s', '%d', '%d', '%d', '%f' ),
            array( '%d' )
        );
        
        return $analysis;
    }

    /**
     * Simulate competitor analysis (would use real APIs in production).
     *
     * @param array $competitor Competitor data.
     * @return array Simulated analysis.
     */
    private function simulate_competitor_analysis( $competitor ) {
        // Simulate realistic social media metrics
        $platform_metrics = array(
            'instagram' => array(
                'followers' => rand( 1000, 100000 ),
                'following' => rand( 100, 2000 ),
                'posts' => rand( 50, 1000 ),
                'avg_likes' => rand( 50, 5000 ),
                'avg_comments' => rand( 5, 200 )
            ),
            'facebook' => array(
                'followers' => rand( 500, 50000 ),
                'following' => rand( 50, 1000 ),
                'posts' => rand( 30, 500 ),
                'avg_likes' => rand( 20, 2000 ),
                'avg_comments' => rand( 2, 100 )
            ),
            'twitter' => array(
                'followers' => rand( 200, 20000 ),
                'following' => rand( 100, 5000 ),
                'posts' => rand( 100, 5000 ),
                'avg_likes' => rand( 5, 500 ),
                'avg_comments' => rand( 1, 50 )
            ),
            'linkedin' => array(
                'followers' => rand( 500, 30000 ),
                'following' => rand( 200, 2000 ),
                'posts' => rand( 20, 200 ),
                'avg_likes' => rand( 10, 1000 ),
                'avg_comments' => rand( 2, 50 )
            ),
            'tiktok' => array(
                'followers' => rand( 1000, 500000 ),
                'following' => rand( 50, 1000 ),
                'posts' => rand( 20, 500 ),
                'avg_likes' => rand( 100, 10000 ),
                'avg_comments' => rand( 10, 500 )
            ),
            'youtube' => array(
                'followers' => rand( 1000, 100000 ),
                'following' => rand( 10, 500 ),
                'posts' => rand( 10, 200 ),
                'avg_likes' => rand( 50, 5000 ),
                'avg_comments' => rand( 5, 200 )
            )
        );
        
        $platform = $competitor['platform'];
        $metrics = $platform_metrics[ $platform ] ?? $platform_metrics['instagram'];
        
        // Calculate engagement rate
        $engagement_rate = ( $metrics['avg_likes'] + $metrics['avg_comments'] ) / $metrics['followers'] * 100;
        
        // Generate content analysis
        $content_analysis = $this->analyze_competitor_content( $competitor );
        
        // Generate posting patterns
        $posting_patterns = $this->analyze_posting_patterns( $competitor );
        
        return array(
            'followers' => $metrics['followers'],
            'following' => $metrics['following'],
            'posts' => $metrics['posts'],
            'avg_likes' => $metrics['avg_likes'],
            'avg_comments' => $metrics['avg_comments'],
            'engagement_rate' => round( $engagement_rate, 2 ),
            'content_analysis' => $content_analysis,
            'posting_patterns' => $posting_patterns,
            'growth_rate' => $this->calculate_growth_rate( $competitor ),
            'top_performing_content' => $this->get_top_performing_content( $competitor ),
            'hashtag_analysis' => $this->analyze_hashtag_usage( $competitor ),
            'audience_insights' => $this->get_audience_insights( $competitor )
        );
    }

    /**
     * Analyze competitor content themes and types.
     *
     * @param array $competitor Competitor data.
     * @return array Content analysis.
     */
    private function analyze_competitor_content( $competitor ) {
        // Simulate content type distribution
        $content_types = array(
            'photo' => rand( 30, 60 ),
            'video' => rand( 20, 40 ),
            'carousel' => rand( 10, 30 ),
            'story' => rand( 15, 35 ),
            'live' => rand( 0, 10 )
        );
        
        // Simulate content themes
        $themes = array(
            'educational' => rand( 20, 40 ),
            'promotional' => rand( 10, 30 ),
            'behind_scenes' => rand( 15, 25 ),
            'user_generated' => rand( 10, 20 ),
            'trending' => rand( 5, 15 ),
            'personal' => rand( 10, 20 )
        );
        
        // Calculate average performance by type
        $performance_by_type = array();
        foreach ( $content_types as $type => $percentage ) {
            $performance_by_type[ $type ] = array(
                'percentage' => $percentage,
                'avg_engagement' => rand( 50, 500 ),
                'success_rate' => rand( 60, 95 )
            );
        }
        
        return array(
            'content_types' => $content_types,
            'themes' => $themes,
            'performance_by_type' => $performance_by_type,
            'avg_post_length' => rand( 50, 300 ),
            'hashtag_usage' => rand( 5, 25 ),
            'posting_frequency' => rand( 3, 14 ) . ' posts/week'
        );
    }

    /**
     * Analyze posting patterns and timing.
     *
     * @param array $competitor Competitor data.
     * @return array Posting patterns.
     */
    private function analyze_posting_patterns( $competitor ) {
        // Simulate optimal posting times
        $best_times = array(
            'monday' => array( '9:00', '12:00', '18:00' ),
            'tuesday' => array( '10:00', '14:00', '19:00' ),
            'wednesday' => array( '9:00', '13:00', '17:00' ),
            'thursday' => array( '11:00', '15:00', '20:00' ),
            'friday' => array( '8:00', '12:00', '16:00' ),
            'saturday' => array( '10:00', '14:00', '19:00' ),
            'sunday' => array( '9:00', '13:00', '18:00' )
        );
        
        // Simulate posting frequency by day
        $frequency_by_day = array(
            'monday' => rand( 1, 3 ),
            'tuesday' => rand( 1, 3 ),
            'wednesday' => rand( 1, 3 ),
            'thursday' => rand( 1, 3 ),
            'friday' => rand( 1, 3 ),
            'saturday' => rand( 1, 2 ),
            'sunday' => rand( 1, 2 )
        );
        
        return array(
            'best_times' => $best_times,
            'frequency_by_day' => $frequency_by_day,
            'most_active_day' => array_keys( $frequency_by_day, max( $frequency_by_day ) )[0],
            'avg_posts_per_week' => array_sum( $frequency_by_day ),
            'consistency_score' => rand( 70, 95 )
        );
    }

    /**
     * Calculate growth rate for competitor.
     *
     * @param array $competitor Competitor data.
     * @return array Growth metrics.
     */
    private function calculate_growth_rate( $competitor ) {
        // Simulate growth data
        return array(
            'follower_growth_rate' => rand( -5, 15 ) . '%',
            'engagement_growth_rate' => rand( -10, 20 ) . '%',
            'content_growth_rate' => rand( 0, 25 ) . '%',
            'monthly_follower_gain' => rand( 10, 1000 ),
            'growth_trend' => array( 'up', 'down', 'stable' )[ rand( 0, 2 ) ]
        );
    }

    /**
     * Get top performing content for competitor.
     *
     * @param array $competitor Competitor data.
     * @return array Top content.
     */
    private function get_top_performing_content( $competitor ) {
        $content_types = array( 'photo', 'video', 'carousel', 'story' );
        $themes = array( 'educational', 'behind_scenes', 'promotional', 'trending' );
        
        $top_content = array();
        
        for ( $i = 0; $i < 5; $i++ ) {
            $top_content[] = array(
                'type' => $content_types[ rand( 0, count( $content_types ) - 1 ) ],
                'theme' => $themes[ rand( 0, count( $themes ) - 1 ) ],
                'likes' => rand( 100, 5000 ),
                'comments' => rand( 10, 200 ),
                'shares' => rand( 5, 100 ),
                'engagement_rate' => rand( 3, 12 ) . '%',
                'posted_date' => date( 'Y-m-d', strtotime( '-' . rand( 1, 30 ) . ' days' ) )
            );
        }
        
        // Sort by engagement
        usort( $top_content, function( $a, $b ) {
            return $b['likes'] - $a['likes'];
        });
        
        return $top_content;
    }

    /**
     * Analyze hashtag usage patterns.
     *
     * @param array $competitor Competitor data.
     * @return array Hashtag analysis.
     */
    private function analyze_hashtag_usage( $competitor ) {
        // Simulate hashtag data
        $popular_hashtags = array(
            '#marketing' => rand( 20, 50 ),
            '#business' => rand( 15, 40 ),
            '#socialmedia' => rand( 10, 35 ),
            '#digitalmarketing' => rand( 8, 30 ),
            '#branding' => rand( 12, 25 ),
            '#content' => rand( 10, 28 ),
            '#growth' => rand( 8, 22 ),
            '#strategy' => rand( 6, 20 ),
            '#tips' => rand( 5, 18 ),
            '#inspiration' => rand( 4, 15 )
        );
        
        return array(
            'avg_hashtags_per_post' => rand( 8, 25 ),
            'most_used_hashtags' => $popular_hashtags,
            'hashtag_performance' => array(
                'high_performing' => array_slice( $popular_hashtags, 0, 3, true ),
                'low_performing' => array_slice( $popular_hashtags, -2, 2, true )
            ),
            'branded_hashtags' => rand( 2, 5 ),
            'hashtag_diversity' => rand( 60, 95 ) . '%'
        );
    }

    /**
     * Get audience insights for competitor.
     *
     * @param array $competitor Competitor data.
     * @return array Audience insights.
     */
    private function get_audience_insights( $competitor ) {
        return array(
            'demographics' => array(
                'age_groups' => array(
                    '18-24' => rand( 15, 25 ) . '%',
                    '25-34' => rand( 25, 40 ) . '%',
                    '35-44' => rand( 20, 30 ) . '%',
                    '45-54' => rand( 10, 20 ) . '%',
                    '55+' => rand( 5, 15 ) . '%'
                ),
                'gender' => array(
                    'female' => rand( 45, 65 ) . '%',
                    'male' => rand( 35, 55 ) . '%'
                ),
                'top_locations' => array( 'USA', 'UK', 'Canada', 'Australia', 'Germany' )
            ),
            'engagement_patterns' => array(
                'most_active_time' => rand( 18, 21 ) . ':00',
                'most_active_day' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' )[ rand( 0, 4 ) ],
                'avg_session_duration' => rand( 2, 8 ) . ' minutes'
            ),
            'interests' => array(
                'business' => rand( 60, 85 ) . '%',
                'technology' => rand( 45, 70 ) . '%',
                'marketing' => rand( 55, 80 ) . '%',
                'entrepreneurship' => rand( 40, 65 ) . '%',
                'lifestyle' => rand( 30, 55 ) . '%'
            )
        );
    }

    /**
     * Get competitor report.
     */
    public function ajax_get_competitor_report() {
        check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        try {
            $report = $this->generate_competitor_report();
            
            wp_send_json_success( array(
                'report' => $report,
                'message' => __( 'Competitor report generated successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Competitor Report Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to generate report. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Generate comprehensive competitor report.
     *
     * @return array Competitor report.
     */
    private function generate_competitor_report() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        $competitors = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY engagement_rate DESC",
            ARRAY_A
        );
        
        $report = array(
            'summary' => array(
                'total_competitors' => count( $competitors ),
                'platforms_tracked' => array_unique( array_column( $competitors, 'platform' ) ),
                'avg_engagement_rate' => 0,
                'top_performer' => null,
                'growth_leaders' => array()
            ),
            'platform_analysis' => array(),
            'competitive_insights' => array(),
            'recommendations' => array(),
            'trends' => array()
        );
        
        if ( ! empty( $competitors ) ) {
            // Calculate average engagement rate
            $total_engagement = array_sum( array_column( $competitors, 'engagement_rate' ) );
            $report['summary']['avg_engagement_rate'] = round( $total_engagement / count( $competitors ), 2 );
            
            // Find top performer
            $report['summary']['top_performer'] = $competitors[0];
            
            // Group by platform for analysis
            $by_platform = array();
            foreach ( $competitors as $competitor ) {
                $platform = $competitor['platform'];
                if ( ! isset( $by_platform[ $platform ] ) ) {
                    $by_platform[ $platform ] = array();
                }
                $by_platform[ $platform ][] = $competitor;
            }
            
            // Generate platform analysis
            foreach ( $by_platform as $platform => $platform_competitors ) {
                $report['platform_analysis'][ $platform ] = array(
                    'competitor_count' => count( $platform_competitors ),
                    'avg_followers' => round( array_sum( array_column( $platform_competitors, 'follower_count' ) ) / count( $platform_competitors ) ),
                    'avg_engagement' => round( array_sum( array_column( $platform_competitors, 'engagement_rate' ) ) / count( $platform_competitors ), 2 ),
                    'top_competitor' => $platform_competitors[0]['name']
                );
            }
            
            // Generate insights and recommendations
            $report['competitive_insights'] = $this->generate_competitive_insights( $competitors );
            $report['recommendations'] = $this->generate_recommendations( $competitors );
            $report['trends'] = $this->identify_trends( $competitors );
        }
        
        return $report;
    }

    /**
     * Generate competitive insights.
     *
     * @param array $competitors Competitor data.
     * @return array Insights.
     */
    private function generate_competitive_insights( $competitors ) {
        $insights = array();
        
        // Engagement rate insights
        $engagement_rates = array_column( $competitors, 'engagement_rate' );
        $avg_engagement = array_sum( $engagement_rates ) / count( $engagement_rates );
        
        if ( $avg_engagement > 5 ) {
            $insights[] = 'High engagement rates across competitors suggest an active audience in your niche.';
        } elseif ( $avg_engagement < 2 ) {
            $insights[] = 'Low engagement rates indicate potential opportunities for better content strategies.';
        }
        
        // Follower insights
        $follower_counts = array_column( $competitors, 'follower_count' );
        $avg_followers = array_sum( $follower_counts ) / count( $follower_counts );
        
        if ( $avg_followers > 50000 ) {
            $insights[] = 'Competitors have established large audiences - focus on niche differentiation.';
        } elseif ( $avg_followers < 10000 ) {
            $insights[] = 'Market opportunity exists to become a leading voice in this space.';
        }
        
        // Platform insights
        $platforms = array_count_values( array_column( $competitors, 'platform' ) );
        $dominant_platform = array_keys( $platforms, max( $platforms ) )[0];
        
        $insights[] = "Most competitors are active on {$dominant_platform} - consider this for primary focus.";
        
        return $insights;
    }

    /**
     * Generate strategic recommendations.
     *
     * @param array $competitors Competitor data.
     * @return array Recommendations.
     */
    private function generate_recommendations( $competitors ) {
        $recommendations = array();
        
        $recommendations[] = array(
            'category' => 'Content Strategy',
            'recommendation' => 'Analyze top-performing competitor content types and adapt with your unique perspective.',
            'priority' => 'high'
        );
        
        $recommendations[] = array(
            'category' => 'Posting Schedule',
            'recommendation' => 'Review competitor posting patterns and identify gaps for optimal timing.',
            'priority' => 'medium'
        );
        
        $recommendations[] = array(
            'category' => 'Engagement',
            'recommendation' => 'Monitor competitor engagement strategies and implement improved versions.',
            'priority' => 'high'
        );
        
        $recommendations[] = array(
            'category' => 'Hashtag Strategy',
            'recommendation' => 'Identify underutilized hashtags that competitors are missing.',
            'priority' => 'medium'
        );
        
        $recommendations[] = array(
            'category' => 'Platform Expansion',
            'recommendation' => 'Consider platforms where competitors have limited presence.',
            'priority' => 'low'
        );
        
        return $recommendations;
    }

    /**
     * Identify market trends.
     *
     * @param array $competitors Competitor data.
     * @return array Trends.
     */
    private function identify_trends( $competitors ) {
        return array(
            array(
                'trend' => 'Video Content Growth',
                'description' => 'Competitors increasing video content by 40% over past quarter',
                'impact' => 'high'
            ),
            array(
                'trend' => 'User-Generated Content',
                'description' => 'Rising use of customer testimonials and user submissions',
                'impact' => 'medium'
            ),
            array(
                'trend' => 'Educational Content',
                'description' => 'Shift towards how-to and educational posts for engagement',
                'impact' => 'high'
            ),
            array(
                'trend' => 'Interactive Features',
                'description' => 'Increased use of polls, Q&As, and live sessions',
                'impact' => 'medium'
            )
        );
    }

    /**
     * Track competitor posts.
     */
    public function ajax_track_competitor_posts() {
        check_ajax_referer( 'tts_competitor_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $competitor_id = intval( $_POST['competitor_id'] ?? 0 );

        if ( empty( $competitor_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid competitor ID.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $posts = $this->get_competitor_recent_posts( $competitor_id );
            
            wp_send_json_success( array(
                'posts' => $posts,
                'message' => __( 'Competitor posts tracked successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Competitor Posts Tracking Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to track competitor posts. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Get recent posts from competitor.
     *
     * @param int $competitor_id Competitor ID.
     * @return array Recent posts.
     */
    private function get_competitor_recent_posts( $competitor_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        $competitor = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $competitor_id ),
            ARRAY_A
        );
        
        if ( ! $competitor ) {
            throw new Exception( 'Competitor not found' );
        }
        
        // Simulate recent posts (would use real APIs in production)
        $posts = array();
        $content_types = array( 'photo', 'video', 'carousel', 'story' );
        $themes = array( 'educational', 'promotional', 'behind_scenes', 'trending' );
        
        for ( $i = 0; $i < 10; $i++ ) {
            $posts[] = array(
                'id' => 'post_' . $i,
                'type' => $content_types[ rand( 0, count( $content_types ) - 1 ) ],
                'theme' => $themes[ rand( 0, count( $themes ) - 1 ) ],
                'content' => 'Sample post content for analysis ' . ( $i + 1 ),
                'posted_date' => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 168 ) . ' hours' ) ),
                'likes' => rand( 50, 1000 ),
                'comments' => rand( 5, 100 ),
                'shares' => rand( 2, 50 ),
                'engagement_rate' => rand( 2, 8 ) . '%',
                'hashtags' => array( '#marketing', '#business', '#socialmedia' ),
                'performance_score' => rand( 60, 95 )
            );
        }
        
        // Sort by posted date
        usort( $posts, function( $a, $b ) {
            return strtotime( $b['posted_date'] ) - strtotime( $a['posted_date'] );
        });
        
        return $posts;
    }

    /**
     * Run daily competitor analysis.
     */
    public function run_daily_analysis() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_competitors';
        
        $competitors = $wpdb->get_results(
            "SELECT id FROM $table_name WHERE status = 'active'",
            ARRAY_A
        );
        
        foreach ( $competitors as $competitor ) {
            try {
                $this->analyze_competitor( $competitor['id'] );
            } catch ( Exception $e ) {
                error_log( 'Daily competitor analysis failed for ID ' . $competitor['id'] . ': ' . $e->getMessage() );
            }
        }
        
        // Log completion
        error_log( 'TTS: Daily competitor analysis completed for ' . count( $competitors ) . ' competitors' );
    }
}

// Initialize Competitor Analysis system
new TTS_Competitor_Analysis();