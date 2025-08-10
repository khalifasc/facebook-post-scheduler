<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests from admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Ajax_Handler {
    
    /**
     * Facebook API instance
     * @var FPS_Facebook_API
     */
    private $facebook_api;
    
    /**
     * Scheduler instance
     * @var FPS_Scheduler
     */
    private $scheduler;
    
    /**
     * Token manager instance
     * @var FPS_Token_Manager
     */
    private $token_manager;
    
    /**
     * Constructor
     * 
     * @param FPS_Facebook_API $facebook_api Facebook API instance
     * @param FPS_Scheduler $scheduler Scheduler instance
     * @param FPS_Token_Manager $token_manager Token manager instance
     */
    public function __construct($facebook_api, $scheduler, $token_manager) {
        $this->facebook_api = $facebook_api;
        $this->scheduler = $scheduler;
        $this->token_manager = $token_manager;
        
        // Register AJAX handlers
        add_action('wp_ajax_fps_schedule_post', array($this, 'handle_schedule_post'));
        add_action('wp_ajax_fps_update_post', array($this, 'handle_update_post'));
        add_action('wp_ajax_fps_delete_post', array($this, 'handle_delete_post'));
        add_action('wp_ajax_fps_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_fps_disconnect_facebook', array($this, 'handle_disconnect_facebook'));
        add_action('wp_ajax_fps_refresh_pages', array($this, 'handle_refresh_pages'));
        add_action('wp_ajax_fps_get_post_preview', array($this, 'handle_get_post_preview'));
        add_action('wp_ajax_fps_get_insights', array($this, 'handle_get_insights'));
    }
    
    /**
     * Handle schedule post AJAX request
     */
    public function handle_schedule_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate required fields
        $required_fields = array('page_id', 'message', 'scheduled_time');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => sprintf(__('Field %s is required', 'facebook-post-scheduler'), $field)));
            }
        }
        
        // Prepare post data
        $post_data = array(
            'page_id' => sanitize_text_field($_POST['page_id']),
            'message' => wp_kses_post($_POST['message']),
            'scheduled_time' => sanitize_text_field($_POST['scheduled_time']),
            'link' => !empty($_POST['link']) ? esc_url_raw($_POST['link']) : '',
        );
        
        // Handle file uploads
        if (!empty($_FILES['image']['name'])) {
            $post_data['image_file'] = $_FILES['image'];
        }
        
        if (!empty($_FILES['video']['name'])) {
            $post_data['video_file'] = $_FILES['video'];
        }
        
        // Validate scheduled time
        $scheduled_timestamp = strtotime($post_data['scheduled_time']);
        if ($scheduled_timestamp <= time()) {
            wp_send_json_error(array('message' => __('Scheduled time must be in the future', 'facebook-post-scheduler')));
        }
        
        // Schedule the post
        $post_id = $this->scheduler->schedule_post($post_data);
        
        if ($post_id) {
            wp_send_json_success(array(
                'message' => __('Post scheduled successfully!', 'facebook-post-scheduler'),
                'post_id' => $post_id,
                'redirect' => admin_url('admin.php?page=fps-scheduled-posts')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to schedule post', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle update post AJAX request
     */
    public function handle_update_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Post ID is required', 'facebook-post-scheduler')));
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Prepare update data
        $update_data = array();
        
        if (isset($_POST['message'])) {
            $update_data['message'] = wp_kses_post($_POST['message']);
        }
        
        if (isset($_POST['link'])) {
            $update_data['link'] = esc_url_raw($_POST['link']);
        }
        
        if (isset($_POST['scheduled_time'])) {
            $scheduled_timestamp = strtotime($_POST['scheduled_time']);
            if ($scheduled_timestamp > time()) {
                $update_data['scheduled_time'] = sanitize_text_field($_POST['scheduled_time']);
            } else {
                wp_send_json_error(array('message' => __('Scheduled time must be in the future', 'facebook-post-scheduler')));
            }
        }
        
        // Update the post
        $result = $this->scheduler->update_scheduled_post($post_id, $update_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Post updated successfully!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to update post', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle delete post AJAX request
     */
    public function handle_delete_post() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        // Validate post ID
        if (empty($_POST['post_id'])) {
            wp_send_json_error(array('message' => __('Post ID is required', 'facebook-post-scheduler')));
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Delete the post
        $result = $this->scheduler->delete_scheduled_post($post_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Post deleted successfully!', 'facebook-post-scheduler')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete post', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle test connection AJAX request
     */
    public function handle_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        $token_data = $this->token_manager->get_user_token($user_id);
        
        if (!$token_data) {
            wp_send_json_error(array('message' => __('No Facebook token found. Please connect your account first.', 'facebook-post-scheduler')));
        }
        
        // Test the connection
        $result = $this->facebook_api->test_connection($token_data['access_token']);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Connection test successful!', 'facebook-post-scheduler'),
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Handle disconnect Facebook AJAX request
     */
    public function handle_disconnect_facebook() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        
        // Remove user token
        $this->token_manager->remove_user_token($user_id);
        
        // Remove user pages
        delete_user_meta($user_id, 'fps_facebook_pages');
        
        // Clear selected pages
        delete_option('fps_selected_pages');
        
        wp_send_json_success(array('message' => __('Facebook account disconnected successfully!', 'facebook-post-scheduler')));
    }
    
    /**
     * Handle refresh pages AJAX request
     */
    public function handle_refresh_pages() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $user_id = get_current_user_id();
        
        // Get fresh pages from Facebook
        $pages = $this->facebook_api->get_user_pages($user_id);
        
        if ($pages !== false) {
            update_user_meta($user_id, 'fps_facebook_pages', $pages);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Found %d pages', 'facebook-post-scheduler'), count($pages)),
                'pages' => $pages
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to refresh pages', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Handle get post preview AJAX request
     */
    public function handle_get_post_preview() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';
        $link = isset($_POST['link']) ? esc_url_raw($_POST['link']) : '';
        
        // Generate preview HTML
        $preview_html = $this->generate_post_preview($message, $link);
        
        wp_send_json_success(array('preview' => $preview_html));
    }
    
    /**
     * Handle get insights AJAX request
     */
    public function handle_get_insights() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fps_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'facebook-post-scheduler')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'facebook-post-scheduler')));
        }
        
        $page_id = isset($_POST['page_id']) ? sanitize_text_field($_POST['page_id']) : '';
        
        if (empty($page_id)) {
            wp_send_json_error(array('message' => __('Page ID is required', 'facebook-post-scheduler')));
        }
        
        // Get insights from Facebook
        $insights = $this->facebook_api->get_page_insights($page_id);
        
        if ($insights !== false) {
            wp_send_json_success(array('insights' => $insights));
        } else {
            wp_send_json_error(array('message' => __('Failed to get insights', 'facebook-post-scheduler')));
        }
    }
    
    /**
     * Generate post preview HTML
     * 
     * @param string $message Post message
     * @param string $link Post link
     * @return string Preview HTML
     */
    private function generate_post_preview($message, $link) {
        ob_start();
        ?>
        <div class="fps-post-preview">
            <div class="fps-post-header">
                <div class="fps-post-avatar">
                    <div class="fps-avatar-placeholder"></div>
                </div>
                <div class="fps-post-meta">
                    <div class="fps-page-name"><?php _e('Your Facebook Page', 'facebook-post-scheduler'); ?></div>
                    <div class="fps-post-time"><?php _e('Scheduled post', 'facebook-post-scheduler'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="fps-post-content">
                <?php echo nl2br(esc_html($message)); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($link)): ?>
            <div class="fps-post-link">
                <div class="fps-link-preview">
                    <div class="fps-link-image"></div>
                    <div class="fps-link-content">
                        <div class="fps-link-title"><?php echo esc_html(parse_url($link, PHP_URL_HOST)); ?></div>
                        <div class="fps-link-url"><?php echo esc_html($link); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="fps-post-actions">
                <span class="fps-action"><?php _e('Like', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action"><?php _e('Comment', 'facebook-post-scheduler'); ?></span>
                <span class="fps-action"><?php _e('Share', 'facebook-post-scheduler'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}