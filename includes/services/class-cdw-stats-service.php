<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CDW_Stats_Service {
    const TRANSIENT_KEY = 'cdw_stats_cache';
    const CACHE_DURATION = 60;

    public function get_stats() {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        $stats = $this->fetch_stats();
        set_transient( self::TRANSIENT_KEY, $stats, self::CACHE_DURATION );

        return $stats;
    }

    private function fetch_stats() {
        $page_counts   = wp_count_posts( 'page' );
        $page_total    = $this->sum_post_statuses( $page_counts );

        $stats = array(
            'posts'      => wp_count_posts()->publish,
            'pages'      => $page_total,
            'comments'   => wp_count_comments()->approved,
            'users'      => count_users()['total_users'],
            'media'      => wp_count_posts( 'attachment' )->inherit,
            'categories' => wp_count_terms( array( 'taxonomy' => 'category' ) ),
            'tags'       => wp_count_terms( array( 'taxonomy' => 'post_tag' ) ),
            'plugins'    => count( get_plugins() ),
            'themes'     => count( wp_get_themes() ),
        );

        if ( class_exists( 'WooCommerce' ) ) {
            $stats['products'] = $this->get_woocommerce_products_count();
        }

        return $stats;
    }

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

    private function get_woocommerce_products_count() {
        $product_counts   = wp_count_posts( 'product' );
        return $this->sum_post_statuses( $product_counts );
    }

    public function clear_cache() {
        delete_transient( self::TRANSIENT_KEY );
    }
}
