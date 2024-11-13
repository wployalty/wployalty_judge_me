<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class Users extends Base {
	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_users';
		$this->primary_key = 'id';
		$this->fields      = [
			'user_email'          => '%s',
			'refer_code'          => '%s',
			'points'              => '%s',
			'used_total_points'   => '%s',
			'earn_total_point'    => '%s',
			'birth_date'          => '%s',
			'level_id'            => '%s',
			'is_allow_send_email' => '%d',
			'created_date'        => '%s',
			'birthday_date'       => '%s',
			'last_login'          => '%s',
			'is_banned_user'      => '%d',
		];
	}

	function insertOrUpdate( $data, $id = 0 ) {
		if ( empty( $data ) ) {
			return false;
		}
		$user = new \stdClass();
		if ( $id > 0 ) {
			$user = $this->getByKey( $id );
		}
		$user_fields = [
			'user_email'          => '',
			'refer_code'          => '',
			'points'              => 0,
			'used_total_points'   => 0,
			'earn_total_point'    => 0,
			'birth_date'          => 0,
			'level_id'            => 0,
			'is_allow_send_email' => 1,
			'created_date'        => 0,
			'birthday_date'       => null,
			'last_login'          => 0,
			'is_banned_user'      => 0
		];
		foreach ( $user_fields as $field_name => $field_value ) {
			$user_fields[ $field_name ] = ( isset( $data[ $field_name ] ) ) ? $data[ $field_name ] :
				( isset( $user ) && ! empty( $user ) && isset( $user->$field_name ) ? $user->$field_name : $field_value );
		}
		$old_level_id            = $user_fields['level_id'];
		$user_fields['level_id'] = apply_filters( 'wlr_user_level_id', $user_fields['level_id'],
			$user_fields['earn_total_point'], $user_fields );
		if ( ! empty( $id ) && $id > 0 && ! empty( $user ) ) {
			$this->updateRow( $user_fields, [ 'id' => $user->id ] );
			$status = true;
		} else {
			$status = $this->insertRow( $user_fields );
		}
		if ( $status && ( $old_level_id != $user_fields['level_id'] ) ) {
			do_action( 'wlr_after_user_level_changed', $old_level_id, $user_fields );
		}

		return apply_filters( 'wlr_after_user_updated', $status, $user_fields );
	}
}