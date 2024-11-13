<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class PointsLedger extends Base {
	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_points_ledger';
		$this->primary_key = 'id';
		$this->fields      = [
			'user_email'          => '%s',
			'action_type'         => '%s',
			'action_process_type' => '%s',
			'credit_points'       => '%s',
			'debit_points'        => '%s',
			'note'                => '%s',
			'created_at'          => '%s'
		];
	}
}