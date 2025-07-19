<?php
/**
 * Plugin Name: GDWS Tools
 * Plugin URI: https://github.com/cjscrofani/gdws-wp-tools
 * Description: A comprehensive toolkit providing useful shortcodes and functionality for GDWS clients
 * Version: 1.0.0
 * Author: GDWS
 * Author URI: https://gdws.co/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gdws-tools
 * GitHub Plugin URI: https://github.com/cjscrofani/gdws-wp-tools
 * Primary Branch: main
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('GDWS_TOOLS_VERSION', '1.0.0');
define('GDWS_TOOLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDWS_TOOLS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GDWS_TOOLS_PLUGIN_FILE', __FILE__);

/**
 * Main GDWS Tools Class
 */
class GDWS_Tools {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * CPT Module instance
     */
    private $cpt_module = null;
    
    /**
     * GitHub Updater instance
     */
    private $updater = null;
    
    /**
     * Get single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin components
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        
        // Load individual modules
        $this->load_modules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load plugin text domain for translations
        load_plugin_textdomain('gdws-tools', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Load plugin modules
     */
    private function load_modules() {
        // Load shortcodes module
        require_once GDWS_TOOLS_PLUGIN_DIR . 'modules/shortcodes.php';
        new GDWS_Tools_Shortcodes();
        
        // Load custom post types module
        require_once GDWS_TOOLS_PLUGIN_DIR . 'modules/custom-post-types.php';
        $this->cpt_module = new GDWS_Tools_Custom_Post_Types();
        
        // Load GitHub updater module
        require_once GDWS_TOOLS_PLUGIN_DIR . 'modules/github-updater.php';
        $this->updater = new GDWS_Tools_GitHub_Updater(__FILE__);
        
        // Future modules can be loaded here
        // require_once GDWS_TOOLS_PLUGIN_DIR . 'modules/another-feature.php';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('GDWS Tools', 'gdws-tools'),
            __('GDWS Tools', 'gdws-tools'),
            'manage_options',
            'gdws-tools',
            array($this, 'admin_page'),
            'dashicons-admin-tools',
            80
        );
        
        // Add updates submenu
        add_submenu_page(
            'gdws-tools',
            __('Updates', 'gdws-tools'),
            __('Updates', 'gdws-tools'),
            'manage_options',
            'gdws-tools-updates',
            array($this, 'updates_page')
        );
    }
    
    /**
     * Updates page
     */
    public function updates_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('GDWS Tools Updates', 'gdws-tools'); ?></h1>
            
            <div class="card">
                <h2><?php _e('GitHub Updates', 'gdws-tools'); ?></h2>
                <p><?php _e('This plugin can automatically update from GitHub releases.', 'gdws-tools'); ?></p>
                
                <p><strong><?php _e('Current Version:', 'gdws-tools'); ?></strong> <?php echo GDWS_TOOLS_VERSION; ?></p>
                
                <p><?php _e('Updates will appear in the standard WordPress updates page when a new version is available on GitHub.', 'gdws-tools'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('gdws_check_updates', 'gdws_update_nonce'); ?>
                    <p class="submit">
                        <button type="submit" name="check_updates" class="button button-primary">
                            <?php _e('Check for Updates Now', 'gdws-tools'); ?>
                        </button>
                    </p>
                </form>
                
                <?php
                if (isset($_POST['check_updates']) && wp_verify_nonce($_POST['gdws_update_nonce'], 'gdws_check_updates')) {
                    if ($this->updater) {
                        $this->updater->force_check();
                        echo '<div class="notice notice-success"><p>' . __('Checking for updates... Please check the WordPress updates page.', 'gdws-tools') . '</p></div>';
                    }
                }
                ?>
                
                <h3><?php _e('How to Release Updates', 'gdws-tools'); ?></h3>
                <ol>
                    <li><?php _e('Update the Version number in the plugin header', 'gdws-tools'); ?></li>
                    <li><?php _e('Commit and push your changes to GitHub', 'gdws-tools'); ?></li>
                    <li><?php _e('Create a new Release on GitHub with a tag like "v1.0.1"', 'gdws-tools'); ?></li>
                    <li><?php _e('The plugin will automatically detect the new version', 'gdws-tools'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="gdws-tools-welcome">
                <h2><?php _e('Welcome to GDWS Tools', 'gdws-tools'); ?></h2>
                <p><?php _e('This plugin provides various tools and shortcodes to enhance your WordPress site.', 'gdws-tools'); ?></p>
                <p><small><?php echo sprintf(__('Version %s', 'gdws-tools'), GDWS_TOOLS_VERSION); ?></small></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Available Shortcodes', 'gdws-tools'); ?></h2>
                
                <h3><?php _e('Current Year Shortcode', 'gdws-tools'); ?></h3>
                <p><?php _e('Display the current year anywhere on your site.', 'gdws-tools'); ?></p>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Shortcode', 'gdws-tools'); ?></th>
                            <th><?php _e('Description', 'gdws-tools'); ?></th>
                            <th><?php _e('Example Output', 'gdws-tools'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[current_year]</code></td>
                            <td><?php _e('Display 4-digit year', 'gdws-tools'); ?></td>
                            <td><?php echo date('Y'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[current_year format="y"]</code></td>
                            <td><?php _e('Display 2-digit year', 'gdws-tools'); ?></td>
                            <td><?php echo date('y'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[current_year format="Y-m-d"]</code></td>
                            <td><?php _e('Display full date', 'gdws-tools'); ?></td>
                            <td><?php echo date('Y-m-d'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h4><?php _e('Common Use Cases:', 'gdws-tools'); ?></h4>
                <ul>
                    <li><?php _e('Copyright notice:', 'gdws-tools'); ?> <code>&copy; [current_year] <?php _e('Your Company', 'gdws-tools'); ?></code></li>
                    <li><?php _e('Dynamic content:', 'gdws-tools'); ?> <code><?php _e('Best Products of', 'gdws-tools'); ?> [current_year]</code></li>
                    <li><?php _e('Last updated:', 'gdws-tools'); ?> <code><?php _e('Updated January', 'gdws-tools'); ?> [current_year]</code></li>
                </ul>
            </div>
            
            <div class="card">
                <h2><?php _e('Custom Post Types', 'gdws-tools'); ?></h2>
                <p><?php _e('Create and manage custom post types directly from the WordPress admin.', 'gdws-tools'); ?></p>
                
                <?php 
                if ($this->cpt_module) {
                    $post_types = $this->cpt_module->get_registered_post_types();
                    $active_count = 0;
                    foreach ($post_types as $cpt) {
                        if (!empty($cpt['active'])) {
                            $active_count++;
                        }
                    }
                    ?>
                    <p><strong><?php echo sprintf(_n('%d custom post type registered', '%d custom post types registered', count($post_types), 'gdws-tools'), count($post_types)); ?></strong> 
                    <?php if ($active_count > 0) : ?>
                        (<?php echo sprintf(_n('%d active', '%d active', $active_count, 'gdws-tools'), $active_count); ?>)
                    <?php endif; ?>
                    </p>
                    
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=gdws-custom-post-types'); ?>" class="button">
                            <?php _e('Manage Custom Post Types', 'gdws-tools'); ?>
                        </a>
                    </p>
                    <?php
                }
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('More Features Coming Soon', 'gdws-tools'); ?></h2>
                <p><?php _e('We are continuously adding new tools and features to help improve your website.', 'gdws-tools'); ?></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=gdws-tools-updates'); ?>" class="button">
                        <?php _e('Check for Updates', 'gdws-tools'); ?>
                    </a>
                </p>
            </div>
            
            <style>
                .gdws-tools-welcome {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-left: 4px solid #0073aa;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                    margin: 20px 0;
                    padding: 15px 20px;
                }
                .card {
                    margin-top: 20px;
                }
                .card table {
                    margin-top: 10px;
                }
                .card h3 {
                    margin-top: 20px;
                }
                .card h4 {
                    margin-top: 15px;
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=gdws-tools') . '">' . __('Settings', 'gdws-tools') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables or set default options if needed
        flush_rewrite_rules();
        
        // Set activation flag for welcome message
        set_transient('gdws_tools_activated', true, 30);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
GDWS_Tools::get_instance();