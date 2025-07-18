<?php
/**
 * GDWS Tools - Shortcodes Module
 * 
 * @package GDWS_Tools
 * @subpackage Modules
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes Module for GDWS Tools
 */
class GDWS_Tools_Shortcodes {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_shortcodes();
    }
    
    /**
     * Register all shortcodes
     */
    private function register_shortcodes() {
        add_shortcode('current_year', array($this, 'current_year_shortcode'));
        // Add more shortcodes here as needed
        // add_shortcode('gdws_contact', array($this, 'contact_shortcode'));
        // add_shortcode('gdws_social', array($this, 'social_links_shortcode'));
    }
    
    /**
     * Current Year Shortcode Handler
     * 
     * @param array $atts Shortcode attributes
     * @return string Current year output
     */
    public function current_year_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'format' => 'Y',
            'timezone' => null
        ), $atts, 'current_year');
        
        // Set timezone if specified
        if ($atts['timezone']) {
            $original_timezone = date_default_timezone_get();
            try {
                date_default_timezone_set($atts['timezone']);
                $output = date($atts['format']);
                date_default_timezone_set($original_timezone);
            } catch (Exception $e) {
                // If invalid timezone, use default
                $output = wp_date($atts['format']);
            }
        } else {
            // Use WordPress timezone setting
            $output = wp_date($atts['format']);
        }
        
        return esc_html($output);
    }
    
    /**
     * Example: Contact Information Shortcode
     * Usage: [gdws_contact type="email"]
     * 
     * @param array $atts Shortcode attributes
     * @return string Contact information
     */
    public function contact_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'email', // email, phone, address
        ), $atts, 'gdws_contact');
        
        // Get contact info from options or define defaults
        $contact_info = array(
            'email' => get_option('admin_email'),
            'phone' => get_option('gdws_phone', ''),
            'address' => get_option('gdws_address', '')
        );
        
        if (isset($contact_info[$atts['type']])) {
            return esc_html($contact_info[$atts['type']]);
        }
        
        return '';
    }
    
    /**
     * Example: Social Links Shortcode
     * Usage: [gdws_social platform="facebook"]
     * 
     * @param array $atts Shortcode attributes
     * @return string Social media link
     */
    public function social_links_shortcode($atts) {
        $atts = shortcode_atts(array(
            'platform' => 'facebook',
            'style' => 'link', // link, icon, button
        ), $atts, 'gdws_social');
        
        // Example implementation
        $social_urls = array(
            'facebook' => get_option('gdws_facebook_url', ''),
            'twitter' => get_option('gdws_twitter_url', ''),
            'linkedin' => get_option('gdws_linkedin_url', ''),
            'instagram' => get_option('gdws_instagram_url', '')
        );
        
        if (isset($social_urls[$atts['platform']]) && !empty($social_urls[$atts['platform']])) {
            $url = esc_url($social_urls[$atts['platform']]);
            
            switch ($atts['style']) {
                case 'icon':
                    return sprintf('<a href="%s" class="gdws-social-icon gdws-social-%s" target="_blank" rel="noopener"><span class="dashicons dashicons-%s"></span></a>', 
                        $url, 
                        esc_attr($atts['platform']), 
                        esc_attr($atts['platform'])
                    );
                case 'button':
                    return sprintf('<a href="%s" class="button gdws-social-button" target="_blank" rel="noopener">Follow on %s</a>', 
                        $url, 
                        ucfirst($atts['platform'])
                    );
                default:
                    return sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', 
                        $url, 
                        ucfirst($atts['platform'])
                    );
            }
        }
        
        return '';
    }
}