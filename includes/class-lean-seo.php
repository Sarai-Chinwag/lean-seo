<?php
/**
 * Main Lean SEO Class
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-meta.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-sitemap.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-schema.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-admin.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Meta tags
        add_filter('document_title_parts', array($this, 'filter_title_parts'), 10);
        add_filter('document_title_separator', array($this, 'title_separator'));
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_canonical'), 1);
        add_action('wp_head', array($this, 'output_schema'), 2);

        // Remove WP default canonical
        remove_action('wp_head', 'rel_canonical');

        // Sitemap
        add_action('init', array($this, 'register_sitemap_routes'));
        add_filter('query_vars', array($this, 'sitemap_query_vars'));
        add_action('template_redirect', array($this, 'handle_sitemap'));

        // Admin
        if (is_admin()) {
            add_action('add_meta_boxes', array($this, 'add_meta_box'));
            add_action('save_post', array($this, 'save_meta'), 10, 1);
        }

        // Filter robots.txt to include our sitemap
        add_filter('robots_txt', array($this, 'filter_robots_txt'), 999, 2);
    }

    /**
     * Filter robots.txt to include our sitemap
     */
    public function filter_robots_txt($output, $public) {
        // Remove any existing sitemap references (e.g., from old Yoast)
        $output = preg_replace('/^.*sitemap.*$\n?/mi', '', $output);
        
        // Remove Yoast comment blocks
        $output = preg_replace('/# START YOAST BLOCK.*?# END YOAST BLOCK\s*/s', '', $output);
        
        // Trim and add our sitemap
        $output = trim($output);
        if ($output) {
            $output .= "\n\n";
        }
        $output .= "Sitemap: " . home_url('/sitemap.xml') . "\n";
        
        return $output;
    }

    /**
     * Filter document title parts
     */
    public function filter_title_parts($title) {
        if (is_singular()) {
            $custom_title = get_post_meta(get_the_ID(), '_lean_seo_title', true);
            if ($custom_title) {
                $title['title'] = $custom_title;
            }
        }
        return $title;
    }

    /**
     * Title separator
     */
    public function title_separator($sep) {
        return '|';
    }

    /**
     * Output meta tags
     */
    public function output_meta_tags() {
        Lean_SEO_Meta::output();
    }

    /**
     * Output canonical
     */
    public function output_canonical() {
        Lean_SEO_Meta::output_canonical();
    }

    /**
     * Output schema
     */
    public function output_schema() {
        Lean_SEO_Schema::output();
    }

    /**
     * Register sitemap routes
     */
    public function register_sitemap_routes() {
        Lean_SEO_Sitemap::register_routes();
    }

    /**
     * Sitemap query vars
     */
    public function sitemap_query_vars($vars) {
        $vars[] = 'lean_sitemap';
        $vars[] = 'sitemap_page';
        return $vars;
    }

    /**
     * Handle sitemap requests
     */
    public function handle_sitemap() {
        Lean_SEO_Sitemap::handle_request();
    }

    /**
     * Add meta box
     */
    public function add_meta_box() {
        Lean_SEO_Admin::add_meta_box();
    }

    /**
     * Save meta
     */
    public function save_meta($post_id) {
        Lean_SEO_Admin::save_meta($post_id);
    }
}
