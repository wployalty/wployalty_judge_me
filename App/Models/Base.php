<?php

namespace Wljm\App\Models;

abstract class Base {
	public static $tables, $field_list = [];
	static protected $db;
	protected $table = null, $primary_key = null, $fields = [];

	function __construct() {
		global $wpdb;
		self::$db = $wpdb;
	}

	function getTableName() {
		return $this->table;
	}

	function getTablePrefix() {
		return self::$db->prefix;
	}

	function getByKey( $key ) {
		$key   = sanitize_key( $key );
		$query = self::$db->prepare( "SELECT * FROM {$this->table} WHERE `{$this->primary_key}` = %d;", [ $key ] );

		return self::$db->get_row( $query, OBJECT );
	}

	function getWhere( $where, $select = '*', $single = true ) {
		if ( is_array( $select ) || is_object( $select ) ) {
			$select = implode( ',', $select );
		}
		if ( empty( $select ) ) {
			$select = '*';
		}
		$query = "SELECT {$select} FROM {$this->table} WHERE {$where};";
		if ( $single ) {
			return self::$db->get_row( $query, OBJECT );
		} else {
			return self::$db->get_results( $query, OBJECT );
		}
	}

	protected function getSearchStopWords() {
		// Translators: This is a comma-separated list of very common words that should be excluded from a search, like a, an, and the. These are usually called "stopwords". You should not simply translate these individual words into your language. Instead, look for and provide commonly accepted stopwords in your language.
		return array_map(
			'wc_strtolower',
			array_map(
				'trim',
				explode(
					',',
					_x(
						'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
						'Comma-separated list of search stopwords in your language',
						'woocommerce'
					)
				)
			)
		);
	}

	function deleteRow( $where ) {
		return self::$db->delete( $this->table, $where );
	}

	function getValidSearchWords( $terms ) {
		$valid_terms = [];
		$stopwords   = $this->getSearchStopWords();

		foreach ( $terms as $term ) {
			// keep before/after spaces when term is for exact match, otherwise trim quotes and spaces.
			if ( preg_match( '/^".+"$/', $term ) ) {
				$term = trim( $term, "\"'" );
			} else {
				$term = trim( $term, "\"' " );
			}
			// Avoid single A-Z and single dashes.
			if ( empty( $term ) || ( strlen( $term ) <= 0 && preg_match( '/^[a-z\-]$/i', $term ) ) ) {
				continue;
			}

			if ( in_array( wc_strtolower( $term ), $stopwords, true ) ) {
				continue;
			}

			$valid_terms[] = $term;
		}

		return $valid_terms;
	}

	function getQueryData( $data = [], $select = '*', $search_fields = [], $order_by = true, $is_single = true ) {
		if ( empty( $data ) || empty( $select ) ) {
			return [];
		}
		$search         = isset( $data['search'] ) && ! empty( $data['search'] ) ? $data['search'] : '';
		$campaign_where = '';
		if ( ! empty( $search ) && preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $search, $matches ) ) {
			$search_terms = $this->getValidSearchWords( $matches[0] );
			$search_and   = '';
			foreach ( $search_terms as $search_term ) {
				$like         = '%' . self::$db->esc_like( $search_term ) . '%';
				$search_where = [];
				foreach ( $search_fields as $key ) {
					$search_where[] = self::$db->prepare( '(' . $key . ' like %s)', [ $like ] );
				}

				if ( ! empty( $search_where ) ) {
					$campaign_where .= $search_and . ' ' . '(' . implode( ' OR ', $search_where ) . ')';
				}
				$search_and = ' AND ';
			}
		}

		$conditions                   = [];
		$fields                       = $this->fields;
		$fields[ $this->primary_key ] = '%d';
		foreach ( $fields as $field => $value ) {
			$field_option = isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ? $data[ $field ] : [];

			if ( isset( $field_option['operator'] ) && ! empty( $field_option['operator'] ) && isset( $field_option['value'] ) ) {
				$conditions[] = self::$db->prepare( $field . ' ' . $field_option['operator'] . ' %s',
					[ $field_option['value'] ] );
			}
		}
		$campaign_where .= ! empty( $campaign_where ) ? ' AND ' . implode( ' AND ', $conditions ) : implode( ' AND ',
			$conditions );
		if ( $order_by && ! empty( $campaign_where ) ) {
			$filter_order          = (string) isset( $data['filter_order'] ) && ! empty( $data['filter_order'] ) ? $data['filter_order'] : 'id';
			$filter_order_dir      = (string) isset( $data['filter_order_dir'] ) && ! empty( $data['filter_order_dir'] ) ? $data['filter_order_dir'] : 'DESC';
			$campaign_order_by_sql = sanitize_sql_orderby( "{$filter_order} {$filter_order_dir}" );
			if ( ! empty( $campaign_order_by_sql ) ) {
				$campaign_where .= " ORDER BY {$campaign_order_by_sql}";
			}
		}
		$limit  = (int) isset( $data['limit'] ) && ! empty( $data['limit'] ) ? $data['limit'] : 0;
		$offset = (int) isset( $data['offset'] ) && ! empty( $data['offset'] ) ? $data['offset'] : 0;
		if ( $limit > 0 && ! empty( $campaign_where ) && ! $is_single ) {
			$campaign_where .= self::$db->prepare( ' LIMIT %d OFFSET %d', [ $limit, $offset ] );
		}

		return $this->getWhere( $campaign_where, $select, $is_single );
	}

	function insertRow( $data ) {
		if ( ! empty( $this->fields ) ) {
			$columns       = implode( '`,`', array_keys( $this->fields ) );
			$values        = implode( ',', $this->fields );
			$actual_values = $this->formatData( $data );
			$query         = self::$db->prepare( "INSERT INTO {$this->table} (`{$columns}`) VALUES ({$values});",
				$actual_values );
			self::$db->query( $query );

			return self::$db->insert_id;
		}

		return 0;
	}

	function updateRow( $data, $where = [] ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		return self::$db->update( $this->table, $data, $where );
	}

	function formatData( $data ) {
		if ( empty( $this->fields ) ) {
			return [];
		}
		$result = [];
		foreach ( $this->fields as $key => $value ) {
			$key = trim( $key );
			if ( '%d' == trim( $value ) ) {
				$value = intval( isset( $data[ $key ] ) ? $data[ $key ] : 0 );
			} elseif ( '%f' == trim( $value ) ) {
				$value = floatval( isset( $data[ $key ] ) ? $data[ $key ] : 0 );
			} else {
				$value = isset( $data[ $key ] ) ? $data[ $key ] : null;
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = json_encode( $value );
				}
			}
			$result[ $key ] = $value;
		}

		return $result;
	}

}