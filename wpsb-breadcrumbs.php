<?php

/*
Plugin Name: WPsB Breadcrumbs
Description: Create array data for breadcrumbs.
Author: @people_of_the_s
Version: 0.1.0
License: GPL2

WPsB Breadcrumbs is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

WPsB Breadcrumbs is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WPsB Breadcrumbs. If not, see https://github.com/p-o-t-s/wpsb-breadcrumbs
*/

class WPsB_Breadcrumbs
{
    private static $instance = null;
    private $object = null;
    private $breadcrumbs_options = [];
    private $breadcrumbs_array = [];

    private function __construct()
    {
    }

    public static function get_instance()
    {
        $class = get_called_class();

        if (self::$instance === null) {
            self::$instance = new $class;
        }

        return self::$instance;
    }

    public final function __clone()
    {
        throw new \Exception('Clone is not allowed, ' . get_class($this));
    }

    public function register()
    {
        add_action('plugins_loaded', array($this, 'plugins_loaded_action'));

    }

    public function plugins_loaded_action()
    {

    }

    public function get_breadcrumbs($options)
    {
        $this->object = get_queried_object();

        $defaults = array(
            'home' => array(
                'type' => 'home',
                'object' => null,
                'label' => get_bloginfo('name'),
                'url' => get_home_url('/')
            ),
            '404' => array(
                'type' => '404',
                'object' => null,
                'label' => '404 Not Found',
                'url' => false,
            ),
        );
        // set user defined options
        $this->breadcrumbs_options = array_merge($defaults, $options);

        /**
         * set breadcrumbs data.
         */
        $this->assign_home_data();

        switch (true) {
            case (is_404()):
                $this->assign_404_data();
                break;
            case (is_singular() || is_page()):
                $this->assign_singular_data();
                break;
            case (is_post_type_archive()):
                $this->assign_post_type_archive_data();
                break;
            case (is_category()):
                $this->assign_category_data();
                break;
            case (is_date()):
                $this->assign_date_archive_data();
                break;
            default:
                break;
        }

        return $this->breadcrumbs_array;
    }

    /**
     * @return array
     */
    private function assign_home_data()
    {
        $this->breadcrumbs_array[] = $this->breadcrumbs_options['home'];
    }

    /**
     * @return array
     */
    private function assign_404_data()
    {
        $this->breadcrumbs_array[] = $this->breadcrumbs_options['404'];
    }

    private function assign_date_archive_data()
    {
        if (is_year()) {
            $year = get_query_var('year');
            $this->breadcrumbs_array[] = [
                'type' => 'year',
                'object' => [],
                'label' => $year . '年',
                'url' => get_year_link($year),
            ];
        }
        // @todo monthly, daily
    }

    private function assign_category_data()
    {
        $obj = $this->object;

        if ($obj->parent_id !== 0) {
            $this->breadcrumbs_array = array_merge(
                $this->breadcrumbs_array,
                $this->get_term_ancestors($obj->term_id, 'category')
            );
        }

        $this->breadcrumbs_array[] = [
            'type' => 'category',
            'object' => $obj,
            'label' => $obj->name,
            'url' => get_permalink($obj->term_id),
        ];
    }

    private function assign_post_type_archive_data()
    {
        $post_type_object = $this->object; // @todo

        // has_archive = true
        if ($post_type_object->has_archive) {
            $this->breadcrumbs_array[] = [
                'type' => $post_type_object->post_type,
                'object' => $post_type_object,
                'label' => $post_type_object->label,
                'url' => get_post_type_archive_link($post_type_object->post_type),
            ];
        }
    }

    /**
     * @return array
     */
    private function assign_singular_data($post = null)
    {
        global $post;

        $output = array();
        $post_type = get_post_type($post->ID);
        $post_type_object = get_post_type_object($post_type);


        // has_archive = true
        if ($post_type_object->has_archive) {
            $this->breadcrumbs_array[] = [
                'type' => $post_type,
                'object' => $post_type_object,
                'label' => $post_type_object->label,
                'url' => get_post_type_archive_link($post_type),
            ];
        }

        // Category
        if (has_category()) {
            $cat = get_the_category($post)[0];

            if ($cat->parent_id !== 0) {
                $this->breadcrumbs_array = array_merge(
                    $this->breadcrumbs_array,
                    $this->get_term_ancestors($cat->term_id, 'category')
                );
            }

            $this->breadcrumbs_array[] = [
                'type' => 'category',
                'object' => $cat,
                'label' => $cat->name,
                'url' => get_term_link($cat),
            ];
        }


        // hierarchical = true
        // @todo will make common
        if (is_post_type_hierarchical($post_type) && 0 !== wp_get_post_parent_id($post->ID)) {
            $ancestors_post_id = array_reverse(get_post_ancestors($post));

            foreach ($ancestors_post_id as $ancestor_post_id) {
                $this->breadcrumbs_array[] = array(
                    'type' => $post_type,
                    'object' => get_post($ancestor_post_id),
                    'label' => get_the_title($ancestor_post_id),
                    'url' => get_permalink($ancestor_post_id),
                );
            }
        }

        $this->breadcrumbs_array[] = array(
            'type' => $post_type,
            'object' => $post,
            'label' => get_the_title($post->ID),
            'url' => get_permalink($post->ID),
        );

        return $output;

    }

    /**
     * @see https://codex.wordpress.org/Function_Reference/get_ancestors
     */
    private static function get_term_ancestors($object_id, $taxonomy)
    {
        $ancestors_ids = get_ancestors($object_id, $taxonomy);

        if (empty($ancestors_ids)) {
            return [];
        }

        $ancestors_ids = array_reverse($ancestors_ids);

        $formatted_terms = [];
        foreach ($ancestors_ids as $ancestors_id) {
            $term = get_term($ancestors_id, $taxonomy);

            $formatted_terms[] = [
                'type' => $taxonomy,
                'object' => $term,
                'label' => $term->name,
                'url' => get_term_link($term)
            ];
        }

        return $formatted_terms;
    }
}

$WPsB_Breadcrumbs = WPsB_Breadcrumbs::get_instance();
$WPsB_Breadcrumbs->register();

if (!function_exists('wpsb_get_breadcrumbs')) {
    function wpsb_get_breadcrumbs($options = [])
    {
        $WPsB_Breadcrumbs = WPsB_Breadcrumbs::get_instance();

        return $WPsB_Breadcrumbs->get_breadcrumbs($options);
    }
}
