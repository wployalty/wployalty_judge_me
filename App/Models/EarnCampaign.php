<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class EarnCampaign extends Base {
	public static $campaign_actions;

	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_earn_campaign';
		$this->primary_key = 'id';
		$this->fields      = [
			'name'                   => '%s',
			'description'            => '%s',
			'active'                 => '%d',
			'ordering'               => '%d',
			'is_show_way_to_earn'    => '%d',
			'achievement_type'       => '%s',
			'levels'                 => '%s',
			'start_at'               => '%s',
			'end_at'                 => '%s',
			'icon'                   => '%s',
			'action_type'            => '%s',
			'campaign_type'          => '%s',
			'point_rule'             => '%s',
			'usage_limits'           => '%d',
			'condition_relationship' => '%s',
			'conditions'             => '%s',
			'priority'               => '%d',
			'created_at'             => '%s',
			'modified_at'            => '%s',
		];
	}

	function getCampaignByAction( $action_type ) {
		if ( empty( $action_type ) ) {
			return [];
		}
		if ( isset( self::$campaign_actions[ $action_type ] ) ) {
			return self::$campaign_actions[ $action_type ];
		}
		$current_date   = date( 'Y-m-d H:i:s' );
		$campaign_where = self::$db->prepare( '(start_at <= %s OR start_at=0) AND  (end_at >= %s OR end_at=0) AND action_type = %s AND active = %d ORDER BY %s',
			[
				strtotime( $current_date ),
				strtotime( $current_date ),
				$action_type,
				1,
				'priority,id'
			] );

		return self::$campaign_actions[ $action_type ] = $this->getWhere( $campaign_where, '*', false );
	}
}