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

        // Person schema — empty by default; themes/plugins populate via filter.
        $person_schema = self::get_person_schema();
        if ($person_schema) {
            $schema[] = $person_schema;
        }

        // Article schema for posts
        if (is_singular('post')) {
            $schema[] = self::get_webpage_schema();
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

        /**
         * Filter the complete JSON-LD @graph before output.
         *
         * Allows adding, removing, or reordering schema nodes. Runs after
         * individual node filters (lean_seo_website_schema, etc.) so the
         * graph this filter receives contains each node's final shape.
         *
         * @since 1.5.0
         * @param array $graph Array of schema node arrays.
         */
        $schema = apply_filters('lean_seo_schema_graph', $schema);

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
        $schema = array(
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

        /**
         * Filter the WebSite schema node.
         *
         * @since 1.5.0
         * @param array $schema WebSite schema array.
         */
        return apply_filters('lean_seo_website_schema', $schema);
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
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            $logo_meta = wp_get_attachment_metadata($custom_logo_id);
            $logo_schema = array(
                '@type' => 'ImageObject',
                'url' => $logo_url,
            );
            if ($logo_meta && isset($logo_meta['width'], $logo_meta['height'])) {
                $logo_schema['width'] = $logo_meta['width'];
                $logo_schema['height'] = $logo_meta['height'];
            }
            $schema['logo'] = $logo_schema;
        }

        /**
         * Filter the Organization schema node.
         *
         * Use this to add sameAs (social URLs), description, founder,
         * contactPoint, or any other Schema.org Organization properties.
         *
         * @since 1.5.0
         * @param array $schema Organization schema array.
         */
        return apply_filters('lean_seo_organization_schema', $schema);
    }

    /**
     * Person schema (opt-in via filter).
     *
     * Not emitted by default. Sites representing an individual (personal
     * blogs, author sites) can return a populated Person node via the
     * lean_seo_person_schema filter and it will be added to the graph.
     *
     * @since 1.5.0
     * @return array|null Person schema array, or null to omit.
     */
    private static function get_person_schema() {
        $default = null;

        /**
         * Filter the Person schema node.
         *
         * Return a Schema.org Person array to include it in the graph,
         * or null (default) to omit. Typical shape:
         *
         *     array(
         *         '@type'       => 'Person',
         *         '@id'         => home_url('/#person'),
         *         'name'        => 'Jane Doe',
         *         'url'         => home_url('/'),
         *         'description' => 'Writer, developer, sailor.',
         *         'sameAs'      => array('https://twitter.com/...', ...),
         *     )
         *
         * @since 1.5.0
         * @param array|null $schema Default null (omitted).
         */
        return apply_filters('lean_seo_person_schema', $default);
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
            $thumb_id = get_post_thumbnail_id(get_the_ID());
            $thumb_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
            $thumb_meta = wp_get_attachment_metadata($thumb_id);
            $image_schema = array(
                '@type' => 'ImageObject',
                'url' => $thumb_url,
            );
            if ($thumb_meta && isset($thumb_meta['width'], $thumb_meta['height'])) {
                $image_schema['width'] = $thumb_meta['width'];
                $image_schema['height'] = $thumb_meta['height'];
            }
            $schema['image'] = $image_schema;
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
        $schema = array(
            '@type' => 'WebPage',
            '@id' => get_permalink() . '#webpage',
            'url' => get_permalink(),
            'name' => get_the_title(),
            'isPartOf' => array('@id' => home_url('/#website')),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
        );

        /**
         * Filter the WebPage schema node.
         *
         * @since 1.5.0
         * @param array $schema WebPage schema array.
         */
        return apply_filters('lean_seo_webpage_schema', $schema);
    }

    /**
     * Breadcrumb schema
     *
     * Emits a BreadcrumbList. On the homepage, only a single "Home"
     * crumb is emitted (the current-page entry is omitted to avoid the
     * "Home → Home" duplicate — see GitHub issue #2). On singular views,
     * the home crumb is followed by an optional category (posts) and
     * the current page title.
     */
    private static function get_breadcrumb_schema() {
        $items = array();
        $position = 1;

        // Home crumb
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

        // Current page (singular only — the homepage is already "Home").
        // Last crumb intentionally omits the item URL per BreadcrumbList
        // best practices.
        if (is_singular() && ! is_front_page() && ! is_home()) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => get_the_title()
            );
        }

        $schema = array(
            '@type' => 'BreadcrumbList',
            '@id' => ((is_front_page() || is_home()) ? home_url('/') : get_permalink()) . '#breadcrumb',
            'itemListElement' => $items
        );

        /**
         * Filter the BreadcrumbList schema node.
         *
         * @since 1.5.0
         * @param array $schema Breadcrumb schema array.
         */
        return apply_filters('lean_seo_breadcrumb_schema', $schema);
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
     * 3. Numbered/stepped fact pattern: H3 headings like "1. Fact text" or
     *    "Step 1: Instruction text" — used for "Facts About" and how-to posts.
     * 4. Thematic spiritual meaning pattern: H3 noun-phrase headings on posts
     *    whose title contains "Spiritual Meaning" or "Symbolism" — generates
     *    questions like "What does [topic] mean spiritually in terms of [heading]?"
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
        $qa_pairs = self::extract_faq_pairs( $content, $post );

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
     * Question words that indicate a heading is a question even without "?".
     *
     * @since 1.5.0
     * @var string
     */
    private static $question_words = 'What|Why|How|When|Where|Can|Do|Is|Are|Does|Did|Will|Would|Should|Could';

    /**
     * Check whether a heading is a question.
     *
     * A heading counts as a question if it:
     * 1. Ends with a question mark, OR
     * 2. Starts with a recognised question word (What, Why, How, etc.)
     *
     * @since 1.5.0
     * @param string $heading_text Plain-text heading (tags already stripped).
     * @return bool
     */
    private static function is_question_heading( $heading_text ) {
        // Ends with "?"
        if ( substr( $heading_text, -1 ) === '?' ) {
            return true;
        }

        // Starts with a question word (case-insensitive, followed by a space)
        if ( preg_match( '/^(' . self::$question_words . ')\s/i', $heading_text ) ) {
            return true;
        }

        return false;
    }

    /**
     * Extract question/answer pairs from post content HTML.
     *
     * Runs detection strategies in order, returning the first set that
     * yields at least 2 Q&A pairs:
     *
     * Strategy 1 — Question headings: H2/H3 ending in "?" or starting with
     *   a question word (What, Why, How, …). Also handles explicit FAQ sections.
     * Strategy 2 — Numbered/stepped facts: H3 headings like "1. Fact text" or
     *   "Step N: Instruction text". Designed for "Facts About" and how-to posts.
     * Strategy 3 — Thematic spiritual meaning: H3 noun-phrase headings on posts
     *   whose title contains "Spiritual Meaning" or "Symbolism". Generates
     *   questions like "What does [topic] mean spiritually in terms of [heading]?"
     *
     * @since 1.1.0
     * @since 1.5.0 Added question-word detection for headings without "?".
     * @since 1.6.0 Added numbered-fact and thematic-spiritual fallback strategies;
     *              accepts optional $post for title-aware extraction.
     * @param string       $content Raw post content (may contain block markup).
     * @param WP_Post|null $post    Optional post object for title-aware strategies.
     * @return array Array of ['question' => string, 'answer' => string].
     */
    private static function extract_faq_pairs( $content, $post = null ) {
        // Render blocks/shortcodes to get final HTML
        $html = do_blocks( $content );
        $html = do_shortcode( $html );

        // --- Strategy 1: Question headings (existing logic) ---

        // Split on H2 and H3 tags, keeping the delimiters
        $parts = preg_split(
            '/(<h[23][^>]*>.*?<\/h[23]>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $qa_pairs = array();
        $in_faq_section = false;

        if ( $parts ) {
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

                // Check if heading is a question (ends with "?" or starts with question word)
                if ( ! self::is_question_heading( $heading_text ) ) {
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
                    'answer'   => $answer_text,
                );
            }
        }

        if ( count( $qa_pairs ) >= 2 ) {
            return $qa_pairs;
        }

        // --- Strategy 2: Numbered / stepped headings (Facts About & how-to posts) ---
        $numbered = self::extract_numbered_pairs( $html, $post );
        if ( count( $numbered ) >= 2 ) {
            return $numbered;
        }

        // --- Strategy 3: Thematic headings (Spiritual Meaning / Symbolism posts) ---
        $thematic = self::extract_thematic_pairs( $html, $post );
        if ( count( $thematic ) >= 2 ) {
            return $thematic;
        }

        return $qa_pairs;
    }

    /**
     * Extract Q&A pairs from numbered or stepped H3 headings.
     *
     * Matches headings like:
     * - "1. Parrots Are Exceptionally Smart"
     * - "Step 1: Go Where the UFOs Go"
     * - "2) Their beaks are incredibly strong"
     *
     * The number/step prefix is stripped; "They/They're/Their" pronouns at
     * the start of a heading are replaced with the subject extracted from the
     * post title (e.g. "Parrots" from "10 Amazing Facts About Parrots").
     *
     * @since 1.6.0
     * @param string       $html Rendered HTML content.
     * @param WP_Post|null $post Optional post object for subject extraction.
     * @return array Array of ['question' => string, 'answer' => string].
     */
    private static function extract_numbered_pairs( $html, $post = null ) {
        // Split on H3 tags only — numbered facts are always H3
        $parts = preg_split(
            '/(<h3[^>]*>.*?<\/h3>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ( ! $parts ) {
            return array();
        }

        // Try to extract the subject from the post title for pronoun replacement.
        // "10 Amazing Facts About Parrots" → "Parrots"
        // "How to Get Abducted by Aliens"  → no replacement
        $subject = '';
        if ( $post && preg_match( '/\bfacts about\s+(.+?)(?:\s*[\|\-–].*)?$/i', $post->post_title, $m ) ) {
            $subject = trim( $m[1] );
        }

        $qa_pairs = array();
        $max      = 5;

        for ( $i = 0; $i < count( $parts ); $i++ ) {
            $part = trim( $parts[ $i ] );

            if ( ! preg_match( '/<h3[^>]*>(.*?)<\/h3>/is', $part, $heading_match ) ) {
                continue;
            }

            $heading_text = trim( wp_strip_all_tags( $heading_match[1] ) );

            // Match numbered pattern: "1. Text", "1) Text", "Step 1: Text", "Step 1. Text"
            $clean = '';
            if ( preg_match( '/^\d+[.)]\s+(.+)$/', $heading_text, $num_m ) ) {
                $clean = trim( $num_m[1] );
            } elseif ( preg_match( '/^Step\s*\d+[.:]\s*(.+)$/i', $heading_text, $step_m ) ) {
                $clean = trim( $step_m[1] );
            } else {
                continue;
            }

            if ( strlen( $clean ) < 10 ) {
                continue;
            }

            // Replace leading pronouns with the post subject (if known)
            if ( $subject ) {
                $clean = preg_replace( '/^They\'re\s/i', $subject . ' are ', $clean );
                $clean = preg_replace( '/^They\'ve\s/i', $subject . ' have ', $clean );
                $clean = preg_replace( '/^They\s/i',     $subject . ' ',      $clean );
                $clean = preg_replace( '/^Their\s/i',    $subject . '\'s ',   $clean );
            }

            // Ensure it ends with "?"
            $question = ( substr( $clean, -1 ) === '?' ) ? $clean : $clean . '?';

            // Get the answer (content between this H3 and the next tag)
            $answer_html = '';
            if ( isset( $parts[ $i + 1 ] ) ) {
                $next = trim( $parts[ $i + 1 ] );
                if ( ! preg_match( '/^<h[23][^>]*>/i', $next ) ) {
                    $answer_html = $next;
                }
            }

            $answer_text = self::clean_answer_text( $answer_html );
            if ( strlen( $answer_text ) < 20 ) {
                continue;
            }
            if ( strlen( $answer_text ) > 500 ) {
                $answer_text = mb_substr( $answer_text, 0, 497 ) . '...';
            }

            $qa_pairs[] = array(
                'question' => $question,
                'answer'   => $answer_text,
            );

            if ( count( $qa_pairs ) >= $max ) {
                break;
            }
        }

        return $qa_pairs;
    }

    /**
     * Extract Q&A pairs from thematic H3 headings on Spiritual Meaning posts.
     *
     * Applies only when the post title contains "Spiritual Meaning" or
     * "Symbolism". Thematic headings like "Transformation and Rebirth" are
     * converted into natural questions:
     * "What does [topic] mean spiritually in terms of [heading]?"
     *
     * Headings that are already questions (handled by Strategy 1) or numbered
     * (handled by Strategy 2) are skipped.
     *
     * @since 1.6.0
     * @param string       $html Rendered HTML content.
     * @param WP_Post|null $post Post object (required — returns [] without it).
     * @return array Array of ['question' => string, 'answer' => string].
     */
    private static function extract_thematic_pairs( $html, $post = null ) {
        if ( ! $post ) {
            return array();
        }

        // Only applies to spiritual-meaning / symbolism posts
        if ( ! preg_match( '/\b(spiritual\s+meaning|symbolism|symbolize)\b/i', $post->post_title ) ) {
            return array();
        }

        // Extract the topic from the title.
        // "The Spiritual Meaning of Fire"        → "fire"
        // "Bird of Paradise Spiritual Meaning"   → "Bird of Paradise"
        // "Crow Symbolism: What Does It Mean?"   → "Crow"
        $topic = '';
        if ( preg_match( '/spiritual\s+meaning\s+of\s+(.+?)(?:\s*[\|\-–:].*)?$/i', $post->post_title, $m ) ) {
            $topic = trim( $m[1] );
        } elseif ( preg_match( '/^(.+?)\s+spiritual\s+meaning/i', $post->post_title, $m ) ) {
            $topic = trim( $m[1] );
        } elseif ( preg_match( '/^(.+?)\s+symbolism/i', $post->post_title, $m ) ) {
            $topic = trim( $m[1] );
        }

        if ( empty( $topic ) ) {
            return array();
        }

        // Strip common leading articles for natural sentence flow
        $topic = preg_replace( '/^(a|an|the)\s+/i', '', $topic );

        // Split on H3 tags
        $parts = preg_split(
            '/(<h3[^>]*>.*?<\/h3>)/is',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ( ! $parts ) {
            return array();
        }

        // Words that signal a section heading rather than a meaningful theme
        $skip_pattern = '/^(conclusion|takeaway|final|summary|related|introduction|overview|quick|note|tip|warning|caution|read|faq|frequently)/i';

        $qa_pairs = array();
        $max      = 5;

        for ( $i = 0; $i < count( $parts ); $i++ ) {
            $part = trim( $parts[ $i ] );

            if ( ! preg_match( '/<h3[^>]*>(.*?)<\/h3>/is', $part, $heading_match ) ) {
                continue;
            }

            $heading_text = trim( wp_strip_all_tags( $heading_match[1] ) );

            // Skip if already a question (Strategy 1 covers it)
            if ( self::is_question_heading( $heading_text ) ) {
                continue;
            }

            // Skip numbered/stepped headings (Strategy 2 covers them)
            if ( preg_match( '/^\d+[.)]\s|^Step\s*\d+[.:]/i', $heading_text ) ) {
                continue;
            }

            // Skip short, vague, or navigational headings
            if ( strlen( $heading_text ) < 5 || preg_match( $skip_pattern, $heading_text ) ) {
                continue;
            }

            // Generate a natural spiritual-meaning question
            $question = 'What does ' . $topic . ' mean spiritually in terms of ' . lcfirst( $heading_text ) . '?';

            // Get the answer
            $answer_html = '';
            if ( isset( $parts[ $i + 1 ] ) ) {
                $next = trim( $parts[ $i + 1 ] );
                if ( ! preg_match( '/^<h[23][^>]*>/i', $next ) ) {
                    $answer_html = $next;
                }
            }

            $answer_text = self::clean_answer_text( $answer_html );
            if ( strlen( $answer_text ) < 20 ) {
                continue;
            }
            if ( strlen( $answer_text ) > 500 ) {
                $answer_text = mb_substr( $answer_text, 0, 497 ) . '...';
            }

            $qa_pairs[] = array(
                'question' => $question,
                'answer'   => $answer_text,
            );

            if ( count( $qa_pairs ) >= $max ) {
                break;
            }
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
