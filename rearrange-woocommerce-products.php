<?php
/*
**************************************************************************

Plugin Name: Rearrange Woocommerce Products
Plugin URI: https://wordpress.org/plugins/rearrange-woocommerce-products/
Description: a plugin to Rearrange Woocommerce Products listed on the Shop page
Version: 3.0.7
Author: Aslam Doctor
Author URI: https://aslamdoctor.com/	
Developer: Aslam Doctor
Developer URI: https://aslamdoctor.com/
Text Domain:  rwpp
*
* WC requires at least: 4.3
* WC tested up to: 6.0.0
* 
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html

**************************************************************************

Rearrange Woocommerce Products is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

Rearrange Woocommerce Products is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Rearrange Woocommerce Products. If not, see <http://www.gnu.org/licenses/>.

**************************************************************************
*/
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * define reusable paths for plugin globally
 */
define('RWPP_LOCATION', dirname(__FILE__));
define('RWPP_LOCATION_URL', plugins_url('', __FILE__));
define('RWPP_BASENAME', plugin_basename(__FILE__));

if (!class_exists('ReWooProducts')) {
	class ReWooProducts
	{
		public function __construct()
		{
			$this->setup_actions();
		}

		/**
		 * Setup Hooks
		 */
		public function setup_actions()
		{
			register_activation_hook(RWPP_LOCATION, array($this, 'activate'));
			register_deactivation_hook(RWPP_LOCATION, array($this, 'deactivate'));

			add_action('admin_init', array($this, 'check_required_plugin'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
			add_action('admin_menu', array($this, 'register_admin_menus'));

			add_action('wp_ajax_save_all_order', array($this, 'save_all_order_handler'));
			add_action('wp_ajax_save_all_order_by_category', array($this, 'save_all_order_by_category_handler'));

			add_filter('product_cat_row_actions', array($this, 'add_rearrange_link'), 10, 2);

			add_action('save_post_product', array($this, 'new_product_added'), 10, 3);

			add_action('pre_get_posts', array($this, 'sort_products_by_category'), 999);
		}

		/**
		 * Activate plugin callback
		 */
		public static function activate()
		{
		}

		/**
		 * Dectivate plugin callback
		 */
		public static function dectivate()
		{
		}

		/**
		 * Enqueue CSS and JS files
		 */
		public function enqueue_assets($hook)
		{
			if ('product_page_rwpp-page' != $hook) {
				return;
			}

			// Stylesheets
			wp_register_style('rwpp_css', (RWPP_LOCATION_URL . "/dist/css/main.css"), false);
			wp_enqueue_style('rwpp_css');

			// Javascripts
			wp_register_script('rwpp_js', (RWPP_LOCATION_URL . "/dist/js/main.js"), array('jquery', 'jquery-ui-sortable'), false, true);
			wp_enqueue_script('rwpp_js');
		}

		/**
		 * Check if required plugin is available
		 */
		public function check_required_plugin()
		{
			// check if woocommerce is installed
			if (is_admin() && current_user_can('activate_plugins') && !class_exists('WooCommerce')) {
				add_action('admin_notices', array($this, 'plugin_notice'));

				deactivate_plugins(RWPP_BASENAME);

				if (isset($_GET['activate'])) {
					unset($_GET['activate']);
				}
			}
		}

		// show plugin activation notice
		public function plugin_notice()
		{
			_e('<div class="error"><p>Please activate Woocommerce plugin before using <strong>Rearrange Woocommerce Products</strong> plugin.</p></div>', 'rwpp');
		}

		/**
		 * Register Admin Menus
		 */
		public function register_admin_menus()
		{
			if (is_user_logged_in()) {
				$user = wp_get_current_user();
				$role = (array) $user->roles;

				if (in_array('administrator', $role)) {
					$this->add_pages('manage_options');
				} else if (in_array('shop_manager', $role)) {
					$this->add_pages('shop_manager');
				} else if (current_user_can('manage_woocommerce')) {
					$this->add_pages('shop_manager');
				}
			}
		}

		public function add_pages($role)
		{
			add_submenu_page(
				'edit.php?post_type=product',
				__('Rearrange Products', 'rwpp'),
				__('Rearrange Products', 'rwpp'),
				$role,
				'rwpp-page',
				array($this, 'add_pages_callback')
			);
		}

		public function add_pages_callback()
		{
			include "inc/helpers.php";
			include "views/rearrange_all_products.php";
		}

		/**
		 * Save All Products sort order
		 */
		public function save_all_order_handler()
		{
			if (is_admin()) {
				if (isset($_POST['sort_orders'])) {
					$sort_orders = $this->clear_sort_orders($_POST['sort_orders']);

					if (is_array($sort_orders) && count($sort_orders) > 0) {
						global $wpdb;

						$sql_query = "UPDATE {$wpdb->prefix}posts SET menu_order = ( CASE ";
						$fields_in = '';

						foreach ($sort_orders as $new_sort_order => $product_id) {
							$sql_query .= "WHEN ID = '" . $product_id . "' THEN '" . $new_sort_order . "' ";
							$fields_in .= $product_id . ',';
						}

						$fields_in = rtrim($fields_in, ',');

						$sql_query .= "END )";

						//echo $sql_query;
						$wpdb->query($sql_query);

						echo '<div class="notice notice-success is-dismissible">
						<p><strong>' . __('All products are rearranged now.', 'rwpp') . '</strong></p>
						</div>';
					}
				}
			}

			wp_die();
		}

		/* Additional security to escape sort order data */
		public function clear_sort_orders($sort_orders)
		{
			$sort_orders = isset($sort_orders) ?
				array_map('sanitize_text_field', wp_unslash($sort_orders)) :
				array();
			$sort_orders = isset($sort_orders) ?
				array_map('esc_attr', wp_unslash($sort_orders)) :
				array();
			$sort_orders = array_filter($sort_orders, 'is_numeric');

			return $sort_orders;
		}

		/**
		 * Save sort order by category
		 */
		public function save_all_order_by_category_handler()
		{
			if (is_admin()) {
				if (isset($_POST['sort_orders']) && isset($_POST['term_id'])) {
					$sort_orders = $this->clear_sort_orders($_POST['sort_orders']);

					$sort_orders = isset($_POST['sort_orders']) ?
						array_map('sanitize_text_field', wp_unslash($_POST['sort_orders'])) :
						array();

					$term_id = isset($_POST['term_id']) ? sanitize_text_field(wp_unslash($_POST['term_id'])) : '';

					if (is_array($sort_orders) && count($sort_orders) > 0) {
						foreach ($sort_orders as $new_sort_order => $product_id) {
							$meta_key = 'rwpp_sortorder_' . $term_id;
							$meta_value = $new_sort_order;
							update_post_meta($product_id, $meta_key, $meta_value);
						}

						echo '<div class="notice notice-success is-dismissible">
						<p><strong>' . __('All products are rearranged now.', 'rwpp') . '</strong></p>
						</div>';
					}
				}
			}
			wp_die();
		}

		/**
		 * Add "Rearrange" link on Product categories under admin
		 */
		public function add_rearrange_link($actions, $term)
		{
			$url = admin_url('edit.php?post_type=product&page=rwpp-page&current_tab=sortby-categories&term_id=' . $term->term_id);
			$actions['rearrange_link'] = '<a href="' . $url . '" class="rearrange_link">' . __('Rearrange Products') . '</a>';
			return $actions;
		}

		/**
		 * Modify Products loop query to sort by categories
		 */
		public function sort_products_by_category($query)
		{
			if (is_product_category() && $query->is_main_query() && !is_admin()) {
				$term = get_queried_object();
				$term_id = $term->term_id;
				if ($term && $term_id) {
					$meta_key = 'rwpp_sortorder_' . $term_id;
					if ($this->meta_field_exists($meta_key)) {
						$query->set('meta_key', $meta_key);
						$query->set('orderby', 'meta_value_num menu_order title');
						$query->set('order', 'ASC');
					}
				}
			}
		}

		/**
		 * Update products meta
		 */
		public function update_products_meta($term_id)
		{
			global $post;
			$products = new WP_Query(array(
				'post_type'         => array('product'),
				'posts_per_page'    => '-1',
				'post_status'       => array('publish'),
				'tax_query'				 => array(
					array(
						'taxonomy' => 'product_cat',
						'terms' => [$term_id],
						'field' => 'id',
						'operator' => 'IN'
					)
				)
			));

			if ($products->have_posts()) :
				while ($products->have_posts()) : $products->the_post();
					$meta_key = 'rwpp_sortorder_' . $term_id;
					$menu_order = $post->menu_order;
					$sort_order = get_post_meta($post->ID, $meta_key);
					if (!$sort_order) {
						update_post_meta($post->ID, $meta_key, $menu_order);
					}
				endwhile;
			endif;

			wp_reset_postdata();
		}


		/**
		 * When new product created, add its post meta for sort order (default 0)
		 */
		public function new_product_added($post_id, $post, $update)
		{
			$terms = wp_get_post_terms($post_id, 'product_cat');

			if ($terms) {
				foreach ($terms as $term) {
					if (!metadata_exists('post', $post_id, 'rwpp_sortorder_' . $term->term_id)) {
						update_post_meta($post_id, 'rwpp_sortorder_' . $term->term_id, 0);
					}
				}
			}
		}

		/**
		 * Check if meta data exists for specific term in post_meta table
		 */
		public function meta_field_exists($meta_key)
		{
			global $wpdb;
			$result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key='$meta_key'");
			if ($result)
				return true;
			else
				return false;
		}
	}

	$rwpp_plugin_obj = new ReWooProducts();
}
