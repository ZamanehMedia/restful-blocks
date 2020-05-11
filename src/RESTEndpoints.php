<?php
namespace Leoloso\BlockMetadata;

class RESTEndpoints {
    /**
     * Return the block's data
     *
     * @param [type] $request
     * @return void
     */
    public static function get_post_blocks($request)
    {
        $post = \get_post($request['post_id']);
        if (!$post) {
            return new \WP_Error('empty_post', 'There is no post with this ID', array('status' => 404));
        }

        $block_data = Data::get_block_data($post->post_content);
        $response = new \WP_REST_Response($block_data);
        $response->set_status(200);
        return $response;
    }

    /**
     * * Return the block's metadata
     *
     * @param [type] $request
     * @return void
     */
    public static function get_post_block_meta($request)
    {
        $post = \get_post($request['post_id']);
        if (!$post) {
            return new \WP_Error('empty_post', 'There is no post with this ID', array('status' => 404));
        }

        $item_metadata = array();

        foreach( $post as $meta_key => $meta_value ) {
            if ($meta_key != 'post_content') {
                $item_metadata[$meta_key] = $meta_value;
            }
        }

        $featured_image_id = \get_post_thumbnail_id($post);
        $featured_image_sizes = array();

        if ($featured_image_id) {
            $small_image_instance = \wp_get_attachment_image_src($featured_image_id, 'medium');
            $small_image = array();
            $small_image['src'] = $small_image_instance[0];
            $small_image['width'] = $small_image_instance[1];
            $small_image['height'] = $small_image_instance[2];
            $featured_image_sizes['small'] = $small_image;

            $medium_image_instance = \wp_get_attachment_image_src($featured_image_id, 'medium_large');
            $medium_image = array();
            $medium_image['src'] = $medium_image_instance[0];
            $medium_image['width'] = $medium_image_instance[1];
            $medium_image['height'] = $medium_image_instance[2];
            $featured_image_sizes['medium'] = $medium_image;

            $large_image_instance = \wp_get_attachment_image_src($featured_image_id, 'full');
            $large_image = array();
            $large_image['src'] = $large_image_instance[0];
            $large_image['width'] = $large_image_instance[1];
            $large_image['height'] = $large_image_instance[2];
            $featured_image_sizes['large'] = $large_image;

  		    	$featured_image = $featured_image_sizes;
            if ($image_alt = \get_post_meta($featured_image_id, '_wp_attachment_image_alt', true)) {
                $featured_image['alt'] = $image_alt;
            }
            if ($caption = \wp_get_attachment_caption($featured_image_id)) {
                $featured_image['caption'] = $caption;
            }
        	  $item_metadata['featured_image'] = $featured_image;
        }

        $item_metadata['categories'] = \get_the_category($item_metadata["ID"]);
		    $item_metadata['post_author'] = \get_the_author_meta('display_name', $post->post_author);

        $block_data = Data::get_block_data($post->post_content);
        $block_metadata = Metadata::get_block_metadata($block_data);
        $item_metadata['blocks'] = $block_metadata;

        $response = new \WP_REST_Response($item_metadata);
        $response->set_status(200);
        return $response;
    }

    public static function get_all_post_block_meta($request)
    {
        $params = $request->get_params();
        $posts_per_page = $params['numberposts'];
        if (!isset($posts_per_page)) {
            $posts_per_page = 3000;
            $params['numberposts'] = $posts_per_page;
        }

        $posts = \get_posts($params);

        $count_query = new \WP_Query();
        $count_query->query( array() );
        $total_posts  = $count_query->found_posts;
        $total_pages = ceil( $total_posts / $posts_per_page );

        $result = array();
        foreach($posts as $post) {
            $item_metadata = array();

            foreach( $post as $meta_key => $meta_value ) {
                if ($meta_key != 'post_content') {
                    $item_metadata[$meta_key] = $meta_value;
                }
            }

            $featured_image_id = \get_post_thumbnail_id($post);
            $featured_image_sizes = array();

            if ($featured_image_id) {
                $small_image_instance = \wp_get_attachment_image_src($featured_image_id, 'medium');
                $small_image = array();
                $small_image['src'] = $small_image_instance[0];
                $small_image['width'] = $small_image_instance[1];
                $small_image['height'] = $small_image_instance[2];
                $featured_image_sizes['small'] = $small_image;

                $medium_image_instance = \wp_get_attachment_image_src($featured_image_id, 'medium_large');
                $medium_image = array();
                $medium_image['src'] = $medium_image_instance[0];
                $medium_image['width'] = $medium_image_instance[1];
                $medium_image['height'] = $medium_image_instance[2];
                $featured_image_sizes['medium'] = $medium_image;

                $large_image_instance = \wp_get_attachment_image_src($featured_image_id, 'full');
                $large_image = array();
                $large_image['src'] = $large_image_instance[0];
                $large_image['width'] = $large_image_instance[1];
                $large_image['height'] = $large_image_instance[2];
                $featured_image_sizes['large'] = $large_image;

                $featured_image = $featured_image_sizes;
                $featured_image['alt'] = \get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
                $featured_image['caption'] = \wp_get_attachment_caption($featured_image_id);
        		    $item_metadata['featured_image'] = $featured_image;
            }

            $item_metadata['categories'] = \get_the_category($item_metadata["ID"]);
            $item_metadata['post_author'] = \get_the_author_meta('display_name', $post->post_author);

            $block_data = Data::get_block_data($post->post_content);
            $block_metadata = Metadata::get_block_metadata($block_data);
            $item_metadata['blocks'] = $block_metadata;
            $result[] = $item_metadata;
        }
        $response = new \WP_REST_Response($result);
        $response->header( 'X-WP-Total', (int) $total_posts );
        $response->header( 'X-WP-TotalPages', (int) $total_pages );
        $response->set_status(200);
        return $response;
    }
}
