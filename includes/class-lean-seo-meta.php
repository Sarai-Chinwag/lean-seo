<?php
/**
 * Meta Tags Handler
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO_Meta {

    /**
     * Output meta tags
     */
    public static function output() {
        $description = self::get_description();
        $image = self::get_image();
        $url = self::get_url();
        $site_name = get_bloginfo('name');
        $title = wp_get_document_title();

        // Basic meta description
        if ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        // Open Graph
        echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";
        echo '<meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";

        if ($description) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        }

        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";

        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }

        // Article specific
        if (is_singular('post')) {
            echo '<meta property="article:published_time" content="' . get_the_date('c') . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . get_the_modified_date('c') . '">' . "\n";
        }

        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";

        if ($description) {
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }

        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    /**
     * Output canonical URL
     */
    public static function output_canonical() {
        $canonical = self::get_canonical();
        if ($canonical) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        }
    }

    /**
     * Get meta description
     */
    public static function get_description() {
        // Allow themes/plugins to provide custom descriptions
        $custom = apply_filters('lean_seo_custom_description', false);
        if ($custom) {
            return $custom;
        }

        if (is_singular()) {
            // Custom meta description
            $custom_desc = get_post_meta(get_the_ID(), '_lean_seo_description', true);
            if ($custom_desc) {
                return $custom_desc;
            }

            // Fall back to excerpt
            $post = get_post();
            if ($post->post_excerpt) {
                return wp_strip_all_tags($post->post_excerpt);
            }

            // Fall back to trimmed content
            $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
            return wp_trim_words($content, 30, '...');
        }

        if (is_home() || is_front_page()) {
            return get_bloginfo('description');
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && $term->description) {
                return wp_strip_all_tags($term->description);
            }
            return sprintf('Browse all %s posts on %s', single_term_title('', false), get_bloginfo('name'));
        }

        if (is_search()) {
            return sprintf('Search results for "%s" on %s', get_search_query(), get_bloginfo('name'));
        }

        if (is_author()) {
            $author = get_queried_object();
            return sprintf('Posts by %s on %s', $author->display_name, get_bloginfo('name'));
        }

        return get_bloginfo('description');
    }

    /**
     * Get primary image
     */
    public static function get_image() {
        if (is_singular() && has_post_thumbnail()) {
            return get_the_post_thumbnail_url(get_the_ID(), 'large');
        }

        // Default fallback image
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            return wp_get_attachment_image_url($custom_logo_id, 'full');
        }

        return apply_filters('lean_seo_default_image', '');
    }

    /**
     * Get current URL
     */
    public static function get_url() {
        if (is_singular()) {
            return get_permalink();
        }
        return home_url(add_query_arg(array()));
    }

    /**
     * Get canonical URL
     */
    public static function get_canonical() {
        if (is_singular()) {
            return get_permalink();
        }
        
        if (is_home() || is_front_page()) {
            return home_url('/');
        }
        
        if (is_category() || is_tag() || is_tax()) {
            $link = get_term_link(get_queried_object());
            return is_wp_error($link) ? null : $link;
        }
        
        if (is_search()) {
            return get_search_link();
        }

        if (is_author()) {
            return get_author_posts_url(get_queried_object_id());
        }

        return null;
    }
}
