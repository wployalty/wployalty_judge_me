<?php

namespace Wljm\App\Helpers;

use Wljm\App\Models\EarnCampaignTransactions;
use Wljm\App\Models\Logs;
use Wljm\App\Models\PointsLedger;
use Wljm\App\Models\UserRewards;
use Wljm\App\Models\Users;
use Exception;

class Base {
	public static $user_model, $earn_campaign_transaction_model, $user_by_email;
	public static $user_reward_by_coupon = [];

	public function __construct( $config = [] ) {
		self::$user_model                      = empty( self::$user_model ) ? new Users() : self::$user_model;
		self::$earn_campaign_transaction_model = empty( self::$earn_campaign_transaction_model ) ? new EarnCampaignTransactions() : self::$earn_campaign_transaction_model;
	}

	public function getPointLabel( $point, $label_translate = true ) {
		$setting_option = get_option( 'wlr_settings', '' );
		$singular       = ( isset( $setting_option['wlr_point_singular_label'] ) && ! empty( $setting_option['wlr_point_singular_label'] ) ) ? $setting_option['wlr_point_singular_label'] : 'point';
		if ( $label_translate ) {
			$singular = __( $singular, 'wp-loyalty-judge-me' );
		}
		$plural = ( isset( $setting_option['wlr_point_label'] ) && ! empty( $setting_option['wlr_point_label'] ) ) ? $setting_option['wlr_point_label'] : 'points';
		if ( $label_translate ) {
			$plural = __( $plural, 'wp-loyalty-judge-me' );
		}
		$point_label = ( $point == 0 || $point > 1 ) ? $plural : $singular;

		return apply_filters( 'wlr_get_point_label', $point_label, $point );
	}

	function isEligibleForEarn( $action_type, $extra = [] ) {
		return apply_filters( 'wlr_is_eligible_for_earning', true, $action_type, $extra );
	}

	function getTotalEarning(
		$action_type = '',
		$ignore_condition = [],
		$extra = [],
		$is_product_level = false
	) {
		$earning            = [];
		$woocommerce_helper = Woocommerce::getInstance();
		if ( ! $this->is_valid_action( $action_type ) || ! $this->isEligibleForEarn( $action_type,
				$extra ) || $woocommerce_helper->isBannedUser() ) {
			return $earning;
		}
		$campaign_helper     = EarnCampaign::getInstance();
		$earn_campaign_table = new \Wljm\App\Models\EarnCampaign();
		$campaign_list       = $earn_campaign_table->getCampaignByAction( $action_type );

		if ( ! empty( $campaign_list ) ) {
			$action_data = [
				'action_type'      => $action_type,
				'ignore_condition' => $ignore_condition,
				'is_product_level' => $is_product_level,
			];
			if ( ! empty( $extra ) && is_array( $extra ) ) {
				foreach ( $extra as $key => $value ) {
					$action_data[ $key ] = $value;
				}
			}
			$action_data = apply_filters( 'wlr_before_rule_data_process', $action_data, $campaign_list );
			$order_id    = isset( $action_data['order'] ) && ! empty( $action_data['order'] ) ? $action_data['order']->get_id() : 0;
			$woocommerce_helper->_log( 'getTotalEarning Action data:' . json_encode( $action_data ) );
			$social_share = $this->getSocialActionList();
			foreach ( $campaign_list as $campaign ) {
				$processing_campaign = $campaign_helper->getCampaign( $campaign );
				$campaign_id         = isset( $processing_campaign->earn_campaign->id ) && $processing_campaign->earn_campaign->id > 0 ? $processing_campaign->earn_campaign->id : 0;
				if ( $campaign_id && $order_id ) {
					$woocommerce_helper->_log( 'getTotalEarning Action:' . $action_type . ',Campaign id:' . $campaign_id . ', Before check user already earned' );
					if ( $this->checkUserEarnedInCampaignFromOrder( $order_id, $campaign_id ) ) {
						continue;
					}
				}
				$action_data['campaign_id'] = $campaign_id;
				$campaign_earning           = [];
				if ( isset( $processing_campaign->earn_campaign->campaign_type ) && 'point' === $processing_campaign->earn_campaign->campaign_type ) {
					//campaign_id and order_id
					$woocommerce_helper->_log( 'getTotalEarning Action:' . $action_type . ',Campaign id:' . $campaign_id . ', Before earn point:' . json_encode( $action_data ) );
					$campaign_earning['point']         = $processing_campaign->getCampaignPoint( $action_data );
					$earning[ $campaign->id ]['point'] = $campaign_earning['point'];
				} elseif ( isset( $processing_campaign->earn_campaign->campaign_type ) && 'coupon' === $processing_campaign->earn_campaign->campaign_type ) {
					$woocommerce_helper->_log( 'getTotalEarning Action:' . $action_type . ',Campaign id:' . $campaign_id . ', Before earn coupon:' . json_encode( $action_data ) );
					$earning[ $campaign->id ]['rewards'][] = $campaign_earning['rewards'][] = $processing_campaign->getCampaignReward( $action_data );
				}
				$earning[ $campaign->id ]['messages'] = $this->processCampaignMessage( $action_type,
					$processing_campaign, $campaign_earning );
				if ( in_array( $action_type, $social_share ) ) {
					$earning[ $campaign->id ]['icon'] = isset( $processing_campaign->earn_campaign->icon ) && ! empty( $processing_campaign->earn_campaign->icon ) ? $processing_campaign->earn_campaign->icon : '';
				}
			}
			$woocommerce_helper->_log( 'getTotalEarning Action:' . $action_type . ', Total earning:' . json_encode( $earning ) );
		}

		return $earning;
	}

	function processCampaignMessage( $action_type, $rule, $earning ) {
		$messages           = [];
		$woocommerce_helper = Woocommerce::getInstance();
		if ( ! empty( $action_type ) && $action_type === $rule->earn_campaign->action_type ) {
			if ( isset( $rule->earn_campaign->point_rule ) && ! empty( $rule->earn_campaign->point_rule ) ) {
				if ( $woocommerce_helper->isJson( $rule->earn_campaign->point_rule ) ) {
					$point_rule        = json_decode( $rule->earn_campaign->point_rule );
					$class_name        = ucfirst( $this->camelCaseAction( $action_type ) );
					$class_free_helper = '\\Wljm\\App\\Helpers\\' . $class_name;
					$class_pro_helper  = '\\Wljm\\App\\Premium\\Helpers\\' . $class_name;
					if ( class_exists( $class_free_helper ) ) {
						$helper = new $class_free_helper;
					} elseif ( class_exists( $class_pro_helper ) ) {
						$helper = new $class_pro_helper;
					}
					if ( isset( $helper ) && method_exists( $helper, 'processMessage' ) ) {
						$messages = $helper->processMessage( $point_rule, $earning );
					}
				}
			}
		}

		return $messages;
	}

	public function roundPoints( $points ) {
		$setting_option  = get_option( 'wlr_settings', '' );
		$rounding_option = ( isset( $setting_option['wlr_point_rounding_type'] ) && ! empty( $setting_option['wlr_point_rounding_type'] ) ) ? $setting_option['wlr_point_rounding_type'] : 'round';
		switch ( $rounding_option ) {
			case 'ceil':
				$point_earned = ceil( $points );
				break;
			case 'floor':
				$point_earned = floor( $points );
				break;
			default:
				$point_earned = round( $points );
				break;
		}

		return $point_earned;
	}

	public function getRewardLabel( $reward_count = 0 ) {
		$setting_option = get_option( 'wlr_settings', '' );
		$singular       = ( isset( $setting_option['reward_singular_label'] ) && ! empty( $setting_option['reward_singular_label'] ) ) ? __( $setting_option['reward_singular_label'],
			'wp-loyalty-judge-me' ) : __( 'reward', 'wp-loyalty-judge-me' );
		$plural         = ( isset( $setting_option['reward_plural_label'] ) && ! empty( $setting_option['reward_plural_label'] ) ) ? __( $setting_option['reward_plural_label'],
			'wp-loyalty-judge-me' ) : __( 'rewards', 'wp-loyalty-judge-me' );
		$reward_label   = ( $reward_count == 0 || $reward_count > 1 ) ? $plural : $singular;

		return apply_filters( 'wlr_get_reward_label', $reward_label, $reward_count );
	}

	function processShortCodes( $short_codes, $message ) {
		if ( ! is_array( $short_codes ) ) {
			return $message;
		}
		foreach ( $short_codes as $key => $value ) {
			$message = str_replace( $key, $value, $message );
		}

		return apply_filters( 'wlr_process_message_short_codes', $message, $short_codes );
	}

	function isAllowEarningWhenCoupon( $is_cart = true, $order = '' ) {
		$setting_option = get_option( 'wlr_settings', '' );
		$allow_earning  = ( isset( $setting_option['allow_earning_when_coupon'] ) && ! empty( $setting_option['allow_earning_when_coupon'] ) ) ? $setting_option['allow_earning_when_coupon'] : 'yes';
		if ( $allow_earning == 'yes' ) {
			return true;
		}
		$coupons = [];
		if ( $is_cart && function_exists( 'WC' ) && isset( WC()->cart->applied_coupons ) && ! empty( WC()->cart->applied_coupons ) ) {
			$coupons = WC()->cart->applied_coupons;
		} elseif ( ! empty( $order ) ) {
			$woocommerce_helper = Woocommerce::getInstance();
			$order              = $woocommerce_helper->getOrder( $order );
			$items              = $woocommerce_helper->isMethodExists( $order,
				'get_items' ) ? $order->get_items( 'coupon' ) : [];
			foreach ( $items as $item ) {
				if ( $woocommerce_helper->isMethodExists( $item, 'get_code' ) ) {
					$coupons[] = $item->get_code();
				}
			}
		}
		if ( empty( $coupons ) ) {
			return true;
		}

		if ( ! apply_filters( 'wlr_is_allow_earning_when_coupon', true, $coupons ) ) {
			return false;
		}

		foreach ( $coupons as $code ) {
			if ( $this->is_loyalty_coupon( $code ) ) {
				return false;
			}
		}

		return true;
	}

	function get_unique_refer_code( $ref_code = '', $recursive = false, $email = '' ) {
		$referral_settings = get_option( 'wlr_settings' );
		$prefix            = ( isset( $referral_settings['wlr_referral_prefix'] ) && ! empty( $referral_settings['wlr_referral_prefix'] ) ) ? $referral_settings['wlr_referral_prefix'] : 'REF-';
		$ref_code          = ! empty( $ref_code ) ? $ref_code : $prefix . $this->get_random_code();
		if ( ! empty( $ref_code ) ) {
			if ( $recursive ) {
				$ref_code = $prefix . $this->get_random_code();
			}
			$ref_code = sanitize_text_field( $ref_code );
			$user     = self::$user_model->getQueryData( [
				'refer_code' => [
					'operator' => '=',
					'value'    => $ref_code
				]
			], '*', [], false );
			if ( ! empty( $user ) ) {
				return $this->get_unique_refer_code( $ref_code, true, $email );
			}
		}

		return apply_filters( 'wlr_generate_referral_code', $ref_code, $prefix, $email );
	}

	function get_random_code() {
		$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$ref_code_random = '';
		for ( $i = 0; $i < 2; $i ++ ) {
			$ref_code_random .= substr( str_shuffle( $permitted_chars ), 0, 3 ) . '-';
		}

		return strtoupper( trim( $ref_code_random, '-' ) );
	}

	function getActionName( $action_type ) {
		$action_name = '';
		if ( empty( $action_type ) ) {
			return $action_name;
		}
		$woocommerce_helper = Woocommerce::getInstance();
		$action_types       = $woocommerce_helper->getActionTypes();
		if ( isset( $action_types[ $action_type ] ) ) {
			$action_name = $action_types[ $action_type ];
		}
		if ( empty( $action_name ) ) {
			$extra_action_types = $this->getExtraActionList();
			if ( isset( $extra_action_types[ $action_type ] ) ) {
				$action_name = $extra_action_types[ $action_type ];
			}
		}

		return empty( $action_name ) ? __( "-", 'wp-loyalty-judge-me' ) : $action_name;
	}

	function getAchievementName( $achievement_key ) {
		if ( empty( $achievement_key ) ) {
			return '';
		}
		$achievement_names = [
			'level_update'  => __( 'Level Update', 'wp-loyalty-judge-me' ),
			'daily_login'   => __( 'Daily Login', 'wp-loyalty-judge-me' ),
			'custom_action' => __( 'Custom Action', 'wp-loyalty-judge-me' ),
		];
		$achievement_names = apply_filters( 'wlr_achievement_names', $achievement_names, $achievement_key );

		return isset( $achievement_names[ $achievement_key ] ) && ! empty( $achievement_names[ $achievement_key ] ) ? $achievement_names[ $achievement_key ] : '';
	}

	function updatePointLedger( $data = [], $point_action = 'credit', $is_update = true ) {
		if ( ! is_array( $data ) || empty( $data['user_email'] ) || ( $data['points'] <= 0 && ! $this->isValidPointLedgerExtraAction( $data['action_type'] ) ) || empty( $data['action_type'] ) ) {
			return false;
		}
		$conditions               = [
			'user_email' => [
				'operator' => '=',
				'value'    => sanitize_email( $data['user_email'] ),
			],
		];
		$point_ledger             = new PointsLedger();
		$user_ledger              = $point_ledger->getQueryData( $conditions, '*', [], false );
		$point_ledger_is_starting = false;
		if ( empty( $user_ledger ) ) {
			/*$user = self::$user_model->getQueryData($conditions, '*', array(), false);
            $credit_points = isset($user->points) && !empty($user->points) ? $user->points : 0;
            if ($this->isValidExtraAction($data['action_type']) && empty($credit_points)) {
                $credit_points = (isset($data['points']) && $data['points'] > 0 ? $data['points'] : 0);
            }*/
			$point_data = [
				'user_email'          => $data['user_email'],
				'credit_points'       => (int) isset( $data['points'] ) && $data['points'] > 0 ? $data['points'] : 0,
				'action_type'         => 'starting_point',
				'debit_points'        => 0,
				'action_process_type' => 'starting_point',
				'note'                => __( 'Starting point of customer', 'wp-loyalty-judge-me' ),
				'created_at'          => strtotime(
					date( 'Y-m-d H:i:s' )
				),
			];
			$point_ledger->insertRow( $point_data );
			$point_ledger_is_starting = true;
		}
		if ( $is_update && ! $point_ledger_is_starting ) {
			$point_data = [
				'user_email'          => $data['user_email'],
				'credit_points'       => $point_action == 'credit' ? $data['points'] : 0,
				'action_type'         => $data['action_type'],
				'debit_points'        => $point_action == 'debit' ? $data['points'] : 0,
				'action_process_type' => isset( $data['action_process_type'] ) && ! empty( $data['action_process_type'] ) ? $data['action_process_type'] : $data['action_type'],
				'note'                => isset( $data['note'] ) && ! empty( $data['note'] ) ? $data['note'] : '',
				'created_at'          => strtotime( date( 'Y-m-d H:i:s' ) ),
			];
			$point_ledger->insertRow( $point_data );
		}

		return true;
	}

	function isValidPointLedgerExtraAction( $action_type ) {
		$action_types = apply_filters( 'wlr_extra_point_ledger_action_list', [
			'new_user_add',
			'admin_change',
			'import'
		] );

		return ! empty( $action_type ) && in_array( $action_type, $action_types );
	}

	function add_note( $data ) {
		return ( new Logs() )->saveLog( $data );
	}

	function getReferralUrl( $code = '' ) {
		if ( empty( $code ) ) {
			$woocommerce_helper = Woocommerce::getInstance();
			$user_email         = $woocommerce_helper->get_login_user_email();
			$user               = $this->getPointUserByEmail( $user_email );
			$code               = ! empty( $user ) && isset( $user->refer_code ) && ! empty( $user->refer_code ) ? $user->refer_code : '';
		}
		$url = '';
		if ( ! empty( $code ) ) {
			$url = site_url() . '?wlr_ref=' . $code;
		}

		return apply_filters( 'wlr_get_referral_url', $url, $code );
	}

	function getPointUserByEmail( $user_email ) {
		if ( empty( $user_email ) ) {
			return '';
		}

		$user_email = sanitize_email( $user_email );

		if ( ! isset( self::$user_by_email[ $user_email ] ) ) {
			self::$user_by_email[ $user_email ] = self::$user_model->getQueryData(
				[
					'user_email' => [
						'operator' => '=',
						'value'    => $user_email,
					],
				],
				'*',
				[],
				false
			);
		}

		return self::$user_by_email[ $user_email ];
	}

	function getSocialActionList() {
		$social_action_list = [
			'facebook_share',
			'twitter_share',
			'whatsapp_share',
			'email_share'
		];

		return apply_filters( 'wlr_social_action_list', $social_action_list );
	}

	function is_loyalty_coupon( $code ) {
		if ( empty( $code ) ) {
			return false;
		}
		$user_reward = $this->getUserRewardByCoupon( $code );
		if ( ! empty( $user_reward ) ) {
			return true;
		}

		return false;
	}

	function getExtraActionList() {
		$action_list = [
			'admin_change'             => __( 'Admin updated', 'wp-loyalty-judge-me' ),
			'redeem_point'             => sprintf( __( 'Convert %s to coupon', 'wp-loyalty-judge-me' ),
				$this->getPointLabel( 3 ) ),
			'new_user_add'             => __( 'New Customer', 'wp-loyalty-judge-me' ),
			'import'                   => __( 'Import Customer', 'wp-loyalty-judge-me' ),
			'revoke_coupon'            => __( 'Revoke coupon', 'wp-loyalty-judge-me' ),
			'expire_date_change'       => __( 'Expiry date has been changed manually', 'wp-loyalty-judge-me' ),
			'expire_email_date_change' => __( 'Expiry email date has been changed manually', 'wp-loyalty-judge-me' ),
			'expire_point'             => sprintf( __( '%s Expired', 'wp-loyalty-judge-me' ),
				$this->getPointLabel( 3 ) ),
			'new_level'                => __( 'New Level', 'wp-loyalty-judge-me' ),
			'rest_api'                 => __( 'REST API', 'wp-loyalty-judge-me' ),
			'birthday_change'          => __( 'Birthday change', 'wp-loyalty-judge-me' )
		];

		return apply_filters( "wlr_extra_action_list", $action_list );
	}

	function getUserRewardByCoupon( $code ) {
		if ( empty( $code ) ) {
			return '';
		}
		$code = ( is_object( $code ) && isset( $code->code ) ) ? $code->get_code() : $code;
		if ( ! isset( self::$user_reward_by_coupon[ $code ] ) ) {
			self::$user_reward_by_coupon[ $code ] = ( new UserRewards() )->getQueryData(
				[
					'discount_code' => [
						'operator' => '=',
						'value'    => $code,
					],
				],
				'*',
				[],
				false
			);
		}

		return isset( self::$user_reward_by_coupon[ $code ] ) ? self::$user_reward_by_coupon[ $code ] : '';
	}

	function checkUserEarnedInCampaignFromOrder( $order_id, $campaign_id ) {
		if ( $order_id <= 0 || $campaign_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$where  = $wpdb->prepare( 'order_id = %s AND campaign_id = %s', [ $order_id, $campaign_id ] );
		$result = ( new EarnCampaignTransactions() )->getWhere( $where );

		return ! empty( $result );
	}

	protected function camelCaseAction( $action_type ) {
		$action_type = trim( $action_type );
		$action_type = lcfirst( $action_type );
		$action_type = preg_replace( '/^[-_]+/', '', $action_type );
		$action_type = preg_replace_callback(
			'/[-_\s]+(.)?/u',
			function ( $match ) {
				if ( isset( $match[1] ) ) {
					return strtoupper( $match[1] );
				} else {
					return '';
				}
			},
			$action_type
		);
		$action_type = preg_replace_callback(
			'/[\d]+(.)?/u',
			function ( $match ) {
				return strtoupper( $match[0] );
			},
			$action_type
		);

		return $action_type;
	}

	function isPro() {
		return apply_filters( 'wlr_is_pro', false );
	}

	function is_valid_action( $action_type ) {
		$status             = false;
		$woocommerce_helper = Woocommerce::getInstance();
		$action_types       = $woocommerce_helper->getActionTypes();
		if ( ! empty( $action_type ) && isset( $action_types[ $action_type ] ) && ! empty( $action_types[ $action_type ] ) ) {
			$status = true;
		}

		return $status;
	}

	function isIncludingTax() {
		$woocommerce_helper   = Woocommerce::getInstance();
		$setting_option       = $woocommerce_helper->getOptions( 'wlr_settings', [] );
		$tax_calculation_type = ( isset( $setting_option['tax_calculation_type'] ) && ! empty( $setting_option['tax_calculation_type'] ) ) ? $setting_option['tax_calculation_type'] : 'inherit';
		$is_including_tax     = false;
		if ( $tax_calculation_type == 'inherit' ) {
			$is_including_tax = ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		} elseif ( $tax_calculation_type === 'including' ) {
			$is_including_tax = true;
		}

		return $is_including_tax;
	}

	function addExtraPointAction(
		$action_type,
		$point,
		$action_data,
		$trans_type = 'credit',
		$is_update_used_point = false,
		$force_update_earn_campaign = false,
		$update_earn_total_point = true
	) {
		$woocommerce_helper = Woocommerce::getInstance();
		$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Trans:' . $trans_type );
		if ( ! is_array( $action_data ) || $point < 0 || empty( $action_data['user_email'] ) || empty( $action_type ) || ! $this->isValidExtraAction( $action_type ) ) {
			return false;
		}
		$action_data = apply_filters( 'wlr_before_extra_point_data', $action_data, $point, $action_type );
		$status      = true;
		$point       = apply_filters( 'wlr_before_add_earn_point', $point, $action_type, $action_data );
		$point       = apply_filters( 'wlr_notify_before_add_earn_point', $point, $action_type, $action_data );
		$conditions  = [
			'user_email' => [
				'operator' => '=',
				'value'    => sanitize_email( $action_data['user_email'] ),
			],
		];
		$user        = self::$user_model->getQueryData( $conditions, '*', [], false );
		$created_at  = strtotime( date( 'Y-m-d H:i:s' ) );
		$id          = 0;
		if ( ! empty( $user ) && $user->id > 0 ) {
			$id = $user->id;
			if ( $trans_type == 'credit' ) {
				$user->points += $point;
				if ( $update_earn_total_point ) {
					$user->earn_total_point = $user->earn_total_point + $point;
				}
				if ( $is_update_used_point ) {
					$user->used_total_points -= $point;
					if ( $user->used_total_points < 0 ) {
						$user->used_total_points = 0;
					}
				}
			} else {
				if ( $user->points < $point ) {
					$point        = $user->points;
					$user->points = 0;
				} else {
					$user->points -= $point;
				}

				if ( $is_update_used_point ) {
					$user->used_total_points += $point;
				}
				if ( $user->points <= 0 ) {
					$user->points = 0;
				}
			}

			$birthday_date = isset( $action_data['birthday_date'] ) && ! empty( $action_data['birthday_date'] ) ? $action_data['birthday_date'] : $user->birthday_date;
			$birth_date    = empty( $birthday_date ) || $birthday_date == '0000-00-00' ? $user->birth_date : strtotime( $birthday_date );
			$_data         = [
				'points'            => (int) $user->points,
				'earn_total_point'  => (int) $user->earn_total_point,
				'birth_date'        => $birth_date,
				'birthday_date'     => $birthday_date,
				'used_total_points' => (int) $user->used_total_points,
			];
		} else {
			if ( $trans_type == 'debit' ) {
				$point = 0;
			}
			$ref_code        = isset( $action_data['referral_code'] ) && ! empty( $action_data['referral_code'] ) ? $action_data['referral_code'] : '';
			$uniqueReferCode = $this->get_unique_refer_code( $ref_code, false, $action_data['user_email'] );
			$_data           = [
				'user_email'        => sanitize_email( $action_data['user_email'] ),
				'refer_code'        => $uniqueReferCode,
				'used_total_points' => 0,
				'points'            => (int) $point,
				'earn_total_point'  => (int) $point,
				'birth_date'        => 0,
				'birthday_date'     => null,
				'created_date'      => $created_at,
			];
		}
		$ledger_data = [
			'user_email'          => $action_data['user_email'],
			'points'              => (int) $point,
			'action_type'         => $action_type,
			'action_process_type' => isset( $action_data['action_process_type'] ) && ! empty( $action_data['action_process_type'] ) ? $action_data['action_process_type'] : $action_type,
			'note'                => isset( $action_data['note'] ) && ! empty( $action_data['note'] ) ? $action_data['note'] : '',
			'created_at'          => $created_at,
		];
		$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Ledger data:' . json_encode( $ledger_data ) );
		$ledger_status = $this->updatePointLedger( $ledger_data, $trans_type );
		$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', User data:' . json_encode( $_data ) );
		if ( $ledger_status && self::$user_model->insertOrUpdate( $_data, $id ) ) {
			$args = [
				'user_email'       => $action_data['user_email'],
				'action_type'      => $action_type,
				'campaign_type'    => 'point',
				'points'           => (int) $point,
				'transaction_type' => $trans_type,
				'campaign_id'      => (int) isset( $action_data['campaign_id'] ) && ! empty( $action_data['campaign_id'] ) ? $action_data['campaign_id'] : 0,
				'created_at'       => $created_at,
				'modified_at'      => 0,
				'product_id'       => (int) isset( $action_data['product_id'] ) && ! empty( $action_data['product_id'] ) ? $action_data['product_id'] : 0,
				'order_id'         => (int) isset( $action_data['order_id'] ) && ! empty( $action_data['order_id'] ) ? $action_data['order_id'] : 0,
				'order_currency'   => isset( $action_data['order_currency'] ) && ! empty( $action_data['order_currency'] ) ? $action_data['order_currency'] : '',
				'order_total'      => isset( $action_data['order_total'] ) && ! empty( $action_data['order_total'] ) ? $action_data['order_total'] : '',
				'referral_type'    => isset( $action_data['referral_type'] ) && ! empty( $action_data['referral_type'] ) ? $action_data['referral_type'] : '',
				'display_name'     => isset( $action_data['reward_display_name'] ) && ! empty( $action_data['reward_display_name'] ) ? $action_data['reward_display_name'] : null,
				'reward_id'        => (int) isset( $action_data['reward_id'] ) && ! empty( $action_data['reward_id'] ) ? $action_data['reward_id'] : 0,
				'admin_user_id'    => null,
				'log_data'         => '{}',
				'customer_command' => isset( $action_data['customer_command'] ) && ! empty( $action_data['customer_command'] ) ? $action_data['customer_command'] : '',
				'action_sub_type'  => isset( $action_data['action_sub_type'] ) && ! empty( $action_data['action_sub_type'] ) ? $action_data['action_sub_type'] : '',
				'action_sub_value' => isset( $action_data['action_sub_value'] ) && ! empty( $action_data['action_sub_value'] ) ? $action_data['action_sub_value'] : '',
			];
			if ( is_admin() ) {
				$admin_user            = wp_get_current_user();
				$args['admin_user_id'] = $admin_user->ID;
			}
			try {
				$earn_trans_id = 0;
				if ( $point > 0 || $force_update_earn_campaign ) {
					$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Earn Trans data:' . json_encode( $args ) );
					$earn_trans_id = self::$earn_campaign_transaction_model->insertRow( $args );
					$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Earn Trans id:' . $earn_trans_id );
					$earn_trans_id = apply_filters( 'wlr_after_add_extra_earn_point_transaction', $earn_trans_id,
						$args );
					if ( $earn_trans_id == 0 ) {
						$status = false;
					}
				}
				if ( $status ) {
					$log_data = [
						'user_email'          => sanitize_email( $action_data['user_email'] ),
						'action_type'         => $action_type,
						'earn_campaign_id'    => (int) $earn_trans_id > 0 ? $earn_trans_id : 0,
						'campaign_id'         => $args['campaign_id'],
						'note'                => $ledger_data['note'],
						'customer_note'       => isset( $action_data['customer_note'] ) && ! empty( $action_data['customer_note'] ) ? $action_data['customer_note'] : '',
						'order_id'            => $args['order_id'],
						'product_id'          => $args['product_id'],
						'admin_id'            => $args['admin_user_id'],
						'created_at'          => $created_at,
						'modified_at'         => 0,
						'points'              => (int) $point,
						'action_process_type' => $ledger_data['action_process_type'],
						'referral_type'       => isset( $action_data['referral_type'] ) && ! empty( $action_data['referral_type'] ) ? $action_data['referral_type'] : '',
						'reward_id'           => (int) isset( $action_data['reward_id'] ) && ! empty( $action_data['reward_id'] ) ? $action_data['reward_id'] : 0,
						'user_reward_id'      => (int) isset( $action_data['user_reward_id'] ) && ! empty( $action_data['user_reward_id'] ) ? $action_data['user_reward_id'] : 0,
						'expire_email_date'   => isset( $action_data['expire_email_date'] ) && ! empty( $action_data['expire_email_date'] ) ? $action_data['expire_email_date'] : 0,
						'expire_date'         => isset( $action_data['expire_date'] ) && ! empty( $action_data['expire_date'] ) ? $action_data['expire_date'] : 0,
						'reward_display_name' => isset( $action_data['reward_display_name'] ) && ! empty( $action_data['reward_display_name'] ) ? $action_data['reward_display_name'] : null,
						'required_points'     => (int) isset( $action_data['required_points'] ) && ! empty( $action_data['required_points'] ) ? $action_data['required_points'] : 0,
						'discount_code'       => isset( $action_data['discount_code'] ) && ! empty( $action_data['discount_code'] ) ? $action_data['discount_code'] : null,
					];
					$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Log data:' . json_encode( $log_data ) );
					$this->add_note( $log_data );
				}
			} catch ( Exception $e ) {
				$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Trans/Log Exception:' . $e->getMessage() );
				$status = false;
			}
		} else {
			$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', User save failed' );
			$status = false;
		}
		$woocommerce_helper->_log( 'Extra Action :' . $action_type . ',Point:' . $point . ', Extra Action status:' . $status );
		if ( $status ) {
			\WC_Emails::instance();
			do_action( 'wlr_after_add_extra_earn_point', $action_data['user_email'], $point, $action_type,
				$action_data );
			do_action( 'wlr_notify_after_add_extra_earn_point', $action_data['user_email'], $point, $action_type,
				$action_data );
		}

		return $status;
	}

	function isValidExtraAction( $action_type ) {
		$status       = false;
		$action_types = $this->getExtraActionList();
		if ( ! empty( $action_type ) && isset( $action_types[ $action_type ] ) && ! empty( $action_types[ $action_type ] ) ) {
			$status = true;
		}

		return $status;
	}

	public function get_coupon_expiry_date( $expiry_date, $as_timestamp = false ) {
		if ( ! empty( $expiry_date ) && '' != $expiry_date ) {
			if ( $as_timestamp ) {
				return strtotime( $expiry_date );
			}

			return date( 'Y-m-d', strtotime( $expiry_date ) );
		}

		return '';
	}
}