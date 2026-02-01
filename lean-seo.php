<?php
/**
 * Plugin Name: Lean SEO
 * Plugin URI: https://github.com/Sarai-Chinwag/lean-seo
 * Description: Lightweight SEO without the bloat. Meta tags, Open Graph, Schema markup, XML sitemaps, and per-post SEO fields. A Yoast replacement that doesn't slow your site down.
 * Version: 1.0.0
 * Author: Sarai Chinwag
 * Author URI: https://saraichinwag.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lean-seo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Lean_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LEAN_SEO_VERSION', '1.0.0');
define('LEAN_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEAN_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load the main class
require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo.php';

// Initialize
function lean_seo_init() {
    return Lean_SEO::get_instance();
}
add_action('plugins_loaded', 'lean_seo_init');

// Activation hook
register_activation_hook(__FILE__, 'lean_seo_activate');
function lean_seo_activate() {
    // Flush rewrite rules for sitemap
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'lean_seo_deactivate');
function lean_seo_deactivate() {
    flush_rewrite_rules();
}
