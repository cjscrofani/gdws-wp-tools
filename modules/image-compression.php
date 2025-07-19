<?php
/**
 * GDWS Tools - Image Compression Module
 * 
 * @package GDWS_Tools
 * @subpackage Modules
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Compression Module for GDWS Tools
 */
class GDWS_Tools_Image_Compression {
    
    /**
     * Option name for compression settings
     */
    private $option_name = 'gdws_image_compression_settings';
    
    /**
     * Backup directory name
     */
    private $backup_dir = 'gdws-image-backups';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->create_backup_directory();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin submenu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_gdws_compress_images', array($this, 'ajax_compress_images'));
        add_action('wp_ajax_gdws_get_compression_stats', array($this, 'ajax_get_compression_stats'));
        add_action('wp_ajax_gdws_restore_image', array($this, 'ajax_restore_image'));
        add_action('wp_ajax_gdws_get_image_list', array($this, 'ajax_get_image_list'));
        
        // Auto-compress new uploads (optional)
        add_filter('wp_handle_upload', array($this, 'auto_compress_upload'), 10, 2);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'gdws-tools',
            __('Image Compression', 'gdws-tools'),
            __('Image Compression', 'gdws-tools'),
            'manage_options',
            'gdws-image-compression',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'gdws-tools_page_gdws-image-compression') {
            return;
        }
        
        wp_enqueue_script('gdws-image-compression-admin', GDWS_TOOLS_PLUGIN_URL . 'assets/js/image-compression-admin.js', array('jquery'), GDWS_TOOLS_VERSION, true);
        wp_localize_script('gdws-image-compression-admin', 'gdws_compression', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdws_compression_nonce'),
            'strings' => array(
                'processing' => __('Processing...', 'gdws-tools'),
                'completed' => __('Compression completed!', 'gdws-tools'),
                'error' => __('An error occurred', 'gdws-tools'),
                'confirm_restore' => __('Are you sure you want to restore this image? This will replace the compressed version.', 'gdws-tools'),
            )
        ));
        
        wp_enqueue_style('gdws-image-compression-admin', GDWS_TOOLS_PLUGIN_URL . 'assets/css/image-compression-admin.css', array(), GDWS_TOOLS_VERSION);
    }
    
    /**
     * Create backup directory
     */
    private function create_backup_directory() {
        $upload_dir = wp_upload_dir();
        $backup_path = $upload_dir['basedir'] . '/' . $this->backup_dir;
        
        if (!file_exists($backup_path)) {
            wp_mkdir_p($backup_path);
            
            // Add .htaccess to protect backups
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($backup_path . '/.htaccess', $htaccess_content);
            
            // Add index.php for security
            file_put_contents($backup_path . '/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Get compression settings
     */
    private function get_settings() {
        return wp_parse_args(get_option($this->option_name, array()), array(
            'jpeg_quality' => 85,
            'png_compression' => 6,
            'webp_quality' => 80,
            'auto_compress' => false,
            'max_width' => 0,
            'max_height' => 0,
            'backup_originals' => true,
        ));
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        $settings = $this->get_settings();
        
        // Handle settings update
        if (isset($_POST['update_settings']) && wp_verify_nonce($_POST['gdws_compression_nonce'], 'gdws_compression_settings')) {
            $new_settings = array(
                'jpeg_quality' => intval($_POST['jpeg_quality']),
                'png_compression' => intval($_POST['png_compression']),
                'webp_quality' => intval($_POST['webp_quality']),
                'auto_compress' => isset($_POST['auto_compress']),
                'max_width' => intval($_POST['max_width']),
                'max_height' => intval($_POST['max_height']),
                'backup_originals' => isset($_POST['backup_originals']),
            );
            
            update_option($this->option_name, $new_settings);
            $settings = $new_settings;
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'gdws-tools') . '</p></div>';
        }
        
        $stats = $this->get_compression_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('Image Compression', 'gdws-tools'); ?></h1>
            
            <!-- Statistics Dashboard -->
            <div class="gdws-compression-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php _e('Total Images', 'gdws-tools'); ?></h3>
                        <div class="stat-number"><?php echo number_format($stats['total_images']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Compressed Images', 'gdws-tools'); ?></h3>
                        <div class="stat-number"><?php echo number_format($stats['compressed_images']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Space Saved', 'gdws-tools'); ?></h3>
                        <div class="stat-number"><?php echo $this->format_bytes($stats['space_saved']); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Compression Ratio', 'gdws-tools'); ?></h3>
                        <div class="stat-number"><?php echo $stats['compression_ratio']; ?>%</div>
                    </div>
                </div>
            </div>
            
            <!-- Compression Tools -->
            <div class="gdws-compression-tools">
                <div class="card">
                    <h2><?php _e('Bulk Compression', 'gdws-tools'); ?></h2>
                    <p><?php _e('Compress all uncompressed images in your media library.', 'gdws-tools'); ?></p>
                    
                    <div class="compression-controls">
                        <button id="start-compression" class="button button-primary">
                            <?php _e('Start Bulk Compression', 'gdws-tools'); ?>
                        </button>
                        <button id="stop-compression" class="button" style="display: none;">
                            <?php _e('Stop Compression', 'gdws-tools'); ?>
                        </button>
                        <button id="refresh-stats" class="button">
                            <?php _e('Refresh Statistics', 'gdws-tools'); ?>
                        </button>
                    </div>
                    
                    <div id="compression-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-info">
                            <span id="progress-text"><?php _e('Preparing...', 'gdws-tools'); ?></span>
                            <span id="progress-percentage">0%</span>
                        </div>
                    </div>
                    
                    <div id="compression-results" style="display: none;">
                        <h3><?php _e('Compression Results', 'gdws-tools'); ?></h3>
                        <div id="results-content"></div>
                    </div>
                </div>
                
                <!-- Image List -->
                <div class="card">
                    <h2><?php _e('Media Library Images', 'gdws-tools'); ?></h2>
                    <div class="image-filters">
                        <label>
                            <input type="radio" name="image_filter" value="all" checked> <?php _e('All Images', 'gdws-tools'); ?>
                        </label>
                        <label>
                            <input type="radio" name="image_filter" value="compressed"> <?php _e('Compressed', 'gdws-tools'); ?>
                        </label>
                        <label>
                            <input type="radio" name="image_filter" value="uncompressed"> <?php _e('Uncompressed', 'gdws-tools'); ?>
                        </label>
                    </div>
                    
                    <div id="image-list-container">
                        <div id="image-list"></div>
                        <div id="image-list-pagination"></div>
                    </div>
                </div>
            </div>
            
            <!-- Settings -->
            <div class="card">
                <h2><?php _e('Compression Settings', 'gdws-tools'); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('gdws_compression_settings', 'gdws_compression_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="jpeg_quality"><?php _e('JPEG Quality', 'gdws-tools'); ?></label></th>
                            <td>
                                <input type="range" id="jpeg_quality" name="jpeg_quality" min="60" max="100" value="<?php echo $settings['jpeg_quality']; ?>" class="quality-slider">
                                <span class="quality-value"><?php echo $settings['jpeg_quality']; ?>%</span>
                                <p class="description"><?php _e('Lower values = smaller files, higher values = better quality. Recommended: 85%', 'gdws-tools'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="png_compression"><?php _e('PNG Compression Level', 'gdws-tools'); ?></label></th>
                            <td>
                                <input type="range" id="png_compression" name="png_compression" min="0" max="9" value="<?php echo $settings['png_compression']; ?>" class="quality-slider">
                                <span class="quality-value"><?php echo $settings['png_compression']; ?></span>
                                <p class="description"><?php _e('0 = no compression, 9 = maximum compression. Recommended: 6', 'gdws-tools'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="webp_quality"><?php _e('WebP Quality', 'gdws-tools'); ?></label></th>
                            <td>
                                <input type="range" id="webp_quality" name="webp_quality" min="60" max="100" value="<?php echo $settings['webp_quality']; ?>" class="quality-slider">
                                <span class="quality-value"><?php echo $settings['webp_quality']; ?>%</span>
                                <p class="description"><?php _e('Quality for WebP conversion. Recommended: 80%', 'gdws-tools'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Maximum Dimensions', 'gdws-tools'); ?></th>
                            <td>
                                <label>
                                    <?php _e('Width:', 'gdws-tools'); ?>
                                    <input type="number" name="max_width" value="<?php echo $settings['max_width']; ?>" min="0" class="small-text">
                                    <?php _e('px', 'gdws-tools'); ?>
                                </label>
                                <label>
                                    <?php _e('Height:', 'gdws-tools'); ?>
                                    <input type="number" name="max_height" value="<?php echo $settings['max_height']; ?>" min="0" class="small-text">
                                    <?php _e('px', 'gdws-tools'); ?>
                                </label>
                                <p class="description"><?php _e('Maximum width/height for images (0 = no limit). Images larger than this will be resized.', 'gdws-tools'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Options', 'gdws-tools'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="auto_compress" value="1" <?php checked($settings['auto_compress']); ?>>
                                        <?php _e('Automatically compress new uploads', 'gdws-tools'); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="backup_originals" value="1" <?php checked($settings['backup_originals']); ?>>
                                        <?php _e('Keep backup copies of original images', 'gdws-tools'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="update_settings" class="button button-primary">
                            <?php _e('Save Settings', 'gdws-tools'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for bulk compression
     */
    public function ajax_compress_images() {
        check_ajax_referer('gdws_compression_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'gdws-tools'));
        }
        
        $batch_size = 5; // Process 5 images at a time
        $offset = intval($_POST['offset']);
        
        // Get uncompressed images
        $images = $this->get_uncompressed_images($batch_size, $offset);
        
        if (empty($images)) {
            wp_send_json_success(array(
                'completed' => true,
                'message' => __('All images have been processed!', 'gdws-tools')
            ));
        }
        
        $results = array();
        $settings = $this->get_settings();
        
        foreach ($images as $attachment_id) {
            $result = $this->compress_image($attachment_id, $settings);
            $results[] = $result;
        }
        
        wp_send_json_success(array(
            'completed' => false,
            'processed' => count($results),
            'results' => $results,
            'next_offset' => $offset + $batch_size
        ));
    }
    
    /**
     * AJAX handler for getting compression statistics
     */
    public function ajax_get_compression_stats() {
        check_ajax_referer('gdws_compression_nonce', 'nonce');
        
        $stats = $this->get_compression_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX handler for getting image list
     */
    public function ajax_get_image_list() {
        check_ajax_referer('gdws_compression_nonce', 'nonce');
        
        $filter = sanitize_text_field($_POST['filter']);
        $page = intval($_POST['page']);
        $per_page = 20;
        
        $images = $this->get_image_list($filter, $page, $per_page);
        wp_send_json_success($images);
    }
    
    /**
     * AJAX handler for restoring image
     */
    public function ajax_restore_image() {
        check_ajax_referer('gdws_compression_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'gdws-tools'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $result = $this->restore_image($attachment_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Image restored successfully!', 'gdws-tools')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to restore image.', 'gdws-tools')
            ));
        }
    }
    
    /**
     * Compress a single image
     */
    private function compress_image($attachment_id, $settings) {
        $file_path = get_attached_file($attachment_id);
        
        if (!file_exists($file_path)) {
            return array(
                'id' => $attachment_id,
                'success' => false,
                'message' => __('File not found', 'gdws-tools')
            );
        }
        
        $original_size = filesize($file_path);
        $image_info = getimagesize($file_path);
        
        if (!$image_info) {
            return array(
                'id' => $attachment_id,
                'success' => false,
                'message' => __('Invalid image file', 'gdws-tools')
            );
        }
        
        // Create backup if enabled
        if ($settings['backup_originals']) {
            $this->create_backup($attachment_id, $file_path);
        }
        
        $compressed = false;
        $mime_type = $image_info['mime'];
        
        switch ($mime_type) {
            case 'image/jpeg':
                $compressed = $this->compress_jpeg($file_path, $settings['jpeg_quality']);
                break;
            case 'image/png':
                $compressed = $this->compress_png($file_path, $settings['png_compression']);
                break;
            case 'image/webp':
                $compressed = $this->compress_webp($file_path, $settings['webp_quality']);
                break;
        }
        
        if ($compressed) {
            $new_size = filesize($file_path);
            $saved = $original_size - $new_size;
            $percentage = round(($saved / $original_size) * 100, 1);
            
            // Update metadata
            update_post_meta($attachment_id, '_gdws_compressed', true);
            update_post_meta($attachment_id, '_gdws_original_size', $original_size);
            update_post_meta($attachment_id, '_gdws_compressed_size', $new_size);
            update_post_meta($attachment_id, '_gdws_space_saved', $saved);
            
            return array(
                'id' => $attachment_id,
                'success' => true,
                'original_size' => $original_size,
                'new_size' => $new_size,
                'saved' => $saved,
                'percentage' => $percentage,
                'message' => sprintf(__('Saved %s (%s%%)', 'gdws-tools'), $this->format_bytes($saved), $percentage)
            );
        }
        
        return array(
            'id' => $attachment_id,
            'success' => false,
            'message' => __('Compression failed', 'gdws-tools')
        );
    }
    
    /**
     * Compress JPEG image
     */
    private function compress_jpeg($file_path, $quality) {
        $image = imagecreatefromjpeg($file_path);
        if (!$image) return false;
        
        $result = imagejpeg($image, $file_path, $quality);
        imagedestroy($image);
        
        return $result;
    }
    
    /**
     * Compress PNG image
     */
    private function compress_png($file_path, $compression) {
        $image = imagecreatefrompng($file_path);
        if (!$image) return false;
        
        imagesavealpha($image, true);
        imagealphablending($image, false);
        
        $result = imagepng($image, $file_path, $compression);
        imagedestroy($image);
        
        return $result;
    }
    
    /**
     * Compress WebP image
     */
    private function compress_webp($file_path, $quality) {
        if (!function_exists('imagecreatefromwebp')) {
            return false;
        }
        
        $image = imagecreatefromwebp($file_path);
        if (!$image) return false;
        
        $result = imagewebp($image, $file_path, $quality);
        imagedestroy($image);
        
        return $result;
    }
    
    /**
     * Create backup of original image
     */
    private function create_backup($attachment_id, $file_path) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/' . $this->backup_dir;
        
        $filename = basename($file_path);
        $backup_path = $backup_dir . '/' . $attachment_id . '_' . $filename;
        
        if (!file_exists($backup_path)) {
            copy($file_path, $backup_path);
            update_post_meta($attachment_id, '_gdws_backup_path', $backup_path);
        }
    }
    
    /**
     * Restore image from backup
     */
    private function restore_image($attachment_id) {
        $backup_path = get_post_meta($attachment_id, '_gdws_backup_path', true);
        
        if (!$backup_path || !file_exists($backup_path)) {
            return false;
        }
        
        $file_path = get_attached_file($attachment_id);
        
        if (copy($backup_path, $file_path)) {
            // Remove compression metadata
            delete_post_meta($attachment_id, '_gdws_compressed');
            delete_post_meta($attachment_id, '_gdws_original_size');
            delete_post_meta($attachment_id, '_gdws_compressed_size');
            delete_post_meta($attachment_id, '_gdws_space_saved');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get uncompressed images
     */
    private function get_uncompressed_images($limit = -1, $offset = 0) {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/webp'),
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'meta_query' => array(
                array(
                    'key' => '_gdws_compressed',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'fields' => 'ids'
        );
        
        return get_posts($args);
    }
    
    /**
     * Get compression statistics
     */
    private function get_compression_stats() {
        global $wpdb;
        
        // Total images
        $total_images = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ('image/jpeg', 'image/png', 'image/webp')
            AND post_status = 'inherit'
        ");
        
        // Compressed images
        $compressed_images = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/webp')
            AND p.post_status = 'inherit'
            AND pm.meta_key = '_gdws_compressed'
            AND pm.meta_value = '1'
        ");
        
        // Total space saved
        $space_saved = $wpdb->get_var("
            SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_gdws_space_saved'
        ");
        
        $compression_ratio = $total_images > 0 ? round(($compressed_images / $total_images) * 100, 1) : 0;
        
        return array(
            'total_images' => intval($total_images),
            'compressed_images' => intval($compressed_images),
            'space_saved' => intval($space_saved),
            'compression_ratio' => $compression_ratio
        );
    }
    
    /**
     * Get image list with pagination
     */
    private function get_image_list($filter, $page, $per_page) {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png', 'image/webp'),
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
        );
        
        if ($filter === 'compressed') {
            $args['meta_query'] = array(
                array(
                    'key' => '_gdws_compressed',
                    'value' => '1'
                )
            );
        } elseif ($filter === 'uncompressed') {
            $args['meta_query'] = array(
                array(
                    'key' => '_gdws_compressed',
                    'compare' => 'NOT EXISTS'
                )
            );
        }
        
        $query = new WP_Query($args);
        $images = array();
        
        foreach ($query->posts as $post) {
            $compressed = get_post_meta($post->ID, '_gdws_compressed', true);
            $original_size = get_post_meta($post->ID, '_gdws_original_size', true);
            $compressed_size = get_post_meta($post->ID, '_gdws_compressed_size', true);
            $space_saved = get_post_meta($post->ID, '_gdws_space_saved', true);
            
            $images[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'url' => wp_get_attachment_url($post->ID),
                'thumbnail' => wp_get_attachment_image_url($post->ID, 'thumbnail'),
                'compressed' => (bool) $compressed,
                'original_size' => $original_size ? $this->format_bytes($original_size) : '',
                'compressed_size' => $compressed_size ? $this->format_bytes($compressed_size) : '',
                'space_saved' => $space_saved ? $this->format_bytes($space_saved) : '',
                'has_backup' => (bool) get_post_meta($post->ID, '_gdws_backup_path', true)
            );
        }
        
        return array(
            'images' => $images,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page,
            'total_items' => $query->found_posts
        );
    }
    
    /**
     * Auto-compress uploads
     */
    public function auto_compress_upload($upload, $context) {
        $settings = $this->get_settings();
        
        if (!$settings['auto_compress']) {
            return $upload;
        }
        
        $file_path = $upload['file'];
        $file_type = $upload['type'];
        
        if (strpos($file_type, 'image/') === 0) {
            // Get attachment ID after upload
            add_action('add_attachment', function($attachment_id) use ($settings) {
                $this->compress_image($attachment_id, $settings);
            });
        }
        
        return $upload;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB');
        $factor = floor(log($bytes, 1024));
        
        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}