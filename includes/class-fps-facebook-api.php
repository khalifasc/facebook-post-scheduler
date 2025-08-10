<?php
/**
 * Facebook API Handler Class
 * 
 * Handles all Facebook Graph API interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Facebook_API {
    
    /**
     * Token manager instance
     * @var FPS_Token_Manager
     */
    private $token_manager;
    
    /**
     * Facebook Graph API base URL
     * @var string
     */
    private $api_base_url = 'https://graph.facebook.com/v18.0/';
    
    /**
     * Constructor
     * 
     * @param FPS_Token_Manager $token_manager Token manager instance
     */
    public function __construct($token_manager) {
        $this->token_manager = $token_manager;
    }
    
    /**
     * Get Facebook login URL
     * 
     * @param string $redirect_uri Redirect URI
     * @param array $permissions Required permissions
     * @return string Login URL
     */
    public function get_login_url($redirect_uri, $permissions = array()) {
        $app_id = get_option('fps_facebook_app_id');
        
        if (!$app_id) {
            return false;
        }
        
        $default_permissions = array(
            'pages_manage_posts',
            'pages_read_engagement',
            'pages_show_list'
        );
        
        $permissions = array_merge($default_permissions, $permissions);
        
        $params = array(
            'client_id' => $app_id,
            'redirect_uri' => $redirect_uri,
            'scope' => implode(',', $permissions),
            'response_type' => 'code',
            'state' => wp_create_nonce('fps_facebook_oauth')
        );
        
        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code
     * @param string $redirect_uri Redirect URI
     * @return array|false Token data or false
     */
    public function exchange_code_for_token($code, $redirect_uri) {
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if (!$app_id || !$app_secret) {
            FPS_Logger::log('App ID or App Secret not configured', 'error');
            return false;
        }
        
        $url = $this->api_base_url . 'oauth/access_token';
        $params = array(
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code
        );
        
        $response = wp_remote_post($url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('Token exchange failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('Token exchange error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        if (!isset($data['access_token'])) {
            FPS_Logger::log('Invalid token exchange response', 'error');
            return false;
        }
        
        // Exchange for long-lived token
        return $this->token_manager->exchange_for_long_lived_token($data['access_token']);
    }
    
    /**
     * Get user's Facebook pages
     * 
     * @param int $user_id User ID
     * @return array|false Pages data or false
     */
    public function get_user_pages($user_id) {
        $token_data = $this->token_manager->get_user_token($user_id);
        
        if (!$token_data) {
            return false;
        }
        
        $url = $this->api_base_url . 'me/accounts';
        $params = array(
            'access_token' => $token_data['access_token'],
            'fields' => 'id,name,access_token,category,picture,fan_count,tasks'
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('Failed to get user pages: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('Get pages error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        if (!isset($data['data'])) {
            return array();
        }
        
        // Store page tokens
        foreach ($data['data'] as $page) {
            if (isset($page['access_token'])) {
                $page_token_data = array(
                    'access_token' => $page['access_token'],
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                    'created_at' => time(),
                    'expires_at' => 0 // Page tokens don't expire
                );
                
                $this->token_manager->store_page_token($page['id'], $page_token_data);
            }
        }
        
        FPS_Logger::log('Retrieved ' . count($data['data']) . ' pages for user ' . $user_id, 'info');
        return $data['data'];
    }
    
    /**
     * Create a Facebook post
     * 
     * @param string $page_id Page ID
     * @param array $post_data Post data
     * @return array|false Post response or false
     */
    public function create_post($page_id, $post_data) {
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            FPS_Logger::log("No valid token for page {$page_id}", 'error');
            return false;
        }
        
        // Prepare post parameters
        $params = array(
            'access_token' => $token_data['access_token']
        );
        
        // Add message
        if (!empty($post_data['message'])) {
            $params['message'] = $post_data['message'];
        }
        
        // Add link
        if (!empty($post_data['link'])) {
            $params['link'] = $post_data['link'];
        }
        
        // Add scheduled publish time
        if (!empty($post_data['scheduled_publish_time'])) {
            $params['published'] = 'false';
            $params['scheduled_publish_time'] = $post_data['scheduled_publish_time'];
        }
        
        // Determine endpoint based on content type
        $endpoint = 'feed';
        
        // Handle image upload
        if (!empty($post_data['image_path']) && file_exists($post_data['image_path'])) {
            $endpoint = 'photos';
            $params['source'] = new CURLFile($post_data['image_path']);
            
            if (!empty($post_data['message'])) {
                $params['caption'] = $post_data['message'];
                unset($params['message']);
            }
        } elseif (!empty($post_data['image_url'])) {
            $endpoint = 'photos';
            $params['url'] = $post_data['image_url'];
            
            if (!empty($post_data['message'])) {
                $params['caption'] = $post_data['message'];
                unset($params['message']);
            }
        }
        
        // Handle video upload
        if (!empty($post_data['video_path']) && file_exists($post_data['video_path'])) {
            $endpoint = 'videos';
            $params['source'] = new CURLFile($post_data['video_path']);
            
            if (!empty($post_data['message'])) {
                $params['description'] = $post_data['message'];
                unset($params['message']);
            }
        }
        
        $url = $this->api_base_url . $page_id . '/' . $endpoint;
        
        // Use cURL for file uploads, wp_remote_post for others
        if (isset($params['source'])) {
            return $this->curl_post($url, $params);
        } else {
            return $this->wp_remote_post($url, $params);
        }
    }
    
    /**
     * Update a scheduled post
     * 
     * @param string $post_id Post ID
     * @param array $post_data Updated post data
     * @return array|false Response or false
     */
    public function update_post($post_id, $post_data) {
        // Get the page ID from the post
        $post_info = $this->get_post($post_id);
        
        if (!$post_info || !isset($post_info['from']['id'])) {
            return false;
        }
        
        $page_id = $post_info['from']['id'];
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $params = array(
            'access_token' => $token_data['access_token']
        );
        
        // Add updatable fields
        if (isset($post_data['message'])) {
            $params['message'] = $post_data['message'];
        }
        
        if (isset($post_data['scheduled_publish_time'])) {
            $params['scheduled_publish_time'] = $post_data['scheduled_publish_time'];
        }
        
        $url = $this->api_base_url . $post_id;
        
        return $this->wp_remote_post($url, $params);
    }
    
    /**
     * Delete a post
     * 
     * @param string $post_id Post ID
     * @return bool Success
     */
    public function delete_post($post_id) {
        // Get the page ID from the post
        $post_info = $this->get_post($post_id);
        
        if (!$post_info || !isset($post_info['from']['id'])) {
            return false;
        }
        
        $page_id = $post_info['from']['id'];
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $url = $this->api_base_url . $post_id;
        $params = array(
            'access_token' => $token_data['access_token']
        );
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('Failed to delete post: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('Delete post error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        return isset($data['success']) && $data['success'];
    }
    
    /**
     * Get post information
     * 
     * @param string $post_id Post ID
     * @return array|false Post data or false
     */
    public function get_post($post_id) {
        // Try to get page token from stored posts first
        global $wpdb;
        $table_name = $wpdb->prefix . 'fps_scheduled_posts';
        
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT page_id FROM {$table_name} WHERE facebook_post_id = %s",
            $post_id
        ));
        
        if (!$page_id) {
            // Try to extract page ID from post ID format
            $parts = explode('_', $post_id);
            if (count($parts) >= 2) {
                $page_id = $parts[0];
            }
        }
        
        if (!$page_id) {
            return false;
        }
        
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $url = $this->api_base_url . $post_id;
        $params = array(
            'access_token' => $token_data['access_token'],
            'fields' => 'id,message,created_time,scheduled_publish_time,is_published,from,permalink_url'
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get page insights
     * 
     * @param string $page_id Page ID
     * @param array $metrics Metrics to retrieve
     * @param string $period Time period
     * @return array|false Insights data or false
     */
    public function get_page_insights($page_id, $metrics = array(), $period = 'day') {
        $token_data = $this->token_manager->get_page_token($page_id);
        
        if (!$token_data) {
            return false;
        }
        
        $default_metrics = array(
            'page_impressions',
            'page_reach',
            'page_engaged_users',
            'page_post_engagements'
        );
        
        $metrics = empty($metrics) ? $default_metrics : $metrics;
        
        $url = $this->api_base_url . $page_id . '/insights';
        $params = array(
            'access_token' => $token_data['access_token'],
            'metric' => implode(',', $metrics),
            'period' => $period
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return false;
        }
        
        return isset($data['data']) ? $data['data'] : array();
    }
    
    /**
     * Make POST request using wp_remote_post
     * 
     * @param string $url URL
     * @param array $params Parameters
     * @return array|false Response or false
     */
    private function wp_remote_post($url, $params) {
        $response = wp_remote_post($url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            FPS_Logger::log('API request failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('API error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Make POST request using cURL (for file uploads)
     * 
     * @param string $url URL
     * @param array $params Parameters
     * @return array|false Response or false
     */
    private function curl_post($url, $params) {
        if (!function_exists('curl_init')) {
            FPS_Logger::log('cURL not available for file upload', 'error');
            return false;
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'WordPress Facebook Post Scheduler v' . FPS_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            FPS_Logger::log('cURL error: ' . $error, 'error');
            return false;
        }
        
        if ($http_code !== 200) {
            FPS_Logger::log('HTTP error: ' . $http_code, 'error');
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            FPS_Logger::log('API error: ' . $data['error']['message'], 'error');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Test API connection
     * 
     * @param string $access_token Access token to test
     * @return array|false Test result or false
     */
    public function test_connection($access_token) {
        $url = $this->api_base_url . 'me';
        $params = array(
            'access_token' => $access_token,
            'fields' => 'id,name'
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'WordPress Facebook Post Scheduler v' . FPS_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return array(
                'success' => false,
                'message' => $data['error']['message']
            );
        }
        
        return array(
            'success' => true,
            'data' => $data
        );
    }
}