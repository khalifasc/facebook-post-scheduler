<?php
/**
 * Token Manager Class
 * 
 * Handles Facebook OAuth tokens with encryption and automatic refresh
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPS_Token_Manager {
    
    /**
     * Encryption key for tokens
     * @var string
     */
    private $encryption_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->encryption_key = $this->get_encryption_key();
    }
    
    /**
     * Get or create encryption key
     * 
     * @return string
     */
    private function get_encryption_key() {
        $key = get_option('fps_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            add_option('fps_encryption_key', $key, '', false); // Don't autoload
        }
        
        return $key;
    }
    
    /**
     * Encrypt token data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    private function encrypt($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback to base64
        }
        
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($data, $method, $this->encryption_key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt token data
     * 
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    private function decrypt($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback from base64
        }
        
        $data = base64_decode($encrypted_data);
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $this->encryption_key, 0, $iv);
    }
    
    /**
     * Store user access token
     * 
     * @param int $user_id User ID
     * @param array $token_data Token data
     * @return bool Success
     */
    public function store_user_token($user_id, $token_data) {
        $encrypted_data = $this->encrypt(json_encode($token_data));
        
        $result = update_user_meta($user_id, 'fps_facebook_token', $encrypted_data);
        
        if ($result) {
            FPS_Logger::log("User token stored for user {$user_id}", 'info');
            return true;
        }
        
        FPS_Logger::log("Failed to store user token for user {$user_id}", 'error');
        return false;
    }
    
    /**
     * Get user access token
     * 
     * @param int $user_id User ID
     * @return array|false Token data or false
     */
    public function get_user_token($user_id) {
        $encrypted_data = get_user_meta($user_id, 'fps_facebook_token', true);
        
        if (!$encrypted_data) {
            return false;
        }
        
        $decrypted_data = $this->decrypt($encrypted_data);
        $token_data = json_decode($decrypted_data, true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return false;
        }
        
        // Check if token is expired
        if (isset($token_data['expires_at']) && time() > $token_data['expires_at']) {
            FPS_Logger::log("Token expired for user {$user_id}", 'warning');
            return false;
        }
        
        return $token_data;
    }
    
    /**
     * Store page access token
     * 
     * @param string $page_id Page ID
     * @param array $token_data Token data
     * @return bool Success
     */
    public function store_page_token($page_id, $token_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        $encrypted_data = $this->encrypt(json_encode($token_data));
        
        $result = $wpdb->replace(
            $table_name,
            array(
                'page_id' => $page_id,
                'token_data' => $encrypted_data,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            FPS_Logger::log("Page token stored for page {$page_id}", 'info');
            return true;
        }
        
        FPS_Logger::log("Failed to store page token for page {$page_id}", 'error');
        return false;
    }
    
    /**
     * Get page access token
     * 
     * @param string $page_id Page ID
     * @return array|false Token data or false
     */
    public function get_page_token($page_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        
        $encrypted_data = $wpdb->get_var($wpdb->prepare(
            "SELECT token_data FROM {$table_name} WHERE page_id = %s",
            $page_id
        ));
        
        if (!$encrypted_data) {
            return false;
        }
        
        $decrypted_data = $this->decrypt($encrypted_data);
        $token_data = json_decode($decrypted_data, true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return false;
        }
        
        // Check if token is expired
        if (isset($token_data['expires_at']) && time() > $token_data['expires_at']) {
            FPS_Logger::log("Page token expired for page {$page_id}", 'warning');
            return false;
        }
        
        return $token_data;
    }
    
    /**
     * Exchange short-lived token for long-lived token
     * 
     * @param string $short_token Short-lived access token
     * @return array|false Long-lived token data or false
     */
    public function exchange_for_long_lived_token($short_token) {
        $app_id = get_option('fps_facebook_app_id');
        $app_secret = get_option('fps_facebook_app_secret');
        
        if (!$app_id || !$app_secret) {
            FPS_Logger::log('App ID or App Secret not configured', 'error');
            return false;
        }
        
        $url = 'https://graph.facebook.com/v18.0/oauth/access_token';
        $params = array(
            'grant_type' => 'fb_exchange_token',
            'client_id' => $app_id,
            'client_secret' => $app_secret,
            'fb_exchange_token' => $short_token
        );
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
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
        
        // Calculate expiration time
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 5184000; // 60 days default
        $expires_at = time() + $expires_in;
        
        $token_data = array(
            'access_token' => $data['access_token'],
            'token_type' => isset($data['token_type']) ? $data['token_type'] : 'bearer',
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
            'created_at' => time()
        );
        
        FPS_Logger::log('Long-lived token obtained successfully', 'info');
        return $token_data;
    }
    
    /**
     * Refresh all stored tokens
     */
    public function refresh_all_tokens() {
        global $wpdb;
        
        // Get all page tokens
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        $tokens = $wpdb->get_results("SELECT page_id, token_data FROM {$table_name}");
        
        foreach ($tokens as $token_row) {
            $token_data = json_decode($this->decrypt($token_row->token_data), true);
            
            if (!$token_data || !isset($token_data['access_token'])) {
                continue;
            }
            
            // Check if token expires within 7 days
            $expires_at = isset($token_data['expires_at']) ? $token_data['expires_at'] : 0;
            $seven_days = 7 * DAY_IN_SECONDS;
            
            if ($expires_at > 0 && ($expires_at - time()) < $seven_days) {
                $new_token_data = $this->exchange_for_long_lived_token($token_data['access_token']);
                
                if ($new_token_data) {
                    $this->store_page_token($token_row->page_id, $new_token_data);
                    FPS_Logger::log("Token refreshed for page {$token_row->page_id}", 'info');
                } else {
                    FPS_Logger::log("Failed to refresh token for page {$token_row->page_id}", 'error');
                }
            }
        }
    }
    
    /**
     * Validate access token
     * 
     * @param string $access_token Access token to validate
     * @return array|false Token info or false
     */
    public function validate_token($access_token) {
        $url = 'https://graph.facebook.com/v18.0/me';
        $params = array(
            'access_token' => $access_token,
            'fields' => 'id,name,email'
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
     * Remove user token
     * 
     * @param int $user_id User ID
     * @return bool Success
     */
    public function remove_user_token($user_id) {
        $result = delete_user_meta($user_id, 'fps_facebook_token');
        
        if ($result) {
            FPS_Logger::log("User token removed for user {$user_id}", 'info');
        }
        
        return $result;
    }
    
    /**
     * Remove page token
     * 
     * @param string $page_id Page ID
     * @return bool Success
     */
    public function remove_page_token($page_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fps_page_tokens';
        
        $result = $wpdb->delete(
            $table_name,
            array('page_id' => $page_id),
            array('%s')
        );
        
        if ($result !== false) {
            FPS_Logger::log("Page token removed for page {$page_id}", 'info');
            return true;
        }
        
        return false;
    }
}