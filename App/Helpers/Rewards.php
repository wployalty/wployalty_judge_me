<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Helpers;

defined( 'ABSPATH' ) or die;

use WC_Coupon;
use WP_Error;
use Wljm\App\Models\UserRewards;
use Exception;

class Rewards extends EarnCampaign {
	public static $instance = null;
	public static $user_rewards;

	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	function createCartUserReward( $user_reward, $user_email ) {
		if ( empty( $user_email ) || ! is_object( $user_reward ) || ! isset( $user_reward->id ) || empty( $user_reward->id ) ) {
			return;
		}
		$woocommerce_helper = Woocommerce::getInstance();
		//No need to enter data in UserReward table
		if ( isset( $user_reward->discount_code ) && ! empty( $user_reward->discount_code ) ) {
			// If discount code available apply to cart
			$woocommerce_helper->setSession( 'wlr_discount_code', $user_reward->discount_code );
			$cart = $woocommerce_helper->getCart();
			if ( empty( $cart->get_cart() ) && apply_filters( 'wlr_show_coupon_will_apply_message', true,
					$user_reward ) ) {
				wc_add_notice( __( 'Coupon will apply, when cart have items', 'wp-loyalty-judge-me' ) );
			}
			//WC()->cart->apply_coupon($user_reward->discount_code);
		} else {
			$conditions      = ( isset( $user_reward->conditions ) && ! empty( $user_reward->conditions ) && $woocommerce_helper->isJson( $user_reward->conditions ) ) ? json_decode( $user_reward->conditions ) : [];
			$condition_type  = ( isset( $user_reward->condition_relationship ) && ! empty( $user_reward->condition_relationship ) ) ? $user_reward->condition_relationship : 'and';
			$condition_data  = $this->convertCouponData( $conditions, $condition_type );
			$discount_amount = $user_reward->discount_value;
			if ( isset( $condition_data['currency'] ) && ! empty( $condition_data['currency'] ) && ( isset( $user_reward->discount_type ) && $user_reward->discount_type != 'percent' ) ) {
				$discount_amount = apply_filters( 'wlr_convert_to_default_currency', $discount_amount,
					$condition_data['currency'] );
			}
			$user_reward_model = new UserRewards();
			$options           = $woocommerce_helper->getOptions( 'wlr_settings' );

			$data = [
				'code'                         => $this->generateRewardCode(),
				'type'                         => $user_reward->discount_type,
				'amount'                       => $discount_amount,
				'individual_use'               => is_array( $options ) && isset( $options['individual_use_coupon'] ) && $options['individual_use_coupon'] == 'yes',
				'product_ids'                  => isset( $condition_data['product_ids'] ) && ! empty( $condition_data['product_ids'] ) ? $condition_data['product_ids'] : [],
				'exclude_product_ids'          => isset( $condition_data['exclude_product_ids'] ) && ! empty( $condition_data['exclude_product_ids'] ) ? $condition_data['exclude_product_ids'] : [],
				'usage_limit'                  => isset( $user_reward->usage_limits ) && ! empty( $user_reward->usage_limits ) ? (int) $user_reward->usage_limits : 1,
				'usage_limit_per_user'         => isset( $user_reward->usage_limits ) && ! empty( $user_reward->usage_limits ) ? (int) $user_reward->usage_limits : 1,
				'limit_usage_to_x_items'       => '',
				'usage_count'                  => '',
				'expiry_date'                  => isset( $user_reward->end_at ) && ! empty( $user_reward->end_at ) ? date( 'Y-m-d H:i:s',
					$user_reward->end_at ) : '',
				'enable_free_shipping'         => false,
				'product_category_ids'         => isset( $condition_data['product_category_ids'] ) && ! empty( $condition_data['product_category_ids'] ) ? $condition_data['product_category_ids'] : [],
				'exclude_product_category_ids' => isset( $condition_data['exclude_product_category_ids'] ) && ! empty( $condition_data['exclude_product_category_ids'] ) ? $condition_data['exclude_product_category_ids'] : [],
				'exclude_sale_items'           => isset( $condition_data['exclude_sale_items'] ) && $condition_data['exclude_sale_items'] ? $condition_data['exclude_sale_items'] : false,
				'minimum_amount'               => isset( $condition_data['minimum_amount'] ) && ! empty( $condition_data['minimum_amount'] ) ? $condition_data['minimum_amount'] : '',
				'maximum_amount'               => isset( $condition_data['maximum_amount'] ) && ! empty( $condition_data['maximum_amount'] ) ? $condition_data['maximum_amount'] : '',
				'customer_emails'              => [
					$user_email
				],
				'description'                  => $user_reward->name
			];
			if ( isset( $user_reward->discount_type ) && ! empty( $user_reward->discount_type ) && in_array( $user_reward->discount_type,
					[
						'free_shipping',
						'free_product'
					] ) ) {
				$data['type']   = 'fixed_cart';
				$data['amount'] = 0;
				if ( $user_reward->discount_type === 'free_shipping' ) {
					$data['enable_free_shipping'] = true;
				}
			} elseif ( isset( $user_reward->discount_type ) && ! empty( $user_reward->discount_type ) && $user_reward->discount_type == 'points_conversion' ) {
				$data['type'] = $user_reward->coupon_type == 'percent' ? 'percent' : 'fixed_cart';
			}
			$data['action_type']    = isset( $user_reward->action_type ) && ! empty( $user_reward->action_type ) ? $user_reward->action_type : '';
			$data['reward_id']      = isset( $user_reward->reward_id ) && ! empty( $user_reward->reward_id ) ? $user_reward->reward_id : 0;
			$data['user_reward_id'] = $user_reward->id;
			$data['campaign_id']    = isset( $user_reward->campaign_id ) && ! empty( $user_reward->campaign_id ) ? $user_reward->campaign_id : 0;
			$data['display_name']   = isset( $user_reward->display_name ) && ! empty( $user_reward->display_name ) ? $user_reward->display_name : '';
			// else create woccommerce coupon and apply to cart
			$data   = apply_filters( 'wlr_before_create_coupon_data', $data, $user_reward );
			$coupon = $this->create_coupon( $data );
			if ( ! is_wp_error( $coupon ) ) {
				$coupon_code = $coupon->get_code();
				// Update UserReward table
				$updateData = [
					'discount_code' => $coupon_code,
					'discount_id'   => $coupon->get_id(),
					'status'        => 'active'
				];
				$where      = [ 'id' => $user_reward->id ];
				try {
					$status = $user_reward_model->updateRow( $updateData, $where );
					if ( $status >= 0 ) {
						$earn_campaign = new EarnCampaign();
						$customer_note = sprintf( __( '%s coupon created for %s from %s reward',
							'wp-loyalty-judge-me' ), $coupon_code, $user_reward->email, $user_reward->display_name );
						$log_data      = [
							'user_email'          => sanitize_email( $user_email ),
							'action_type'         => $user_reward->action_type,
							'reward_id'           => $user_reward->reward_id,
							'user_reward_id'      => $user_reward->id,
							'campaign_id'         => $user_reward->campaign_id,
							'note'                => $customer_note,
							'customer_note'       => $customer_note,
							'created_at'          => strtotime( date( 'Y-m-d H:i:s' ) ),
							'modified_at'         => 0,
							'discount_code'       => isset( $updateData['discount_code'] ) && ! empty( $updateData['discount_code'] ) ? $updateData['discount_code'] : null,
							'action_process_type' => 'coupon_generated',
							'reward_display_name' => $user_reward->display_name
						];

						if ( $user_reward->reward_type == 'redeem_point' ) {
							//update User table require_point
							$action_data = [
								'user_email'          => sanitize_email( $user_email ),
								'points'              => (int) $user_reward->require_point,
								'action_type'         => 'redeem_point',
								'action_process_type' => 'coupon_generated',
								'campaign_id'         => $user_reward->campaign_id,
								'customer_note'       => $customer_note,
								'note'                => $customer_note,
								'reward_id'           => $user_reward->reward_id,
								'user_reward_id'      => $user_reward->id,
								'reward_display_name' => $user_reward->display_name,
								'required_points'     => (int) $user_reward->require_point,
								'discount_code'       => isset( $updateData['discount_code'] ) && ! empty( $updateData['discount_code'] ) ? $updateData['discount_code'] : ''
							];
							$this->addExtraPointAction( 'redeem_point', $action_data['points'], $action_data, 'debit',
								true );
						} else {
							$earn_campaign->add_note( $log_data );
						}
						//Apply to cart
						//WC()->cart->apply_coupon($coupon_code);
						$woocommerce_helper->setSession( 'wlr_discount_code', $coupon_code );
						do_action( 'wlr_after_coupon_code_generation', $coupon_code, $user_reward );
					}
				} catch ( Exception $e ) {
				}
			} elseif ( isset( $user_reward->reward_type ) && $user_reward->id > 0 && $user_reward->reward_type == 'redeem_point' ) {
				$where = [ 'id' => $user_reward->id ];
				$user_reward_model->deleteRow( $where );
			}
		}
	}

	protected function convertCouponData( $conditions, $condition_relationship = 'and' ) {
		$data = [
			'minimum_amount'               => '',
			'maximum_amount'               => '',
			'product_ids'                  => [],
			'exclude_product_ids'          => [],
			'product_category_ids'         => [],
			'exclude_product_category_ids' => [],
			'exclude_sale_items'           => false
		];
		if ( empty( $conditions ) ) {
			return $data;
		}
		$available_conditions                    = ( ! empty( $this->available_conditions ) ) ? $this->available_conditions : $this->getAvailableConditions();
		$min_condition_list                      = [];
		$max_condition_list                      = [];
		$product_ids_conditions                  = [];
		$exclude_product_ids_conditions          = [];
		$product_category_ids_conditions         = [];
		$exclude_product_category_ids_conditions = [];
		$exclude_sale_items_conditions           = [];
		$currency_conditions                     = [];
		if ( $condition_relationship == 'and' ) {
			$min_condition_list                      = apply_filters( 'wlr_minimum_amount_conditions',
				[ 'cart_subtotal' ], $available_conditions );
			$max_condition_list                      = apply_filters( 'wlr_maximum_amount_conditions',
				[ 'cart_subtotal' ], $available_conditions );
			$product_ids_conditions                  = apply_filters( 'wlr_product_ids_conditions', [ 'products' ],
				$available_conditions );
			$exclude_product_ids_conditions          = apply_filters( 'wlr_exclude_product_ids_conditions',
				[ 'products' ], $available_conditions );
			$product_category_ids_conditions         = apply_filters( 'wlr_product_category_ids_conditions',
				[ 'product_category' ], $available_conditions );
			$exclude_product_category_ids_conditions = apply_filters( 'wlr_exclude_product_category_ids_conditions',
				[ 'product_category' ], $available_conditions );
			$exclude_sale_items_conditions           = apply_filters( 'wlr_exclude_sale_items_conditions',
				[ 'product_onsale' ], $available_conditions );
			$currency_conditions                     = [ 'currency' ];
		}

		//exclude_sale_items
		foreach ( $available_conditions as $condition_name => $ava_condition ) {
			foreach ( $conditions as $condition ) {
				if ( isset( $condition->type ) && isset( $condition->options ) && $condition->type == $condition_name ) {
					if ( in_array( $condition->type, $min_condition_list ) && method_exists( $ava_condition['object'],
							'getMinimumAmount' ) ) {
						$min = $ava_condition['object']->getMinimumAmount( $condition->options );
						//We have find, highest minimum amount, then only all condition will work
						if ( ( empty( $data['minimum_amount'] ) && $min > 0 ) || ( $min > 0 && $min > $data['minimum_amount'] ) ) {
							$data['minimum_amount'] = $min;
						}
					}
					if ( in_array( $condition->type, $currency_conditions ) && isset( $condition->options->value ) ) {
						$data['currency'] = $condition->options->value;
					}
					if ( in_array( $condition->type, $max_condition_list ) && method_exists( $ava_condition['object'],
							'getMaximumAmount' ) ) {
						$max = $ava_condition['object']->getMaximumAmount( $condition->options );
						//We have find, lowest maximum amount, then only all condition will work
						if ( ( empty( $data['maximum_amount'] ) && $max > 0 ) || ( $max > 0 && $max < $data['maximum_amount'] ) ) {
							$data['maximum_amount'] = $max;
						}
					}
					if ( in_array( $condition->type,
							$product_ids_conditions ) && method_exists( $ava_condition['object'],
							'getProductInclude' ) ) {
						$include_product = $ava_condition['object']->getProductInclude( $condition->options );
						if ( ! empty( $include_product ) && is_array( $include_product ) ) {
							$data['product_ids'] = array_unique( array_merge( $data['product_ids'],
								$include_product ) );
						}
					}
					if ( in_array( $condition->type,
							$exclude_product_ids_conditions ) && method_exists( $ava_condition['object'],
							'getProductExclude' ) ) {
						$exclude_product = $ava_condition['object']->getProductExclude( $condition->options );
						if ( ! empty( $exclude_product ) && is_array( $exclude_product ) ) {
							$data['exclude_product_ids'] = array_unique( array_merge( $data['exclude_product_ids'],
								$exclude_product ) );
						}
					}
					if ( in_array( $condition->type,
							$product_category_ids_conditions ) && method_exists( $ava_condition['object'],
							'getProductCategoryInclude' ) ) {
						$include_product_category = $ava_condition['object']->getProductCategoryInclude( $condition->options );
						if ( ! empty( $include_product_category ) && is_array( $include_product_category ) ) {
							$data['product_category_ids'] = array_unique( array_merge( $data['product_category_ids'],
								$include_product_category ) );
						}
					}
					if ( in_array( $condition->type,
							$exclude_product_category_ids_conditions ) && method_exists( $ava_condition['object'],
							'getProductCategoryExclude' ) ) {
						$exclude_product_category = $ava_condition['object']->getProductCategoryExclude( $condition->options );
						if ( ! empty( $exclude_product_category ) && is_array( $exclude_product_category ) ) {
							$data['exclude_product_category_ids'] = array_unique( array_merge( $data['exclude_product_category_ids'],
								$exclude_product_category ) );
						}
					}
					if ( in_array( $condition->type,
							$exclude_sale_items_conditions ) && method_exists( $ava_condition['object'],
							'isSaleItemExclude' ) ) {
						$is_sale_item_exclude = $ava_condition['object']->isSaleItemExclude( $condition->options );
						if ( $is_sale_item_exclude ) {
							$data['exclude_sale_items'] = $is_sale_item_exclude;
						}
					}
				}
			}
		}

		return $data;
	}

	function generateRewardCode() {
		$setting_data = get_option( 'wlr_settings' );
		$prefix       = isset( $setting_data['reward_code_prefix'] ) && ! empty( $setting_data['reward_code_prefix'] ) ? $setting_data['reward_code_prefix'] : 'WLR-';
		$prefix       = str_replace( ' ', '', trim( $prefix ) );
		$random_code  = $this->get_random_code();
		$reward_code  = strtoupper( $prefix . $random_code );

		return apply_filters( 'wlr_generate_reward_code', $reward_code, $prefix );
	}

	public function create_coupon( $data ) {
		try {
			$data = apply_filters( 'wlr_create_coupon_data', $data, $this );
			// Check if coupon code is specified
			if ( ! isset( $data['code'] ) ) {
				throw new Exception( sprintf( __( 'Missing parameter %s', 'wp-loyalty-judge-me' ), 'code' ), 400 );
			}
			$coupon_code  = wc_format_coupon_code( $data['code'] );
			$id_from_code = wc_get_coupon_id_by_code( $coupon_code );
			if ( $id_from_code ) {
				throw new Exception( __( 'The coupon code already exists', 'wp-loyalty-judge-me' ), 400 );
			}
			$defaults    = [
				'type'                         => 'fixed_cart',
				'amount'                       => 0,
				'individual_use'               => false,
				'product_ids'                  => [],
				'exclude_product_ids'          => [],
				'usage_limit'                  => '',
				'usage_limit_per_user'         => '',
				'limit_usage_to_x_items'       => '',
				'usage_count'                  => '',
				'expiry_date'                  => '',
				'enable_free_shipping'         => false,
				'product_category_ids'         => [],
				'exclude_product_category_ids' => [],
				'exclude_sale_items'           => false,
				'minimum_amount'               => '',
				'maximum_amount'               => '',
				'customer_emails'              => [],
				'description'                  => '',
			];
			$coupon_data = wp_parse_args( $data, $defaults );
			// Validate coupon types
			if ( ! in_array( wc_clean( $coupon_data['type'] ), array_keys( wc_get_coupon_types() ) ) ) {
				throw new Exception( sprintf( __( 'Invalid coupon type - the coupon type must be any of these: %s',
					'wp-loyalty-judge-me' ), implode( ', ', array_keys( wc_get_coupon_types() ) ) ), 400 );
			}
			$new_coupon = [
				'post_title'   => $coupon_code,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_type'    => 'shop_coupon',
				'post_excerpt' => $coupon_data['description'],
			];
			$id         = wp_insert_post( $new_coupon, true );
			if ( is_wp_error( $id ) ) {
				throw new Exception( $id->get_error_message(), 400 );
			}
			// Set coupon meta
			update_post_meta( $id, 'discount_type', $coupon_data['type'] );
			update_post_meta( $id, 'coupon_amount', wc_format_decimal( $coupon_data['amount'] ) );
			update_post_meta( $id, 'individual_use', ( true === $coupon_data['individual_use'] ) ? 'yes' : 'no' );
			update_post_meta( $id, 'product_ids',
				implode( ',', array_filter( array_map( 'intval', $coupon_data['product_ids'] ) ) ) );
			update_post_meta( $id, 'exclude_product_ids',
				implode( ',', array_filter( array_map( 'intval', $coupon_data['exclude_product_ids'] ) ) ) );
			update_post_meta( $id, 'usage_limit', absint( $coupon_data['usage_limit'] ) );
			update_post_meta( $id, 'usage_limit_per_user', absint( $coupon_data['usage_limit_per_user'] ) );
			update_post_meta( $id, 'limit_usage_to_x_items', absint( $coupon_data['limit_usage_to_x_items'] ) );
			update_post_meta( $id, 'usage_count', absint( $coupon_data['usage_count'] ) );
			update_post_meta( $id, 'expiry_date',
				$this->get_coupon_expiry_date( wc_clean( $coupon_data['expiry_date'] ) ) );
			update_post_meta( $id, 'date_expires',
				$this->get_coupon_expiry_date( wc_clean( $coupon_data['expiry_date'] ), true ) );
			update_post_meta( $id, 'free_shipping', ( true === $coupon_data['enable_free_shipping'] ) ? 'yes' : 'no' );
			update_post_meta( $id, 'product_categories',
				array_filter( array_map( 'intval', $coupon_data['product_category_ids'] ) ) );
			update_post_meta( $id, 'exclude_product_categories',
				array_filter( array_map( 'intval', $coupon_data['exclude_product_category_ids'] ) ) );
			update_post_meta( $id, 'exclude_sale_items',
				( true === $coupon_data['exclude_sale_items'] ) ? 'yes' : 'no' );
			update_post_meta( $id, 'minimum_amount', wc_format_decimal( $coupon_data['minimum_amount'] ) );
			update_post_meta( $id, 'maximum_amount', wc_format_decimal( $coupon_data['maximum_amount'] ) );
			update_post_meta( $id, 'customer_email',
				array_filter( array_map( 'sanitize_email', $coupon_data['customer_emails'] ) ) );
			/*loyalty data*/
			update_post_meta( $id, 'is_wployalty_couppon', 'yes' );
			update_post_meta( $id, 'wlr_action_type',
				(string) isset( $data['action_type'] ) && ! empty( $data['action_type'] ) ? $data['action_type'] : '' );
			update_post_meta( $id, 'wlr_reward_id',
				(int) isset( $data['reward_id'] ) && ! empty( $data['reward_id'] ) ? $data['reward_id'] : 0 );
			update_post_meta( $id, 'wlr_user_reward_id',
				(int) isset( $data['user_reward_id'] ) && ! empty( $data['user_reward_id'] ) ? $data['user_reward_id'] : 0 );
			update_post_meta( $id, 'wlr_campaign_id',
				(int) isset( $data['campaign_id'] ) && ! empty( $data['campaign_id'] ) ? $data['campaign_id'] : 0 );
			update_post_meta( $id, 'wlr_display_name',
				(string) isset( $data['display_name'] ) && ! empty( $data['display_name'] ) ? $data['display_name'] : '' );

			do_action( 'wlr_create_coupon', $id, $data );
			do_action( 'wlr_new_coupon', $id );

			return new WC_Coupon( $id );
		} catch ( Exception $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage(), [ 'status' => $e->getCode() ] );
		}
	}
}