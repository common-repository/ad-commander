<?php
namespace ADCmdr;

/**
 * Local-specific tracking functions.
 * Extends primary Tracking class.
 */
class TrackingLocal extends Tracking {

	/**
	 * An instance of this class.
	 *
	 * @var TrackingLocal|null
	 */
	private static $instance = null;

	/**
	 * Create an instance of self if necessary and return it.
	 *
	 * @return TrackingLocal
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enqueue the front-end script for local tracking.
	 *
	 * @return string
	 */
	public function enqueue_track_local() {
		$track_local_handle = Util::ns( 'track-local' );

		wp_register_script( $track_local_handle, AdCommander::assets_url() . 'js/track-local.js', array(), AdCommander::version(), true );
		wp_enqueue_script( $track_local_handle );

		return $track_local_handle;
	}

	/**
	 * Gets the tracking table for a specific type of tracking.
	 *
	 * @param string $type Sting for impressions or clicks.
	 *
	 * @return string|null
	 */
	public static function get_tracking_table( $type ) {
		global $wpdb;

		$type = strtolower( trim( $type ) );

		if ( $type === 'impressions' || $type === 'clicks' ) {
			return $wpdb->prefix . Util::ns( $type, '_' );
		}

		return null;
	}

	/**
	 * Create local tracking tables for impressions and clicks.
	 * This is done on activation in most circumstances.
	 *
	 * @return void
	 */
	public static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		maybe_create_table(
			self::get_tracking_table( 'impressions' ),
			'CREATE TABLE IF NOT EXISTS ' . self::get_tracking_table( 'impressions' ) . " (
			`timestamp` BIGINT UNSIGNED NOT NULL,
			`ad_id` BIGINT UNSIGNED NOT NULL,
			`count` MEDIUMINT UNSIGNED NOT NULL,
			PRIMARY KEY (`timestamp`, `ad_id`)
			) $charset_collate"
		);

		maybe_create_table(
			self::get_tracking_table( 'clicks' ),
			'CREATE TABLE IF NOT EXISTS ' . self::get_tracking_table( 'clicks' ) . " (
			`timestamp` BIGINT UNSIGNED NOT NULL,
			`ad_id` BIGINT UNSIGNED NOT NULL,
			`count` MEDIUMINT UNSIGNED NOT NULL,
			PRIMARY KEY (`timestamp`, `ad_id`)
			) $charset_collate"
		);
	}

	/**
	 * Track an event.
	 *
	 * @param array|int $ad_ids One or more Ad IDs to track.
	 * @param string    $type The type of event (impressions or clicks).
	 * @param int|null  $timestamp Optionally specify the timestamp to track.
	 * @param int       $count The number to increment the event.
	 *
	 * @return void
	 */
	public function track( $ad_ids, $type, $timestamp = null, $count = 1 ) {
		$type = trim( strtolower( $type ) );

		if ( ! $ad_ids || ! self::can_track_local() || ( $type !== 'impressions' && $type !== 'clicks' ) ) {
			return;
		}

		$ad_ids = Util::arrayify( $ad_ids );

		global $wpdb;
		$table = self::get_tracking_table( $type );

		if ( ! $timestamp ) {
			$timestamp = Util::start_of_hour_timestamp();
		}

		foreach ( $ad_ids as $ad_id ) {
			if ( ! is_int( $ad_id ) || $ad_id <= 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This query should not be cached.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (`ad_id`, `timestamp`, `count`) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE `count` = `count` + %d', array( $table, $ad_id, $timestamp, $count, $count ) ) );
		}
	}

	/**
	 * Get the 'total' transient name for a specific type and specific Ad IDs.
	 * This transient stores the total number of impressions or clicks so we don't have to recount them every time.
	 *
	 * @param array|int $ad_ids One or more Ad IDs.
	 * @param string    $type The type of event (impressions or clicks).
	 * @param int|null  $start_ts Optionally specify the starting timestamp.
	 * @param int|null  $end_ts Optionally specify the ending timestamp.
	 *
	 * @return string
	 */
	public static function total_transient( $ad_ids, $type, $start_ts = null, $end_ts = null ) {
		if ( $type === 'impressions' ) {
			$t = 'i';
		} elseif ( $type === 'clicks' ) {
			$t = 'c';
		}

		if ( is_array( $ad_ids ) ) {
			$ad_ids = implode( '_', $ad_ids );
		}

		$transient = AdCommander::posttype_ad() . '_t' . $t . '_' . $ad_ids;

		if ( $start_ts ) {
			$transient .= '_' . $start_ts;
		}

		if ( $end_ts ) {
			$transient .= '_' . $end_ts;
		}

		return $transient;
	}

	/**
	 * Get total stats for specified Ad IDs.
	 *
	 * @param array|int|null $ad_ids One or more Ad IDs to total.
	 * @param string         $type The type of event (impressions or clicks).
	 * @param int|null       $start_ts Optionally specify the starting timestamp.
	 * @param int|null       $end_ts Optionally specify the ending timestamp.
	 * @param bool           $use_transient Whether to use the transient while fetching stats.
	 *
	 * @return int|bool
	 */
	public static function total_stats( $ad_ids = null, $type = 'impressions', $start_ts = null, $end_ts = null, $use_transient = true ) {

		$sum = false;

		/**
		 * Avoid querying all time stats with no limitations.
		 */
		if ( ( ! $ad_ids || empty( $ad_ids ) ) && ( $start_ts === null || $end_ts === null ) ) {
			return $sum;
		}

		/**
		 * Only use transient if we have ad IDs.
		 */
		if ( $use_transient && $ad_ids && ! empty( $ad_ids ) ) {
			$transient = self::total_transient( $ad_ids, $type, $start_ts, $end_ts );
			$sum       = get_transient( $transient );

			if ( $sum !== false ) {
				$sum = intval( $sum );
			}
		}

		/**
		 * These queries are written like this to staisfy plugin review requirements, but it is not ideal.
		 *
		 * TODO: https://stackoverflow.com/questions/54382863/collect-where-clause-array-with-join-using-wpdb-prepare-safely
		 * Consider something like this and how we were previously building queries.
		 * Put where clauses with placeholders into array
		 * Then loop through and wpdb->prepare each clause and append it to the main $sql variable
		 * Comebine that solution with the array_fill option currently in use.
		 */
		if ( $sum === false ) {
			global $wpdb;

			$only_ad_ids_placeholder = null;

			if ( $ad_ids && ! is_array( $ad_ids ) ) {
				$ad_ids = array( $ad_ids );
			}

			if ( $ad_ids && ! empty( $ad_ids ) ) {
				$ad_ids                  = array_map( 'absint', $ad_ids );
				$only_ad_ids_placeholder = implode( ', ', array_fill( 0, count( $ad_ids ), '%d' ) );
			}

			if ( $start_ts && ! $end_ts ) {
				$args = array( self::get_tracking_table( $type ), $start_ts );
				if ( $ad_ids && ! empty( $ad_ids ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(count) FROM %i WHERE `timestamp` >= %d AND ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $ad_ids ) ) );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(count) FROM %i WHERE `timestamp` >= %d', $args ) );
				}
			} elseif ( ! $start_ts && $end_ts ) {
				$args = array( self::get_tracking_table( $type ), $end_ts );
				if ( $ad_ids && ! empty( $ad_ids ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(count) FROM %i WHERE `timestamp` <= %d AND ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $ad_ids ) ) );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(count) FROM %i WHERE `timestamp` <= %d', $args ) );
				}
			} elseif ( $start_ts && $end_ts ) {
				$args = array( self::get_tracking_table( $type ), $start_ts, $end_ts );
				if ( $ad_ids && ! empty( $ad_ids ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(count) FROM %i WHERE `timestamp` >= %d AND `timestamp` <= %d AND ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $ad_ids ) ) );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(count) FROM %i WHERE `timestamp` >= %d AND `timestamp` <= %d', $args ) );
				}
			} elseif ( ! $start_ts && ! $end_ts && $ad_ids && ! empty( $ad_ids ) ) {
					$args = array( self::get_tracking_table( $type ) );
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
					$sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(count) FROM %i WHERE ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $ad_ids ) ) );
			} else {
				$args = array( self::get_tracking_table( $type ) );
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive and query results are already cahced in transient.
				$sum = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(count) FROM %i WHERE 1', array( self::get_tracking_table( $type ) ) ) );
			}

			$sum = intval( $sum );

			if ( $use_transient ) {
				// This transient is prefixed with our CPT name (which is prefixed with adcmdr).
				set_transient( $transient, $sum, MINUTE_IN_SECONDS );
			}
		}

		return $sum;
	}

	/**
	 * Get total impressions for specified Ad IDs.
	 *
	 * @param array|int|null $ad_ids Ad ID to total.
	 * @param int|null       $start_ts Optionally specify the starting timestamp.
	 * @param int|null       $end_ts Optionally specify the ending timestamp.
	 * @param bool           $use_transient Whether to use the transient while fetching stats.
	 *
	 * @return int
	 */
	public static function total_impressions( $ad_ids = null, $start_ts = null, $end_ts = null, $use_transient = true ) {
		return self::total_stats( $ad_ids, 'impressions', $start_ts, $end_ts, $use_transient );
	}

	/**
	 * Get total clicks for specified Ad IDs.
	 *
	 * @param array|int|null $ad_ids Ad ID to total.
	 * @param int|null       $start_ts Optionally specify the starting timestamp.
	 * @param int|null       $end_ts Optionally specify the ending timestamp.
	 * @param bool           $use_transient Whether to use the transient while fetching stats.
	 *
	 * @return int
	 */
	public static function total_clicks( $ad_ids = null, $start_ts = null, $end_ts = null, $use_transient = true ) {
		return self::total_stats( $ad_ids, 'clicks', $start_ts, $end_ts, $use_transient );
	}

	/**
	 * Find Ad IDs that have tracking stats.
	 *
	 * @param int|null $start_ts Optionally specify the starting timestamp.
	 * @param int|null $end_ts Optionally specify the ending timestamp.
	 * @param array    $only_ad_ids Limit the query to certain Ad IDs.
	 * @param array    $types  Impressions and/or clicks.
	 *
	 * @return array
	 */
	public static function ads_with_stats( $start_ts = null, $end_ts = null, $only_ad_ids = array(), $types = array( 'impressions', 'clicks' ) ) {
		global $wpdb;

		$ad_ids = array();

		if ( $only_ad_ids && ! is_array( $only_ad_ids ) ) {
			$only_ad_ids = array( $only_ad_ids );
		}

		if ( ! empty( $only_ad_ids ) ) {
			$only_ad_ids             = array_map( 'absint', $only_ad_ids );
			$only_ad_ids_placeholder = implode( ', ', array_fill( 0, count( $only_ad_ids ), '%d' ) );
		}

		/**
		 * These queries are written like this to satisfy plugin review requirements.
		 * Consider rewriting in the future (see $this->total_stats() notes).
		 */
		foreach ( $types as $type ) {

			if ( $start_ts && ! $end_ts ) {
				$args = array( self::get_tracking_table( $type ), $start_ts );
				if ( ! empty( $only_ad_ids ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT ad_id FROM %i WHERE `timestamp` >= %d AND ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $only_ad_ids ) ) );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( 'SELECT ad_id FROM %i WHERE `timestamp` >= %d', $args ) );
				}
			} elseif ( ! $start_ts && $end_ts ) {
				$args = array( self::get_tracking_table( $type ), $end_ts );
				if ( ! empty( $only_ad_ids ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT ad_id FROM %i WHERE `timestamp` <= %d AND ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $only_ad_ids ) ) );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( 'SELECT ad_id FROM %i WHERE `timestamp` <= %d', $args ) );
				}
			} elseif ( $start_ts && $end_ts ) {
				$args = array( self::get_tracking_table( $type ), $start_ts, $end_ts );
				if ( ! empty( $only_ad_ids ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT ad_id FROM %i WHERE `timestamp` >= %d AND `timestamp` <= %d AND ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $only_ad_ids ) ) );
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( 'SELECT ad_id FROM %i WHERE `timestamp` >= %d AND `timestamp` <= %d', $args ) );
				}
			} elseif ( ! $start_ts && ! $end_ts && ! empty( $only_ad_ids ) ) {
					$args = array( self::get_tracking_table( $type ) );
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT ad_id FROM %i WHERE ad_id IN ($only_ad_ids_placeholder)", array_merge( $args, $only_ad_ids ) ) );
			} else {
				$args = array( self::get_tracking_table( $type ) );
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- false positive + we don't want to cache this query (it's used for real-time reports in the admin).
					$results = $wpdb->get_results( $wpdb->prepare( 'SELECT ad_id FROM %i WHERE 1', array( self::get_tracking_table( $type ) ) ) );
			}

			if ( ! empty( $results ) ) {
				$ad_ids = array_merge( $ad_ids, wp_list_pluck( $results, 'ad_id' ) );
			}
		}

		return array_map( 'absint', array_unique( $ad_ids ) );
	}
}
