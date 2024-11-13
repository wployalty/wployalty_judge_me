<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class EarnCampaignTransactions extends Base {
	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_earn_campaign_transaction';
		$this->primary_key = 'id';
		$this->fields      = [
			'user_email'       => '%s',
			'action_type'      => '%s',
			'transaction_type' => '%s',
			'campaign_type'    => '%s',
			'referral_type'    => '%s',
			'points'           => '%s',
			'display_name'     => '%s',
			'campaign_id'      => '%d',
			'reward_id'        => '%s',
			'order_id'         => '%s',
			'order_currency'   => '%s',
			'order_total'      => '%s',
			'product_id'       => '%s',
			'admin_user_id'    => '%s',
			'log_data'         => '%s',
			'customer_command' => '%s',
			'action_sub_type'  => '%s',
			'action_sub_value' => '%s',
			'created_at'       => '%s',
			'modified_at'      => '%s',
		];
	}
}