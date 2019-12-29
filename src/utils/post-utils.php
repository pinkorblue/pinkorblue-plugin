<?php
namespace Robera\AB;

if (class_exists('PostUtils')) {
    return;
}

class PostUtils
{

    public static function createDuplicatedPost($post_id, $name)
    {
        global $wpdb;

        $post = get_post($post_id);

        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;

        if (isset($post) && $post != null) {
            $args = array(
                'comment_status' => $post->comment_status,
                'ping_status'    => $post->ping_status,
                'post_author'    => $new_post_author,
                'post_content'   => $post->post_content,
                'post_excerpt'   => $post->post_excerpt,
                'post_name'      => $name,
                'post_parent'    => $post->post_parent,
                'post_password'  => $post->post_password,
                'post_status'    => RoberaABPlugin::$AB_POST_STATUS,
                'post_title'     => $post->post_title,
                'post_type'      => $post->post_type,
                'to_ping'        => $post->to_ping,
                'menu_order'     => $post->menu_order
            );

            $new_post_id = wp_insert_post($args, true);

            $taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
                wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
            }

            $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
            if (count($post_meta_infos)!=0) {
                $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
                foreach ($post_meta_infos as $meta_info) {
                    $meta_key = $meta_info->meta_key;
                    if ($meta_key == '_wp_old_slug') {
                        continue;
                    }
                    $meta_value = addslashes($meta_info->meta_value);
                    $sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
                }
                $sql_query.= implode(" UNION ALL ", $sql_query_sel);
                $wpdb->query($sql_query);
            }
            update_post_meta($new_post_id, RoberaABPlugin::$IS_VARIANT_POST_META_KEY, true);

            return $new_post_id;
        } else {
            throw new \Exception(sprintf(esc_html__('Post creation failed, could not find original post: %1$s', 'robera-ab-test'), $post_id));
        }
    }

    public static function createEmptyPost($post_id, $name)
    {
        $post = get_post($post_id);

        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;
        $args = array(
            'post_status'    => RoberaABPlugin::$AB_POST_STATUS,
            'post_author'    => $new_post_author,
            'post_type'      => $post->post_type,
            'post_name'      => $name
        );
        $new_post_id = wp_insert_post($args);
        update_post_meta($new_post_id, RoberaABPlugin::$IS_VARIANT_POST_META_KEY, true);
        return $new_post_id;
    }

    public static function isGutenburgForPostType($post_type)
    {
        if (!post_type_exists($post_type)) {
            return false;
        }

        if (!post_type_supports($post_type, 'editor')) {
            return false;
        }

        $post_type_object = get_post_type_object($post_type);
        if ($post_type_object && ! $post_type_object->show_in_rest) {
            return false;
        }

        /**
         * Filter whether a post is able to be edited in the block editor.
         *
         * @since 5.0.0
         *
         * @param bool   $use_block_editor  Whether the post type can be edited or not. Default true.
         * @param string $post_type         The post type being checked.
         */
        return apply_filters('use_block_editor_for_post_type', true, $post_type);
    }
}
