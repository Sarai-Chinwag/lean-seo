<?php
/**
 * IndexNow integration for Lean SEO.
 *
 * Automatically notifies search engines (Bing, Yandex, etc.) when content
 * is published or updated via the IndexNow protocol.
 *
 * @package LeanSEO
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lean_SEO_IndexNow {

	/**
	 * Option key for the IndexNow API key.
	 */
	const OPTION_KEY = 'lean_seo_indexnow_key';

	/**
	 * IndexNow API endpoint.
	 */
	const API_URL = 'https://api.indexnow.org/indexnow';

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'save_post', array( __CLASS__, 'on_post_save' ), 10, 3 );
		add_action( 'lean_seo_indexnow_submit', array( __CLASS__, 'submit_urls' ) );
	}

	/**
	 * Get the configured API key.
	 *
	 * Falls back to legacy theme option if present.
	 *
	 * @return string API key or empty string.
	 */
	public static function get_api_key(): string {
		$key = get_option( self::OPTION_KEY, '' );

		// Fallback: migrate from legacy theme option.
		if ( empty( $key ) ) {
			$legacy_key = get_option( 'sarai_chinwag_indexnow_key', '' );
			if ( ! empty( $legacy_key ) ) {
				update_option( self::OPTION_KEY, $legacy_key );
				$key = $legacy_key;
			}
		}

		return $key;
	}

	/**
	 * Handle post save — submit URL to IndexNow.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public static function on_post_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_url = get_permalink( $post_id );
		if ( ! $post_url ) {
			return;
		}

		self::submit_urls( array( $post_url ) );
	}

	/**
	 * Submit URLs to IndexNow.
	 *
	 * @param array $urls List of URLs to submit.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function submit_urls( array $urls ) {
		$key = self::get_api_key();
		if ( empty( $key ) ) {
			return new \WP_Error( 'indexnow_no_key', __( 'IndexNow API key not configured.', 'lean-seo' ) );
		}

		$host         = wp_parse_url( home_url(), PHP_URL_HOST );
		$key_location = home_url( '/' . $key . '.txt' );

		$data = array(
			'host'        => $host,
			'key'         => $key,
			'keyLocation' => $key_location,
			'urlList'     => array_values( $urls ),
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new \WP_Error(
			'indexnow_api_error',
			sprintf( __( 'IndexNow API returned %d', 'lean-seo' ), $code )
		);
	}
}
