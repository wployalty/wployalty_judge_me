<?php

namespace Wljm\App\Helpers;

use Wljm\App\Models\Users;

class Woocommerce {
	public static $instance = null;
	protected static $options = [];
	protected static $banned_user = [];
	protected static $products = [];

	public static function hasAdminPrivilege() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		} else {
			return false;
		}
	}

	public static function create_nonce( $action = - 1 ) {
		return wp_create_nonce( $action );
	}

	public static function verify_nonce( $nonce, $action = - 1 ) {
		if ( wp_verify_nonce( $nonce, $action ) ) {
			return true;
		} else {
			return false;
		}
	}

	public static function getCleanHtml( $html ) {
		try {
			$html         = html_entity_decode( $html );
			$html         = preg_replace( '/(<(script|style|iframe)\b[^>]*>).*?(<\/\2>)/is', "$1$3", $html );
			$allowed_html = [
				'br'     => [],
				'strong' => [],
				'span'   => [ 'class' => [] ],
				'div'    => [ 'class' => [] ],
				'p'      => [ 'class' => [] ],
				'b'      => [ 'class' => [] ],
				'i'      => [ 'class' => [] ],
			];

			return wp_kses( $html, $allowed_html );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	function _log( $message ) {
		$options    = $this->getOptions( 'wlr_settings' );
		$debug_mode = is_array( $options ) && isset( $options['debug_mode'] ) && ! empty( $options['debug_mode'] ) ? $options['debug_mode'] : 'no';
		if ( $debug_mode == 'yes' && class_exists( 'WC_Logger' ) ) {
			$logger = new \WC_Logger();
			if ( $this->isMethodExists( $logger, 'add' ) ) {
				$logger->add( 'Loyalty', $message );
			}
		}
	}

	function isMethodExists( $object, $method_name ) {
		if ( is_object( $object ) && method_exists( $object, $method_name ) ) {
			return true;
		}

		return false;
	}

	function getProduct( $product_id ) {
		if ( ! empty( $product_id ) && is_object( $product_id ) ) {
			return $product_id;
		}
		if ( isset( self::$products[ $product_id ] ) ) {
			return self::$products[ $product_id ];
		} elseif ( function_exists( 'wc_get_product' ) ) {
			self::$products[ $product_id ] = apply_filters( 'wlr_rules_get_wc_product', wc_get_product( $product_id ),
				$product_id );

			return self::$products[ $product_id ];
		}

		return false;
	}

	function getOrder( $order = null ) {
		if ( isset( $order ) && is_object( $order ) ) {
			return $order;
		}
		if ( isset( $order ) && is_integer( $order ) && function_exists( 'wc_get_order' ) ) {
			return wc_get_order( $order );
		}

		return null;
	}

	function numberFormatI18n( $point ) {
		if ( $point <= 0 ) {
			return $point;
		}

		return apply_filters( 'wlr_handle_number_format_i18n', number_format_i18n( $point ), $point );
	}

	function getOptions( $key = '', $default = '' ) {
		if ( empty( $key ) ) {
			return [];
		}
		if ( ! isset( self::$options[ $key ] ) || empty( self::$options[ $key ] ) ) {
			self::$options[ $key ] = get_option( $key, $default );
		}

		return self::$options[ $key ];
	}

	public static function getInstance( array $config = [] ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $config );
		}

		return self::$instance;
	}

	function getActionTypes() {
		$earn_helper  = EarnCampaign::getInstance();
		$action_types = [
			'point_for_purchase' => is_admin() ? __( 'Points For Purchase',
				'wp-loyalty-judge-me' ) : sprintf( __( '%s For Purchase', 'wp-loyalty-judge-me' ),
				$earn_helper->getPointLabel( 3 ) ),
		];

		return apply_filters( 'wlr_action_types', $action_types );
	}

	function get_login_user_email() {
		$user       = get_user_by( 'id', get_current_user_id() );
		$user_email = '';
		if ( ! empty( $user ) ) {
			$user_email = $user->user_email;
		}

		return $user_email;
	}

	function isJson( $string ) {
		json_decode( $string );

		return ( json_last_error() == JSON_ERROR_NONE );
	}

	function isBannedUser( $user_email = "" ) {
		if ( empty( $user_email ) ) {
			$user_email = $this->get_login_user_email();
			if ( empty( $user_email ) ) {
				return false;
			}
		}
		if ( isset( static::$banned_user[ $user_email ] ) ) {
			return static::$banned_user[ $user_email ];
		}
		$user_modal = new Users();
		global $wpdb;
		$where = $wpdb->prepare( "user_email = %s AND is_banned_user = %d ", [ $user_email, 1 ] );
		$user  = $user_modal->getWhere( $where, "*", true );

		return static::$banned_user[ $user_email ] = ( ! empty( $user ) && is_object( $user ) && isset( $user->is_banned_user ) );
	}

	function set_referral_code( $referral_code ) {
		if ( isset( WC()->session ) && WC()->session !== null ) {
			WC()->session->set( 'wlr_referral_code', $referral_code );
		}
	}

	function getParentProduct( $product ) {
		if ( $this->productTypeIs( $product, 'variation' ) ) {
			$parent_id = $this->getProductParentId( $product );
			$product   = $this->getProduct( $parent_id );
		}

		return $product;
	}

	function productTypeIs( $product, $type ) {
		if ( $this->isMethodExists( $product, 'is_type' ) ) {
			return $product->is_type( $type );
		}

		return false;
	}

	function getProductParentId( $product ) {
		$parent_id = 0;
		if ( is_int( $product ) ) {
			$product = $this->getProduct( $product );
		}
		if ( $this->isMethodExists( $product, 'get_parent_id' ) ) {
			$parent_id = $product->get_parent_id();
		}

		return apply_filters( 'wlr_rules_get_product_parent_id', $parent_id, $product );
	}

	function getProductAttributes( $product ) {
		if ( is_object( $product ) && $this->isMethodExists( $product, 'get_attributes' ) ) {
			return $product->get_attributes();
		}

		return [];
	}

	function getAttributeName( $attribute ) {
		if ( $this->isMethodExists( $attribute, 'get_name' ) ) {
			return $attribute->get_name();
		}

		return null;
	}

	function getAttributeOption( $attribute ) {
		if ( $this->isMethodExists( $attribute, 'get_options' ) ) {
			return $attribute->get_options();
		}

		return [];
	}

	function getAttributeVariation( $attribute ) {
		if ( $this->isMethodExists( $attribute, 'get_variation' ) ) {
			return $attribute->get_variation();
		}

		return true;
	}

	function getProductCategories( $product ) {
		$categories = [];
		if ( $this->isMethodExists( $product, 'get_category_ids' ) ) {
			if ( $this->productTypeIs( $product, 'variation' ) ) {
				$parent_id = $this->getProductParentId( $product );
				$product   = $this->getProduct( $parent_id );
			}
			$categories = $product->get_category_ids();
		}

		return apply_filters( 'wlr_get_product_categories', $categories, $product );
	}

	function getProductSku( $product ) {
		if ( $this->isMethodExists( $product, 'get_sku' ) ) {
			return $product->get_sku();
		}

		return null;
	}

	function getProductTags( $product ) {
		if ( $this->isMethodExists( $product, 'get_tag_ids' ) ) {
			return $product->get_tag_ids();
		}

		return [];
	}

	function getVariantsOfProducts( $product_ids ) {
		$variants = [];
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				$product = $this->getProduct( $product_id );
				if ( ! empty( $product ) && is_object( $product ) && method_exists( $product, 'is_type' ) ) {
					if ( $product->is_type( [ 'variable', 'variable-subscription' ] ) ) {
						$additional_variants = $this->getProductChildren( $product );
						if ( ! empty( $additional_variants ) && is_array( $additional_variants ) ) {
							$variants = array_merge( $variants, $additional_variants );
						}
					}
				}
			}
		}

		return $variants;
	}

	function getProductChildren( $product ) {
		if ( ! empty( $product ) ) {
			if ( is_object( $product ) && method_exists( $product, 'get_children' ) ) {
				return $product->get_children();
			}
		}

		return [];
	}

	function getProductId( $product ) {
		if ( is_object( $product ) && $this->isMethodExists( $product, 'get_id' ) ) {
			return $product->get_id();
		} elseif ( isset( $product->id ) ) {
			$product_id = $product->id;
			if ( isset( $product->variation_id ) ) {
				$product_id = $product->variation_id;
			}

			return $product_id;
		} else {
			return null;
		}
	}

	function getCartItems( $cart = '' ) {
		if ( isset( $cart ) && is_object( $cart ) && isset( $cart->cart ) ) {
			return $cart->cart->get_cart();
		}
		if ( function_exists( 'WC' ) && isset( WC()->cart ) ) {
			return WC()->cart->get_cart();
		}

		return [];
	}

	function getOrderItems( $order = null ) {
		if ( isset( $order ) && is_object( $order ) ) {
			return $order->get_items( 'line_item' );
		}
		if ( isset( $order ) && is_integer( $order ) && function_exists( 'wc_get_order' ) ) {
			return wc_get_order( $order )->get_items( 'line_item' );
		}

		return [];
	}

	function getCartSubtotal( $cart_data = null ) {
		$cart     = $this->getCart( $cart_data );
		$subtotal = 0;
		if ( ! empty( $cart ) && is_object( $cart ) ) {
			$base_helper = new Base();
			if ( $this->isMethodExists( $cart, 'get_subtotal' ) ) {
				$subtotal = $cart->get_subtotal();
				if ( $base_helper->isIncludingTax() && $this->isMethodExists( $cart, 'get_subtotal_tax' ) ) {
					$subtotal_tax = $cart->get_subtotal_tax();
					$subtotal     += $subtotal_tax;
				}
			} elseif ( isset( $cart->subtotal ) ) {
				$subtotal = $cart->subtotal;
				if ( $base_helper->isIncludingTax() && isset( $cart->subtotal_tax ) ) {
					$subtotal_tax = $cart->subtotal_tax;
					$subtotal     += $subtotal_tax;
				}
			}
		}

		return apply_filters( 'wlr_get_cart_subtotal', $subtotal, $cart_data );
	}

	function getCart( $cart = null ) {
		if ( isset( $cart ) && is_object( $cart ) ) {
			return $cart;
		}
		if ( function_exists( 'WC' ) ) {
			return WC()->cart;
		}

		return null;
	}

	function getOrderSubtotal( $order_data = null ) {
		$order    = $this->getOrder( $order_data );
		$subtotal = 0;
		if ( ! empty( $order ) && is_object( $order ) ) {
			$subtotal_tax = 0;
			if ( $this->isMethodExists( $order, 'get_subtotal' ) ) {
				$subtotal    = $order->get_subtotal();
				$base_helper = new Base();
				if ( $base_helper->isIncludingTax() ) {
					$order_items = $this->getOrderItems( $order );
					foreach ( $order_items as $item ) {
						//$subtotal += $item->get_subtotal();
						$subtotal_tax += wc_round_tax_total( $item->get_subtotal_tax() );
					}
				}
			}
			$subtotal = $subtotal + $subtotal_tax;
		}

		return apply_filters( 'wlr_get_order_subtotal', $subtotal, $order_data );
	}

	function getRole( $user ) {
		if ( ! empty( $user ) && isset( $user->user_login ) ) {
			return $user->roles;
		}

		return [];
	}

	function setSession( $key, $data ) {
		if ( function_exists( 'WC' ) ) {
			if ( isset( WC()->session ) && is_object( WC()->session ) && $this->isMethodExists( WC()->session,
					'set' ) ) {
				WC()->session->set( $key, $data );
			}
		}
	}

}