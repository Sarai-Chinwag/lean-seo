<?php
/**
 * XML Sitemap Handler
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO_Sitemap {

    /**
     * Register rewrite rules
     */
    public static function register_routes() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?lean_sitemap=index', 'top');
        // Legacy Yoast sitemap URL redirect for backwards compatibility
        add_rewrite_rule('^sitemap_index\.xml$', 'index.php?lean_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-posts\.xml$', 'index.php?lean_sitemap=posts', 'top');
        add_rewrite_rule('^sitemap-posts-([0-9]+)\.xml$', 'index.php?lean_sitemap=posts&sitemap_page=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-pages\.xml$', 'index.php?lean_sitemap=pages', 'top');
        add_rewrite_rule('^sitemap-categories\.xml$', 'index.php?lean_sitemap=categories', 'top');
        add_rewrite_rule('^sitemap-tags\.xml$', 'index.php?lean_sitemap=tags', 'top');
    }

    /**
     * Handle sitemap request
     */
    public static function handle_request() {
        $sitemap = get_query_var('lean_sitemap');
        if (!$sitemap) {
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

        switch ($sitemap) {
            case 'index':
                self::render_index();
                break;
            case 'posts':
                self::render_posts();
                break;
            case 'pages':
                self::render_pages();
                break;
            case 'categories':
                self::render_categories();
                break;
            case 'tags':
                self::render_tags();
                break;
        }

        exit;
    }

    /**
     * Render sitemap index
     */
    private static function render_index() {
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Posts sitemap(s)
        $post_count = wp_count_posts('post')->publish;
        $pages_needed = ceil($post_count / 1000);
        
        if ($pages_needed <= 1) {
            echo '  <sitemap>' . "\n";
            echo '    <loc>' . home_url('/sitemap-posts.xml') . '</loc>' . "\n";
            echo '    <lastmod>' . self::get_latest_post_date() . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        } else {
            for ($i = 1; $i <= $pages_needed; $i++) {
                echo '  <sitemap>' . "\n";
                echo '    <loc>' . home_url("/sitemap-posts-{$i}.xml") . '</loc>' . "\n";
                echo '  </sitemap>' . "\n";
            }
        }

        // Pages
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . home_url('/sitemap-pages.xml') . '</loc>' . "\n";
        echo '  </sitemap>' . "\n";

        // Categories
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . home_url('/sitemap-categories.xml') . '</loc>' . "\n";
        echo '  </sitemap>' . "\n";

        // Tags
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . home_url('/sitemap-tags.xml') . '</loc>' . "\n";
        echo '  </sitemap>' . "\n";

        // Allow themes to add custom sitemaps
        do_action('lean_seo_sitemap_index');

        echo '</sitemapindex>';
    }

    /**
     * Render posts sitemap
     */
    private static function render_posts() {
        $page = get_query_var('sitemap_page', 1);
        $offset = ($page - 1) * 1000;

        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        foreach ($posts as $post) {
            echo '  <url>' . "\n";
            echo '    <loc>' . get_permalink($post) . '</loc>' . "\n";
            echo '    <lastmod>' . get_the_modified_date('c', $post) . '</lastmod>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    /**
     * Render pages sitemap
     */
    private static function render_pages() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        echo '  <url>' . "\n";
        echo '    <loc>' . home_url('/') . '</loc>' . "\n";
        echo '    <changefreq>daily</changefreq>' . "\n";
        echo '    <priority>1.0</priority>' . "\n";
        echo '  </url>' . "\n";

        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ));

        foreach ($pages as $page) {
            echo '  <url>' . "\n";
            echo '    <loc>' . get_permalink($page) . '</loc>' . "\n";
            echo '    <lastmod>' . get_the_modified_date('c', $page) . '</lastmod>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    /**
     * Render categories sitemap
     */
    private static function render_categories() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $categories = get_categories(array('hide_empty' => true));

        foreach ($categories as $category) {
            echo '  <url>' . "\n";
            echo '    <loc>' . get_category_link($category->term_id) . '</loc>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    /**
     * Render tags sitemap
     */
    private static function render_tags() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $tags = get_tags(array('hide_empty' => true));

        foreach ($tags as $tag) {
            echo '  <url>' . "\n";
            echo '    <loc>' . get_tag_link($tag->term_id) . '</loc>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    /**
     * Get latest post modified date
     */
    private static function get_latest_post_date() {
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
        ));

        if ($posts) {
            return get_the_modified_date('c', $posts[0]);
        }

        return current_time('c');
    }
}
