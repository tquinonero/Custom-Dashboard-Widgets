<?php
/**
 * Site statistics service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects and caches site-wide statistics (posts, pages, comments, etc.).
 *
 * @package CDW
 */
class CDW_Stats_Service {
	const TRANSIENT_KEY  = 'cdw_stats_cache';
	const CACHE_DURATION = 60;

	/**
	 * Returns site statistics, reading from a short-lived transient cache.
	 *
	 * @return array<string, int>
	 */
	public function get_stats() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$stats = $this->fetch_stats();
		set_transient( self::TRANSIENT_KEY, $stats, self::CACHE_DURATION );

		return $stats;
	}

	/**
	 * Queries the database for all statistics.
	 *
	 * @return array<string, int>
	 */
	private function fetch_stats() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$page_counts = wp_count_posts( 'page' );
		$page_total  = $this->sum_post_statuses( $page_counts );

		$cat_count = wp_count_terms( array( 'taxonomy' => 'category' ) );
		$tag_count = wp_count_terms( array( 'taxonomy' => 'post_tag' ) );

		$stats = array(
			'posts'      => (int) wp_count_posts()->publish,
			'pages'      => $page_total,
			'comments'   => (int) wp_count_comments()->approved,
			'users'      => count_users()['total_users'],
			'media'      => (int) wp_count_posts( 'attachment' )->inherit,
			'categories' => is_wp_error( $cat_count ) ? 0 : (int) $cat_count,
			'tags'       => is_wp_error( $tag_count ) ? 0 : (int) $tag_count,
			'plugins'    => count( get_plugins() ),
			'themes'     => count( wp_get_themes() ),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$stats['products'] = $this->get_woocommerce_products_count();
		}

		return $stats;
	}

	/**
	 * Sums publish, draft, pending and private post counts from a wp_count_posts() result.
	 *
	 * @param object $post_counts Result from wp_count_posts().
	 * @return int
	 */
	private function sum_post_statuses( $post_counts ) {
		$total    = 0;
		$statuses = array( 'publish', 'draft', 'pending', 'private' );

		foreach ( $statuses as $status ) {
			if ( isset( $post_counts->$status ) ) {
				$total += $post_counts->$status;
			}
		}

		return $total;
	}

	/**
	 * Returns the total number of WooCommerce products.
	 *
	 * @return int
	 */
	private function get_woocommerce_products_count() {
		$product_counts = wp_count_posts( 'product' );
		return $this->sum_post_statuses( $product_counts );
	}

	/**
	 * Deletes the statistics transient to force a fresh fetch on next request.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
