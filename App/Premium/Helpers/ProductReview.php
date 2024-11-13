<?php

namespace Wljm\App\Premium\Helpers;

defined( 'ABSPATH' ) or die();

use Wljm\App\Helpers\Base;
use Wljm\App\Helpers\EarnCampaign;
use Wljm\App\Helpers\Woocommerce;

class ProductReview extends Base {
	public static $instance = null;

	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	function applyEarnProductReview( $action_data ) {
		if ( ! is_array( $action_data ) || empty( $action_data['user_email'] ) ) {
			return false;
		}
		if ( empty( $action_data['product_id'] ) ) {
			return false;
		}
		$status           = false;
		$earn_campaign    = EarnCampaign::getInstance();
		$cart_action_list = [ 'product_review' ];
		foreach ( $cart_action_list as $action_type ) {
			$variant_reward = $this->getTotalEarning( $action_type, [], $action_data );
			foreach ( $variant_reward as $campaign_id => $v_reward ) {
				if ( isset( $v_reward['point'] ) && ! empty( $v_reward['point'] ) && $v_reward['point'] > 0 ) {
					$status = $earn_campaign->addEarnCampaignPoint( $action_type, $v_reward['point'], $campaign_id,
						$action_data );
				}
				if ( isset( $v_reward['rewards'] ) && $v_reward['rewards'] ) {
					foreach ( $v_reward['rewards'] as $single_reward ) {
						$status = $earn_campaign->addEarnCampaignReward( $action_type, $single_reward, $campaign_id,
							$action_data );
					}
				}
			}
		}

		return $status;
	}

	function processMessage( $point_rule, $earning ) {
		$point             = isset( $earning['point'] ) && ! empty( $earning['point'] ) ? (int) $earning['point'] : 0;
		$rewards           = isset( $earning['rewards'] ) && ! empty( $earning['rewards'] ) ? (array) $earning['rewards'] : [];
		$available_rewards = '';
		foreach ( $rewards as $single_reward ) {
			if ( is_object( $single_reward ) && isset( $single_reward->display_name ) ) {
				$available_rewards .= __( $single_reward->display_name, 'wp-loyalty-judge-me' ) . ',';
			}
		}
		$available_rewards = trim( $available_rewards, ',' );
		$reward_count      = 0;
		if ( ! empty( $available_rewards ) ) {
			$reward_count = count( explode( ',', $available_rewards ) );
		}
		$display_message    = '';
		$woocommerce_helper = Woocommerce::getInstance();
		if ( ( $point > 0 || ! empty( $available_rewards ) ) ) {
			$message        = '';
			$review_message = isset( $point_rule->review_message ) && ! empty( $point_rule->review_message ) ? __( $point_rule->review_message,
				'wp-loyalty-judge-me' ) : '';
			if ( ! empty( $review_message ) ) {
				$message = '<span class="wlr-product-review-message">' . Woocommerce::getCleanHtml( $review_message ) . '</span>';
			}
			$point           = $this->roundPoints( $point );
			$short_code_list = [
				'{wlr_points}'       => $point > 0 ? $woocommerce_helper->numberFormatI18n( $point ) : '',
				'{wlr_points_label}' => $this->getPointLabel( $point ),
				'{wlr_reward_label}' => $this->getRewardLabel( $reward_count ),
				'{wlr_rewards}'      => $available_rewards
			];
			$display_message = $this->processShortCodes( $short_code_list, $message );
		}

		return $display_message;
	}

}