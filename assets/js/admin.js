/**
 * Facebook Post Scheduler Admin JavaScript
 * Professional version with enhanced functionality
 */

(function ($) {
  "use strict";

  /**
   * Main Admin Controller
   */
  const FPSAdmin = {
    
    /**
     * Initialize admin functionality
     */
    init: function() {
      this.bindEvents();
      this.initComponents();
      this.setupValidation();
    },
    
    /**
     * Bind event handlers
     */
    bindEvents: function() {
      // Settings tabs
      $('.nav-tab').on('click', this.handleTabClick);
      
      // Schedule post form
      $('#fps-schedule-form').on('submit', this.handleSchedulePost);
      
      // Media upload
      $('.fps-tab-button').on('click', this.handleMediaTabClick);
      $('.fps-upload-area').on('click', this.handleMediaUploadClick);
      $('.fps-remove-media').on('click', this.handleRemoveMedia);
      
      // File inputs
      $('#fps-image-file').on('change', this.handleImageUpload);
      $('#fps-video-file').on('change', this.handleVideoUpload);
      
      // Preview
      $('#fps-refresh-preview').on('click', this.refreshPreview);
      $('#fps-message, #fps-link').on('input', this.updatePreview);
      
      // Character count
      $('#fps-message').on('input', this.updateCharacterCount);
      
      // Connection test
      $('#fps-test-connection').on('click', this.testConnection);
      
      // Disconnect Facebook
      $('#fps-disconnect').on('click', this.disconnectFacebook);
      
      // Refresh pages
      $('#fps-refresh-pages').on('click', this.refreshPages);
      
      // Diagnose pages
      $('#fps-diagnose-pages').on('click', this.diagnosePages);
      
      // Post management
      $('.fps-edit-post').on('click', this.editPost);
      $('.fps-delete-post').on('click', this.deletePost);
      
      // Drag and drop
      this.setupDragAndDrop();
    },
    
    /**
     * Initialize components
     */
    initComponents: function() {
      // Initialize datepicker
      if ($.fn.datepicker) {
        $('#fps-scheduled-date').datepicker({
          dateFormat: 'yy-mm-dd',
          minDate: 0
        });
      }
      
      // Set minimum date for date input
      const today = new Date().toISOString().split('T')[0];
      $('#fps-scheduled-date').attr('min', today);
      
      // Initialize preview
      this.updatePreview();
    },
    
    /**
     * Setup form validation
     */
    setupValidation: function() {
      // Real-time validation
      $('#fps-page-id').on('change', this.validateForm);
      $('#fps-message').on('input', this.validateForm);
      $('#fps-scheduled-date, #fps-scheduled-time').on('change', this.validateForm);
    },
    
    /**
     * Handle tab clicks
     */
    handleTabClick: function(e) {
      e.preventDefault();
      
      const target = $(this).attr('href');
      
      // Update tab states
      $('.nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');
      
      // Update content
      $('.fps-tab-content').removeClass('active');
      $(target).addClass('active');
    },
    
    /**
     * Handle media tab clicks
     */
    handleMediaTabClick: function(e) {
      e.preventDefault();
      
      const tab = $(this).data('tab');
      
      // Update tab states
      $('.fps-tab-button').removeClass('active');
      $(this).addClass('active');
      
      // Update content
      $('.fps-tab-content').removeClass('active');
      $('#fps-tab-' + tab).addClass('active');
    },
    
    /**
     * Handle media upload area clicks
     */
    handleMediaUploadClick: function(e) {
      e.preventDefault();
      
      const uploadArea = $(this);
      
      if (uploadArea.closest('#fps-tab-image').length) {
        $('#fps-image-file').click();
      } else if (uploadArea.closest('#fps-tab-video').length) {
        $('#fps-video-file').click();
      }
    },
    
    /**
     * Handle image upload
     */
    handleImageUpload: function(e) {
      const file = e.target.files[0];
      
      if (!file) return;
      
      // Validate file type
      if (!file.type.startsWith('image/')) {
        alert(fpsAdmin.strings.error + ': Invalid image file');
        return;
      }
      
      // Validate file size (10MB)
      if (file.size > 10 * 1024 * 1024) {
        alert(fpsAdmin.strings.error + ': Image file too large (max 10MB)');
        return;
      }
      
      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
        $('#fps-image-preview-img').attr('src', e.target.result);
        $('#fps-image-upload .fps-upload-placeholder').hide();
        $('#fps-image-upload .fps-image-preview').show();
      };
      reader.readAsDataURL(file);
      
      FPSAdmin.updatePreview();
    },
    
    /**
     * Handle video upload
     */
    handleVideoUpload: function(e) {
      const file = e.target.files[0];
      
      if (!file) return;
      
      // Validate file type
      if (!file.type.startsWith('video/')) {
        alert(fpsAdmin.strings.error + ': Invalid video file');
        return;
      }
      
      // Validate file size (100MB)
      if (file.size > 100 * 1024 * 1024) {
        alert(fpsAdmin.strings.error + ': Video file too large (max 100MB)');
        return;
      }
      
      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
        $('#fps-video-preview-video').attr('src', e.target.result);
        $('#fps-video-upload .fps-upload-placeholder').hide();
        $('#fps-video-upload .fps-video-preview').show();
      };
      reader.readAsDataURL(file);
      
      FPSAdmin.updatePreview();
    },
    
    /**
     * Handle remove media
     */
    handleRemoveMedia: function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const type = $(this).data('type');
      
      if (type === 'image') {
        $('#fps-image-file').val('');
        $('#fps-image-upload .fps-image-preview').hide();
        $('#fps-image-upload .fps-upload-placeholder').show();
      } else if (type === 'video') {
        $('#fps-video-file').val('');
        $('#fps-video-upload .fps-video-preview').hide();
        $('#fps-video-upload .fps-upload-placeholder').show();
      }
      
      FPSAdmin.updatePreview();
    },
    
    /**
     * Update character count
     */
    updateCharacterCount: function() {
      const count = $(this).val().length;
      $('#fps-char-count').text(count);
      
      // Change color based on length
      if (count > 2000) {
        $('#fps-char-count').css('color', '#d63638');
      } else if (count > 1500) {
        $('#fps-char-count').css('color', '#f0b849');
      } else {
        $('#fps-char-count').css('color', '#666');
      }
    },
    
    /**
     * Update post preview
     */
    updatePreview: function() {
      const message = $('#fps-message').val();
      const link = $('#fps-link').val();
      
      if (!message && !link) {
        $('#fps-post-preview').html(`
          <div class="fps-preview-placeholder">
            <span class="dashicons dashicons-facebook"></span>
            <p>${fpsAdmin.strings.previewPlaceholder || 'Preview will appear here as you type'}</p>
          </div>
        `);
        return;
      }
      
      // Get preview via AJAX
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'fps_get_post_preview',
          nonce: fpsAdmin.nonce,
          message: message,
          link: link
        },
        success: function(response) {
          if (response.success) {
            $('#fps-post-preview').html(response.data.preview);
          }
        }
      });
    },
    
    /**
     * Refresh preview manually
     */
    refreshPreview: function(e) {
      e.preventDefault();
      FPSAdmin.updatePreview();
    },
    
    /**
     * Validate form
     */
    validateForm: function() {
      const pageId = $('#fps-page-id').val();
      const message = $('#fps-message').val().trim();
      const date = $('#fps-scheduled-date').val();
      const time = $('#fps-scheduled-time').val();
      
      let isValid = true;
      
      // Check required fields
      if (!pageId) {
        isValid = false;
      }
      
      if (!message) {
        isValid = false;
      }
      
      if (!date || !time) {
        isValid = false;
      }
      
      // Check if date/time is in future
      if (date && time) {
        const scheduledDateTime = new Date(date + 'T' + time);
        if (scheduledDateTime <= new Date()) {
          isValid = false;
        }
      }
      
      // Update submit button
      $('#fps-schedule-form button[type="submit"]').prop('disabled', !isValid);
      
      return isValid;
    },
    
    /**
     * Handle schedule post form submission
     */
    handleSchedulePost: function(e) {
      e.preventDefault();
      
      if (!FPSAdmin.validateForm()) {
        return;
      }
      
      const formData = new FormData(this);
      formData.append('action', 'fps_schedule_post');
      formData.append('nonce', fpsAdmin.nonce);
      
      // Show loading
      $('#fps-loading-overlay').show();
      
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          if (response.success) {
            alert(response.data.message);
            
            if (response.data.redirect) {
              window.location.href = response.data.redirect;
            } else {
              // Reset form
              $('#fps-schedule-form')[0].reset();
              FPSAdmin.updatePreview();
            }
          } else {
            alert(fpsAdmin.strings.error + ': ' + response.data.message);
          }
        },
        error: function() {
          alert(fpsAdmin.strings.error);
        },
        complete: function() {
          $('#fps-loading-overlay').hide();
        }
      });
    },
    
    /**
     * Test Facebook connection
     */
    testConnection: function(e) {
      e.preventDefault();
      
      const button = $(this);
      const originalText = button.html();
      
      button.prop('disabled', true).html('<span class="fps-spinner"></span> ' + fpsAdmin.strings.testing);
      
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'fps_test_connection',
          nonce: fpsAdmin.nonce
        },
        success: function(response) {
          if (response.success) {
            alert(fpsAdmin.strings.success + ': ' + response.data.message);
          } else {
            alert(fpsAdmin.strings.error + ': ' + response.data.message);
          }
        },
        error: function() {
          alert(fpsAdmin.strings.error);
        },
        complete: function() {
          button.prop('disabled', false).html(originalText);
        }
      });
    },
    
    /**
     * Disconnect Facebook account
     */
    disconnectFacebook: function(e) {
      e.preventDefault();
      
      if (!confirm('Are you sure you want to disconnect your Facebook account?')) {
        return;
      }
      
      const button = $(this);
      const originalText = button.html();
      
      button.prop('disabled', true).html('<span class="fps-spinner"></span> Disconnecting...');
      
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'fps_disconnect_facebook',
          nonce: fpsAdmin.nonce
        },
        success: function(response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(fpsAdmin.strings.error + ': ' + response.data.message);
          }
        },
        error: function() {
          alert(fpsAdmin.strings.error);
        },
        complete: function() {
          button.prop('disabled', false).html(originalText);
        }
      });
    },
    
    /**
     * Refresh Facebook pages
     */
    refreshPages: function(e) {
      e.preventDefault();
      
      const button = $(this);
      const originalText = button.html();
      
      button.prop('disabled', true).html('<span class="fps-spinner"></span> Refreshing...');
      
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'fps_refresh_pages',
          nonce: fpsAdmin.nonce
        },
        success: function(response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(fpsAdmin.strings.error + ': ' + response.data.message);
          }
        },
        error: function() {
          alert(fpsAdmin.strings.error);
        },
        complete: function() {
          button.prop('disabled', false).html(originalText);
        }
      });
    },
    
    /**
     * Diagnose pages issues
     */
    diagnosePages: function(e) {
      e.preventDefault();
      
      const button = $(this);
      const originalText = button.html();
      
      button.prop('disabled', true).html('<span class="fps-spinner"></span> Diagnosing...');
      
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'fps_diagnose_pages',
          nonce: fpsAdmin.nonce
        },
        success: function(response) {
          if (response.success) {
            let message = 'Diagnostic Results:\n\n';
            
            if (response.data.user_info) {
              message += 'User: ' + response.data.user_info.name + ' (ID: ' + response.data.user_info.id + ')\n';
            }
            
            if (response.data.permissions) {
              message += 'Permissions: ';
              const grantedPerms = response.data.permissions.filter(p => p.status === 'granted').map(p => p.permission);
              message += grantedPerms.join(', ') + '\n';
            }
            
            message += 'Raw pages found: ' + response.data.raw_pages_count + '\n\n';
            
            if (response.data.raw_pages && response.data.raw_pages.data) {
              message += 'Pages details:\n';
              response.data.raw_pages.data.forEach(function(page, index) {
                message += (index + 1) + '. ' + page.name + ' (ID: ' + page.id + ')\n';
                if (page.tasks) {
                  message += '   Tasks: ' + page.tasks.join(', ') + '\n';
                }
                if (page.access_token) {
                  message += '   Has access token: Yes\n';
                } else {
                  message += '   Has access token: No\n';
                }
              });
            }
            
            alert(message);
          } else {
            alert(fpsAdmin.strings.error + ': ' + response.data.message);
          }
        },
        error: function() {
          alert(fpsAdmin.strings.error);
        },
        complete: function() {
          button.prop('disabled', false).html(originalText);
        }
      });
    },
    
    /**
     * Edit post
     */
    editPost: function(e) {
      e.preventDefault();
      
      const postId = $(this).data('post-id');
      // Implementation would open edit modal
      console.log('Edit post:', postId);
    },
    
    /**
     * Delete post
     */
    deletePost: function(e) {
      e.preventDefault();
      
      if (!confirm(fpsAdmin.strings.confirmDelete)) {
        return;
      }
      
      const postId = $(this).data('post-id');
      const button = $(this);
      
      button.prop('disabled', true);
      
      $.ajax({
        url: fpsAdmin.ajaxUrl,
        type: 'POST',
        data: {
          action: 'fps_delete_post',
          nonce: fpsAdmin.nonce,
          post_id: postId
        },
        success: function(response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(fpsAdmin.strings.error + ': ' + response.data.message);
          }
        },
        error: function() {
          alert(fpsAdmin.strings.error);
        },
        complete: function() {
          button.prop('disabled', false);
        }
      });
    },
    
    /**
     * Setup drag and drop functionality
     */
    setupDragAndDrop: function() {
      $('.fps-upload-area').each(function() {
        const uploadArea = this;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
          uploadArea.addEventListener(eventName, FPSAdmin.preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
          uploadArea.addEventListener(eventName, function() {
            $(uploadArea).addClass('dragover');
          }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
          uploadArea.addEventListener(eventName, function() {
            $(uploadArea).removeClass('dragover');
          }, false);
        });
        
        uploadArea.addEventListener('drop', function(e) {
          const files = e.dataTransfer.files;
          
          if ($(uploadArea).closest('#fps-tab-image').length) {
            FPSAdmin.handleDroppedFiles(files, 'image');
          } else if ($(uploadArea).closest('#fps-tab-video').length) {
            FPSAdmin.handleDroppedFiles(files, 'video');
          }
        }, false);
      });
    },
    
    /**
     * Prevent default drag behaviors
     */
    preventDefaults: function(e) {
      e.preventDefault();
      e.stopPropagation();
    },
    
    /**
     * Handle dropped files
     */
    handleDroppedFiles: function(files, type) {
      if (files.length === 0) return;
      
      const file = files[0];
      
      if (type === 'image') {
        if (file.type.startsWith('image/')) {
          // Trigger the file input change
          const input = document.getElementById('fps-image-file');
          const dt = new DataTransfer();
          dt.items.add(file);
          input.files = dt.files;
          $(input).trigger('change');
        }
      } else if (type === 'video') {
        if (file.type.startsWith('video/')) {
          // Trigger the file input change
          const input = document.getElementById('fps-video-file');
          const dt = new DataTransfer();
          dt.items.add(file);
          input.files = dt.files;
          $(input).trigger('change');
        }
      }
    }
  };
  
  // Initialize when document is ready
  $(document).ready(function () {
    // Initialize admin functionality
    FPSAdmin.init();
  });
  
  // Make FPSAdmin globally accessible
  window.FPSAdmin = FPSAdmin;
  
})(jQuery);
