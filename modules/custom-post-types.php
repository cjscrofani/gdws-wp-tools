<?php
/**
 * GDWS Tools - Dynamic Custom Post Types Module
 * 
 * @package GDWS_Tools
 * @subpackage Modules
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dynamic Custom Post Types Module for GDWS Tools
 */
class GDWS_Tools_Custom_Post_Types {
    
    /**
     * Option name for storing CPT settings
     */
    private $option_name = 'gdws_custom_post_types';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register custom post types on init
        add_action('init', array($this, 'register_saved_post_types'));
        
        // Add admin submenu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle AJAX requests
        add_action('wp_ajax_gdws_save_cpt', array($this, 'ajax_save_cpt'));
        add_action('wp_ajax_gdws_delete_cpt', array($this, 'ajax_delete_cpt'));
        add_action('wp_ajax_gdws_get_cpt', array($this, 'ajax_get_cpt'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Register all saved custom post types
     */
    public function register_saved_post_types() {
        $post_types = get_option($this->option_name, array());
        
        foreach ($post_types as $post_type_key => $config) {
            if (isset($config['active']) && $config['active']) {
                $this->register_post_type($post_type_key, $config);
            }
        }
    }
    
    /**
     * Register a single post type
     */
    private function register_post_type($post_type_key, $config) {
        $labels = array(
            'name'                  => $config['plural_name'],
            'singular_name'         => $config['singular_name'],
            'menu_name'             => $config['plural_name'],
            'name_admin_bar'        => $config['singular_name'],
            'add_new'               => __('Add New', 'gdws-tools'),
            'add_new_item'          => sprintf(__('Add New %s', 'gdws-tools'), $config['singular_name']),
            'new_item'              => sprintf(__('New %s', 'gdws-tools'), $config['singular_name']),
            'edit_item'             => sprintf(__('Edit %s', 'gdws-tools'), $config['singular_name']),
            'view_item'             => sprintf(__('View %s', 'gdws-tools'), $config['singular_name']),
            'all_items'             => sprintf(__('All %s', 'gdws-tools'), $config['plural_name']),
            'search_items'          => sprintf(__('Search %s', 'gdws-tools'), $config['plural_name']),
            'not_found'             => sprintf(__('No %s found.', 'gdws-tools'), strtolower($config['plural_name'])),
            'not_found_in_trash'    => sprintf(__('No %s found in Trash.', 'gdws-tools'), strtolower($config['plural_name'])),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => isset($config['public']) ? $config['public'] : true,
            'publicly_queryable' => isset($config['publicly_queryable']) ? $config['publicly_queryable'] : true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => $config['slug']),
            'capability_type'    => 'post',
            'has_archive'        => isset($config['has_archive']) ? $config['has_archive'] : true,
            'hierarchical'       => isset($config['hierarchical']) ? $config['hierarchical'] : false,
            'menu_position'      => isset($config['menu_position']) ? intval($config['menu_position']) : null,
            'menu_icon'          => isset($config['menu_icon']) ? $config['menu_icon'] : 'dashicons-admin-post',
            'supports'           => isset($config['supports']) ? $config['supports'] : array('title', 'editor'),
            'show_in_rest'       => isset($config['show_in_rest']) ? $config['show_in_rest'] : true,
        );
        
        register_post_type($post_type_key, $args);
        
        // Register associated taxonomies if any
        if (!empty($config['taxonomies'])) {
            foreach ($config['taxonomies'] as $tax_key => $tax_config) {
                $this->register_taxonomy($tax_key, $post_type_key, $tax_config);
            }
        }
    }
    
    /**
     * Register taxonomy for a post type
     */
    private function register_taxonomy($taxonomy_key, $post_type_key, $tax_config) {
        $labels = array(
            'name'              => $tax_config['plural_name'],
            'singular_name'     => $tax_config['singular_name'],
            'search_items'      => sprintf(__('Search %s', 'gdws-tools'), $tax_config['plural_name']),
            'all_items'         => sprintf(__('All %s', 'gdws-tools'), $tax_config['plural_name']),
            'edit_item'         => sprintf(__('Edit %s', 'gdws-tools'), $tax_config['singular_name']),
            'update_item'       => sprintf(__('Update %s', 'gdws-tools'), $tax_config['singular_name']),
            'add_new_item'      => sprintf(__('Add New %s', 'gdws-tools'), $tax_config['singular_name']),
            'new_item_name'     => sprintf(__('New %s Name', 'gdws-tools'), $tax_config['singular_name']),
            'menu_name'         => $tax_config['plural_name'],
        );
        
        $args = array(
            'hierarchical'      => isset($tax_config['hierarchical']) ? $tax_config['hierarchical'] : true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => $tax_config['slug']),
            'show_in_rest'      => true,
        );
        
        register_taxonomy($taxonomy_key, $post_type_key, $args);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'gdws-tools',
            __('Custom Post Types', 'gdws-tools'),
            __('Custom Post Types', 'gdws-tools'),
            'manage_options',
            'gdws-custom-post-types',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'gdws-tools_page_gdws-custom-post-types') {
            return;
        }
        
        wp_enqueue_script('gdws-cpt-admin', GDWS_TOOLS_PLUGIN_URL . 'assets/js/cpt-admin.js', array('jquery'), GDWS_TOOLS_VERSION, true);
        wp_localize_script('gdws-cpt-admin', 'gdws_cpt', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdws_cpt_nonce')
        ));
        
        wp_enqueue_style('gdws-cpt-admin', GDWS_TOOLS_PLUGIN_URL . 'assets/css/cpt-admin.css', array(), GDWS_TOOLS_VERSION);
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        $post_types = get_option($this->option_name, array());
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Post Types', 'gdws-tools'); ?></h1>
            
            <div class="gdws-cpt-container">
                <div class="gdws-cpt-list">
                    <h2><?php _e('Registered Post Types', 'gdws-tools'); ?></h2>
                    
                    <?php if (empty($post_types)) : ?>
                        <p class="description"><?php _e('No custom post types registered yet.', 'gdws-tools'); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'gdws-tools'); ?></th>
                                    <th><?php _e('Slug', 'gdws-tools'); ?></th>
                                    <th><?php _e('Status', 'gdws-tools'); ?></th>
                                    <th><?php _e('Actions', 'gdws-tools'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($post_types as $key => $cpt) : ?>
                                    <tr>
                                        <td>
                                            <span class="dashicons <?php echo esc_attr($cpt['menu_icon']); ?>"></span>
                                            <strong><?php echo esc_html($cpt['plural_name']); ?></strong>
                                        </td>
                                        <td><code><?php echo esc_html($cpt['slug']); ?></code></td>
                                        <td>
                                            <?php if ($cpt['active']) : ?>
                                                <span class="status-active"><?php _e('Active', 'gdws-tools'); ?></span>
                                            <?php else : ?>
                                                <span class="status-inactive"><?php _e('Inactive', 'gdws-tools'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="button button-small edit-cpt" data-key="<?php echo esc_attr($key); ?>">
                                                <?php _e('Edit', 'gdws-tools'); ?>
                                            </button>
                                            <button class="button button-small button-link-delete delete-cpt" data-key="<?php echo esc_attr($key); ?>">
                                                <?php _e('Delete', 'gdws-tools'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <p class="submit">
                        <button class="button button-primary" id="add-new-cpt"><?php _e('Add New Post Type', 'gdws-tools'); ?></button>
                    </p>
                </div>
                
                <div class="gdws-cpt-form" style="display: none;">
                    <h2 id="form-title"><?php _e('Add New Post Type', 'gdws-tools'); ?></h2>
                    
                    <form id="cpt-form">
                        <input type="hidden" id="cpt-key" name="cpt_key" value="">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="singular-name"><?php _e('Singular Name', 'gdws-tools'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <input type="text" id="singular-name" name="singular_name" class="regular-text" required>
                                    <p class="description"><?php _e('e.g. Product, Event, Team Member', 'gdws-tools'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="plural-name"><?php _e('Plural Name', 'gdws-tools'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <input type="text" id="plural-name" name="plural_name" class="regular-text" required>
                                    <p class="description"><?php _e('e.g. Products, Events, Team Members', 'gdws-tools'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="slug"><?php _e('Slug', 'gdws-tools'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <input type="text" id="slug" name="slug" class="regular-text" required pattern="[a-z0-9-]+">
                                    <p class="description"><?php _e('URL-friendly version (lowercase, no spaces)', 'gdws-tools'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="menu-icon"><?php _e('Menu Icon', 'gdws-tools'); ?></label></th>
                                <td>
                                    <select id="menu-icon" name="menu_icon" class="regular-text">
                                        <option value="dashicons-admin-post"><?php _e('Default', 'gdws-tools'); ?></option>
                                        <option value="dashicons-portfolio"><?php _e('Portfolio', 'gdws-tools'); ?></option>
                                        <option value="dashicons-calendar"><?php _e('Calendar', 'gdws-tools'); ?></option>
                                        <option value="dashicons-calendar-alt"><?php _e('Calendar Alt', 'gdws-tools'); ?></option>
                                        <option value="dashicons-products"><?php _e('Products', 'gdws-tools'); ?></option>
                                        <option value="dashicons-groups"><?php _e('Groups', 'gdws-tools'); ?></option>
                                        <option value="dashicons-format-quote"><?php _e('Quote', 'gdws-tools'); ?></option>
                                        <option value="dashicons-format-image"><?php _e('Image', 'gdws-tools'); ?></option>
                                        <option value="dashicons-format-gallery"><?php _e('Gallery', 'gdws-tools'); ?></option>
                                        <option value="dashicons-format-video"><?php _e('Video', 'gdws-tools'); ?></option>
                                        <option value="dashicons-location"><?php _e('Location', 'gdws-tools'); ?></option>
                                        <option value="dashicons-location-alt"><?php _e('Location Alt', 'gdws-tools'); ?></option>
                                        <option value="dashicons-megaphone"><?php _e('Megaphone', 'gdws-tools'); ?></option>
                                        <option value="dashicons-book"><?php _e('Book', 'gdws-tools'); ?></option>
                                        <option value="dashicons-book-alt"><?php _e('Book Alt', 'gdws-tools'); ?></option>
                                        <option value="dashicons-clipboard"><?php _e('Clipboard', 'gdws-tools'); ?></option>
                                        <option value="dashicons-businessman"><?php _e('Businessman', 'gdws-tools'); ?></option>
                                        <option value="dashicons-id"><?php _e('ID', 'gdws-tools'); ?></option>
                                        <option value="dashicons-id-alt"><?php _e('ID Alt', 'gdws-tools'); ?></option>
                                        <option value="dashicons-store"><?php _e('Store', 'gdws-tools'); ?></option>
                                        <option value="dashicons-album"><?php _e('Album', 'gdws-tools'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Features', 'gdws-tools'); ?></th>
                                <td>
                                    <fieldset>
                                        <label><input type="checkbox" name="supports[]" value="title" checked> <?php _e('Title', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="editor" checked> <?php _e('Editor', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="thumbnail"> <?php _e('Featured Image', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="excerpt"> <?php _e('Excerpt', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="author"> <?php _e('Author', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="comments"> <?php _e('Comments', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="revisions"> <?php _e('Revisions', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="page-attributes"> <?php _e('Page Attributes', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="supports[]" value="custom-fields"> <?php _e('Custom Fields', 'gdws-tools'); ?></label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Settings', 'gdws-tools'); ?></th>
                                <td>
                                    <fieldset>
                                        <label><input type="checkbox" name="public" value="1" checked> <?php _e('Public', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="has_archive" value="1" checked> <?php _e('Has Archive', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="show_in_rest" value="1" checked> <?php _e('Show in REST (Block Editor)', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="hierarchical" value="1"> <?php _e('Hierarchical (like Pages)', 'gdws-tools'); ?></label><br>
                                        <label><input type="checkbox" name="active" value="1" checked> <?php _e('Active', 'gdws-tools'); ?></label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Save Post Type', 'gdws-tools'); ?></button>
                            <button type="button" class="button" id="cancel-form"><?php _e('Cancel', 'gdws-tools'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to save CPT
     */
    public function ajax_save_cpt() {
        check_ajax_referer('gdws_cpt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'gdws-tools'));
        }
        
        $post_types = get_option($this->option_name, array());
        
        $cpt_key = isset($_POST['cpt_key']) ? sanitize_key($_POST['cpt_key']) : '';
        $singular_name = sanitize_text_field($_POST['singular_name']);
        $plural_name = sanitize_text_field($_POST['plural_name']);
        $slug = sanitize_title($_POST['slug']);
        
        // Generate key from singular name if not editing
        if (empty($cpt_key)) {
            $cpt_key = sanitize_key(str_replace(' ', '_', strtolower($singular_name)));
        }
        
        $post_types[$cpt_key] = array(
            'singular_name' => $singular_name,
            'plural_name' => $plural_name,
            'slug' => $slug,
            'menu_icon' => sanitize_text_field($_POST['menu_icon']),
            'supports' => isset($_POST['supports']) ? array_map('sanitize_text_field', $_POST['supports']) : array(),
            'public' => isset($_POST['public']),
            'has_archive' => isset($_POST['has_archive']),
            'show_in_rest' => isset($_POST['show_in_rest']),
            'hierarchical' => isset($_POST['hierarchical']),
            'active' => isset($_POST['active']),
            'taxonomies' => array() // Can be extended later
        );
        
        update_option($this->option_name, $post_types);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        wp_send_json_success(array(
            'message' => __('Post type saved successfully. Refreshing...', 'gdws-tools')
        ));
    }
    
    /**
     * AJAX handler to delete CPT
     */
    public function ajax_delete_cpt() {
        check_ajax_referer('gdws_cpt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'gdws-tools'));
        }
        
        $cpt_key = sanitize_key($_POST['cpt_key']);
        $post_types = get_option($this->option_name, array());
        
        if (isset($post_types[$cpt_key])) {
            unset($post_types[$cpt_key]);
            update_option($this->option_name, $post_types);
            flush_rewrite_rules();
            
            wp_send_json_success(array(
                'message' => __('Post type deleted successfully.', 'gdws-tools')
            ));
        }
        
        wp_send_json_error(array(
            'message' => __('Post type not found.', 'gdws-tools')
        ));
    }
    
    /**
     * AJAX handler to get CPT data
     */
    public function ajax_get_cpt() {
        check_ajax_referer('gdws_cpt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'gdws-tools'));
        }
        
        $cpt_key = sanitize_key($_POST['cpt_key']);
        $post_types = get_option($this->option_name, array());
        
        if (isset($post_types[$cpt_key])) {
            wp_send_json_success($post_types[$cpt_key]);
        }
        
        wp_send_json_error(array(
            'message' => __('Post type not found.', 'gdws-tools')
        ));
    }
    
    /**
     * Get registered post types for display
     */
    public function get_registered_post_types() {
        return get_option($this->option_name, array());
    }
}