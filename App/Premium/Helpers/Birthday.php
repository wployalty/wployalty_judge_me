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

class Birthday extends Base {
	public static $instance = null;

	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	function processMessage( $point_rule, $earning ) {
		$message = '';
		if ( isset( $point_rule->birthday_message ) && ! empty( $point_rule->birthday_message ) ) {
			$message = '<span class="wlr-birthday-message">' . Woocommerce::getCleanHtml( $point_rule->birthday_message ) . '</span>';
		}
		$point             = isset( $earning['point'] ) && ! empty( $earning['point'] ) ? (int) $earning['point'] : 0;
		$rewards           = isset( $earning['rewards'] ) && ! empty( $earning['rewards'] ) ? (array) $earning['rewards'] : [];
		$available_rewards = '';
		foreach ( $rewards as $single_reward ) {
			if ( is_object( $single_reward ) && isset( $single_reward->display_name ) ) {
				$available_rewards .= $single_reward->display_name . ',';
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