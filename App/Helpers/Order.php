<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Helpers;

defined( 'ABSPATH' ) or die;

class Order extends Base {
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
}