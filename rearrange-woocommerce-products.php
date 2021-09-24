<?php
/*
**************************************************************************

Plugin Name: Rearrange Woocommerce Products	- V2
Plugin URI: https://wordpress.org/plugins/rearrange-woocommerce-products/
Description: a plugin to Rearrange Woocommerce Products listed on the Shop page
Version: 3.0.0
Author: Aslam Doctor
Author URI: https://aslamdoctor.com/	
Developer: Aslam Doctor
Developer URI: https://aslamdoctor.com/
Text Domain:  rwpp
*
* WC requires at least: 3.7	
* WC tested up to: 4.8.0
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

/**
 * Enqueue CSS and JS files
 */
add_action( 'admin_enqueue_scripts', 'rwpp_enqueue' );
function rwpp_enqueue($hook) {
	/* if ( 'product_page_rwpp-page' != $hook ) {
		return;
	} */

	// Stylesheets
	wp_register_style('rwpp_app_css', (RWPP_LOCATION_URL."/dist/css/main.css"), false);
	wp_enqueue_style('rwpp_app_css');

	// Javascripts
	wp_register_script('rwpp_app_js', (RWPP_LOCATION_URL."/dist/js/main.js"), array(), false, true);
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-sortable');
	wp_enqueue_script('rwpp_app_js');
}