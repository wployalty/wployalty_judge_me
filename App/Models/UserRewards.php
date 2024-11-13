<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class UserRewards extends Base {
	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_user_rewards';
		$this->primary_key = 'id';
		$this->fields      = [
			'name'                   => '%s',
			'description'            => '%s',
			'email'                  => '%s',
			'reward_id'              => '%d',
			'campaign_id'            => '%d',
			'reward_type'            => '%s',
			// 'redeem_point','redeem_coupon'
			'action_type'            => '%s',
			// 'point_for_purchase', 'subtotal_based', etc..
			'discount_type'          => '%s',
			// 'free_product','free_shipping',etc..
			'discount_value'         => '%s',
			// reward value - in cart created reward value
			'reward_currency'        => '%s',
			// reward_value generate time, we must add current currency also
			'discount_code'          => '%s',
			// generated discount code
			'discount_id'            => '%d',
			// generated discount amount
			'display_name'           => '%s',
			'require_point'          => '%d',
			// required point for generate discount code
			'status'                 => '%s',
			// open -  reward still not active, but created(used for redeem_point type), active - reward created and active(user limit didn't reached), used - reward used(user limit reached),expired - reward expired
			'start_at'               => '%s',
			'end_at'                 => '%s',
			'icon'                   => '%s',
			'expire_email_date'      => '%s',
			'is_expire_email_send'   => '%d',
			'usage_limits'           => '%d',
			'conditions'             => '%s',
			'condition_relationship' => '%s',
			'free_product'           => '%s',
			'expire_after'           => '%d',
			'expire_period'          => '%s',
			'enable_expiry_email'    => '%d',
			'expire_email'           => '%d',
			'expire_email_period'    => '%s',
			'minimum_point'          => '%d',
			'maximum_point'          => '%d',
			'created_at'             => '%s',
			'modified_at'            => '%s',
			'is_show_reward'         => '%d',
			'coupon_type'            => '%s',
			'max_discount'           => '%d',
			'max_percentage'         => '%d'
		];
	}
}