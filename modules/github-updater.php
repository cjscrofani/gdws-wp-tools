<?php
/**
 * GDWS Tools - GitHub Updater Module
 * 
 * @package GDWS_Tools
 * @subpackage Modules
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub Updater for GDWS Tools
 * Enables automatic updates from GitHub repository
 */
class GDWS_Tools_GitHub_Updater {
    
    /**
     * GitHub Username
     */
    private $username = 'cjscrofani';
    
    /**
     * GitHub Repository Name
     */
    private $repository = 'gdws-wp-tools';
    
    /**
     * Plugin data from get_plugin_data()
     */
    private $plugin_data;
    
    /**
     * Plugin slug (plugin_directory/plugin_file.php)
     */
    private $plugin_slug;
    
    /**
     * Plugin file
     */
    private $plugin_file;
    
    /**
     * GitHub response
     */
    private $github_response;
    
    /**
     * Constructor
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($this->plugin_file);
        
        // Initialize hooks
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
        
        // Add plugin row meta
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
    }
    
    /**
     * Get plugin data
     */
    private function get_plugin_data() {
        if (is_null($this->plugin_data)) {
            $this->plugin_data = get_plugin_data($this->plugin_file);
        }
    }
    
    /**
     * Get GitHub release info
     */
    private function get_github_release_info() {
        if (!is_null($this->github_response)) {
            return;
        }
        
        // Check transient first
        $transient_name = 'gdws_tools_github_release_' . md5($this->username . $this->repository);
        $cached = get_transient($transient_name);
        
        if ($cached !== false) {
            $this->github_response = $cached;
            return;
        }
        
        // Build API URL
        $url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest";
        
        // Set up args
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );
        
        // Make request
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $this->github_response = json_decode($body, true);
        
        // Store in transient for 6 hours
        set_transient($transient_name, $this->github_response, 6 * HOUR_IN_SECONDS);
    }
    
    /**
     * Check for plugin update
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get plugin data
        $this->get_plugin_data();
        
        // Get GitHub release info
        $this->get_github_release_info();
        
        if (!isset($this->github_response['tag_name'])) {
            return $transient;
        }
        
        // Get version from tag (remove 'v' prefix if present)
        $latest_version = ltrim($this->github_response['tag_name'], 'v');
        
        // Check if update is needed
        if (version_compare($this->plugin_data['Version'], $latest_version, '<')) {
            $plugin_info = array(
                'url' => $this->plugin_data['PluginURI'],
                'slug' => dirname($this->plugin_slug),
                'package' => $this->github_response['zipball_url'],
                'new_version' => $latest_version,
                'id' => $this->plugin_slug,
                'plugin' => $this->plugin_slug,
                'tested' => get_bloginfo('version'),
                'compatibility' => new stdClass(),
            );
            
            $transient->response[$this->plugin_slug] = (object) $plugin_info;
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info for WordPress plugin modal
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        // Get plugin data
        $this->get_plugin_data();
        
        // Get GitHub release info
        $this->get_github_release_info();
        
        if (!isset($this->github_response['tag_name'])) {
            return $result;
        }
        
        // Build plugin info object
        $plugin_info = array(
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->plugin_slug),
            'version' => ltrim($this->github_response['tag_name'], 'v'),
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'last_updated' => $this->github_response['created_at'],
            'homepage' => $this->plugin_data['PluginURI'],
            'short_description' => $this->plugin_data['Description'],
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'changelog' => $this->parse_changelog(),
            ),
            'download_link' => $this->github_response['zipball_url'],
        );
        
        return (object) $plugin_info;
    }
    
    /**
     * Parse changelog from GitHub release body
     */
    private function parse_changelog() {
        if (!isset($this->github_response['body'])) {
            return '<p>No changelog available.</p>';
        }
        
        // Convert markdown to HTML (basic conversion)
        $changelog = $this->github_response['body'];
        
        // Convert headers
        $changelog = preg_replace('/^### (.+?)$/m', '<h4>$1</h4>', $changelog);
        $changelog = preg_replace('/^## (.+?)$/m', '<h3>$1</h3>', $changelog);
        
        // Convert bold and italic
        $changelog = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $changelog);
        $changelog = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $changelog);
        
        // Convert lists
        $changelog = preg_replace('/^- (.+?)$/m', '<li>$1</li>', $changelog);
        $changelog = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog);
        
        // Convert line breaks
        $changelog = nl2br($changelog);
        
        return $changelog;
    }
    
    /**
     * Post installation - rename folder
     */
    public function post_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $response;
        }
        
        // Move files to correct directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($this->plugin_slug);
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;
        
        // Reactivate plugin if it was active
        if (is_plugin_active($this->plugin_slug)) {
            activate_plugin($this->plugin_slug);
        }
        
        return $result;
    }
    
    /**
     * Add plugin row meta
     */
    public function add_plugin_row_meta($links, $file) {
        if ($file !== $this->plugin_slug) {
            return $links;
        }
        
        $links[] = sprintf(
            '<a href="https://github.com/%s/%s" target="_blank">%s</a>',
            $this->username,
            $this->repository,
            __('View on GitHub', 'gdws-tools')
        );
        
        $links[] = sprintf(
            '<a href="https://github.com/%s/%s/releases" target="_blank">%s</a>',
            $this->username,
            $this->repository,
            __('Changelog', 'gdws-tools')
        );
        
        return $links;
    }
    
    /**
     * Force check for updates
     */
    public function force_check() {
        $transient_name = 'gdws_tools_github_release_' . md5($this->username . $this->repository);
        delete_transient($transient_name);
        $this->github_response = null;
        
        // Force WordPress to check for plugin updates
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}