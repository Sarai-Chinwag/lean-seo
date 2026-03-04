<?php
/**
 * WP-CLI Commands for Lean SEO
 *
 * @package Lean_SEO
 * @since 1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lean_SEO_CLI {

	/**
	 * Batch-generate meta descriptions for posts missing _lean_seo_description.
	 *
	 * Extracts the first meaningful sentence from post content that directly
	 * addresses the post title, capped at 155 characters. Only processes
	 * published posts that have no custom meta description set.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<number>]
	 * : Number of posts to process per batch.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--limit=<number>]
	 * : Maximum total posts to process. 0 = all.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--post-type=<type>]
	 * : Post type to process.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--dry-run]
	 * : Preview descriptions without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview descriptions for first 10 posts
	 *     wp lean-seo generate-descriptions --dry-run --limit=10
	 *
	 *     # Generate all missing descriptions in batches of 100
	 *     wp lean-seo generate-descriptions --batch-size=100
	 *
	 *     # Process only pages
	 *     wp lean-seo generate-descriptions --post-type=page
	 *
	 * @subcommand generate-descriptions
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate_descriptions( $args, $assoc_args ) {
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 50 );
		$limit      = (int) ( $assoc_args['limit'] ?? 0 );
		$post_type  = $assoc_args['post-type'] ?? 'post';
		$dry_run    = isset( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			WP_CLI::log( '🔍 DRY RUN — no changes will be saved.' );
		}

		// Count posts missing descriptions.
		$total_missing = $this->count_missing( $post_type );
		WP_CLI::log( sprintf( 'Found %d published %s(s) missing meta descriptions.', $total_missing, $post_type ) );

		if ( 0 === $total_missing ) {
			WP_CLI::success( 'All posts already have meta descriptions.' );
			return;
		}

		$to_process = $limit > 0 ? min( $limit, $total_missing ) : $total_missing;
		WP_CLI::log( sprintf( 'Processing %d posts in batches of %d...', $to_process, $batch_size ) );

		$offset    = 0;
		$processed = 0;
		$saved     = 0;
		$skipped   = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating descriptions', $to_process );

		while ( $processed < $to_process ) {
			$current_batch = min( $batch_size, $to_process - $processed );

			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $current_batch,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_lean_seo_description',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_lean_seo_description',
						'value'   => '',
						'compare' => '=',
					),
				),
				'fields'                 => 'all',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );

			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $post ) {
				$description = $this->extract_description( $post );

				if ( empty( $description ) ) {
					$skipped++;
					$processed++;
					$progress->tick();
					WP_CLI::debug( sprintf( '[SKIP] #%d "%s" — could not extract description', $post->ID, $post->post_title ) );
					continue;
				}

				if ( $dry_run ) {
					WP_CLI::log( sprintf(
						"\n  #%d: %s\n  → %s (%d chars)",
						$post->ID,
						$post->post_title,
						$description,
						mb_strlen( $description )
					) );
				} else {
					update_post_meta( $post->ID, '_lean_seo_description', $description );
				}

				$saved++;
				$processed++;
				$progress->tick();
			}

			// Free memory between batches.
			$this->stop_the_insanity();
		}

		$progress->finish();

		WP_CLI::log( '' );
		if ( $dry_run ) {
			WP_CLI::success( sprintf(
				'DRY RUN complete. %d descriptions would be saved, %d skipped.',
				$saved,
				$skipped
			) );
		} else {
			WP_CLI::success( sprintf(
				'Done! %d descriptions saved, %d skipped.',
				$saved,
				$skipped
			) );
		}
	}

	/**
	 * Count published posts missing _lean_seo_description.
	 *
	 * @param string $post_type Post type to count.
	 * @return int
	 */
	private function count_missing( $post_type ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			   ON p.ID = pm.post_id AND pm.meta_key = '_lean_seo_description'
			 WHERE p.post_type = %s
			   AND p.post_status = 'publish'
			   AND (pm.meta_value IS NULL OR pm.meta_value = '')",
			$post_type
		) );
	}

	/**
	 * Extract a meaningful meta description from post content.
	 *
	 * Strategy:
	 * 1. Strip blocks, shortcodes, HTML → plain text
	 * 2. Split into sentences
	 * 3. Skip short filler sentences (< 40 chars)
	 * 4. Take the first substantive sentence(s) that fit in 155 chars
	 * 5. If the result is still too short, append the next sentence
	 *
	 * @param WP_Post $post The post object.
	 * @return string The generated description, or empty if content is too thin.
	 */
	protected function extract_description( $post ) {
		$content = $post->post_content;

		if ( empty( $content ) ) {
			// Fall back to excerpt if available.
			if ( ! empty( $post->post_excerpt ) ) {
				return $this->truncate( wp_strip_all_tags( $post->post_excerpt ), 155 );
			}
			return '';
		}

		// Strip block markup, shortcodes, HTML.
		$text = $this->content_to_text( $content );

		if ( mb_strlen( $text ) < 20 ) {
			return '';
		}

		// Split into sentences.
		$sentences = $this->split_sentences( $text );

		if ( empty( $sentences ) ) {
			return $this->truncate( $text, 155 );
		}

		// Build description from sentences.
		$description = '';

		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );

			// Skip very short sentences (filler like "Let's dive in." or "Here's the thing.").
			if ( mb_strlen( $sentence ) < 40 ) {
				continue;
			}

			// Skip common intro filler patterns.
			if ( $this->is_filler( $sentence ) ) {
				continue;
			}

			if ( empty( $description ) ) {
				// First substantive sentence.
				if ( mb_strlen( $sentence ) <= 155 ) {
					$description = $sentence;
				} else {
					// Single sentence too long — truncate at word boundary.
					$description = $this->truncate( $sentence, 155 );
					break;
				}
			} else {
				// Try to append next sentence if there's room.
				$combined = $description . ' ' . $sentence;
				if ( mb_strlen( $combined ) <= 155 ) {
					$description = $combined;
				} else {
					break;
				}
			}
		}

		// If we still have nothing, fall back to trimmed content.
		if ( empty( $description ) ) {
			$description = $this->truncate( $text, 155 );
		}

		return $description;
	}

	/**
	 * Convert post content to plain text.
	 *
	 * @param string $content Raw post_content.
	 * @return string Plain text.
	 */
	private function content_to_text( $content ) {
		// Remove block comments (<!-- wp:xxx --> and <!-- /wp:xxx -->).
		$text = preg_replace( '/<!--\s*\/?wp:\S.*?-->/s', '', $content );

		// Strip shortcodes.
		$text = strip_shortcodes( $text );

		// Strip HTML tags.
		$text = wp_strip_all_tags( $text );

		// Decode HTML entities.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Split text into sentences.
	 *
	 * Handles common abbreviations and decimal numbers to avoid false splits.
	 *
	 * @param string $text Plain text.
	 * @return array Array of sentence strings.
	 */
	private function split_sentences( $text ) {
		// Split on sentence-ending punctuation followed by a space and uppercase letter
		// or end of string. This avoids splitting on abbreviations like "Dr." or "U.S."
		$parts = preg_split(
			'/(?<=[.!?])\s+(?=[A-Z"\x{201C}])/u',
			$text,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

		return $parts ?: array();
	}

	/**
	 * Check if a sentence is common intro filler.
	 *
	 * @param string $sentence The sentence to check.
	 * @return bool True if filler.
	 */
	private function is_filler( $sentence ) {
		$filler_patterns = array(
			'/^(have you ever|if you\'ve ever|you might|you may|you\'ve probably)/i',
			'/^(imagine|picture this|let\'s|let us|we\'ve all)/i',
			'/^(in this (article|post|guide|blog))/i',
			'/^(today,?\s+(we|I|we\'re|I\'m)\s+(will|are|\'re|\'m)\s+(going to|gonna|exploring|looking|diving))/i',
			'/let\'s (dive|get|jump|explore|find out|take a look)/i',
			'/here\'s (the thing|what|the deal)/i',
			'/ever wonder(ed)?/i',
			'/^(hey|hello|hi|greetings|welcome)\b/i',
			'/sarai\s*chinwag\s*here/i',
			'/^(do you|are you|does the|have you)\b.*\?\s*$/i',
			'/^(so,?\s+you\'ve|well,?\s+you\'ve|okay,?\s+so)/i',
			'/^(ever (watched|found|noticed|seen|looked))\b/i',
		);

		foreach ( $filler_patterns as $pattern ) {
			if ( preg_match( $pattern, $sentence ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Truncate text to max length at a word boundary.
	 *
	 * @param string $text      Text to truncate.
	 * @param int    $max_chars Maximum character count.
	 * @return string Truncated text with trailing ellipsis if cut.
	 */
	private function truncate( $text, $max_chars = 155 ) {
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $max_chars ) {
			return $text;
		}

		// Reserve 3 chars for ellipsis.
		$cut = mb_substr( $text, 0, $max_chars - 3 );

		// Cut at last word boundary.
		$last_space = mb_strrpos( $cut, ' ' );
		if ( $last_space !== false && $last_space > $max_chars * 0.5 ) {
			$cut = mb_substr( $cut, 0, $last_space );
		}

		return $cut . '...';
	}

	/**
	 * Free memory between batches.
	 *
	 * Clears WP object cache and runs garbage collection.
	 */
	private function stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array();

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = array();
			$wp_object_cache->stats          = array();
			$wp_object_cache->memcache_debug = array();
			$wp_object_cache->cache          = array();

			if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset();
			}
		}
	}
}
