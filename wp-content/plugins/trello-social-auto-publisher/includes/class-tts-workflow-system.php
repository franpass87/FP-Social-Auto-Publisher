<?php
/**
 * Advanced Workflow and Collaboration System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles team collaboration, approval workflows, and content management.
 */
class TTS_Workflow_System {

    /**
     * Initialize workflow system.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'create_workflow_tables' ) );
        add_action( 'wp_ajax_tts_submit_for_approval', array( $this, 'ajax_submit_for_approval' ) );
        add_action( 'wp_ajax_tts_approve_content', array( $this, 'ajax_approve_content' ) );
        add_action( 'wp_ajax_tts_reject_content', array( $this, 'ajax_reject_content' ) );
        add_action( 'wp_ajax_tts_add_workflow_comment', array( $this, 'ajax_add_workflow_comment' ) );
        add_action( 'wp_ajax_tts_assign_task', array( $this, 'ajax_assign_task' ) );
        add_action( 'wp_ajax_tts_get_workflow_status', array( $this, 'ajax_get_workflow_status' ) );
        add_action( 'wp_ajax_tts_create_content_template', array( $this, 'ajax_create_content_template' ) );
        add_action( 'wp_ajax_tts_get_team_dashboard', array( $this, 'ajax_get_team_dashboard' ) );
        
        // Email notifications
        add_action( 'tts_content_submitted_for_approval', array( $this, 'send_approval_notification' ), 10, 2 );
        add_action( 'tts_content_approved', array( $this, 'send_approval_confirmation' ), 10, 2 );
        add_action( 'tts_content_rejected', array( $this, 'send_rejection_notification' ), 10, 3 );
    }

    /**
     * Create workflow-related database tables.
     */
    public function create_workflow_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Workflow states table
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        $sql = "CREATE TABLE $workflow_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'draft',
            assigned_to int(11),
            submitted_by int(11),
            submitted_date datetime,
            approved_by int(11),
            approved_date datetime,
            rejected_by int(11),
            rejected_date datetime,
            rejection_reason text,
            priority varchar(20) DEFAULT 'medium',
            deadline datetime,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";
        
        // Workflow comments table
        $comments_table = $wpdb->prefix . 'tts_workflow_comments';
        $sql2 = "CREATE TABLE $comments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            workflow_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            comment text NOT NULL,
            comment_type varchar(50) DEFAULT 'general',
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Content templates table
        $templates_table = $wpdb->prefix . 'tts_content_templates';
        $sql3 = "CREATE TABLE $templates_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            template_content text NOT NULL,
            template_type varchar(50) NOT NULL,
            platform varchar(50),
            category varchar(100),
            created_by int(11),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            usage_count int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY template_type (template_type),
            KEY platform (platform),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Team assignments table
        $assignments_table = $wpdb->prefix . 'tts_team_assignments';
        $sql4 = "CREATE TABLE $assignments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            role varchar(50) NOT NULL,
            permissions text,
            client_access text,
            platform_access text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY role (role)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
    }

    /**
     * Submit content for approval.
     */
    public function ajax_submit_for_approval() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $assigned_to = intval( $_POST['assigned_to'] ?? 0 );
        $priority = sanitize_text_field( wp_unslash( $_POST['priority'] ?? 'medium' ) );
        $deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
        $notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( empty( $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $workflow_id = $this->submit_for_approval( $post_id, $assigned_to, $priority, $deadline, $notes );
            
            wp_send_json_success( array(
                'workflow_id' => $workflow_id,
                'message' => __( 'Content submitted for approval successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Workflow Submission Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to submit for approval. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Submit content for approval process.
     *
     * @param int $post_id Post ID.
     * @param int $assigned_to User ID to assign to.
     * @param string $priority Priority level.
     * @param string $deadline Deadline date.
     * @param string $notes Additional notes.
     * @return int Workflow ID.
     */
    private function submit_for_approval( $post_id, $assigned_to, $priority, $deadline, $notes ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        // Check if workflow already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM $workflow_table WHERE post_id = %d", $post_id )
        );
        
        $current_user = get_current_user_id();
        $deadline_formatted = $deadline ? date( 'Y-m-d H:i:s', strtotime( $deadline ) ) : null;
        
        if ( $existing ) {
            // Update existing workflow
            $result = $wpdb->update(
                $workflow_table,
                array(
                    'status' => 'pending_approval',
                    'assigned_to' => $assigned_to,
                    'submitted_by' => $current_user,
                    'submitted_date' => current_time( 'mysql' ),
                    'priority' => $priority,
                    'deadline' => $deadline_formatted
                ),
                array( 'id' => $existing->id ),
                array( '%s', '%d', '%d', '%s', '%s', '%s' ),
                array( '%d' )
            );
            
            $workflow_id = $existing->id;
        } else {
            // Create new workflow
            $result = $wpdb->insert(
                $workflow_table,
                array(
                    'post_id' => $post_id,
                    'status' => 'pending_approval',
                    'assigned_to' => $assigned_to,
                    'submitted_by' => $current_user,
                    'submitted_date' => current_time( 'mysql' ),
                    'priority' => $priority,
                    'deadline' => $deadline_formatted
                ),
                array( '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
            );
            
            $workflow_id = $wpdb->insert_id;
        }
        
        if ( false === $result ) {
            throw new Exception( 'Failed to submit content for approval' );
        }
        
        // Add notes as comment if provided
        if ( ! empty( $notes ) ) {
            $this->add_workflow_comment( $workflow_id, $current_user, $notes, 'submission_note' );
        }
        
        // Update post status
        wp_update_post( array(
            'ID' => $post_id,
            'post_status' => 'draft'
        ) );
        
        // Trigger notification
        do_action( 'tts_content_submitted_for_approval', $workflow_id, $post_id );
        
        return $workflow_id;
    }

    /**
     * Approve content.
     */
    public function ajax_approve_content() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'publish_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $workflow_id = intval( $_POST['workflow_id'] ?? 0 );
        $comments = sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) );

        if ( empty( $workflow_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid workflow ID.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $this->approve_content( $workflow_id, $comments );
            
            wp_send_json_success( array(
                'message' => __( 'Content approved successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Workflow Approval Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to approve content. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Approve content in workflow.
     *
     * @param int $workflow_id Workflow ID.
     * @param string $comments Approval comments.
     */
    private function approve_content( $workflow_id, $comments = '' ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        // Get workflow details
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $workflow_table WHERE id = %d", $workflow_id ),
            ARRAY_A
        );
        
        if ( ! $workflow ) {
            throw new Exception( 'Workflow not found' );
        }
        
        $current_user = get_current_user_id();
        
        // Update workflow status
        $result = $wpdb->update(
            $workflow_table,
            array(
                'status' => 'approved',
                'approved_by' => $current_user,
                'approved_date' => current_time( 'mysql' )
            ),
            array( 'id' => $workflow_id ),
            array( '%s', '%d', '%s' ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to update workflow status' );
        }
        
        // Add approval comment if provided
        if ( ! empty( $comments ) ) {
            $this->add_workflow_comment( $workflow_id, $current_user, $comments, 'approval' );
        }
        
        // Update post status to ready for publishing
        wp_update_post( array(
            'ID' => $workflow['post_id'],
            'post_status' => 'publish'
        ) );
        
        // Trigger notification
        do_action( 'tts_content_approved', $workflow_id, $workflow['post_id'] );
    }

    /**
     * Reject content.
     */
    public function ajax_reject_content() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'publish_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $workflow_id = intval( $_POST['workflow_id'] ?? 0 );
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( empty( $workflow_id ) || empty( $reason ) ) {
            wp_send_json_error( array( 'message' => __( 'Workflow ID and rejection reason are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $this->reject_content( $workflow_id, $reason );
            
            wp_send_json_success( array(
                'message' => __( 'Content rejected successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Workflow Rejection Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to reject content. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Reject content in workflow.
     *
     * @param int $workflow_id Workflow ID.
     * @param string $reason Rejection reason.
     */
    private function reject_content( $workflow_id, $reason ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        // Get workflow details
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $workflow_table WHERE id = %d", $workflow_id ),
            ARRAY_A
        );
        
        if ( ! $workflow ) {
            throw new Exception( 'Workflow not found' );
        }
        
        $current_user = get_current_user_id();
        
        // Update workflow status
        $result = $wpdb->update(
            $workflow_table,
            array(
                'status' => 'rejected',
                'rejected_by' => $current_user,
                'rejected_date' => current_time( 'mysql' ),
                'rejection_reason' => $reason
            ),
            array( 'id' => $workflow_id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to update workflow status' );
        }
        
        // Add rejection comment
        $this->add_workflow_comment( $workflow_id, $current_user, $reason, 'rejection' );
        
        // Keep post as draft
        wp_update_post( array(
            'ID' => $workflow['post_id'],
            'post_status' => 'draft'
        ) );
        
        // Trigger notification
        do_action( 'tts_content_rejected', $workflow_id, $workflow['post_id'], $reason );
    }

    /**
     * Add workflow comment.
     */
    public function ajax_add_workflow_comment() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $workflow_id = intval( $_POST['workflow_id'] ?? 0 );
        $comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
        $comment_type = sanitize_text_field( wp_unslash( $_POST['comment_type'] ?? 'general' ) );

        if ( empty( $workflow_id ) || empty( $comment ) ) {
            wp_send_json_error( array( 'message' => __( 'Workflow ID and comment are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $comment_id = $this->add_workflow_comment( $workflow_id, get_current_user_id(), $comment, $comment_type );
            
            wp_send_json_success( array(
                'comment_id' => $comment_id,
                'message' => __( 'Comment added successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Workflow Comment Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to add comment. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Add comment to workflow.
     *
     * @param int $workflow_id Workflow ID.
     * @param int $user_id User ID.
     * @param string $comment Comment text.
     * @param string $comment_type Comment type.
     * @return int Comment ID.
     */
    private function add_workflow_comment( $workflow_id, $user_id, $comment, $comment_type = 'general' ) {
        global $wpdb;
        
        $comments_table = $wpdb->prefix . 'tts_workflow_comments';
        
        $result = $wpdb->insert(
            $comments_table,
            array(
                'workflow_id' => $workflow_id,
                'user_id' => $user_id,
                'comment' => $comment,
                'comment_type' => $comment_type
            ),
            array( '%d', '%d', '%s', '%s' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to add workflow comment' );
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Assign task to team member.
     */
    public function ajax_assign_task() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_others_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $assigned_to = intval( $_POST['assigned_to'] ?? 0 );
        $task_type = sanitize_text_field( wp_unslash( $_POST['task_type'] ?? 'edit' ) );
        $deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
        $instructions = sanitize_textarea_field( wp_unslash( $_POST['instructions'] ?? '' ) );

        if ( empty( $post_id ) || empty( $assigned_to ) ) {
            wp_send_json_error( array( 'message' => __( 'Post ID and assignee are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $assignment_id = $this->assign_task( $post_id, $assigned_to, $task_type, $deadline, $instructions );
            
            wp_send_json_success( array(
                'assignment_id' => $assignment_id,
                'message' => __( 'Task assigned successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Task Assignment Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to assign task. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Assign task to team member.
     *
     * @param int $post_id Post ID.
     * @param int $assigned_to User ID.
     * @param string $task_type Task type.
     * @param string $deadline Deadline.
     * @param string $instructions Instructions.
     * @return int Assignment ID.
     */
    private function assign_task( $post_id, $assigned_to, $task_type, $deadline, $instructions ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        // Create or update workflow
        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id FROM $workflow_table WHERE post_id = %d", $post_id )
        );
        
        $deadline_formatted = $deadline ? date( 'Y-m-d H:i:s', strtotime( $deadline ) ) : null;
        
        if ( $existing ) {
            $result = $wpdb->update(
                $workflow_table,
                array(
                    'assigned_to' => $assigned_to,
                    'status' => 'assigned',
                    'deadline' => $deadline_formatted
                ),
                array( 'id' => $existing->id ),
                array( '%d', '%s', '%s' ),
                array( '%d' )
            );
            
            $workflow_id = $existing->id;
        } else {
            $result = $wpdb->insert(
                $workflow_table,
                array(
                    'post_id' => $post_id,
                    'assigned_to' => $assigned_to,
                    'status' => 'assigned',
                    'deadline' => $deadline_formatted
                ),
                array( '%d', '%d', '%s', '%s' )
            );
            
            $workflow_id = $wpdb->insert_id;
        }
        
        if ( false === $result ) {
            throw new Exception( 'Failed to assign task' );
        }
        
        // Add instructions as comment
        if ( ! empty( $instructions ) ) {
            $this->add_workflow_comment( $workflow_id, get_current_user_id(), $instructions, 'assignment' );
        }
        
        return $workflow_id;
    }

    /**
     * Get workflow status.
     */
    public function ajax_get_workflow_status() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );

        if ( empty( $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $status = $this->get_workflow_status( $post_id );
            
            wp_send_json_success( array(
                'status' => $status,
                'message' => __( 'Workflow status retrieved successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Workflow Status Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to get workflow status. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Get workflow status for post.
     *
     * @param int $post_id Post ID.
     * @return array Workflow status.
     */
    private function get_workflow_status( $post_id ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        $comments_table = $wpdb->prefix . 'tts_workflow_comments';
        
        // Get workflow details
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $workflow_table WHERE post_id = %d", $post_id ),
            ARRAY_A
        );
        
        if ( ! $workflow ) {
            return array(
                'status' => 'draft',
                'workflow_exists' => false
            );
        }
        
        // Get comments
        $comments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, u.display_name 
                FROM $comments_table c 
                LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
                WHERE c.workflow_id = %d 
                ORDER BY c.created_date ASC",
                $workflow['id']
            ),
            ARRAY_A
        );
        
        // Get user names
        $assigned_user = $workflow['assigned_to'] ? get_user_by( 'ID', $workflow['assigned_to'] ) : null;
        $submitted_user = $workflow['submitted_by'] ? get_user_by( 'ID', $workflow['submitted_by'] ) : null;
        $approved_user = $workflow['approved_by'] ? get_user_by( 'ID', $workflow['approved_by'] ) : null;
        $rejected_user = $workflow['rejected_by'] ? get_user_by( 'ID', $workflow['rejected_by'] ) : null;
        
        return array(
            'workflow_exists' => true,
            'status' => $workflow['status'],
            'priority' => $workflow['priority'],
            'deadline' => $workflow['deadline'],
            'assigned_to' => $assigned_user ? $assigned_user->display_name : null,
            'submitted_by' => $submitted_user ? $submitted_user->display_name : null,
            'submitted_date' => $workflow['submitted_date'],
            'approved_by' => $approved_user ? $approved_user->display_name : null,
            'approved_date' => $workflow['approved_date'],
            'rejected_by' => $rejected_user ? $rejected_user->display_name : null,
            'rejected_date' => $workflow['rejected_date'],
            'rejection_reason' => $workflow['rejection_reason'],
            'comments' => $comments,
            'created_date' => $workflow['created_date'],
            'updated_date' => $workflow['updated_date']
        );
    }

    /**
     * Create content template.
     */
    public function ajax_create_content_template() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
        $template_content = sanitize_textarea_field( wp_unslash( $_POST['template_content'] ?? '' ) );
        $template_type = sanitize_text_field( wp_unslash( $_POST['template_type'] ?? 'post' ) );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? '' ) );
        $category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );

        if ( empty( $name ) || empty( $template_content ) ) {
            wp_send_json_error( array( 'message' => __( 'Template name and content are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $template_id = $this->create_content_template( $name, $description, $template_content, $template_type, $platform, $category );
            
            wp_send_json_success( array(
                'template_id' => $template_id,
                'message' => __( 'Content template created successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Template Creation Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to create template. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Create content template.
     *
     * @param string $name Template name.
     * @param string $description Template description.
     * @param string $template_content Template content.
     * @param string $template_type Template type.
     * @param string $platform Platform.
     * @param string $category Category.
     * @return int Template ID.
     */
    private function create_content_template( $name, $description, $template_content, $template_type, $platform, $category ) {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'tts_content_templates';
        
        $result = $wpdb->insert(
            $templates_table,
            array(
                'name' => $name,
                'description' => $description,
                'template_content' => $template_content,
                'template_type' => $template_type,
                'platform' => $platform,
                'category' => $category,
                'created_by' => get_current_user_id()
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to create content template' );
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Get team dashboard data.
     */
    public function ajax_get_team_dashboard() {
        check_ajax_referer( 'tts_workflow_nonce', 'nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        try {
            $dashboard = $this->get_team_dashboard_data();
            
            wp_send_json_success( array(
                'dashboard' => $dashboard,
                'message' => __( 'Team dashboard data retrieved successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Team Dashboard Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to get dashboard data. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Get team dashboard data.
     *
     * @return array Dashboard data.
     */
    private function get_team_dashboard_data() {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        // Get workflow statistics
        $stats = array(
            'pending_approval' => $wpdb->get_var( "SELECT COUNT(*) FROM $workflow_table WHERE status = 'pending_approval'" ),
            'approved' => $wpdb->get_var( "SELECT COUNT(*) FROM $workflow_table WHERE status = 'approved'" ),
            'rejected' => $wpdb->get_var( "SELECT COUNT(*) FROM $workflow_table WHERE status = 'rejected'" ),
            'assigned' => $wpdb->get_var( "SELECT COUNT(*) FROM $workflow_table WHERE status = 'assigned'" ),
            'overdue' => $wpdb->get_var( "SELECT COUNT(*) FROM $workflow_table WHERE deadline < NOW() AND status NOT IN ('approved', 'rejected')" )
        );
        
        // Get recent workflows
        $recent_workflows = $wpdb->get_results(
            "SELECT w.*, p.post_title, u1.display_name as assigned_name, u2.display_name as submitted_name
            FROM $workflow_table w
            LEFT JOIN {$wpdb->posts} p ON w.post_id = p.ID
            LEFT JOIN {$wpdb->users} u1 ON w.assigned_to = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON w.submitted_by = u2.ID
            ORDER BY w.updated_date DESC
            LIMIT 10",
            ARRAY_A
        );
        
        // Get team performance
        $team_performance = $wpdb->get_results(
            "SELECT u.display_name, 
            COUNT(w.id) as total_assigned,
            SUM(CASE WHEN w.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN w.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            AVG(CASE WHEN w.approved_date IS NOT NULL THEN TIMESTAMPDIFF(HOUR, w.submitted_date, w.approved_date) ELSE NULL END) as avg_approval_time
            FROM {$wpdb->users} u
            LEFT JOIN $workflow_table w ON u.ID = w.assigned_to
            WHERE w.id IS NOT NULL
            GROUP BY u.ID, u.display_name
            ORDER BY total_assigned DESC
            LIMIT 10",
            ARRAY_A
        );
        
        // Get deadline alerts
        $deadline_alerts = $wpdb->get_results(
            "SELECT w.*, p.post_title, u.display_name as assigned_name
            FROM $workflow_table w
            LEFT JOIN {$wpdb->posts} p ON w.post_id = p.ID
            LEFT JOIN {$wpdb->users} u ON w.assigned_to = u.ID
            WHERE w.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            AND w.status NOT IN ('approved', 'rejected')
            ORDER BY w.deadline ASC",
            ARRAY_A
        );
        
        return array(
            'statistics' => $stats,
            'recent_workflows' => $recent_workflows,
            'team_performance' => $team_performance,
            'deadline_alerts' => $deadline_alerts,
            'generated_at' => current_time( 'mysql' )
        );
    }

    /**
     * Send approval notification email.
     *
     * @param int $workflow_id Workflow ID.
     * @param int $post_id Post ID.
     */
    public function send_approval_notification( $workflow_id, $post_id ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $workflow_table WHERE id = %d", $workflow_id ),
            ARRAY_A
        );
        
        if ( ! $workflow || ! $workflow['assigned_to'] ) {
            return;
        }
        
        $assigned_user = get_user_by( 'ID', $workflow['assigned_to'] );
        $post = get_post( $post_id );
        $submitted_user = get_user_by( 'ID', $workflow['submitted_by'] );
        
        if ( ! $assigned_user || ! $post || ! $submitted_user ) {
            return;
        }
        
        $subject = sprintf( 
            __( '[%s] Content Approval Required: %s', 'trello-social-auto-publisher' ),
            get_bloginfo( 'name' ),
            $post->post_title
        );
        
        $message = sprintf(
            __( "Hi %s,\n\nNew content has been submitted for your approval:\n\nTitle: %s\nSubmitted by: %s\nPriority: %s\nDeadline: %s\n\nPlease review and approve or reject this content in your dashboard.\n\nBest regards,\nSocial Auto Publisher", 'trello-social-auto-publisher' ),
            $assigned_user->display_name,
            $post->post_title,
            $submitted_user->display_name,
            ucfirst( $workflow['priority'] ),
            $workflow['deadline'] ? date( 'Y-m-d H:i', strtotime( $workflow['deadline'] ) ) : 'No deadline'
        );
        
        wp_mail( $assigned_user->user_email, $subject, $message );
    }

    /**
     * Send approval confirmation email.
     *
     * @param int $workflow_id Workflow ID.
     * @param int $post_id Post ID.
     */
    public function send_approval_confirmation( $workflow_id, $post_id ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $workflow_table WHERE id = %d", $workflow_id ),
            ARRAY_A
        );
        
        if ( ! $workflow || ! $workflow['submitted_by'] ) {
            return;
        }
        
        $submitted_user = get_user_by( 'ID', $workflow['submitted_by'] );
        $post = get_post( $post_id );
        $approved_user = get_user_by( 'ID', $workflow['approved_by'] );
        
        if ( ! $submitted_user || ! $post || ! $approved_user ) {
            return;
        }
        
        $subject = sprintf( 
            __( '[%s] Content Approved: %s', 'trello-social-auto-publisher' ),
            get_bloginfo( 'name' ),
            $post->post_title
        );
        
        $message = sprintf(
            __( "Hi %s,\n\nYour content has been approved:\n\nTitle: %s\nApproved by: %s\nApproved on: %s\n\nYour content is now ready for publishing.\n\nBest regards,\nSocial Auto Publisher", 'trello-social-auto-publisher' ),
            $submitted_user->display_name,
            $post->post_title,
            $approved_user->display_name,
            date( 'Y-m-d H:i', strtotime( $workflow['approved_date'] ) )
        );
        
        wp_mail( $submitted_user->user_email, $subject, $message );
    }

    /**
     * Send rejection notification email.
     *
     * @param int $workflow_id Workflow ID.
     * @param int $post_id Post ID.
     * @param string $reason Rejection reason.
     */
    public function send_rejection_notification( $workflow_id, $post_id, $reason ) {
        global $wpdb;
        
        $workflow_table = $wpdb->prefix . 'tts_workflow_states';
        
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $workflow_table WHERE id = %d", $workflow_id ),
            ARRAY_A
        );
        
        if ( ! $workflow || ! $workflow['submitted_by'] ) {
            return;
        }
        
        $submitted_user = get_user_by( 'ID', $workflow['submitted_by'] );
        $post = get_post( $post_id );
        $rejected_user = get_user_by( 'ID', $workflow['rejected_by'] );
        
        if ( ! $submitted_user || ! $post || ! $rejected_user ) {
            return;
        }
        
        $subject = sprintf( 
            __( '[%s] Content Rejected: %s', 'trello-social-auto-publisher' ),
            get_bloginfo( 'name' ),
            $post->post_title
        );
        
        $message = sprintf(
            __( "Hi %s,\n\nYour content has been rejected:\n\nTitle: %s\nRejected by: %s\nRejected on: %s\nReason: %s\n\nPlease review the feedback and resubmit when ready.\n\nBest regards,\nSocial Auto Publisher", 'trello-social-auto-publisher' ),
            $submitted_user->display_name,
            $post->post_title,
            $rejected_user->display_name,
            date( 'Y-m-d H:i', strtotime( $workflow['rejected_date'] ) ),
            $reason
        );
        
        wp_mail( $submitted_user->user_email, $subject, $message );
    }
}

// Initialize Workflow System
new TTS_Workflow_System();