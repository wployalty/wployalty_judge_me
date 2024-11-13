<?php
/**
 * @author      Wployalty (Alagesan)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wljm\App\Models;

defined( 'ABSPATH' ) or die();

class Logs extends Base {
	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . 'wlr_logs';
		$this->primary_key = 'id';
		$this->fields      = [
			'user_email'       => '%s',
			'action_type'      => '%s',
			'reward_id'        => '%d',
			'user_reward_id'   => '%d',
			'campaign_id'      => '%d',
			'earn_campaign_id' => '%d',
			'note'             => '%s',
			'customer_note'    => '%s',
			'order_id'         => '%s',
			'product_id'       => '%s',
			'admin_id'         => '%s',
			'created_at'       => '%s',
			'modified_at'      => '%s',

			'points'              => '%s',
			'expire_email_date'   => '%s',
			'expire_date'         => '%s',
			'action_process_type' => '%s',
			'reward_display_name' => '%s',
			'required_points'     => '%s',
			'discount_code'       => '%s',
			'referral_type'       => '%s'
		];
	}

	function saveLog( $data ) {
		if ( empty( $data ) || empty( $data['user_email'] ) ) {
			return false;
		}
		if ( ! sanitize_email( $data['user_email'] ) ) {
			return false;
		}
		$status      = false;
		$insert_data = [
			'user_email'     => sanitize_email( $data['user_email'] ),
			'action_type'    => isset( $data['action_type'] ) && ! empty( $data['action_type'] ) ? sanitize_text_field( $data['action_type'] ) : '',
			'reward_id'      => (int) isset( $data['reward_id'] ) && ! empty( $data['reward_id'] ) ? $data['reward_id'] : 0,
			'user_reward_id' => (int) isset( $data['user_reward_id'] ) && ! empty( $data['user_reward_id'] ) ? $data['user_reward_id'] : 0,
			'campaign_id'    => (int) isset( $data['campaign_id'] ) && ! empty( $data['campaign_id'] ) ? $data['campaign_id'] : 0,
			'customer_note'  => (string) isset( $data['customer_note'] ) && ! empty( $data['customer_note'] ) ? $data['customer_note'] : '',
			'note'           => (string) isset( $data['note'] ) && ! empty( $data['note'] ) ? $data['note'] : '',
			'order_id'       => (int) isset( $data['order_id'] ) && ! empty( $data['order_id'] ) ? $data['order_id'] : 0,
			'product_id'     => (int) isset( $data['product_id'] ) && ! empty( $data['product_id'] ) ? $data['product_id'] : 0,
			'admin_id'       => (int) isset( $data['admin_id'] ) && ! empty( $data['admin_id'] ) ? $data['admin_id'] : 0,
			'created_at'     => strtotime( date( 'Y-m-d H:i:s' ) ),
			'modified_at'    => 0,

			'points'              => (int) isset( $data['points'] ) && ! empty( $data['points'] ) ? $data['points'] : 0,
			'action_process_type' => isset( $data['action_process_type'] ) && ! empty( $data['action_process_type'] ) ? $data['action_process_type'] : null,
			'expire_email_date'   => isset( $data['expire_email_date'] ) && ! empty( $data['expire_email_date'] ) ? $data['expire_email_date'] : 0,
			'expire_date'         => isset( $data['expire_date'] ) && ! empty( $data['expire_date'] ) ? $data['expire_date'] : 0,
			'reward_display_name' => isset( $data['reward_display_name'] ) && ! empty( $data['reward_display_name'] ) ? $data['reward_display_name'] : null,
			'required_points'     => (int) isset( $data['required_points'] ) && ! empty( $data['required_points'] ) ? $data['required_points'] : 0,
			'discount_code'       => isset( $data['discount_code'] ) && ! empty( $data['discount_code'] ) ? $data['discount_code'] : null,
			'referral_type'       => isset( $data['referral_type'] ) && ! empty( $data['referral_type'] ) ? $data['referral_type'] : '',
		];
		if ( isset( $data['added_point'] ) && $data['added_point'] > 0 ) {
			$insert_data['points']              = $data['added_point'];
			$insert_data['action_process_type'] = 'earn_point';
		}
		if ( isset( $data['reduced_point'] ) && $data['reduced_point'] > 0 ) {
			$insert_data['points']              = $data['reduced_point'];
			$insert_data['action_process_type'] = 'reduce_point';
		}
		if ( $insert_data['action_type'] == 'expire_date_change' && isset( $data['expire_date'] ) && ! empty( $data['expire_date'] ) ) {
			$insert_data['action_process_type'] = $data['action_process_type'];//expiry_date
		}
		if ( $insert_data['action_type'] == 'expire_email_date_change' && isset( $data['expire_email_date'] ) && ! empty( $data['expire_email_date'] ) ) {
			$insert_data['action_process_type'] = $data['action_process_type'];//expiry_email
		}
		$insert_status = $this->insertRow( $insert_data );
		if ( $insert_status ) {
			$status = true;
		}

		return $status;
	}

}