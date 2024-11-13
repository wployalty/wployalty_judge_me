<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class Rewards extends Base {

	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_rewards';
		$this->primary_key = 'id';
		$this->fields      = [
			'name'                   => '%s',
			'description'            => '%s',
			'reward_type'            => '%s',
			'discount_type'          => '%s',
			'discount_value'         => '%s',
			'free_product'           => '%s',
			'display_name'           => '%s',
			'require_point'          => '%d',
			'expire_after'           => '%d',
			'expire_period'          => '%s',
			'enable_expiry_email'    => '%d',
			'expire_email'           => '%d',
			'expire_email_period'    => '%s',
			'usage_limits'           => '%d',
			'conditions'             => '%s',
			'condition_relationship' => '%s',
			'active'                 => '%d',
			'ordering'               => '%d',
			'is_show_reward'         => '%d',
			'minimum_point'          => '%d',
			'maximum_point'          => '%d',
			'icon'                   => '%s',
			'created_at'             => '%s',
			'modified_at'            => '%s',
			'coupon_type'            => '%s',
			'max_discount'           => '%d',
			'max_percentage'         => '%d'
		];
	}

}