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


class SignUp extends Base {
	public static $instance = null;

	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	function processMessage( $point_rule, $earning ) {
		$message = '';
		$point   = isset( $earning['point'] ) && ! empty( $earning['point'] ) ? (int) $earning['point'] : 0;
		$rewards = isset( $earning['rewards'] ) && ! empty( $earning['rewards'] ) ? (array) $earning['rewards'] : [];
		if ( $point <= 0 && empty( $rewards ) ) {
			return $message;
		}
		$signup_message = isset( $point_rule->signup_message ) && ! empty( $point_rule->signup_message ) ? __( $point_rule->signup_message,
			'wp-loyalty-judge-me' ) : '';
		if ( ! empty( $signup_message ) ) {
			$message = '<span class="wlr-signup-message">' . Woocommerce::getCleanHtml( $signup_message ) . '</span>';
		}
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
		if ( ( $point > 0 || ! empty( $available_rewards ) ) && ! empty( $message ) ) {
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