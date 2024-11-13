<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Helpers;

defined( 'ABSPATH' ) or die();

class PointForPurchase extends Order {
	public static $instance = null;

	public function __construct( $config = [] ) {
		parent::__construct( $config );
	}

	public static function getInstance( array $config = [] ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $config );
		}

		return self::$instance;
	}

	function processMessage( $point_rule, $earning ) {
		$messages             = [
			'single'   => '',
			'variable' => '',
		];
		$category_page        = ( is_shop() || is_product_category() );
		$category_page        = apply_filters( 'wlr_is_product_category_page', $category_page );
		$product_page         = is_product();
		$display_page         = isset( $point_rule->display_product_message_page ) && ! empty( $point_rule->display_product_message_page ) ? $point_rule->display_product_message_page : 'all';
		$msg_background_color = isset( $point_rule->product_message_background ) && ! empty( $point_rule->product_message_background ) ? $point_rule->product_message_background : '';
		$msg_text_color       = isset( $point_rule->product_message_text_color ) && ! empty( $point_rule->product_message_text_color ) ? $point_rule->product_message_text_color : '';
		$msg_border_color     = isset( $point_rule->product_message_border_color ) && ! empty( $point_rule->product_message_border_color ) ? $point_rule->product_message_border_color : '';
		$is_rounded_edge      = isset( $point_rule->is_rounded_edge ) && $point_rule->is_rounded_edge == 'yes';

		$point   = isset( $earning['point'] ) && ! empty( $earning['point'] ) ? (int) $earning['point'] : 0;
		$rewards = isset( $earning['rewards'] ) && ! empty( $earning['rewards'] ) ? (array) $earning['rewards'] : [];
		if ( empty( $point ) && empty( $rewards ) ) {
			return $messages;
		}
		$msg_style = 'display: block;padding: 10px;line-height: 25px;';
		if ( $is_rounded_edge ) {
			$msg_style .= 'border-radius: 7px;';
		}
		if ( ! empty( $msg_background_color ) ) {
			$msg_style .= 'background:' . $msg_background_color . ';';
		}
		if ( ! empty( $msg_text_color ) ) {
			$msg_style .= 'color:' . $msg_text_color . ';';
		}
		if ( ! empty( $msg_border_color ) ) {
			$msg_style .= 'border:1px solid;border-color:' . $msg_border_color . ';';
		}
		$single_product_message   = isset( $point_rule->single_product_message ) && ! empty( $point_rule->single_product_message ) ? __( $point_rule->single_product_message,
			'wp-loyalty-judge-me' ) : '';
		$variable_product_message = isset( $point_rule->variable_product_message ) && ! empty( $point_rule->variable_product_message ) ? __( $point_rule->variable_product_message,
			'wp-loyalty-judge-me' ) : '';
		if ( ( in_array( $display_page, [
					'all',
					'single'
				] ) && $product_page ) || ( in_array( $display_page, [ 'all', 'list' ] ) && $category_page ) ) {
			if ( ! empty( $single_product_message ) ) {
				$single_product_message = Woocommerce::getCleanHtml( $single_product_message );
				$messages['single']     = '<span class="wlr-product-message" style="' . esc_attr( $msg_style ) . '">' . $single_product_message . '</span>';
			}
			if ( ! empty( $variable_product_message ) ) {
				$variable_product_message = Woocommerce::getCleanHtml( $variable_product_message );
				$messages['variable']     = '<span class="wlr-product-message" style="' . esc_attr( $msg_style ) . '">' . $variable_product_message . '</span>';
			}
		}
		$available_rewards = '';
		foreach ( $rewards as $single_reward ) {
			if ( is_object( $single_reward ) && isset( $single_reward->display_name ) ) {
				$available_rewards .= __( $single_reward->display_name, 'wp-loyalty-judge-me' ) . ',';
			}
		}
		$available_rewards = trim( $available_rewards, ',' );
		$point             = $this->roundPoints( $point );
		$reward_count      = 0;
		if ( ! empty( $available_rewards ) ) {
			$reward_count = count( explode( ',', $available_rewards ) );
		}
		$woocommerce_helper = Woocommerce::getInstance();
		$short_code_list    = apply_filters( 'wlr_point_for_purchase_message_shortcodes', [
			'{wlr_points}'         => $point > 0 ? $woocommerce_helper->numberFormatI18n( $point ) : '',
			'{wlr_product_points}' => $point > 0 ? $woocommerce_helper->numberFormatI18n( $point ) : '',
			'{wlr_points_label}'   => $this->getPointLabel( $point ),
			'{wlr_reward_label}'   => $this->getRewardLabel( $reward_count ),
			'{wlr_rewards}'        => $available_rewards,
		] );
		foreach ( $messages as $key => $message ) {
			if ( $point > 0 || ! empty( $available_rewards ) ) {
				$messages[ $key ] = $this->processShortCodes( $short_code_list, $message );
			} else {
				$messages[ $key ] = '';
			}
		}

		return $messages;
	}

}