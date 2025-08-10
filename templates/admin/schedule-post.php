<?php
/**
 * Schedule Post Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Schedule New Post', 'facebook-post-scheduler'); ?></h1>
    
    <?php if (empty($pages)): ?>
    <div class="notice notice-warning">
        <p>
            <?php _e('No Facebook pages found. Please', 'facebook-post-scheduler'); ?>
            <a href="<?php echo admin_url('admin.php?page=fps-settings'); ?>"><?php _e('connect your Facebook account', 'facebook-post-scheduler'); ?></a>
            <?php _e('first.', 'facebook-post-scheduler'); ?>
        </p>
    </div>
    <?php else: ?>
    
    <form id="fps-schedule-form" class="fps-schedule-form">
        <?php wp_nonce_field('fps_admin_nonce', 'fps_nonce'); ?>
        
        <div class="fps-form-grid">
            <!-- Left Column -->
            <div class="fps-form-column">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Post Content', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <!-- Facebook Page Selection -->
                        <div class="fps-form-group">
                            <label for="fps-page-id"><?php _e('Facebook Page', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <select id="fps-page-id" name="page_id" required>
                                <option value=""><?php _e('Select a page...', 'facebook-post-scheduler'); ?></option>
                                <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page['id']); ?>">
                                    <?php echo esc_html($page['name']); ?>
                                    <?php if (isset($page['category'])): ?>
                                    (<?php echo esc_html($page['category']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Post Message -->
                        <div class="fps-form-group">
                            <label for="fps-message"><?php _e('Message', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <textarea id="fps-message" name="message" rows="6" placeholder="<?php _e('What do you want to share?', 'facebook-post-scheduler'); ?>" required></textarea>
                            <div class="fps-character-count">
                                <span id="fps-char-count">0</span> <?php _e('characters', 'facebook-post-scheduler'); ?>
                            </div>
                        </div>
                        
                        <!-- Link -->
                        <div class="fps-form-group">
                            <label for="fps-link"><?php _e('Link (Optional)', 'facebook-post-scheduler'); ?></label>
                            <input type="url" id="fps-link" name="link" placeholder="https://example.com">
                            <p class="description"><?php _e('Add a link to share with your post', 'facebook-post-scheduler'); ?></p>
                        </div>
                        
                        <!-- Media Upload -->
                        <div class="fps-form-group">
                            <label><?php _e('Media (Optional)', 'facebook-post-scheduler'); ?></label>
                            
                            <div class="fps-media-tabs">
                                <button type="button" class="fps-tab-button active" data-tab="image">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <?php _e('Image', 'facebook-post-scheduler'); ?>
                                </button>
                                <button type="button" class="fps-tab-button" data-tab="video">
                                    <span class="dashicons dashicons-format-video"></span>
                                    <?php _e('Video', 'facebook-post-scheduler'); ?>
                                </button>
                            </div>
                            
                            <div class="fps-tab-content active" id="fps-tab-image">
                                <div class="fps-upload-area" id="fps-image-upload">
                                    <input type="file" id="fps-image-file" name="image" accept="image/*" style="display: none;">
                                    <div class="fps-upload-placeholder">
                                        <span class="dashicons dashicons-cloud-upload"></span>
                                        <p><?php _e('Click to upload an image or drag and drop', 'facebook-post-scheduler'); ?></p>
                                        <p class="description"><?php _e('Supported formats: JPG, PNG, GIF (max 10MB)', 'facebook-post-scheduler'); ?></p>
                                    </div>
                                    <div class="fps-image-preview" style="display: none;">
                                        <img id="fps-image-preview-img" src="" alt="">
                                        <button type="button" class="fps-remove-media" data-type="image">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="fps-tab-content" id="fps-tab-video">
                                <div class="fps-upload-area" id="fps-video-upload">
                                    <input type="file" id="fps-video-file" name="video" accept="video/*" style="display: none;">
                                    <div class="fps-upload-placeholder">
                                        <span class="dashicons dashicons-cloud-upload"></span>
                                        <p><?php _e('Click to upload a video or drag and drop', 'facebook-post-scheduler'); ?></p>
                                        <p class="description"><?php _e('Supported formats: MP4, MOV, AVI (max 100MB)', 'facebook-post-scheduler'); ?></p>
                                    </div>
                                    <div class="fps-video-preview" style="display: none;">
                                        <video id="fps-video-preview-video" controls></video>
                                        <button type="button" class="fps-remove-media" data-type="video">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="fps-form-column">
                <!-- Scheduling Options -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Scheduling', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="fps-form-group">
                            <label for="fps-scheduled-date"><?php _e('Date', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <input type="date" id="fps-scheduled-date" name="scheduled_date" required>
                        </div>
                        
                        <div class="fps-form-group">
                            <label for="fps-scheduled-time"><?php _e('Time', 'facebook-post-scheduler'); ?> <span class="required">*</span></label>
                            <input type="time" id="fps-scheduled-time" name="scheduled_time" required>
                        </div>
                        
                        <div class="fps-timezone-info">
                            <span class="dashicons dashicons-clock"></span>
                            <?php
                            $timezone = get_option('timezone_string', 'UTC');
                            printf(__('Timezone: %s', 'facebook-post-scheduler'), $timezone);
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Post Preview -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Preview', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <div id="fps-post-preview">
                            <div class="fps-preview-placeholder">
                                <span class="dashicons dashicons-facebook"></span>
                                <p><?php _e('Preview will appear here as you type', 'facebook-post-scheduler'); ?></p>
                            </div>
                        </div>
                        
                        <button type="button" id="fps-refresh-preview" class="button">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh Preview', 'facebook-post-scheduler'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Submit Actions -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2><?php _e('Actions', 'facebook-post-scheduler'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="fps-submit-actions">
                            <button type="submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php _e('Schedule Post', 'facebook-post-scheduler'); ?>
                            </button>
                            
                            <button type="button" id="fps-save-draft" class="button">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save as Draft', 'facebook-post-scheduler'); ?>
                            </button>
                        </div>
                        
                        <div class="fps-form-help">
                            <p class="description">
                                <?php _e('Your post will be automatically published to Facebook at the scheduled time.', 'facebook-post-scheduler'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div id="fps-loading-overlay" style="display: none;">
    <div class="fps-loading-content">
        <div class="fps-spinner"></div>
        <p><?php _e('Scheduling your post...', 'facebook-post-scheduler'); ?></p>
    </div>
</div>