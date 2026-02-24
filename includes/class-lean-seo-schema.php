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
            $faq_schema = self::get_faq_schema();
            if ($faq_schema) {
                $schema[] = $faq_schema;
            }
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
            'author' => self::get_author_schema(),
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

    /**
     * Get publisher/author defaults from options.
     *
     * @since 1.3.0
     * @return array {
     *     @type string $author_name    Fallback author name.
     *     @type string $author_url     Fallback author URL.
     *     @type string $author_type    Schema type: 'Person' or 'Organization'.
     * }
     */
    public static function get_publisher_defaults() {
        $defaults = array(
            'author_name' => get_bloginfo( 'name' ),
            'author_url'  => home_url( '/' ),
            'author_type' => 'Person',
        );

        $saved = get_option( 'lean_seo_schema', array() );

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Build author schema for the current post.
     *
     * Uses post author if available, otherwise falls back to
     * publisher defaults from options.
     *
     * @since 1.3.0
     * @return array Schema.org Person or Organization array.
     */
    private static function get_author_schema() {
        $author_name = get_the_author();

        if ( $author_name ) {
            return array(
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => get_author_posts_url( get_the_author_meta( 'ID' ) ),
            );
        }

        $publisher = self::get_publisher_defaults();

        return array(
            '@type' => $publisher['author_type'],
            'name'  => $publisher['author_name'],
            'url'   => $publisher['author_url'],
        );
    }

    /**
     * FAQPage schema — auto-detected from post content.
     *
     * Detection strategies (in priority order):
     * 1. Explicit FAQ section: an H2/H3 containing "FAQ" or "Frequently Asked",
     *    followed by H3/H4 question headings (ending in ?) with paragraph answers.
     * 2. Question-heading pattern: 2+ H2/H3 headings ending in "?" where the
     *    text between that heading and the next heading is the answer.
     *
     * Returns null if fewer than 2 Q&A pairs are found.
     *
     * @since 1.1.0
     * @return array|null FAQPage schema array or null.
     */
    private static function get_faq_schema() {
        $post = get_post();
        if ( ! $post ) {
            return null;
        }

        /**
         * Filter to disable FAQ schema for specific posts.
         *
         * @param bool $enabled Whether FAQ schema is enabled. Default true.
         * @param int  $post_id The current post ID.
         */
        if ( ! apply_filters( 'lean_seo_faq_schema_enabled', true, $post->ID ) ) {
            return null;
        }

        $content = $post->post_content;
        $qa_pairs = self::extract_faq_pairs( $content );

        /**
         * Filter the extracted FAQ Q&A pairs before schema output.
         *
         * @param array $qa_pairs Array of ['question' => string, 'answer' => string].
         * @param int   $post_id  The current post ID.
         */
        $qa_pairs = apply_filters( 'lean_seo_faq_pairs', $qa_pairs, $post->ID );

        // Google requires at least 2 FAQ items for rich results
        if ( count( $qa_pairs ) < 2 ) {
            return null;
        }

        $faq_entities = array();
        foreach ( $qa_pairs as $pair ) {
            $faq_entities[] = array(
                '@type' => 'Question',
                'name' => $pair['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $pair['answer'],
                ),
            );
        }

        return array(
            '@type' => 'FAQPage',
            '@id' => get_permalink() . '#faq',
            'mainEntity' => $faq_entities,
        );
    }

    /**
     * Extract question/answer pairs from post content HTML.
     *
     * Splits content on H2/H3 boundaries, identifies headings that end
     * with a question mark, and captures the following content as the answer.
     *
     * @since 1.1.0
     * @param string $content Raw post content (may contain block markup).
     * @return array Array of ['question' => string, 'answer' => string].
     */
    private static function extract_faq_pairs( $content ) {
        // Render blocks/shortcodes to get final HTML
        $html = do_blocks( $content );
        $html = do_shortcode( $html );

        // Split on H2 and H3 tags, keeping the delimiters
        $parts = preg_split(
            '/(<h[23][^>]*>.*?<\/h[23]>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ( ! $parts ) {
            return array();
        }

        $qa_pairs = array();
        $in_faq_section = false;

        for ( $i = 0; $i < count( $parts ); $i++ ) {
            $part = trim( $parts[ $i ] );

            // Check if this is a heading
            if ( ! preg_match( '/<h([23])[^>]*>(.*?)<\/h\1>/is', $part, $heading_match ) ) {
                continue;
            }

            $heading_text = trim( wp_strip_all_tags( $heading_match[2] ) );

            // Strategy 1: Detect explicit FAQ section header
            if ( preg_match( '/\b(FAQ|Frequently\s+Asked)/i', $heading_text ) ) {
                $in_faq_section = true;
                continue;
            }

            // Only process headings that end with a question mark
            if ( substr( $heading_text, -1 ) !== '?' ) {
                // If we were in an explicit FAQ section and hit a non-question
                // non-FAQ heading, we've left the section
                if ( $in_faq_section && ! preg_match( '/\b(FAQ|Frequently\s+Asked)/i', $heading_text ) ) {
                    $in_faq_section = false;
                }
                continue;
            }

            // Get the answer: everything between this heading and the next heading
            $answer_html = '';
            if ( isset( $parts[ $i + 1 ] ) ) {
                $next = trim( $parts[ $i + 1 ] );
                // If next part is NOT a heading, it's the answer content
                if ( ! preg_match( '/^<h[23][^>]*>/i', $next ) ) {
                    $answer_html = $next;
                }
            }

            // Clean the answer text
            $answer_text = self::clean_answer_text( $answer_html );

            // Skip if answer is too short (likely not a real Q&A)
            if ( strlen( $answer_text ) < 20 ) {
                continue;
            }

            // Truncate very long answers (Google recommends concise FAQ answers)
            if ( strlen( $answer_text ) > 500 ) {
                $answer_text = mb_substr( $answer_text, 0, 497 ) . '...';
            }

            $qa_pairs[] = array(
                'question' => $heading_text,
                'answer' => $answer_text,
            );
        }

        return $qa_pairs;
    }

    /**
     * Clean answer HTML into plain text suitable for schema.
     *
     * Preserves basic structure but strips tags for JSON-LD output.
     *
     * @since 1.1.0
     * @param string $html Raw HTML answer content.
     * @return string Cleaned plain text.
     */
    private static function clean_answer_text( $html ) {
        if ( empty( $html ) ) {
            return '';
        }

        // Remove images and figures (not useful in schema text)
        $html = preg_replace( '/<figure[^>]*>.*?<\/figure>/is', '', $html );
        $html = preg_replace( '/<img[^>]*>/is', '', $html );

        // Convert list items to readable text
        $html = preg_replace( '/<li[^>]*>/i', '• ', $html );

        // Convert <br> and block elements to spaces
        $html = preg_replace( '/<br\s*\/?>/i', ' ', $html );
        $html = preg_replace( '/<\/(p|div|li|ul|ol)>/i', ' ', $html );

        // Strip remaining tags
        $text = wp_strip_all_tags( $html );

        // Normalize whitespace
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( $text );
    }
}
