# Facebook Post Scheduler - Professional WordPress Plugin

A comprehensive, production-ready WordPress plugin for scheduling and automating Facebook page posts using Meta's Graph API with OAuth 2.0 authentication, secure token management, and professional scheduling features.

## 🚀 Features

### Core Functionality
- **OAuth 2.0 Authentication**: Secure Facebook login with automatic token refresh
- **Multi-Page Management**: Connect and manage multiple Facebook pages
- **Advanced Scheduling**: Schedule posts with text, images, videos, and links
- **Real-time Preview**: See how your posts will look before publishing
- **Comprehensive Analytics**: Track post performance and page insights
- **Secure Token Storage**: Encrypted token storage with automatic refresh
- **Professional Admin Interface**: Clean, responsive WordPress admin integration

### Security & Performance
- **Encrypted Token Storage**: All Facebook tokens are encrypted in the database
- **Automatic Token Refresh**: Long-lived tokens are automatically refreshed
- **Comprehensive Logging**: Detailed activity logs for debugging and monitoring
- **Input Validation**: All user inputs are sanitized and validated
- **WordPress Standards**: Follows WordPress coding standards and best practices
- **Error Handling**: Robust error handling with user-friendly messages

### Technical Features
- **Dual Scheduling**: Uses Facebook's native scheduling with WordPress cron backup
- **File Upload Support**: Handle images and videos with proper validation
- **Bulk Operations**: Manage multiple posts efficiently
- **Database Optimization**: Efficient database structure with proper indexing
- **Internationalization**: Ready for translation (i18n)
- **Responsive Design**: Works perfectly on all devices

## 📋 Requirements

### System Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **SSL Certificate**: HTTPS is required for Facebook API
- **PHP Extensions**: cURL, JSON, OpenSSL

### Facebook Requirements
- Facebook Developer Account
- Facebook App with Business verification (for production)
- Admin access to Facebook pages you want to manage
- Valid SSL certificate on your WordPress site

## 🔧 Installation

### 1. Download and Install
1. Download the plugin files
2. Upload the `facebook-post-scheduler` folder to `/wp-content/plugins/`
3. Activate the plugin through WordPress admin
4. You'll be redirected to the dashboard automatically

### 2. Facebook App Setup

#### Step 1: Create Facebook App
1. Go to [Facebook Developers](https://developers.facebook.com/apps/)
2. Click **"Create App"**
3. Select **"Business"** as app type
4. Fill in app details:
   - **App Name**: Your website name + "Post Scheduler"
   - **Contact Email**: Your email address
   - **Business Account**: Select if you have one

#### Step 2: Configure Basic Settings
1. In App Dashboard → **Settings** → **Basic**
2. Note your **App ID** and **App Secret**
3. Add your domain to **App Domains**
4. Set **Privacy Policy URL** and **Terms of Service URL**
5. Save changes

#### Step 3: Add Facebook Login Product
1. In left sidebar → **Add Product**
2. Find **Facebook Login** → **Set Up**
3. Choose **Web** platform
4. In **Valid OAuth Redirect URIs**, add:
   ```
   https://yourdomain.com/wp-admin/admin.php?page=fps-settings
   ```
5. Save changes

#### Step 4: Request Permissions
Your app needs these permissions:
- `pages_manage_posts` - Publish posts to pages
- `pages_read_engagement` - Read engagement metrics
- `pages_show_list` - List user's pages

For production, submit for App Review in **App Review** → **Permissions and Features**

### 3. Plugin Configuration

#### Method 1: WordPress Admin Setup (Recommended)
1. Go to **Facebook Scheduler** → **Settings**
2. Enter your **App ID** and **App Secret**
3. Click **"Connect Facebook Account"**
4. Authorize the app and select pages
5. Save settings

#### Method 2: Manual Configuration
If OAuth doesn't work due to network restrictions:
1. Get a long-lived page access token from [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Use the manual configuration section in settings
3. Test connection before saving

## 📖 Usage Guide

### Scheduling Your First Post

1. **Go to Schedule Post**
   - Navigate to **Facebook Scheduler** → **Schedule Post**

2. **Select Facebook Page**
   - Choose from your connected pages

3. **Create Content**
   - Write your message (supports emojis and hashtags)
   - Add links for automatic preview generation
   - Upload images or videos (optional)

4. **Set Schedule**
   - Pick date and time (must be in future)
   - Preview shows in your WordPress timezone

5. **Preview & Schedule**
   - Review the Facebook-style preview
   - Click **"Schedule Post"** to confirm

### Managing Scheduled Posts

1. **View All Posts**
   - Go to **Facebook Scheduler** → **Scheduled Posts**
   - Filter by status: Scheduled, Published, Failed
   - Search posts by content

2. **Edit Scheduled Posts**
   - Click edit icon on pending posts
   - Modify message, links, or schedule time
   - Changes sync with Facebook automatically

3. **Monitor Performance**
   - Check **Analytics** for insights
   - View engagement metrics
   - Track posting success rates

### Analytics & Insights

1. **Page Analytics**
   - Go to **Facebook Scheduler** → **Analytics**
   - Select page to view insights
   - See reach, impressions, and engagement

2. **Post Performance**
   - View individual post metrics
   - Track clicks, likes, shares, comments
   - Export data for reporting

## 🛠️ Advanced Configuration

### Custom Scheduling Options

```php
// Add to your theme's functions.php
add_filter('fps_post_settings', function($settings) {
    $settings['use_facebook_scheduling'] = true; // Use Facebook's native scheduling
    $settings['auto_link_preview'] = true;       // Generate link previews
    $settings['image_quality'] = 'high';         // Image quality setting
    return $settings;
});
```

### Custom Post Processing

```php
// Modify post data before scheduling
add_filter('fps_before_schedule_post', function($post_data) {
    // Add custom hashtags
    $post_data['message'] .= ' #YourHashtag';
    
    // Add UTM parameters to links
    if (!empty($post_data['link'])) {
        $post_data['link'] = add_query_arg([
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'scheduled_post'
        ], $post_data['link']);
    }
    
    return $post_data;
});
```

### Webhook Integration

```php
// Handle Facebook webhooks for real-time updates
add_action('fps_facebook_webhook', function($data) {
    if ($data['object'] === 'page') {
        foreach ($data['entry'] as $entry) {
            // Process page events
            FPS_Logger::info('Webhook received for page: ' . $entry['id']);
        }
    }
});
```

## 🔍 Troubleshooting

### Common Issues

#### "App ID not configured"
**Solution**: Enter your Facebook App ID in Settings → Facebook App

#### "No pages found"
**Causes**:
- Not admin of any Facebook pages
- App permissions not granted
- Token expired

**Solution**:
1. Ensure you're admin of at least one Facebook page
2. Reconnect your account with proper permissions
3. Check App Review status for production apps

#### "Failed to publish post"
**Causes**:
- Token expired
- Page permissions changed
- Facebook API limits
- Network connectivity

**Solution**:
1. Check connection in Settings
2. Refresh page tokens
3. Verify page admin status
4. Check error logs

#### "WordPress Cron not working"
**Causes**:
- `DISABLE_WP_CRON` is true
- Server doesn't support cron
- High traffic blocking cron

**Solution**:
1. Enable WordPress cron in wp-config.php
2. Set up server-level cron job
3. Use Facebook's native scheduling

### Debug Mode

Enable debug logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs in:
- WordPress: `/wp-content/debug.log`
- Plugin: **Facebook Scheduler** → **Logs** (when WP_DEBUG is enabled)

### API Testing

Test your setup:

1. **Graph API Explorer**: https://developers.facebook.com/tools/explorer/
2. **Access Token Debugger**: https://developers.facebook.com/tools/debug/accesstoken/
3. **App Dashboard**: https://developers.facebook.com/apps/

### Performance Optimization

For high-volume sites:

```php
// Optimize database queries
add_filter('fps_posts_per_page', function() {
    return 50; // Increase posts per page
});

// Reduce log retention
add_filter('fps_log_retention_days', function() {
    return 7; // Keep logs for 7 days only
});
```

## 🔒 Security Best Practices

### Token Security
- Tokens are encrypted using OpenSSL AES-256-CBC
- Encryption keys are unique per installation
- Tokens are automatically refreshed before expiration
- Failed tokens are logged and removed

### Data Protection
- All user inputs are sanitized
- SQL queries use prepared statements
- File uploads are validated and restricted
- CSRF protection on all forms

### Access Control
- Only users with `manage_options` capability can access
- All AJAX requests verify nonces
- IP addresses and user agents are logged
- Failed attempts are monitored

## 📊 Database Schema

### Tables Created
- `wp_fps_scheduled_posts` - Scheduled post data
- `wp_fps_page_tokens` - Encrypted page tokens
- `wp_fps_logs` - Activity logs
- `wp_fps_page_insights` - Analytics data

### Data Retention
- Published posts: 90 days (configurable)
- Activity logs: 30 days (configurable)
- Failed posts: Kept until manually deleted
- Analytics: 1 year (configurable)

## 🌐 Internationalization

The plugin is translation-ready. To add your language:

1. Create translation files in `/languages/` folder
2. Use WordPress translation tools
3. Submit translations to WordPress.org

Current translations:
- English (default)
- Portuguese (included)

## 🔄 Updates & Maintenance

### Automatic Updates
- Database schema updates automatically
- Settings migrate between versions
- Backward compatibility maintained

### Manual Maintenance
- Clean old logs: **Settings** → **Advanced** → **Clean Logs**
- Refresh tokens: **Settings** → **Account** → **Test Connection**
- Database cleanup: Runs automatically weekly

## 📞 Support

### Getting Help
1. Check this README for solutions
2. Enable debug logging and check logs
3. Test with Facebook's Graph API Explorer
4. Verify app permissions and review status

### Reporting Issues
When reporting issues, include:
- WordPress version
- PHP version
- Plugin version
- Error messages from logs
- Steps to reproduce

### Contributing
1. Fork the repository
2. Create feature branch
3. Follow WordPress coding standards
4. Add tests for new features
5. Submit pull request

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🙏 Credits

- **Facebook Graph API**: Meta Platforms, Inc.
- **WordPress**: WordPress Foundation
- **Icons**: WordPress Dashicons

---

## 📚 Developer Documentation

### Hooks and Filters

#### Actions
```php
// Before post is scheduled
do_action('fps_before_schedule_post', $post_data);

// After post is published
do_action('fps_after_publish_post', $post_id, $facebook_response);

// When token is refreshed
do_action('fps_token_refreshed', $page_id, $new_token);
```

#### Filters
```php
// Modify post data before scheduling
apply_filters('fps_post_data', $post_data);

// Customize Facebook API parameters
apply_filters('fps_api_params', $params, $endpoint);

// Modify scheduling intervals
apply_filters('fps_cron_schedules', $schedules);
```

### API Reference

#### FPS_Facebook_API
```php
// Create a post
$api = new FPS_Facebook_API($token_manager);
$result = $api->create_post($page_id, $post_data);

// Get page insights
$insights = $api->get_page_insights($page_id, $metrics);

// Test connection
$test = $api->test_connection($access_token);
```

#### FPS_Scheduler
```php
// Schedule a post
$scheduler = new FPS_Scheduler($facebook_api);
$post_id = $scheduler->schedule_post($post_data);

// Get scheduled posts
$posts = $scheduler->get_scheduled_posts($args);

// Update scheduled post
$scheduler->update_scheduled_post($post_id, $update_data);
```

#### FPS_Logger
```php
// Log messages
FPS_Logger::info('Post scheduled successfully');
FPS_Logger::error('Failed to publish post', $context);
FPS_Logger::warning('Token expires soon');

// Get logs
$logs = FPS_Logger::get_logs($args);
```

### Database Schema

#### fps_scheduled_posts
```sql
CREATE TABLE wp_fps_scheduled_posts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    page_id varchar(50) NOT NULL,
    message longtext NOT NULL,
    link varchar(500) DEFAULT '',
    image_url varchar(500) DEFAULT '',
    image_path varchar(500) DEFAULT '',
    video_url varchar(500) DEFAULT '',
    video_path varchar(500) DEFAULT '',
    scheduled_time datetime NOT NULL,
    status varchar(20) DEFAULT 'scheduled',
    facebook_post_id varchar(100) DEFAULT '',
    permalink varchar(500) DEFAULT '',
    error_message text DEFAULT '',
    retry_count int(11) DEFAULT 0,
    created_by bigint(20) unsigned DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY page_id (page_id),
    KEY scheduled_time (scheduled_time),
    KEY status (status)
);
```

## 🔐 Security Considerations

### Token Management
- All tokens are encrypted using AES-256-CBC
- Encryption keys are unique per installation
- Tokens are stored separately from main options
- Automatic cleanup of expired tokens

### Data Validation
- All inputs sanitized using WordPress functions
- File uploads validated for type and size
- SQL queries use prepared statements
- CSRF protection on all forms

### Access Control
- Capability checks on all admin functions
- Nonce verification on AJAX requests
- User activity logging
- IP address tracking

## 🚀 Production Deployment

### Pre-deployment Checklist
- [ ] Facebook App reviewed and approved
- [ ] SSL certificate installed and working
- [ ] WordPress and PHP versions meet requirements
- [ ] Database backups configured
- [ ] Error logging enabled
- [ ] Cron jobs working properly

### Performance Optimization
- Enable object caching (Redis/Memcached)
- Use CDN for media files
- Configure proper cron intervals
- Monitor database performance
- Set up log rotation

### Monitoring
- Monitor error logs regularly
- Check token expiration dates
- Verify cron job execution
- Track API rate limits
- Monitor post success rates

---

**Note**: This plugin requires a Facebook Developer account and proper app configuration. Make sure to follow Facebook's Platform Policies and Terms of Service when using this plugin.

For the most up-to-date documentation and support, visit the [plugin repository](https://github.com/yourname/facebook-post-scheduler).