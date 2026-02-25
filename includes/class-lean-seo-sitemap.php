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

        // Custom post type sitemaps
        $cpts = get_post_types(array('public' => true, '_builtin' => false), 'names');
        foreach ($cpts as $cpt) {
            add_rewrite_rule('^sitemap-' . $cpt . '\.xml$', 'index.php?lean_sitemap=cpt&lean_cpt=' . $cpt, 'top');
            add_rewrite_rule('^sitemap-' . $cpt . '-([0-9]+)\.xml$', 'index.php?lean_sitemap=cpt&lean_cpt=' . $cpt . '&sitemap_page=$matches[1]', 'top');
        }
    }

    /**
     * Register query vars
     */
    public static function register_query_vars($vars) {
        $vars[] = 'lean_cpt';
        return $vars;
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
            case 'cpt':
                $cpt = get_query_var('lean_cpt');
                if ($cpt && post_type_exists($cpt)) {
                    self::render_cpt($cpt);
                }
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
            echo '    <lastmod>' . self::get_latest_modified_date('post') . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        } else {
            for ($i = 1; $i <= $pages_needed; $i++) {
                $offset = ($i - 1) * 1000;
                $lastmod = self::get_latest_modified_date('post', 1000, $offset);
                echo '  <sitemap>' . "\n";
                echo '    <loc>' . home_url("/sitemap-posts-{$i}.xml") . '</loc>' . "\n";
                echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
                echo '  </sitemap>' . "\n";
            }
        }

        // Pages
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . home_url('/sitemap-pages.xml') . '</loc>' . "\n";
        echo '    <lastmod>' . self::get_latest_modified_date('page') . '</lastmod>' . "\n";
        echo '  </sitemap>' . "\n";

        // Categories
        $cat_lastmod = self::get_term_latest_modified('category');
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . home_url('/sitemap-categories.xml') . '</loc>' . "\n";
        if ($cat_lastmod) {
            echo '    <lastmod>' . $cat_lastmod . '</lastmod>' . "\n";
        }
        echo '  </sitemap>' . "\n";

        // Tags
        $tag_lastmod = self::get_term_latest_modified('post_tag');
        echo '  <sitemap>' . "\n";
        echo '    <loc>' . home_url('/sitemap-tags.xml') . '</loc>' . "\n";
        if ($tag_lastmod) {
            echo '    <lastmod>' . $tag_lastmod . '</lastmod>' . "\n";
        }
        echo '  </sitemap>' . "\n";

        // Custom post types
        $cpts = get_post_types(array('public' => true, '_builtin' => false), 'names');
        foreach ($cpts as $cpt) {
            $cpt_count = wp_count_posts($cpt)->publish;
            if ($cpt_count < 1) {
                continue;
            }
            $cpt_pages = ceil($cpt_count / 1000);

            if ($cpt_pages <= 1) {
                echo '  <sitemap>' . "\n";
                echo '    <loc>' . home_url("/sitemap-{$cpt}.xml") . '</loc>' . "\n";
                echo '    <lastmod>' . self::get_latest_modified_date($cpt) . '</lastmod>' . "\n";
                echo '  </sitemap>' . "\n";
            } else {
                for ($i = 1; $i <= $cpt_pages; $i++) {
                    $offset = ($i - 1) * 1000;
                    $lastmod = self::get_latest_modified_date($cpt, 1000, $offset);
                    echo '  <sitemap>' . "\n";
                    echo '    <loc>' . home_url("/sitemap-{$cpt}-{$i}.xml") . '</loc>' . "\n";
                    echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
                    echo '  </sitemap>' . "\n";
                }
            }
        }

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
            $lastmod = self::get_term_post_lastmod($category->term_id, 'category');
            if ($lastmod) {
                echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            }
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
            $lastmod = self::get_term_post_lastmod($tag->term_id, 'post_tag');
            if ($lastmod) {
                echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            }
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    /**
     * Render custom post type sitemap
     */
    private static function render_cpt($post_type) {
        $page = get_query_var('sitemap_page', 1);
        $offset = ($page - 1) * 1000;

        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $posts = get_posts(array(
            'post_type' => $post_type,
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
     * Get latest modified date for a post type, optionally within a page range
     */
    private static function get_latest_modified_date($post_type = 'post', $limit = 1, $offset = 0) {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        if ($posts) {
            return get_the_modified_date('c', $posts[0]);
        }

        return current_time('c');
    }

    /**
     * Get the most recently modified post date for a given term
     */
    private static function get_term_post_lastmod($term_id, $taxonomy) {
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'terms' => $term_id,
                ),
            ),
        ));

        if ($posts) {
            return get_the_modified_date('c', $posts[0]);
        }

        return null;
    }

    /**
     * Get the overall latest modified post date across a taxonomy
     */
    private static function get_term_latest_modified($taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true, 'fields' => 'ids'));
        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'terms' => $terms,
                ),
            ),
        ));

        if ($posts) {
            return get_the_modified_date('c', $posts[0]);
        }

        return null;
    }
}
