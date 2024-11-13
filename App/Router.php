<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App;

use Wljm\App\Controllers\Controller;

defined( 'ABSPATH' ) or die;

class Router {
	private static $controller;

	function init() {
		self::$controller = empty( self::$controller ) ? new Controller() : self::$controller;
		if ( is_admin() ) {
			add_action( 'admin_menu', [ self::$controller, 'addMenu' ] );
			add_action( 'network_admin_menu', [ self::$controller, 'addMenu' ] );
			add_action( 'admin_enqueue_scripts', [ self::$controller, 'adminScripts' ], 100 );
			add_action( 'admin_footer', [ self::$controller, 'menuHideProperties' ] );
			add_action( 'wp_ajax_wljm_save_settings', [ self::$controller, 'saveSettings' ] );
			add_action( 'wp_ajax_wljm_webhook_delete', [ self::$controller, 'deleteWebHook' ] );
			add_action( 'wp_ajax_wljm_webhook_create', [ self::$controller, 'createWebHook' ] );
		} /*else {
            add_action('wp_enqueue_scripts', array(self::$controller, 'addFrontEndScripts'));
        }*/
		add_action( 'rest_api_init', [ self::$controller, 'register_wp_api_endpoints' ] );
		$hide_widget = get_option( 'judgeme_option_hide_widget' );
		if ( ! $hide_widget ) {
			add_action( 'woocommerce_after_single_product_summary',
				[ self::$controller, 'displayProductReviewMessage' ], 13 );
		}
	}
}