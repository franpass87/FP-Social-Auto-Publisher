<?php
/**
 * AI & Advanced Features Page
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AI and advanced features admin page.
 */
class TTS_AI_Features_Page {

    /**
     * Initialize the page.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin page.
     */
    public function register_page() {
        add_submenu_page(
            'fp-publisher',
            __( 'AI & Advanced Features', 'fp-publisher' ),
            __( 'AI & Advanced Features', 'fp-publisher' ),
            'manage_options',
            'tts-ai-features',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue page assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'fp-publisher_page_tts-ai-features' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tts-ai-features',
            plugin_dir_url( __FILE__ ) . '../css/tts-ai-features.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'tts-ai-features',
            plugin_dir_url( __FILE__ ) . '../js/tts-ai-features.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script( 'tts-ai-features', 'ttsAI', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tts_ai_nonce' ),
            'competitor_nonce' => wp_create_nonce( 'tts_competitor_nonce' ),
            'workflow_nonce' => wp_create_nonce( 'tts_workflow_nonce' ),
            'media_nonce' => wp_create_nonce( 'tts_media_nonce' ),
            'integration_nonce' => wp_create_nonce( 'tts_integration_nonce' )
        ) );
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        ?>
        <div class="wrap tts-ai-features-page">
            <h1><?php esc_html_e( 'AI & Advanced Features', 'fp-publisher' ); ?></h1>
            
            <div class="tts-features-grid">
                
                <!-- AI Content Enhancement -->
                <div class="tts-feature-card">
                    <div class="tts-feature-header">
                        <span class="tts-feature-icon">🤖</span>
                        <h2><?php esc_html_e( 'AI Content Enhancement', 'fp-publisher' ); ?></h2>
                    </div>
                    <div class="tts-feature-content">
                        <p><?php esc_html_e( 'Leverage AI to optimize your content for maximum engagement across all social platforms.', 'fp-publisher' ); ?></p>
                        
                        <div class="tts-ai-tools">
                            <div class="tts-tool">
                                <h4><?php esc_html_e( 'AI Hashtag Generator', 'fp-publisher' ); ?></h4>
                                <textarea id="hashtag-content" placeholder="<?php esc_attr_e( 'Enter your content here...', 'fp-publisher' ); ?>"></textarea>
                                <select id="hashtag-platform">
                                    <option value="general"><?php esc_html_e( 'General', 'fp-publisher' ); ?></option>
                                    <option value="instagram"><?php esc_html_e( 'Instagram', 'fp-publisher' ); ?></option>
                                    <option value="facebook"><?php esc_html_e( 'Facebook', 'fp-publisher' ); ?></option>
                                    <option value="twitter"><?php esc_html_e( 'Twitter', 'fp-publisher' ); ?></option>
                                    <option value="linkedin"><?php esc_html_e( 'LinkedIn', 'fp-publisher' ); ?></option>
                                    <option value="tiktok"><?php esc_html_e( 'TikTok', 'fp-publisher' ); ?></option>
                                </select>
                                <button type="button" class="button button-primary" id="generate-hashtags">
                                    <?php esc_html_e( 'Generate Hashtags', 'fp-publisher' ); ?>
                                </button>
                                <div id="hashtag-results" class="tts-results"></div>
                            </div>
                            
                            <div class="tts-tool">
                                <h4><?php esc_html_e( 'Content Performance Predictor', 'fp-publisher' ); ?></h4>
                                <textarea id="predict-content" placeholder="<?php esc_attr_e( 'Enter content to analyze...', 'fp-publisher' ); ?>"></textarea>
                                <select id="predict-platform">
                                    <option value="general"><?php esc_html_e( 'General', 'fp-publisher' ); ?></option>
                                    <option value="instagram"><?php esc_html_e( 'Instagram', 'fp-publisher' ); ?></option>
                                    <option value="facebook"><?php esc_html_e( 'Facebook', 'fp-publisher' ); ?></option>
                                    <option value="twitter"><?php esc_html_e( 'Twitter', 'fp-publisher' ); ?></option>
                                    <option value="linkedin"><?php esc_html_e( 'LinkedIn', 'fp-publisher' ); ?></option>
                                </select>
                                <button type="button" class="button button-primary" id="predict-performance">
                                    <?php esc_html_e( 'Predict Performance', 'fp-publisher' ); ?>
                                </button>
                                <div id="prediction-results" class="tts-results"></div>
                            </div>
                            
                            <div class="tts-tool">
                                <h4><?php esc_html_e( 'Content Suggestions', 'fp-publisher' ); ?></h4>
                                <input type="text" id="suggestion-topic" placeholder="<?php esc_attr_e( 'Enter topic or keyword...', 'fp-publisher' ); ?>">
                                <select id="suggestion-platform">
                                    <option value="instagram"><?php esc_html_e( 'Instagram', 'fp-publisher' ); ?></option>
                                    <option value="facebook"><?php esc_html_e( 'Facebook', 'fp-publisher' ); ?></option>
                                    <option value="twitter"><?php esc_html_e( 'Twitter', 'fp-publisher' ); ?></option>
                                    <option value="linkedin"><?php esc_html_e( 'LinkedIn', 'fp-publisher' ); ?></option>
                                    <option value="tiktok"><?php esc_html_e( 'TikTok', 'fp-publisher' ); ?></option>
                                </select>
                                <button type="button" class="button button-primary" id="get-suggestions">
                                    <?php esc_html_e( 'Get Suggestions', 'fp-publisher' ); ?>
                                </button>
                                <div id="suggestion-results" class="tts-results"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Competitor Analysis -->
                <div class="tts-feature-card">
                    <div class="tts-feature-header">
                        <span class="tts-feature-icon">📊</span>
                        <h2><?php esc_html_e( 'Competitor Analysis', 'fp-publisher' ); ?></h2>
                    </div>
                    <div class="tts-feature-content">
                        <p><?php esc_html_e( 'Track and analyze your competitors\' social media performance to stay ahead.', 'fp-publisher' ); ?></p>
                        
                        <div class="tts-competitor-tools">
                            <div class="tts-add-competitor">
                                <h4><?php esc_html_e( 'Add Competitor', 'fp-publisher' ); ?></h4>
                                <input type="text" id="competitor-name" placeholder="<?php esc_attr_e( 'Competitor name', 'fp-publisher' ); ?>">
                                <select id="competitor-platform">
                                    <option value="instagram"><?php esc_html_e( 'Instagram', 'fp-publisher' ); ?></option>
                                    <option value="facebook"><?php esc_html_e( 'Facebook', 'fp-publisher' ); ?></option>
                                    <option value="twitter"><?php esc_html_e( 'Twitter', 'fp-publisher' ); ?></option>
                                    <option value="linkedin"><?php esc_html_e( 'LinkedIn', 'fp-publisher' ); ?></option>
                                    <option value="tiktok"><?php esc_html_e( 'TikTok', 'fp-publisher' ); ?></option>
                                </select>
                                <input type="text" id="competitor-handle" placeholder="<?php esc_attr_e( '@username or handle', 'fp-publisher' ); ?>">
                                <button type="button" class="button button-primary" id="add-competitor">
                                    <?php esc_html_e( 'Add Competitor', 'fp-publisher' ); ?>
                                </button>
                            </div>
                            
                            <div class="tts-competitor-actions">
                                <button type="button" class="button" id="generate-competitor-report">
                                    <?php esc_html_e( 'Generate Report', 'fp-publisher' ); ?>
                                </button>
                            </div>
                            
                            <div id="competitor-results" class="tts-results"></div>
                        </div>
                    </div>
                </div>

                <!-- Workflow & Collaboration -->
                <div class="tts-feature-card">
                    <div class="tts-feature-header">
                        <span class="tts-feature-icon">🔄</span>
                        <h2><?php esc_html_e( 'Workflow & Collaboration', 'fp-publisher' ); ?></h2>
                    </div>
                    <div class="tts-feature-content">
                        <p><?php esc_html_e( 'Streamline team collaboration with approval workflows and task management.', 'fp-publisher' ); ?></p>
                        
                        <div class="tts-workflow-demo">
                            <div class="tts-workflow-stats">
                                <div class="stat-item">
                                    <span class="stat-number" id="pending-approvals">0</span>
                                    <span class="stat-label"><?php esc_html_e( 'Pending Approvals', 'fp-publisher' ); ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" id="approved-content">0</span>
                                    <span class="stat-label"><?php esc_html_e( 'Approved Content', 'fp-publisher' ); ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" id="team-members">0</span>
                                    <span class="stat-label"><?php esc_html_e( 'Team Members', 'fp-publisher' ); ?></span>
                                </div>
                            </div>
                            
                            <button type="button" class="button button-primary" id="get-team-dashboard">
                                <?php esc_html_e( 'View Team Dashboard', 'fp-publisher' ); ?>
                            </button>
                            
                            <div id="workflow-results" class="tts-results"></div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Media Management -->
                <div class="tts-feature-card">
                    <div class="tts-feature-header">
                        <span class="tts-feature-icon">🎨</span>
                        <h2><?php esc_html_e( 'Advanced Media Management', 'fp-publisher' ); ?></h2>
                    </div>
                    <div class="tts-feature-content">
                        <p><?php esc_html_e( 'Optimize, resize, and enhance your media for each social platform automatically.', 'fp-publisher' ); ?></p>
                        
                        <div class="tts-media-tools">
                            <div class="tts-media-optimizer">
                                <h4><?php esc_html_e( 'Platform Optimizer', 'fp-publisher' ); ?></h4>
                                <p><?php esc_html_e( 'Automatically resize images for optimal performance on each platform:', 'fp-publisher' ); ?></p>
                                <ul class="tts-platform-sizes">
                                    <li><strong>Instagram:</strong> Square (1080x1080), Portrait (1080x1350), Story (1080x1920)</li>
                                    <li><strong>Facebook:</strong> Shared Image (1200x630), Cover Photo (1640x859)</li>
                                    <li><strong>Twitter:</strong> Header (1500x500), Card (1200x628)</li>
                                    <li><strong>LinkedIn:</strong> Shared Image (1200x627), Cover (1536x768)</li>
                                    <li><strong>YouTube:</strong> Thumbnail (1280x720), Channel Art (2560x1440)</li>
                                </ul>
                                
                                <button type="button" class="button button-primary" id="analyze-media-performance">
                                    <?php esc_html_e( 'Analyze Media Performance', 'fp-publisher' ); ?>
                                </button>
                            </div>
                            
                            <div id="media-results" class="tts-results"></div>
                        </div>
                    </div>
                </div>

                <!-- Integration Hub -->
                <div class="tts-feature-card">
                    <div class="tts-feature-header">
                        <span class="tts-feature-icon">🔗</span>
                        <h2><?php esc_html_e( 'Integration Hub', 'fp-publisher' ); ?></h2>
                    </div>
                    <div class="tts-feature-content">
                        <p><?php esc_html_e( 'Connect with your favorite tools and platforms for seamless workflow automation.', 'fp-publisher' ); ?></p>
                        
                        <div class="tts-integrations-grid">
                            <div class="integration-category">
                                <h4><?php esc_html_e( 'CRM', 'fp-publisher' ); ?></h4>
                                <ul>
                                    <li>HubSpot</li>
                                    <li>Salesforce</li>
                                    <li>Pipedrive</li>
                                </ul>
                            </div>
                            
                            <div class="integration-category">
                                <h4><?php esc_html_e( 'E-commerce', 'fp-publisher' ); ?></h4>
                                <ul>
                                    <li>WooCommerce</li>
                                    <li>Shopify</li>
                                    <li>Stripe</li>
                                </ul>
                            </div>
                            
                            <div class="integration-category">
                                <h4><?php esc_html_e( 'Email Marketing', 'fp-publisher' ); ?></h4>
                                <ul>
                                    <li>Mailchimp</li>
                                    <li>ConvertKit</li>
                                    <li>Constant Contact</li>
                                </ul>
                            </div>
                            
                            <div class="integration-category">
                                <h4><?php esc_html_e( 'Design Tools', 'fp-publisher' ); ?></h4>
                                <ul>
                                    <li>Canva</li>
                                    <li>Figma</li>
                                    <li>Adobe Creative</li>
                                </ul>
                            </div>
                        </div>
                        
                        <button type="button" class="button button-primary" id="view-integrations">
                            <?php esc_html_e( 'View Available Integrations', 'fp-publisher' ); ?>
                        </button>
                        
                        <div id="integration-results" class="tts-results"></div>
                    </div>
                </div>

            </div>
            
            <!-- Loading overlay -->
            <div id="tts-loading-overlay" class="tts-loading-overlay" style="display: none;">
                <div class="tts-spinner"></div>
                <p><?php esc_html_e( 'Processing...', 'fp-publisher' ); ?></p>
            </div>
            
        </div>
        
        <style>
        .tts-ai-features-page {
            max-width: 1200px;
        }
        
        .tts-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .tts-feature-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .tts-feature-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .tts-feature-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .tts-feature-header h2 {
            margin: 0;
            font-size: 18px;
            color: #23282d;
        }
        
        .tts-tool, .tts-add-competitor, .tts-media-optimizer {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .tts-tool h4, .tts-add-competitor h4, .tts-media-optimizer h4 {
            margin: 0 0 10px 0;
            color: #2271b1;
        }
        
        .tts-tool textarea, .tts-tool input, .tts-tool select,
        .tts-add-competitor input, .tts-add-competitor select {
            width: 100%;
            margin-bottom: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .tts-tool textarea {
            height: 80px;
            resize: vertical;
        }
        
        .tts-results {
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .tts-workflow-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            flex: 1;
        }
        
        .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .tts-platform-sizes {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .tts-platform-sizes li {
            margin-bottom: 8px;
        }
        
        .tts-integrations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .integration-category {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .integration-category h4 {
            margin: 0 0 10px 0;
            color: #2271b1;
        }
        
        .integration-category ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .integration-category li {
            margin-bottom: 5px;
        }
        
        .tts-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            color: white;
        }
        
        .tts-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2271b1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hashtag-tag {
            display: inline-block;
            background: #2271b1;
            color: white;
            padding: 3px 8px;
            margin: 2px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        .performance-meter {
            background: #e0e0e0;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff4444, #ffaa00, #44ff44);
            transition: width 0.3s ease;
        }
        
        .suggestion-item {
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin: 8px 0;
            background: #fafafa;
        }
        
        .suggestion-title {
            font-weight: bold;
            color: #2271b1;
        }
        
        .suggestion-meta {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            function showLoading() {
                $('#tts-loading-overlay').show();
            }
            
            function hideLoading() {
                $('#tts-loading-overlay').hide();
            }
            
            // AI Hashtag Generator
            $('#generate-hashtags').on('click', function() {
                const content = $('#hashtag-content').val();
                const platform = $('#hashtag-platform').val();
                
                if (!content.trim()) {
                    alert('<?php esc_js( __( 'Please enter some content first.', 'fp-publisher' ) ); ?>');
                    return;
                }
                
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_generate_hashtags',
                        nonce: ttsAI.nonce,
                        content: content,
                        platform: platform
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            let html = '<h5><?php esc_js( __( 'Generated Hashtags:', 'fp-publisher' ) ); ?></h5>';
                            response.data.hashtags.forEach(function(hashtag) {
                                html += '<span class="hashtag-tag">' + hashtag + '</span>';
                            });
                            $('#hashtag-results').html(html);
                        } else {
                            $('#hashtag-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#hashtag-results').html('<p style="color: red;"><?php esc_js( __( 'Error generating hashtags.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // Performance Predictor
            $('#predict-performance').on('click', function() {
                const content = $('#predict-content').val();
                const platform = $('#predict-platform').val();
                
                if (!content.trim()) {
                    alert('<?php esc_js( __( 'Please enter some content first.', 'fp-publisher' ) ); ?>');
                    return;
                }
                
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_predict_performance',
                        nonce: ttsAI.nonce,
                        content: content,
                        platform: platform
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            const pred = response.data.prediction;
                            let html = '<h5><?php esc_js( __( 'Performance Prediction:', 'fp-publisher' ) ); ?></h5>';
                            html += '<div class="performance-meter"><div class="performance-fill" style="width: ' + pred.confidence + '%"></div></div>';
                            html += '<p><strong><?php esc_js( __( 'Confidence:', 'fp-publisher' ) ); ?></strong> ' + pred.confidence + '%</p>';
                            html += '<p><strong><?php esc_js( __( 'Engagement Rate:', 'fp-publisher' ) ); ?></strong> ' + pred.engagement_rate + '%</p>';
                            html += '<p><strong><?php esc_js( __( 'Predicted Likes:', 'fp-publisher' ) ); ?></strong> ' + pred.predicted_likes + '</p>';
                            html += '<p><strong><?php esc_js( __( 'Recommendation:', 'fp-publisher' ) ); ?></strong> ' + pred.recommendation + '</p>';
                            $('#prediction-results').html(html);
                        } else {
                            $('#prediction-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#prediction-results').html('<p style="color: red;"><?php esc_js( __( 'Error predicting performance.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // Content Suggestions
            $('#get-suggestions').on('click', function() {
                const topic = $('#suggestion-topic').val();
                const platform = $('#suggestion-platform').val();
                
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_suggest_content',
                        nonce: ttsAI.nonce,
                        topic: topic,
                        platform: platform
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            let html = '<h5><?php esc_js( __( 'Content Suggestions:', 'fp-publisher' ) ); ?></h5>';
                            response.data.suggestions.forEach(function(suggestion) {
                                html += '<div class="suggestion-item">';
                                html += '<div class="suggestion-title">' + suggestion.title + '</div>';
                                html += '<div class="suggestion-meta"><?php esc_js( __( 'Platform:', 'fp-publisher' ) ); ?> ' + suggestion.platform + ' | <?php esc_js( __( 'Est. Performance:', 'fp-publisher' ) ); ?> ' + suggestion.estimated_performance + '%</div>';
                                html += '</div>';
                            });
                            $('#suggestion-results').html(html);
                        } else {
                            $('#suggestion-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#suggestion-results').html('<p style="color: red;"><?php esc_js( __( 'Error getting suggestions.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // Add Competitor
            $('#add-competitor').on('click', function() {
                const name = $('#competitor-name').val();
                const platform = $('#competitor-platform').val();
                const handle = $('#competitor-handle').val();
                
                if (!name || !handle) {
                    alert('<?php esc_js( __( 'Please fill in all fields.', 'fp-publisher' ) ); ?>');
                    return;
                }
                
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_add_competitor',
                        nonce: ttsAI.competitor_nonce,
                        competitor_name: name,
                        platform: platform,
                        handle: handle
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            $('#competitor-results').html('<p style="color: green;">' + response.data.message + '</p>');
                            $('#competitor-name, #competitor-handle').val('');
                        } else {
                            $('#competitor-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#competitor-results').html('<p style="color: red;"><?php esc_js( __( 'Error adding competitor.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // Generate Competitor Report
            $('#generate-competitor-report').on('click', function() {
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_get_competitor_report',
                        nonce: ttsAI.competitor_nonce
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            const report = response.data.report;
                            let html = '<h5><?php esc_js( __( 'Competitor Analysis Report:', 'fp-publisher' ) ); ?></h5>';
                            html += '<p><strong><?php esc_js( __( 'Total Competitors:', 'fp-publisher' ) ); ?></strong> ' + report.summary.total_competitors + '</p>';
                            html += '<p><strong><?php esc_js( __( 'Average Engagement:', 'fp-publisher' ) ); ?></strong> ' + report.summary.avg_engagement_rate + '%</p>';
                            
                            if (report.recommendations && report.recommendations.length > 0) {
                                html += '<h6><?php esc_js( __( 'Recommendations:', 'fp-publisher' ) ); ?></h6>';
                                report.recommendations.forEach(function(rec) {
                                    html += '<div style="margin: 8px 0;"><strong>' + rec.category + ':</strong> ' + rec.recommendation + '</div>';
                                });
                            }
                            
                            $('#competitor-results').html(html);
                        } else {
                            $('#competitor-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#competitor-results').html('<p style="color: red;"><?php esc_js( __( 'Error generating report.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // Team Dashboard
            $('#get-team-dashboard').on('click', function() {
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_get_team_dashboard',
                        nonce: ttsAI.workflow_nonce
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            const dashboard = response.data.dashboard;
                            const stats = dashboard.statistics;
                            
                            $('#pending-approvals').text(stats.pending_approval || 0);
                            $('#approved-content').text(stats.approved || 0);
                            $('#team-members').text(dashboard.team_performance ? dashboard.team_performance.length : 0);
                            
                            let html = '<h5><?php esc_js( __( 'Team Dashboard:', 'fp-publisher' ) ); ?></h5>';
                            html += '<p><strong><?php esc_js( __( 'Pending Approvals:', 'fp-publisher' ) ); ?></strong> ' + (stats.pending_approval || 0) + '</p>';
                            html += '<p><strong><?php esc_js( __( 'Approved Content:', 'fp-publisher' ) ); ?></strong> ' + (stats.approved || 0) + '</p>';
                            html += '<p><strong><?php esc_js( __( 'Rejected Content:', 'fp-publisher' ) ); ?></strong> ' + (stats.rejected || 0) + '</p>';
                            html += '<p><strong><?php esc_js( __( 'Overdue Items:', 'fp-publisher' ) ); ?></strong> ' + (stats.overdue || 0) + '</p>';
                            
                            $('#workflow-results').html(html);
                        } else {
                            $('#workflow-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#workflow-results').html('<p style="color: red;"><?php esc_js( __( 'Error loading dashboard.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // Media Performance Analysis
            $('#analyze-media-performance').on('click', function() {
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_analyze_media_performance',
                        nonce: ttsAI.media_nonce
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            const analysis = response.data.analysis;
                            let html = '<h5><?php esc_js( __( 'Media Performance Analysis:', 'fp-publisher' ) ); ?></h5>';
                            html += '<p><strong><?php esc_js( __( 'Posts Analyzed:', 'fp-publisher' ) ); ?></strong> ' + analysis.total_posts_analyzed + '</p>';
                            
                            if (analysis.recommendations && analysis.recommendations.length > 0) {
                                html += '<h6><?php esc_js( __( 'Optimization Recommendations:', 'fp-publisher' ) ); ?></h6>';
                                analysis.recommendations.forEach(function(rec) {
                                    html += '<div style="margin: 8px 0;"><strong>' + rec.category + ':</strong> ' + rec.recommendation + ' <span style="color: #666;">(Impact: ' + rec.impact + ', Effort: ' + rec.effort + ')</span></div>';
                                });
                            }
                            
                            $('#media-results').html(html);
                        } else {
                            $('#media-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#media-results').html('<p style="color: red;"><?php esc_js( __( 'Error analyzing media.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
            // View Integrations
            $('#view-integrations').on('click', function() {
                showLoading();
                
                $.ajax({
                    url: ttsAI.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tts_get_available_integrations',
                        nonce: ttsAI.integration_nonce
                    },
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            const integrations = response.data.integrations;
                            const connected = response.data.connected;
                            
                            let html = '<h5><?php esc_js( __( 'Available Integrations:', 'fp-publisher' ) ); ?></h5>';
                            
                            if (connected && connected.length > 0) {
                                html += '<h6><?php esc_js( __( 'Connected:', 'fp-publisher' ) ); ?></h6>';
                                connected.forEach(function(conn) {
                                    html += '<div style="color: green; margin: 5px 0;">✓ ' + conn.integration_name + ' (' + conn.integration_type + ')</div>';
                                });
                            }
                            
                            html += '<h6><?php esc_js( __( 'Total Available Integrations:', 'fp-publisher' ) ); ?></h6>';
                            
                            let totalCount = 0;
                            Object.keys(integrations).forEach(function(category) {
                                totalCount += Object.keys(integrations[category]).length;
                            });
                            
                            html += '<p><?php esc_js( __( 'We support', 'fp-publisher' ) ); ?> <strong>' + totalCount + '</strong> <?php esc_js( __( 'different integrations across CRM, E-commerce, Email Marketing, Design Tools, Analytics, and Productivity platforms.', 'fp-publisher' ) ); ?></p>';
                            
                            $('#integration-results').html(html);
                        } else {
                            $('#integration-results').html('<p style="color: red;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        hideLoading();
                        $('#integration-results').html('<p style="color: red;"><?php esc_js( __( 'Error loading integrations.', 'fp-publisher' ) ); ?></p>');
                    }
                });
            });
            
        });
        </script>
        <?php
    }
}

// Initialize the AI Features page
new TTS_AI_Features_Page();