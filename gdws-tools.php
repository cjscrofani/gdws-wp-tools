<?php
/**
 * Plugin Name: GDWS Tools
 * Plugin URI: https://golddust.co/
 * Description: A comprehensive toolkit providing useful shortcodes and functionality for GDWS clients
 * Version: 1.0.0
 * Author: GDWS
 * Author URI: https://golddust.co/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gdws-tools
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('GDWS_TOOLS_VERSION', '1.0.0');
define('GDWS_TOOLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDWS_TOOLS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main GDWS Tools Class
 */
class GDWS_Tools {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
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
                <h2><?php _e('More Features Coming Soon', 'gdws-tools'); ?></h2>
                <p><?php _e('We are continuously adding new tools and features to help improve your website.', 'gdws-tools'); ?></p>
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