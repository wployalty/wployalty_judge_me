<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Controllers;

use Wljm\App\Helpers\EarnCampaign;
use Wljm\App\Helpers\Input;
use Wljm\App\Helpers\Util;
use Wljm\App\Helpers\Woocommerce;
use Wljm\App\Premium\Helpers\ProductReview;

defined( 'ABSPATH' ) or die;

class Controller {
	function addMenu() {
		if ( Woocommerce::hasAdminPrivilege() ) {
			add_menu_page( __( 'WPLoyalty: Judge.me', 'wp-loyalty-judge-me' ),
				__( 'WPLoyalty: Judge.me', 'wp-loyalty-judge-me' ), 'manage_woocommerce', WLJM_PLUGIN_SLUG,
				[ $this, 'manageLoyaltyPages' ], 'dashicons-megaphone', 57 );
		}
	}

	function getDomainUrl() {
		$domain      = 'https://';
		$domain_name = constant( 'JGM_SHOP_DOMAIN' );

		return apply_filters( 'wljm_domain_url', trim( $domain . $domain_name, '/' ) );
	}

	function getReviewKeys() {
		return [
			'review/created',
			'review/updated'
		];
	}

	function manageLoyaltyPages() {
		if ( ! Woocommerce::hasAdminPrivilege() ) {
			wp_die( esc_html( __( "Don't have access permission", 'wp-loyalty-judge-me' ) ) );
		}
		//it will automatically add new table column,via auto generate alter query
		if ( Input::get( 'page', null ) == WLJM_PLUGIN_SLUG ) {
			$path             = WLJM_PLUGIN_PATH . 'App/Views/Admin/main.php';
			$review_keys      = $this->getReviewKeys();
			$webhooks         = $this->getWebHooks();
			$main_page_params = [
				'webhook_list'     => $webhooks,
				'review_keys'      => $review_keys,
				'setting_nonce'    => wp_create_nonce( 'wljm-setting-nonce' ),
				//'settings' => get_option('wljm_settings', array()),
				'back_to_apps_url' => admin_url( 'admin.php?' . http_build_query( [ 'page' => WLR_PLUGIN_SLUG ] ) ) . '#/apps',
			];
			/*$main_page_params = array(
				'webhook_list' => array(
					'review/created' => (object)array(
						'id' => '9847708',
						'key' => 'review/created',
						'url' => $this->getDomainUrl() . '/wp-json/wployalty/v1/review/created',
						'failure_count' => 0,
						'last_error_uuid' => '',
						'app_id' => ''
					),
					'review/updated' => (object)array(
						'id' => '9847747',
						'key' => 'review/updated',
						'url' => $this->getDomainUrl() . '/wp-json/wployalty/v1/review/updated',
						'failure_count' => 0,
						'last_error_uuid' => '',
						'app_id' => ''
					)
				),
				'review_keys' => $review_keys,
				'setting_nonce' => wp_create_nonce('wljm-setting-nonce'),
				'settings' => get_option('wljm_settings', array()),
				'back_to_apps_url' => admin_url('admin.php?' . http_build_query(array('page' => WLR_PLUGIN_SLUG))) . '#/apps',
			);*/
			Util::renderTemplate( $path, $main_page_params );
		} else {
			wp_die( esc_html( __( 'Page query params missing...', 'wp-loyalty-judge-me' ) ) );
		}
	}

	function removeAdminNotice() {
		remove_all_actions( 'admin_notices' );
	}

	function adminScripts() {
		if ( ! Woocommerce::hasAdminPrivilege() ) {
			return;
		}
		if ( Input::get( 'page', null ) != WLJM_PLUGIN_SLUG ) {
			return;
		}
		$this->removeAdminNotice();
		wp_enqueue_style( WLJM_PLUGIN_SLUG . '-wljm-admin', WLJM_PLUGIN_URL . 'Assets/Admin/Css/wljm-admin.css',
			[], WLJM_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_style( WLJM_PLUGIN_SLUG . '-wlr-toast', WLJM_PLUGIN_URL . 'Assets/Admin/Css/wlr-toast.css', [],
			WLJM_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_script( WLJM_PLUGIN_SLUG . '-wljm-admin', WLJM_PLUGIN_URL . 'Assets/Admin/Js/wljm-admin.js', [],
			WLJM_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_script( WLJM_PLUGIN_SLUG . '-wlr-toast', WLJM_PLUGIN_URL . 'Assets/Admin/Js/wlr-toast.js', [],
			WLJM_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_style( WLJM_PLUGIN_SLUG . '-wlr-font', WLJM_PLUGIN_SLUG . 'Assets/Site/Css/wlr-fonts.min.css',
			[], WLJM_PLUGIN_SLUG );
		$localize = [
			'home_url'              => get_home_url(),
			'admin_url'             => admin_url(),
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'delete_nonce'          => wp_create_nonce( 'wljm_delete_nonce' ),
			'create_nonce'          => wp_create_nonce( 'wljm_create_nonce' ),
			'deleting_button_label' => __( 'Deleting...', 'wp-loyalty-judge-me' ),
			'delete_button_label'   => __( 'Delete', 'wp-loyalty-judge-me' ),
			'creating_button_label' => __( 'Creating...', 'wp-loyalty-judge-me' ),
			'create_button_label'   => __( 'Create', 'wp-loyalty-judge-me' ),
			'confirm_label'         => __( 'Are you sure?', 'wp-loyalty-judge-me' ),
			'saving_button_label'   => __( 'Saving...', 'wp-loyalty-judge-me' ),
			'saved_button_label'    => __( 'Save', 'wp-loyalty-judge-me' ),
		];
		wp_localize_script( WLJM_PLUGIN_SLUG . '-wljm-admin', 'wljm_localize_data', $localize );
	}

	function menuHideProperties() {
		?>
        <style>
            #toplevel_page_wp-loyalty-judge-me {
                display: none !important;
            }
        </style>
		<?php
	}

	function getWebHooks() {
		$domain         = constant( 'JGM_SHOP_DOMAIN' );
		$token          = get_option( 'judgeme_shop_token' );
		$api_url        = 'https://judge.me/api/v1/';
		$url            = $api_url . 'webhooks';
		$webhook_params = [
			'api_token'   => $token,
			'shop_domain' => $domain,
		];
		$response       = wp_remote_get( $url, [
			'body' => $webhook_params
		] );
		$return         = [];
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$logger        = wc_get_logger();
			$logger->add( 'WPLoyalty', json_encode( $error_message ) );

		} else {
			$review_keys = $this->getReviewKeys();
			$body        = json_decode( $response['body'] );
			if ( is_object( $body ) && isset( $body->webhooks ) && ! empty( $body->webhooks ) ) {
				foreach ( $body->webhooks as $webhook ) {
					if ( in_array( $webhook->key,
							$review_keys ) && ! isset( $return[ $webhook->key ] ) && in_array( $webhook->url, [
							$this->getDomainUrl() . '/wp-json/wployalty/judgeme/v1/review/created',
							$this->getDomainUrl() . '/wp-json/wployalty/judgeme/v1/review/updated'
						] ) ) {
						$return[ $webhook->key ] = $webhook;
					}
				}
			}
		}

		return $return;
	}

	function deleteWebHook() {
		$wljm_nonce = (string) Input::get( 'wljm_nonce', '' );
		$response   = [];
		if ( ! Woocommerce::hasAdminPrivilege() || ! Woocommerce::verify_nonce( $wljm_nonce, 'wljm_delete_nonce' ) ) {
			$response['success'] = false;
			$response['message'] = __( 'Basic validation failed', 'wp-loyalty-judge-me' );
			wp_send_json( $response );
		}
		$review_keys = $this->getReviewKeys();
		$webhook_key = (string) Input::get( 'webhook_key', '' );
		if ( empty( $webhook_key ) || ! in_array( $webhook_key, $review_keys ) ) {
			$response['success'] = false;
			$response['message'] = __( 'Webhook key invalid', 'wp-loyalty-judge-me' );
			wp_send_json( $response );
		}
		$response_code = $this->deleteHook( $webhook_key );
		if ( $response_code >= 200 && $response_code <= 299 ) {
			$response['success'] = true;
			$response['message'] = __( 'Webhook deleted successfully', 'wp-loyalty-judge-me' );
			wp_send_json( $response );
		}
		$response['success'] = false;
		$response['message'] = __( 'Webhook delete failed', 'wp-loyalty-judge-me' );
		wp_send_json( $response );
	}

	function createWebHook() {
		$wljm_nonce = (string) Input::get( 'wljm_nonce', '' );
		$response   = [];
		if ( ! Woocommerce::hasAdminPrivilege() || ! Woocommerce::verify_nonce( $wljm_nonce, 'wljm_create_nonce' ) ) {
			$response['success'] = false;
			$response['message'] = __( 'Basic validation failed', 'wp-loyalty-judge-me' );
			wp_send_json( $response );
		}
		$review_keys = $this->getReviewKeys();
		$webhook_key = (string) Input::get( 'webhook_key', '' );
		if ( empty( $webhook_key ) || ! in_array( $webhook_key, $review_keys ) ) {
			$response['success'] = false;
			$response['message'] = __( 'Webhook key invalid', 'wp-loyalty-judge-me' );
			wp_send_json( $response );
		}
		$response_code = $this->createHook( $webhook_key );
		if ( $response_code >= 200 && $response_code <= 299 ) {
			$response['success'] = true;
			$response['message'] = __( 'Webhook created successfully', 'wp-loyalty-judge-me' );
			wp_send_json( $response );
		}
		$response['success'] = false;
		$response['message'] = __( 'Webhook create failed', 'wp-loyalty-judge-me' );
		wp_send_json( $response );
	}

	protected function deleteHook( $key ) {
		$domain         = constant( 'JGM_SHOP_DOMAIN' );
		$token          = get_option( 'judgeme_shop_token' );
		$api_url        = 'https://judge.me/api/v1/';
		$url            = $api_url . 'webhooks';
		$webhook_params = [
			'api_token'   => $token,
			'shop_domain' => $domain,
			'key'         => $key,
			'url'         => $this->getDomainUrl() . '/wp-json/wployalty/judgeme/v1/' . $key
		];
		$response       = wp_remote_post( $url, [
			'method'  => 'DELETE',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => json_encode( $webhook_params )
		] );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$logger        = wc_get_logger();
			$logger->add( 'WPLoyalty', json_encode( $error_message ) );
			$response_code = 400;
		} else {
			$response_code = $response['response']['code'];
		}

		return $response_code;
	}

	protected function createHook( $key ) {
		/*$review_keys = array(
			'review/created',
			'review/updated'
		);*/
		$domain         = constant( 'JGM_SHOP_DOMAIN' );
		$token          = get_option( 'judgeme_shop_token' );
		$api_url        = 'https://judge.me/api/v1/';
		$url            = $api_url . 'webhooks';
		$webhook_params = [
			'api_token'   => $token,
			'shop_domain' => $domain,
			'webhook'     => [
				'key' => $key,
				'url' => $this->getDomainUrl() . '/wp-json/wployalty/judgeme/v1/' . $key
			]
		];

		$response = wp_remote_post( $url, [
			'method'  => 'POST',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => json_encode( $webhook_params )
		] );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$logger        = wc_get_logger();
			$logger->add( 'WPLoyalty', json_encode( $error_message ) );
			$response_code = 400;
		} else {
			$response_code = $response['response']['code'];
		}

		return $response_code;
	}

	function register_wp_api_endpoints() {
		$namespace = 'wployalty/judgeme/v1';
		register_rest_route( $namespace, '/review/created', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'webhook_review_created_callback' ],
			'permission_callback' => '__return_true', // authentication is handled in the callback
		] );
		register_rest_route( $namespace, '/review/updated', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'webhook_review_updated_callback' ],
			'permission_callback' => '__return_true', // authentication is handled in the callback
		] );
	}

	public function webhook_review_created_callback( $data ) {
		$token           = get_option( 'judgeme_shop_token' );
		$header_hashed   = $data->get_header( 'JUDGEME-HMAC-SHA256' );
		$internal_hashed = hash_hmac( 'sha256', $data->get_body(), $token, false );
		$response        = [];
		if ( hash_equals( $header_hashed, $internal_hashed ) ) {
			$body           = $data->get_json_params();
			$review_id      = $body['review']['id'];
			$reviewer_email = $body['review']['reviewer']['email'];
			$prod_id        = $body['review']['product_external_id'];
			//need to earn point after create review
			if ( $prod_id > 0 && ! empty( $reviewer_email ) && filter_var( $reviewer_email, FILTER_VALIDATE_EMAIL ) ) {
				$woocommerce           = new Woocommerce();
				$product_review_helper = new ProductReview();
				$action_data           = [
					'user_email'         => $reviewer_email,
					'product_id'         => $prod_id,
					'is_calculate_based' => 'product',
					'product'            => $woocommerce->getProduct( $prod_id )
				];
				$product_review_helper->applyEarnProductReview( $action_data );
			}
			$response['success'] = true;
			$response['message'] = __( 'Webhook received successfully', 'wp-loyalty-judge-me' );
		} else {
			$response['success'] = false;
			$response['message'] = __( 'Hash validation failed', 'wp-loyalty-judge-me' );
		}

		return new \WP_REST_Response( $response, 200 );
	}

	public function webhook_review_updated_callback( $data ) {
		$token           = get_option( 'judgeme_shop_token' );
		$header_hashed   = $data->get_header( 'JUDGEME-HMAC-SHA256' );
		$internal_hashed = hash_hmac( 'sha256', $data->get_body(), $token, false );
		$response        = [];
		if ( hash_equals( $header_hashed, $internal_hashed ) ) {
			$body           = $data->get_json_params();
			$review_id      = $body['review']['id'];
			$reviewer_email = $body['review']['reviewer']['email'];
			$prod_id        = $body['review']['product_external_id'];
			//need to earn point after create review
			if ( $prod_id > 0 && ! empty( $reviewer_email ) && filter_var( $reviewer_email, FILTER_VALIDATE_EMAIL ) ) {
				$woocommerce           = new Woocommerce();
				$product_review_helper = new ProductReview();
				$action_data           = [
					'user_email'         => $reviewer_email,
					'product_id'         => $prod_id,
					'is_calculate_based' => 'product',
					'product'            => $woocommerce->getProduct( $prod_id )
				];
				$product_review_helper->applyEarnProductReview( $action_data );
			}
			$response['success'] = true;
			$response['message'] = __( 'Webhook received successfully', 'wp-loyalty-judge-me' );
		} else {
			$response['success'] = false;
			$response['message'] = __( 'Hash validation failed', 'wp-loyalty-judge-me' );
		}

		return new \WP_REST_Response( $response, 200 );
	}

	function displayProductReviewMessage() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$woocommerce = new Woocommerce();
		global $product;
		$post_id = is_object( $product ) && $woocommerce->isMethodExists( $product, 'get_id' ) ? $product->get_id() : 0;
		if ( $post_id <= 0 ) {
			return;
		}
		$earn_campaign    = EarnCampaign::getInstance();
		$cart_action_list = [
			'product_review'
		];
		$extra            = [
			'user_email'         => $woocommerce->get_login_user_email(),
			'product_id'         => $post_id,
			'is_calculate_based' => 'product',
			'product'            => $woocommerce->getProduct( $post_id )
		];
		$reward_list      = $earn_campaign->getActionEarning( $cart_action_list, $extra );
		$message          = '';
		foreach ( $reward_list as $action => $rewards ) {
			foreach ( $rewards as $key => $reward ) {
				if ( isset( $reward['messages'] ) && ! empty( $reward['messages'] ) ) {
					$message .= "<br/>" . $reward['messages'];
				}
			}
		}
		echo $message;
	}
}