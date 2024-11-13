<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Premium\Helpers;

defined( 'ABSPATH' ) or die();

use Wljm\App\Helpers\Base;
use Wljm\App\Helpers\Woocommerce;

class EmailShare extends Base {
	public static $instance = null;

	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	function processMessage( $point_rule, $earning ) {
		$message = [
			'subject' => '',
			'body'    => ''
		];
		if ( isset( $point_rule->share_subject ) && ! empty( $point_rule->share_subject ) ) {
			$message['subject'] = Woocommerce::getCleanHtml( __( $point_rule->share_subject, 'wp-loyalty-judge-me' ) );
		}
		if ( isset( $point_rule->share_body ) && ! empty( $point_rule->share_body ) ) {
			$message['body'] = Woocommerce::getCleanHtml( __( $point_rule->share_body, 'wp-loyalty-judge-me' ) );
		}
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
		$display_message    = [];
		$woocommerce_helper = Woocommerce::getInstance();
		if ( $point > 0 || ! empty( $available_rewards ) ) {
			$point           = $this->roundPoints( $point );
			$short_code_list = [
				'{wlr_points}'       => $point > 0 ? $woocommerce_helper->numberFormatI18n( $point ) : '',
				'{wlr_points_label}' => $this->getPointLabel( $point ),
				'{wlr_reward_label}' => $this->getRewardLabel( $reward_count ),
				'{wlr_rewards}'      => $available_rewards,
				'{wlr_referral_url}' => $this->getReferralUrl()
			];
			if ( ! empty( $message['subject'] ) ) {
				$display_message['subject'] = $this->processShortCodes( $short_code_list, $message['subject'] );
			}
			if ( ! empty( $message['body'] ) ) {
				$display_message['body'] = $this->processShortCodes( $short_code_list, $message['body'] );
			}
		}

		return $display_message;
	}
}