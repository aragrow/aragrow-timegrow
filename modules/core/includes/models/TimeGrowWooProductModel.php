<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowWooProductModel {

    private $table_name;
    private $wpdb;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'posts'; // Make sure this matches your table name
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

    }
    
    /**
     * Select expenses by ID or array of IDs.
     *
     * @param int|array $ids Single ID or array of IDs.
     * @return array|object|null Array of expense objects or null if no results.
     */
    public function select() {
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
    
            $args = [
            'post_type'      => 'product', // Change this to your custom post type (e.g., 'product', 'event')
            'posts_per_page' => -1,        // Number of posts to return
            'post_status' => 'publish',
            'fields'         => 'ids',   //// Return only post IDs for better performance
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => 'timegrow',
                ],
            ],
        ];
        
        $query = new WP_Query($args);
        $posts = [];

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $posts[] = (object) [
                    'ID'          => $post_id,
                    'post_title'  => get_the_title( $post_id ),
                ];
            }
        }

        return (object) $posts;

    }
    
}