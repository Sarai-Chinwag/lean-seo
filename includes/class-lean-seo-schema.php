<?php
/**
 * Schema/JSON-LD Handler
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO_Schema {

    /**
     * Output JSON-LD schema
     */
    public static function output() {
        $schema = array();

        // Website schema (always)
        $schema[] = self::get_website_schema();

        // Organization schema
        $schema[] = self::get_organization_schema();

        // Article schema for posts
        if (is_singular('post')) {
            $schema[] = self::get_article_schema();
            $schema[] = self::get_breadcrumb_schema();
        }

        // Page schema
        if (is_singular('page')) {
            $schema[] = self::get_webpage_schema();
            $schema[] = self::get_breadcrumb_schema();
        }

        // Filter empty values
        $schema = array_filter($schema);

        // Output
        $output = array(
            '@context' => 'https://schema.org',
            '@graph' => $schema
        );

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "\n</script>\n";
    }

    /**
     * Website schema
     */
    private static function get_website_schema() {
        return array(
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'url' => home_url('/'),
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => array(
                    '@type' => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}')
                ),
                'query-input' => 'required name=search_term_string'
            )
        );
    }

    /**
     * Organization schema
     */
    private static function get_organization_schema() {
        $schema = array(
            '@type' => 'Organization',
            '@id' => home_url('/#organization'),
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        );

        // Add logo if available
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $schema['logo'] = array(
                '@type' => 'ImageObject',
                'url' => wp_get_attachment_image_url($custom_logo_id, 'full'),
            );
        }

        return $schema;
    }

    /**
     * Article schema for posts
     */
    private static function get_article_schema() {
        $post = get_post();
        
        $schema = array(
            '@type' => 'Article',
            '@id' => get_permalink() . '#article',
            'isPartOf' => array('@id' => get_permalink() . '#webpage'),
            'headline' => get_the_title(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'mainEntityOfPage' => array('@id' => get_permalink() . '#webpage'),
            'wordCount' => str_word_count(wp_strip_all_tags($post->post_content)),
            'publisher' => array('@id' => home_url('/#organization')),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => get_author_posts_url(get_the_author_meta('ID')),
            ),
        );

        // Add image
        if (has_post_thumbnail()) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => get_the_post_thumbnail_url(get_the_ID(), 'large'),
            );
        }

        // Add description
        $description = Lean_SEO_Meta::get_description();
        if ($description) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * WebPage schema
     */
    private static function get_webpage_schema() {
        return array(
            '@type' => 'WebPage',
            '@id' => get_permalink() . '#webpage',
            'url' => get_permalink(),
            'name' => get_the_title(),
            'isPartOf' => array('@id' => home_url('/#website')),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
        );
    }

    /**
     * Breadcrumb schema
     */
    private static function get_breadcrumb_schema() {
        $items = array();
        $position = 1;

        // Home
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => home_url('/')
        );

        // Category for posts
        if (is_singular('post')) {
            $categories = get_the_category();
            if ($categories) {
                $cat = $categories[0];
                $items[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $cat->name,
                    'item' => get_category_link($cat->term_id)
                );
            }
        }

        // Current page (no item URL for last breadcrumb)
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => get_the_title()
        );

        return array(
            '@type' => 'BreadcrumbList',
            '@id' => get_permalink() . '#breadcrumb',
            'itemListElement' => $items
        );
    }
}
