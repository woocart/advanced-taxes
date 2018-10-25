<?php
/**
 * Plugin Name: Better Tax Handling
 * Description: Better Tax Handling is a plugin for WooCommerce stores that simplifies the complex part of taxation for B2B and B2C selling.
 * Version:     1.0.0
 * Runtime:     7.2+
 * Author:      WooCart
 * Text Domain: better-tax-handling
 * Domain Path: /langs/
 * Author URI:  www.woocart.com
 */

namespace Niteo\WooCart\BetterTaxHandling {

	/**
	 * Include composer autoload.
	 */
	require_once __DIR__ . '/vendor/autoload.php';

	/**
	 * Constants for the plugin.
	 */
	define( 'Plugin_Url', 	plugin_dir_url( __FILE__ ) );
	define( 'Version',		'1.0.0' );

	/**
	 * BetterTaxHandling class where all the action happens.
	 *
	 * @package WordPress
	 * @subpackage better-tax-handling
	 * @since 1.0.0
	 */
	class BetterTaxHandling {

		/**
		 * Class constructor.
		 */
		public function __construct() {
			// For WP admin.
			new Admin();

			// Tax rates.
			new Rates();

			// Frontend.
			new UserView();
		}

	}

	// Initialize Plugin.
	new BetterTaxHandling();

}
