<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Helpers;

defined( 'ABSPATH' ) or die();

use stdClass;
use Wljm\App\Models\Rewards;
use Wljm\App\Models\UserRewards;

class EarnCampaign extends Base {
	public static $instance = null;
	public static $single_campaign = [];
	public $earn_campaign, $available_conditions = [];

	public static function getInstance( array $config = [] ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $config );
		}

		return self::$instance;
	}

	function addEarnCampaignPoint(
		$action_type,
		$point,
		$campaign_id,
		$action_data
	) {
		$woocommerce_helper = Woocommerce::getInstance();
		$woocommerce_helper->_log( 'Reached EarnCampaign::addEarnCampaignPoint' );
		if ( ! is_array( $action_data ) || $point <= 0
		     || empty( $action_data['user_email'] )
		     || empty( $action_type )
		     || ! $this->is_valid_action( $action_type )
		) {
			return false;
		}
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ', Point :' . $point );
		$point      = apply_filters( 'wlr_before_add_earn_point', $point,
			$action_type, $action_data );
		$point      = apply_filters( 'wlr_notify_before_add_earn_point', $point,
			$action_type, $action_data );
		$conditions = [
			'user_email' => [
				'operator' => '=',
				'value'    => sanitize_email( $action_data['user_email'] )
			]
		];
		$user       = self::$user_model->getQueryData( $conditions, '*',
			[], false, true );
		$id         = 0;
		if ( ! empty( $user ) && $user->id > 0 ) {
			$id               = $user->id;
			$user->points     += $point;
			$earn_total_point = $user->earn_total_point + $point;
			$_data            = [
				'points'           => $user->points,
				'earn_total_point' => $earn_total_point
			];
		} else {
			$uniqueReferCode = $this->get_unique_refer_code( '', false,
				$action_data['user_email'] );
			$_data           = [
				'user_email'        => sanitize_email( $action_data['user_email'] ),
				'refer_code'        => $uniqueReferCode,
				'used_total_points' => 0,
				'points'            => $point,
				'earn_total_point'  => $point,
				'birth_date'        => 0,
				'created_date'      => strtotime( date( "Y-m-d H:i:s" ) ),
			];
		}
		if ( ( isset( $action_data['order_id'] )
		       && ! empty( $action_data['order_id'] )
		       && isset( $action_data['order'] )
		       && ! empty( $action_data['order'] ) )
		     && $woocommerce_helper->isMethodExists( $action_data['order'],
				'get_meta' )
		) {
			$user_dob = $action_data['order']->get_meta( 'wlr_dob' );
			if ( ! empty( $user_dob ) ) {
				$_data['birth_date']    = strtotime( $user_dob );
				$_data['birthday_date'] = $user_dob;
			}
		}
		$ledger_data = [
			'user_email'  => $action_data['user_email'],
			'points'      => $point,
			'action_type' => $action_type,
			'note'        => $action_type != 'achievement'
				? sprintf( __( '%s earned via %s', 'wp-loyalty-judge-me' ),
					$this->getPointLabel( $point ),
					$this->getActionName( $action_type ) )
				: sprintf( __( '%s %s earned via %s (%s)', 'wp-loyalty-judge-me' ),
					$point, $this->getPointLabel( $point ),
					$this->getActionName( $action_type ),
					$this->getAchievementName( $action_data['action_sub_type'] ) ),
			'created_at'  => strtotime( date( "Y-m-d H:i:s" ) )
		];
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ', Ledger data:'
		                           . json_encode( $ledger_data )
		                           . ',User data:'
		                           . json_encode( $_data ) );

		if ( ! self::$user_model->insertOrUpdate( $_data, $id ) ) {
			return false;
		}
		$this->updatePointLedger( $ledger_data );

		if ( $action_type == 'referral' ) {
			$woocommerce_helper->set_referral_code( '' );
		}
		$args = [
			'user_email'       => $action_data['user_email'],
			'points'           => (int) $point,
			'action_type'      => $action_type,
			'campaign_type'    => 'point',
			'transaction_type' => 'credit',
			'referral_type'    => isset( $action_data['referral_type'] )
			                      && ! empty( $action_data['referral_type'] )
				? $action_data['referral_type'] : '',
			'display_name'     => '',
			'campaign_id'      => $campaign_id,
			'created_at'       => strtotime( date( "Y-m-d H:i:s" ) ),
			'modified_at'      => 0,
			'product_id'       => null,
			'order_id'         => null,
			'admin_user_id'    => null,
			'log_data'         => '{}',
			'action_sub_type'  => isset( $action_data['action_sub_type'] )
			                      && ! empty( $action_data['action_sub_type'] )
				? $action_data['action_sub_type'] : '',
			'action_sub_value' => isset( $action_data['action_sub_value'] )
			                      && ! empty( $action_data['action_sub_value'] )
				? $action_data['action_sub_value'] : '',
		];
		if ( ( isset( $action_data['order_currency'] )
		       && ! empty( $action_data['order_currency'] ) )
		) {
			$args['order_currency'] = $action_data['order_currency'];
		}
		if ( ( isset( $action_data['order_total'] )
		       && ! empty( $action_data['order_total'] ) )
		) {
			$args['order_total'] = $action_data['order_total'];
		}
		if ( isset( $action_data['product_id'] ) ) {
			$args['product_id'] = $action_data['product_id'];
		}
		if ( isset( $action_data['log_data'] ) ) {
			$args['log_data'] = json_encode( $action_data['log_data'] );
		}
		if ( is_admin() ) {
			$admin_user            = wp_get_current_user();
			$args['admin_user_id'] = $admin_user->ID;
		}
		if ( ( isset( $action_data['order_id'] )
		       && ! empty( $action_data['order_id'] ) )
		) {
			$args['order_id'] = $action_data['order_id'];
			if ( isset( $action_data['order'] )
			     && ! empty( $action_data['order'] )
			     && ( ! isset( $args['order_currency'] )
			          || ! isset( $args['order_total'] ) )
			) {
				$args['order_currency'] = $action_data['order']->get_currency();
				$args['order_total']    = $action_data['order']->get_total();
			}
		}
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ', Earn Trans Data:'
		                           . json_encode( $args ) );
		try {
			$earn_trans_id
				= self::$earn_campaign_transaction_model->insertRow( $args );
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ', Earn Trans id:'
			                           . $earn_trans_id );
			$earn_trans_id
				= apply_filters( 'wlr_after_add_earn_point_transaction',
				$earn_trans_id, $args );
			if ( $earn_trans_id == 0 ) {
				return false;
			}
			$customer_note = $action_type != 'achievement'
				? sprintf( __( '%s %s earned via %s', 'wp-loyalty-judge-me' ),
					$point, $this->getPointLabel( $point ),
					$this->getActionName( $action_type ) )
				: sprintf( __( '%s %s earned via %s (%s)', 'wp-loyalty-judge-me' ),
					$point, $this->getPointLabel( $point ),
					$this->getActionName( $action_type ),
					$this->getAchievementName( $action_data['action_sub_type'] ) );
			if ( ! empty( $customer_note ) && isset( $args['order_id'] )
			     && $args['order_id'] > 0
			) {
				$order_obj
					= $woocommerce_helper->getOrder( $args['order_id'] );
				if ( ! empty( $order_obj ) ) {
					$order_note = $customer_note . '('
					              . $action_data['user_email'] . ')';
					$order_obj->add_order_note( $order_note );
				}
			}
			$log_data = [
				'user_email'          => sanitize_email( $action_data['user_email'] ),
				'action_type'         => $action_type,
				'earn_campaign_id'    => $earn_trans_id,
				'campaign_id'         => $campaign_id,
				'note'                => $customer_note,
				'customer_note'       => $customer_note,
				'order_id'            => isset( $args['order_id'] )
				                         && ! empty( $args['order_id'] )
					? $args['order_id'] : 0,
				'product_id'          => isset( $args['product_id'] )
				                         && ! empty( $args['product_id'] )
					? $args['product_id'] : 0,
				'admin_id'            => isset( $args['admin_user_id'] )
				                         && ! empty( $args['admin_user_id'] )
					? $args['admin_user_id'] : 0,
				'created_at'          => strtotime( date( 'Y-m-d H:i:s' ) ),
				'modified_at'         => 0,
				'points'              => $point,
				'action_process_type' => 'earn_point',
				'referral_type'       => isset( $action_data['referral_type'] )
				                         && ! empty( $action_data['referral_type'] )
					? $action_data['referral_type'] : '',
			];
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ', Log data:'
			                           . json_encode( $log_data ) );
			$log_data = apply_filters( 'wlr_before_earn_point_log_data', $log_data, $action_type, $action_data );
			$this->add_note( $log_data );
		} catch ( \Exception $e ) {
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ', Earn Trans/ Log Exception:'
			                           . $e->getMessage() );

			return false;
		}
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ', Point earning status: yes' );
		\WC_Emails::instance();
		$action_data['campaign_id'] = $campaign_id;
		do_action( 'wlr_after_add_earn_point', $action_data['user_email'],
			$point, $action_type, $action_data );
		do_action( 'wlr_notify_after_add_earn_point',
			$action_data['user_email'], $point, $action_type, $action_data );

		return true;
	}

	function getActionEarning( $cart_action_list, $extra ) {
		$reward_list = [];
		foreach ( $cart_action_list as $action_type ) {
			$reward_list[ $action_type ] = $this->getTotalEarning( $action_type,
				[], $extra );
		}

		return $reward_list;
	}

	function getCampaign( $campaign ) {
		if ( empty( $campaign ) || ! isset( $campaign->id )
		     || empty( $campaign->id )
		) {
			$this->earn_campaign = new stdClass();

			return $this;
		}
		if ( isset( self::$single_campaign[ $campaign->id ] )
		     && ! empty( self::$single_campaign[ $campaign->id ] )
		) {
			$this->available_conditions
				                 = ( ! empty( $this->available_conditions ) )
				? $this->available_conditions : $this->getAvailableConditions();
			$this->earn_campaign = self::$single_campaign[ $campaign->id ];

			return $this;
		}
		$this->earn_campaign
			                        =
		self::$single_campaign[ $campaign->id ] = $campaign;
		$this->available_conditions = ( ! empty( $this->available_conditions ) )
			? $this->available_conditions : $this->getAvailableConditions();

		return $this;
	}

	function addEarnCampaignReward(
		$action_type,
		$reward,
		$campaign_id,
		$action_data,
		$force_generate_coupon = false
	) {
		$woocommerce_helper = Woocommerce::getInstance();
		$woocommerce_helper->_log( 'Reached EarnCampaign::addEarnCampaignReward' );
		if ( ! is_array( $action_data ) || ! isset( $reward->id )
		     || $reward->id <= 0
		     || empty( $action_data['user_email'] )
		     || empty( $action_type )
		     || ! $this->is_valid_action( $action_type )
		) {
			return false;
		}
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ', Reward id :' . $reward->id );
		$reward   = apply_filters( 'wlr_before_add_earn_reward', $reward,
			$action_type, $action_data );
		$reward   = apply_filters( 'wlr_notify_before_add_earn_reward', $reward,
			$action_type, $action_data );
		$user     = $this->getPointUserByEmail( $action_data['user_email'] );
		$status   = true;
		$user_dob = null;
		if ( ( isset( $action_data['order_id'] )
		       && ! empty( $action_data['order_id'] )
		       && isset( $action_data['order'] )
		       && ! empty( $action_data['order'] ) )
		     && $woocommerce_helper->isMethodExists( $action_data['order'],
				'get_meta' )
		) {
			$user_dob = $action_data['order']->get_meta( 'wlr_dob' );
		}
		if ( empty( $user ) ) {
			$uniqueReferCode = $this->get_unique_refer_code( '', false,
				$action_data['user_email'] );
			$_data           = [
				'user_email'        => $action_data['user_email'],
				'refer_code'        => $uniqueReferCode,
				'points'            => 0,
				'used_total_points' => 0,
				'earn_total_point'  => 0,
				'birth_date'        => ! empty( $user_dob )
					? strtotime( $user_dob ) : 0,
				'birthday_date'     => $user_dob,
				'created_date'      => strtotime( date( "Y-m-d H:i:s" ) ),
			];
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', User data:'
			                           . json_encode( $_data ) );
			$status = (bool) self::$user_model->insertOrUpdate( $_data );
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', User insert status:'
			                           . $status );
		} elseif ( is_object( $user ) && isset( $user->id ) && $user->id > 0
		           && ( isset( $action_data['order_id'] )
		                && ! empty( $action_data['order_id'] )
		                && isset( $action_data['order'] )
		                && ! empty( $action_data['order'] ) )
		           && $woocommerce_helper->isMethodExists( $action_data['order'],
				'get_meta' )
		) {
			$user_dob = $action_data['order']->get_meta( 'wlr_dob' );
			if ( ! empty( $user_dob ) ) {
				$_data  = [ 'birth_date' => $user_dob ];
				$status = (bool) self::$user_model->insertOrUpdate( $_data,
					$user->id );
			}
		}
		if ( ! $status ) {
			return false;
		}

		if ( $action_type == 'referral' ) {
			$woocommerce_helper->set_referral_code( '' );
		}
		$args = [
			'user_email'       => $action_data['user_email'],
			'points'           => 0,
			'action_type'      => $action_type,
			'campaign_type'    => 'coupon',
			'transaction_type' => 'credit',
			'display_name'     => $reward->display_name,
			'campaign_id'      => $campaign_id,
			'reward_id'        => $reward->id,
			'created_at'       => strtotime( date( "Y-m-d H:i:s" ) ),
			'modified_at'      => 0,
			'product_id'       => null,
			'order_id'         => null,
			'admin_user_id'    => null,
			'log_data'         => '{}',
			'referral_type'    => isset( $action_data['referral_type'] )
			                      && ! empty( $action_data['referral_type'] )
				? $action_data['referral_type'] : '',
			'action_sub_type'  => isset( $action_data['action_sub_type'] )
			                      && ! empty( $action_data['action_sub_type'] )
				? $action_data['action_sub_type'] : '',
			'action_sub_value' => isset( $action_data['action_sub_value'] )
			                      && ! empty( $action_data['action_sub_value'] )
				? $action_data['action_sub_value'] : '',
		];
		if ( ( isset( $action_data['order_currency'] )
		       && ! empty( $action_data['order_currency'] ) )
		) {
			$args['order_currency'] = $action_data['order_currency'];
		}
		if ( ( isset( $action_data['order_total'] )
		       && ! empty( $action_data['order_total'] ) )
		) {
			$args['order_total'] = $action_data['order_total'];
		}

		if ( ( isset( $action_data['order_id'] )
		       && ! empty( $action_data['order_id'] ) )
		) {
			$args['order_id'] = $action_data['order_id'];
			if ( isset( $action_data['order'] )
			     && ! empty( $action_data['order'] )
			     && ( ! isset( $args['order_currency'] )
			          || ! isset( $args['order_total'] ) )
			) {
				$args['order_currency'] = $action_data['order']->get_currency();
				$args['order_total']    = $action_data['order']->get_total();
			}
		}
		if ( isset( $action_data['product_id'] ) ) {
			$args['product_id'] = $action_data['product_id'];
		}
		if ( isset( $action_data['log_data'] ) ) {
			$args['log_data'] = json_encode( $action_data['log_data'] );
		}
		if ( is_admin() ) {
			$admin_user            = wp_get_current_user();
			$args['admin_user_id'] = $admin_user->ID;
		}
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ',Reward id :' . $reward->id
		                           . ', Earn trans data:'
		                           . json_encode( $args ) );
		try {
			$earn_trans_id
				= self::$earn_campaign_transaction_model->insertRow( $args );
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', Earn trans id:'
			                           . $earn_trans_id );
			if ( $earn_trans_id == 0 ) {
				$status = false;
			}
		} catch ( \Exception $e ) {
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', Earn trans exception:'
			                           . $e->getMessage() );
			$status = false;
		}
		if ( ! $status ) {
			return false;
		}
		$user_reward_data = [
			'name'                   => $reward->name,
			'description'            => $reward->description,
			'email'                  => sanitize_email( $action_data['user_email'] ),
			'reward_type'            => $reward->reward_type,
			'display_name'           => $reward->display_name,
			'discount_type'          => $reward->discount_type,
			'discount_value'         => $reward->discount_value,
			'reward_currency'        => get_woocommerce_currency(),
			'discount_code'          => '',
			'discount_id'            => 0,
			'require_point'          => $reward->require_point,
			'status'                 => 'open',
			'start_at'               => 0,
			'end_at'                 => 0,
			'conditions'             => $reward->conditions,
			'condition_relationship' => $reward->condition_relationship,
			'usage_limits'           => $reward->usage_limits,
			'icon'                   => $reward->icon,
			'action_type'            => $action_type,
			'reward_id'              => $reward->id,
			'campaign_id'            => $campaign_id,
			'free_product'           => $reward->free_product,
			'expire_after'           => $reward->expire_after,
			'expire_period'          => $reward->expire_period,
			'enable_expiry_email'    => $reward->enable_expiry_email,
			'expire_email'           => $reward->expire_email,
			'expire_email_period'    => $reward->expire_email_period,
			'created_at'             => strtotime( date( "Y-m-d H:i:s" ) ),
			'modified_at'            => 0
		];
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ',Reward id :' . $reward->id
		                           . ', User reward data:'
		                           . json_encode( $user_reward_data ) );
		$user_reward_model = new UserRewards();
		try {
			$user_reward_status
				= $user_reward_model->insertRow( $user_reward_data );
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', User reward status:'
			                           . $user_reward_status );
			if ( $user_reward_status <= 0 ) {
				return false;
			}
			$action_data['user_reward_id'] = $user_reward_status;
			//$customer_note = sprintf(__('%s %s earned via %s', 'wp-loyalty-judge-me'), $reward->display_name, $this->getRewardLabel(1), $this->getActionName($action_type));
			$customer_note = $action_type != 'achievement'
				? sprintf( __( '%s %s earned via %s', 'wp-loyalty-judge-me' ),
					$reward->display_name, $this->getRewardLabel( 1 ),
					$this->getActionName( $action_type ) )
				:
				sprintf( __( '%s %s earned via %s (%s)', 'wp-loyalty-judge-me' ),
					$reward->display_name, $this->getRewardLabel( 1 ),
					$this->getActionName( $action_type ),
					$this->getAchievementName( $action_data['action_sub_type'] ) );
			if ( ! empty( $customer_note ) && isset( $args['order_id'] )
			     && $args['order_id'] > 0
			) {
				$order_obj
					        = $woocommerce_helper->getOrder( $args['order_id'] );
				$order_note = $customer_note . '(' . $action_data['user_email']
				              . ')';
				if ( ! empty( $order_obj ) ) {
					$order_obj->add_order_note( $order_note );
				}
			}
			$log_data = [
				'user_email'          => sanitize_email( $action_data['user_email'] ),
				'action_type'         => $action_type,
				'reward_id'           => $reward->id,
				'user_reward_id'      => $user_reward_status,
				'campaign_id'         => $campaign_id,
				'note'                => $customer_note,
				'customer_note'       => $customer_note,
				'order_id'            => isset( $action_data['order_id'] )
				                         && ! empty( $action_data['order_id'] )
					? $action_data['order_id'] : 0,
				'product_id'          => isset( $action_data['product_id'] )
				                         && ! empty( $action_data['product_id'] )
					? $action_data['product_id'] : 0,
				'admin_id'            => isset( $action_data['admin_user_id'] )
				                         && ! empty( $action_data['admin_user_id'] )
					? $action_data['admin_user_id'] : 0,
				'created_at'          => strtotime( date( 'Y-m-d H:i:s' ) ),
				'modified_at'         => 0,
				'action_process_type' => 'earn_reward',
				'reward_display_name' => $reward->display_name,
				'referral_type'       => isset( $action_data['referral_type'] )
				                         && ! empty( $action_data['referral_type'] )
					? $action_data['referral_type'] : '',
			];
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', Log data:'
			                           . json_encode( $log_data ) );
			$log_data = apply_filters( 'wlr_before_earn_reward_log_data', $log_data, $action_type, $action_data,
				$reward );
			$this->add_note( $log_data );
			$options                    = $woocommerce_helper->getOptions( 'wlr_settings' );
			$allow_auto_generate_coupon = $force_generate_coupon
			                              || ( ! ( is_array( $options )
			                                       && isset( $options['allow_auto_generate_coupon'] )
			                                       && $options['allow_auto_generate_coupon']
			                                          == 'no' ) );
			if ( $allow_auto_generate_coupon ) {
				$allow_auto_generate_coupon
					= $this->checkFreeProductCoupon( $allow_auto_generate_coupon,
					$reward );
			}
			if ( $allow_auto_generate_coupon ) {
				$user_reward_table
					= $user_reward_model->getByKey( $user_reward_status );
				if ( ! empty( $user_reward_table ) ) {
					$reward_helper = new \Wljm\App\Helpers\Rewards();
					if ( isset( $user_reward_table->discount_code )
					     && empty( $user_reward_table->discount_code )
					) {
						$update_data                 = [
							'start_at' => strtotime( date( "Y-m-d H:i:s" ) ),
						];
						$user_reward_table->start_at = $update_data['start_at'];
						if ( $user_reward_table->expire_after > 0 ) {
							$expire_period
								                       = isset( $user_reward_table->expire_period )
								                         && ! empty( $user_reward_table->expire_period )
								? $user_reward_table->expire_period : 'day';
							$update_data['end_at']
								                       = strtotime( date( "Y-m-d H:i:s",
								strtotime( "+"
								           . $user_reward_table->expire_after
								           . " " . $expire_period ) ) );
							$user_reward_table->end_at = $update_data['end_at'];

							if ( isset( $user_reward_table->expire_email )
							     && $user_reward_table->expire_email > 0
							     && isset( $user_reward_table->enable_expiry_email )
							     && $user_reward_table->enable_expiry_email > 0
							) {
								$expire_email_period
									= isset( $user_reward_table->expire_email_period )
									  && ! empty( $user_reward_table->expire_email_period )
									? $user_reward_table->expire_email_period
									: 'day';
								$update_data['expire_email_date']
									= $user_reward_table->expire_email_date
									= strtotime( date( "Y-m-d H:i:s",
									strtotime( "+"
									           . $user_reward_table->expire_email
									           . " "
									           . $expire_email_period ) ) );
							}
						}
						$woocommerce_helper->_log( 'Action :'
						                           . $action_type
						                           . ',Campaign id:'
						                           . $campaign_id
						                           . ',Reward id :'
						                           . $reward->id
						                           . ', auto generate Update data:'
						                           . json_encode( $update_data ) );
						$update_where = [ 'id' => $user_reward_table->id ];
						$user_reward_model->updateRow( $update_data,
							$update_where );
					}
					$reward_helper->createCartUserReward( $user_reward_table,
						$log_data['user_email'] );
				}
			}
		} catch ( \Exception $e ) {
			$woocommerce_helper->_log( 'Action :' . $action_type
			                           . ',Campaign id:' . $campaign_id
			                           . ',Reward id :' . $reward->id
			                           . ', User reward exception:'
			                           . $e->getMessage() );
			$status = false;
		}
		$woocommerce_helper->_log( 'Action :' . $action_type
		                           . ',Campaign id:' . $campaign_id
		                           . ',Reward id :' . $reward->id
		                           . ', Reward earning status:'
		                           . $status );
		if ( $status ) {
			\WC_Emails::instance();
			$action_data['campaign_id'] = $campaign_id;
			do_action( 'wlr_after_add_earn_reward', $action_data['user_email'],
				$reward, $action_type, $action_data );
			do_action( 'wlr_notify_after_add_earn_reward',
				$action_data['user_email'], $reward, $action_type,
				$action_data );
		}

		return $status;
	}

	/**
	 * Check free product out of stock status.
	 *
	 * @param   boolean  $status  Instant coupon apply.
	 * @param   object   $reward  Reward data.
	 *
	 * @return bool
	 */
	function checkFreeProductCoupon( $status, $reward ) {
		if ( ! isset( $reward->id ) || $reward->id <= 0 ) {
			return $status;
		}
		$reward_modal = new Rewards();
		$reward_data  = $reward_modal->getByKey( $reward->id );
		if ( empty( $reward_data )
		     || $reward_data->discount_type != "free_product"
		) {
			return $status;
		}
		$woocommerce_helper = Woocommerce::getInstance();
		$free_products
		                    = $woocommerce_helper->isJson( $reward_data->free_product ) ?
			json_decode( $reward_data->free_product, true ) : [];
		if ( empty( $free_products ) ) {
			return $status;
		}
		foreach ( $free_products as $f_product ) {
			$product = wc_get_product( $f_product['value'] );
			if ( $product && ! $product->is_in_stock() ) {
				return false;
			}
		}

		return $status;
	}

	protected function processCampaignAction(
		$action_type,
		$type,
		$campaign,
		$data
	) {
		if ( empty( $type ) ) {
			return null;
		}
		$reward = [];
		if ( $type == 'point' ) {
			$reward = 0;
		}
		if ( empty( $action_type ) ) {
			return $reward;
		}
		if ( isset( $data['action_type'] ) && ! empty( $data['action_type'] )
		     && $action_type == $data['action_type']
		) {
			$action_type = trim( $action_type );
			$reward      = apply_filters( 'wlr_earn_' . strtolower( $type )
			                              . '_' . strtolower( $action_type ),
				$reward, $campaign, $data );
		}

		return $reward;
	}

	function getCampaignPoint( $data ) {
		/**
		 * 1. Check level, active
		 */
		$status = true;
		if ( ! $this->isActive() ) {
			$status = false;
		}
		$is_product_level = false;
		if ( isset( $data['is_product_level'] ) && $data['is_product_level'] ) {
			$is_product_level = true;
		}
		if ( $status && isset( $data['is_calculate_based'] )
		     && $data['is_calculate_based'] == 'cart'
		) {
			$status = $this->isAllowEarningWhenCoupon();
		} elseif ( $status && isset( $data['is_calculate_based'] )
		           && $data['is_calculate_based'] == 'order'
		) {
			$status = $this->isAllowEarningWhenCoupon( false, $data['order'] );
		}
		$status = apply_filters( 'wlr_before_earn_point_conditions', $status,
			$data );
		/**
		 * 2. check condition
		 */
		if ( $status
		     && ! $this->processCampaignCondition( $data, $is_product_level )
		) {
			$status = false;
		}
		$status = apply_filters( 'wlr_before_earn_point_calculation', $status,
			$data );
		/**
		 * 3. calculate point based on action
		 */
		$point = 0;
		if ( $status ) {
			$point = $this->processCampaignPoint( $data );
		}

		return $point;
	}

	function isActive() {
		$status = false;
		if ( isset( $this->earn_campaign->active )
		     && $this->earn_campaign->active
		) {
			$status = true;
		}

		return $status;
	}

	private function processCampaignPoint( $data ) {
		if ( ! is_array( $data ) || empty( $data['action_type'] ) ) {
			return 0;
		}

		return $this->processCampaignAction( trim( $data['action_type'] ),
			'point', $this, $data );
	}

	function processCampaignCondition( $data, $is_product_level = false ) {
		if ( ! $this->isPro() ) {
			return true;
		}
		/**
		 * 1. check start and end date
		 */
		$current_date = date( "Y-m-d" );
		$status       = false;
		if ( ( ( isset( $this->earn_campaign->start_at )
		         && $current_date >= date( "Y-m-d",
						$this->earn_campaign->start_at ) )
		       || $this->earn_campaign->start_at == 0 )
		     && ( ( isset( $this->earn_campaign->end_at )
		            && $current_date >= date( "Y-m-d",
						$this->earn_campaign->end_at ) )
		          || $this->earn_campaign->end_at )
		) {
			$status = true;
		}

		/*echo "<pre>";
        var_dump($status);exit;
        if (isset($this->earn_campaign->start_at) && isset($this->earn_campaign->end_at) && (is_null($this->earn_campaign->start_at) || $current_date >= date("Y-m-d h:i:s",$this->earn_campaign->start_at)) && (is_null($this->earn_campaign->end_at) || $current_date <= date("Y-m-d h:i:s",$this->earn_campaign->end_at))) {
            $status = true;
        }*/
		//var_dump($status);exit;
		/**
		 * 2. Condition type all match or any match
		 */
		$conditions = $this->getConditions();
		if ( $status && $conditions ) {

			//2. other request
			foreach (
				$this->available_conditions as $condition_name => $ava_condition
			) {
				foreach ( $conditions as $condition ) {
					if ( isset( $condition->type )
					     && isset( $condition->options )
					     && isset( $ava_condition['object'] )
					     && $condition->type == $condition_name
					) {

						if ( isset( $data['ignore_condition'] )
						     && ! empty( $data['ignore_condition'] )
						     && in_array( $condition->type,
								$data['ignore_condition'] )
						) {
							continue;
						}
						if ( isset( $data['allowed_condition'] )
						     && ! empty( $data['allowed_condition'] )
						     && ! in_array( $condition->type,
								$data['allowed_condition'] )
						) {
							continue;
						}
						if ( ! $is_product_level ) {
							if ( ! isset( $data['campaign'] ) ) {
								$data['campaign'] = $this->earn_campaign;
							}
							$condition_status
								= $ava_condition['object']->check( $condition->options,
								$data );
						} else {
							$condition_status
								= $ava_condition['object']->isProductValid( $condition->options,
								$data );
						}
						//1. if its product message, any one condition true , then return true
						/*if (isset($data['is_message']) && $data['is_message'] && isset($data['is_calculate_based']) && $data['is_calculate_based'] === 'product') {
                            if ($condition_status) {
                                $status = true;
                                break 2;
                            } else {
                                $status = false;
                            }
                        } else*/
						if ( isset( $this->earn_campaign->condition_relationship )
						     && $this->earn_campaign->condition_relationship
						        == 'and'
						) {
							if ( ! $condition_status ) {
								$status = false;
								break 2;
							}
						} elseif ( isset( $this->earn_campaign->condition_relationship )
						           && $this->earn_campaign->condition_relationship
						              == 'or'
						) {
							if ( $condition_status ) {
								$status = true;
								break 2;
							} else {
								$status = false;
							}
						}
					}
				}
			}
		}

		return $status;
	}

	function getConditions() {
		if ( $this->hasConditions() ) {
			return json_decode( $this->earn_campaign->conditions );
		}

		return false;
	}

	protected function hasConditions() {
		$status = false;
		if ( isset( $this->earn_campaign->conditions ) ) {
			$status = true;
			if ( empty( $this->earn_campaign->conditions )
			     || $this->earn_campaign->conditions == '{}'
			     || $this->earn_campaign->conditions == '[]'
			) {
				$status = false;
			}
		}

		return apply_filters( 'wlr_has_earn_campaign_conditions', $status,
			$this->earn_campaign );
	}

	public function getAvailableConditions() {
		$available_conditions = [];
		//Read the conditions directory and create condition object
		if ( file_exists( WLJM_PLUGIN_PATH . 'App/Conditions/' ) ) {
			$conditions_list = array_slice( scandir( WLJM_PLUGIN_PATH
			                                         . 'App/Conditions/' ), 2 );
			if ( ! empty( $conditions_list ) ) {
				foreach ( $conditions_list as $condition ) {
					$class_name = basename( $condition, '.php' );
					if ( $class_name == 'Base' ) {
						continue;
					}
					$condition_class_name = 'Wljm\App\Conditions\\'
					                        . $class_name;
					if ( ! class_exists( $condition_class_name ) ) {
						continue;
					}
					$condition_object = new $condition_class_name();
					if ( $condition_object instanceof
					     \Wljm\App\Conditions\Base
					) {
						$condition_name = $condition_object->name();
						if ( ! empty( $condition_name ) ) {
							$available_conditions[ $condition_name ] = [
								'object'       => $condition_object,
								'label'        => $condition_object->label,
								'group'        => $condition_object->group,
								'extra_params' => $condition_object->extra_params,
							];
						}
					}
				}
			}
		}
		$this->available_conditions = apply_filters( 'wlr_available_conditions',
			$available_conditions );

		return $this->available_conditions;
	}

	function getCampaignReward( $data ) {
		/**
		 * 1. Check level, active
		 */
		$status = true;
		if ( ! $this->isActive() ) {
			$status = false;
		}
		$is_product_level = false;
		if ( isset( $data['is_product_level'] ) && $data['is_product_level'] ) {
			$is_product_level = true;
		}

		if ( $status && isset( $data['is_calculate_based'] )
		     && $data['is_calculate_based'] == 'cart'
		) {
			$status = $this->isAllowEarningWhenCoupon();
		} elseif ( $status && isset( $data['is_calculate_based'] )
		           && $data['is_calculate_based'] == 'order'
		) {
			$status = $this->isAllowEarningWhenCoupon( false, $data['order'] );
		}
		$status = apply_filters( 'wlr_before_earn_reward_conditions', $status,
			$data );
		/**
		 * 2. check condition
		 */
		if ( $status
		     && ! $this->processCampaignCondition( $data, $is_product_level )
		) {
			$status = false;
		}
		$status = apply_filters( 'wlr_before_earn_reward_calculation', $status,
			$data );
		/**
		 * 3. calculate point based on action
		 */
		$rewards = [];
		if ( $status ) {
			$rewards = $this->processCampaignRewards( $data );
		}

		return $rewards;
	}

	function processCampaignRewards( $data ) {
		$rewards = [];
		if ( isset( $data['action_type'] )
		     && ! empty( $data['action_type'] )
		) {
			$action_type = trim( $data['action_type'] );
			$rewards     = $this->processCampaignAction( $action_type, 'coupon',
				$this, $data );
		}

		return $rewards;
	}
}