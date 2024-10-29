<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Class for determining if an ad or group should display in a given scenario.
 */
class Targeting {

	/**
	 * Translate targeting meta into usable and/or groups.
	 *
	 * @param array|null $meta The meta to process.
	 *
	 * @return bool|array
	 */
	public static function parse_meta_to_target_groups( $meta ) {
		if ( ! $meta || empty( $meta ) || ! is_array( $meta ) ) {
			return false;
		}

		$groups = array();
		$i      = 0;

		foreach ( $meta as $value ) {
			if ( ! isset( $groups[ $i ] ) ) {
				$groups[ $i ] = array();
			}

			$groups[ $i ][] = $value;

			if ( $value['andor'] === 'or' ) {
				++$i;
			}
		}

		return $groups;
	}

	/**
	 * Determine if all ads are disabled.
	 *
	 * @return bool
	 */
	public static function ads_disabled_all() {
		return Options::instance()->get( 'disable_all', 'general', true, false );
	}

	/**
	 * Check all andor condition groups.
	 *
	 * @param array|null|bool $meta The meta value that contains our conditions.
	 * @param string          $context The type of and/or group we are checking.
	 *
	 * @return bool
	 */
	public static function check_andor_groups( $meta, $context, $object_type, $object_id ) {

		if ( ! $meta || empty( $meta ) ) {
			return true;
		}

		/**
		 * Request a properly formatted array for analyzing and/or conditions.
		 */
		$condition_orgroups = self::parse_meta_to_target_groups( $meta );

		if ( ! $condition_orgroups || empty( $condition_orgroups ) ) {
			return true;
		}

		/**
		 * By default, we fail.
		 */
		$pass_or = false;

		if ( $context === 'content' ) {
			$targeting_instance = new TargetingContent();
		} else {
			$targeting_instance = new TargetingVisitor();
		}

		foreach ( $condition_orgroups as $condition_orgroup ) {
			if ( empty( $condition_orgroup ) ) {
				continue;
			}

			/**
			 * By default, the current condition passes.
			 */
			$pass_and = true;

			foreach ( $condition_orgroup as $condition_and ) {
				if ( ! $targeting_instance->condition_check( $condition_and, $object_type, $object_id ) ) {
					$pass_and = false;
					break;
				}
			}

			/**
			 * If an AND condition passed, we have satisfied the 'or' requirement.
			 */
			if ( $pass_and ) {
				$pass_or = true;
				break;
			}
		}

		return $pass_or;
	}

	/**
	 * Pass or fail a condition by comparing some current values with a second set of values using a specified comparison.
	 *
	 * @param mixed $currents The current values to check.
	 * @param mixed $values The values that pass the condition.
	 * @param mixed $comparison The type of comparison.
	 *
	 * @return bool
	 */
	protected static function passfail_condition( $currents, $values, $comparison ) {
		$pass = false;

		/**
		 * Make sure our current values and our pass values are arrays.
		 * This just makes the overall code shorter and simpler than dealing with mixed values.
		 */
		$values   = Util::arrayify( $values, true );
		$currents = Util::arrayify( $currents, true );

		/**
		 * Store all our passes in an array.
		 */
		$passes = array();

		/**
		 * Loop through all of the current values.
		 */
		foreach ( $currents as $current ) {
			/**
			 * And loop through all of the values that will pass.
			 */
			foreach ( $values as $value ) {
				/**
				 * Compare the current/value set and save the pass result to the array.
				 */
				$compare  = self::compare( $current, $value, $comparison );
				$passes[] = $compare;
			}
		}

		$pass = false;
		/**
		 * By default, fail the check.
		 *
		 * Also grab the comparison type (all or any) for this particular comparison.
		 * The comparison "all" type doesn't exactly mean that all of the attributes are correct. Instead, it means that
		 * all of the attributes aren't matched. It is used with the "is not" comparison.
		 *
		 * An example would be: Author [is not] author1 or author2. We have to make sure that the current post isn't written by any of these authors.
		 * To accomplish this, we make sure all of the results are true and that none of them are false.
		 *
		 * In the inverse (Author [is] author 1 or author2) we only need 1 result to be true and the others can be false ("any").
		 *
		 * The naming is a bit misleading but works.
		 */
		$comparison_type = self::comparison_type( $comparison );

		if ( $comparison_type === 'all' ) {
			/**
			 * Only 1 boolean allowed and it has to be true.
			 */
			$passes = array_unique( $passes );
			$pass   = count( $passes ) === 1 && $passes[0] === true;
		} else {
			/**
			 * As many booleans as needed and only 1 of them has to be true.
			 */
			$pass = array_search( true, $passes, true ) !== false;
		}

		return $pass;
	}

	/**
	 * Determine if a comparison should pass all checks or any checks.
	 *
	 * @param string $comparison The comparison string.
	 *
	 * @return string
	 */
	protected static function comparison_type( $comparison ) {
		/**
		 * See the above explanation in self::passfail_condition() for what we're doing here.
		 */
		switch ( $comparison ) {
			case 'is_not':
				return 'all';
			break;

			default:
				return 'any';
			break;
		}
	}

	/**
	 * Compare two values using a specified comparison method.
	 *
	 * @param mixed  $value1 The first value.
	 * @param mixed  $value2 The second value.
	 * @param string $comparison The type of comparison.
	 *
	 * @return bool
	 */
	protected static function compare( $value1, $value2, $comparison ) {
		switch ( $comparison ) {
			case 'is':
				return self::is( $value1, $value2 );
			break;

			case 'is_not':
				return self::is( $value1, $value2, true );
			break;

			case 'contains':
				return self::contains( $value1, $value2 );
			break;

			case 'does_not_contain':
				return self::contains( $value1, $value2, true );
			break;

			case 'starts_with':
				return self::starts_with( $value1, $value2 );
			break;

			case 'does_not_start_with':
				return self::starts_with( $value1, $value2, true );
			break;

			case 'ends_with':
				return self::ends_with( $value1, $value2 );
			break;

			case 'does_not_end_with':
				return self::ends_with( $value1, $value2, true );
			break;

			case 'greater_than':
			case 'older_than':
				return self::number_compare( $value1, $value2 );
			break;

			case 'less_than':
			case 'newer_than':
				return self::number_compare( $value1, $value2, true );
			break;

			case 'equals':
				return self::number_compare( $value1, $value2, false, true );
			break;
		}
	}

	/**
	 * Basic `is` comparison.
	 *
	 * @param mixed $value1 The first value to compare.
	 * @param mixed $value2 The second value to compare.
	 * @param bool  $isnot Whether to do a negative comparison or not.
	 *
	 * @return bool
	 */
	protected static function is( $value1, $value2, $isnot = false ) {
		if ( $isnot ) {
			return $value1 !== $value2;
		}
		return $value1 === $value2;
	}

	/**
	 * Contains comparison.
	 *
	 * @param mixed $value1 The first value to compare.
	 * @param mixed $value2 The second value to compare.
	 * @param bool  $doesnot Whether to do a negative comparison or not.
	 *
	 * @return bool
	 */
	protected static function contains( $value1, $value2, $doesnot = false ) {

		if ( $value1 === null || $value2 === null ) {
			return false;
		}

		/**
		 * May consider accounting for arrays in $value2 here in the future.
		 * Currently not needed.
		 */
		if ( $doesnot ) {
			return stripos( $value1, $value2 ) === false;
		}

		return stripos( $value1, $value2 ) !== false;
	}

	/**
	 * Starts with comparison.
	 *
	 * @param mixed $value1 The first value to compare.
	 * @param mixed $value2 The second value to compare.
	 * @param bool  $doesnot Whether to do a negative comparison or not.
	 *
	 * @return bool
	 */
	protected static function starts_with( $value1, $value2, $doesnot = false ) {
		$string1 = strtolower( substr( $value1, 0, strlen( $value2 ) ) );
		$string2 = strtolower( $value2 );

		if ( $doesnot ) {
			return $string1 !== $string2;
		}
		return $string1 === $string2;
	}

	/**
	 * Ends with comparison.
	 *
	 * @param mixed $value1 The first value to compare.
	 * @param mixed $value2 The second value to compare.
	 * @param bool  $doesnot Whether to do a negative comparison or not.
	 *
	 * @return bool
	 */
	protected static function ends_with( $value1, $value2, $doesnot = false ) {
		$string1 = strtolower( substr( $value1, strlen( $value2 ) * -1 ) );
		$string2 = strtolower( $value2 );

		if ( $doesnot ) {
			return $string1 !== $string2;
		}
		return $string1 === $string2;
	}

	/**
	 * Number comparison for ints and time. Defaults to 'greater than' ('older than').
	 *
	 * @param mixed $value1 The first value to compare.
	 * @param mixed $value2 The second value to compare.
	 * @param bool  $less_than Whether to do a less than comparison or not.
	 * @param bool  $equals Whether to do an equals comparison or not.
	 *
	 * @return bool
	 */
	protected static function number_compare( $value1, $value2, $less_than = false, $equals = false ) {
		/**
		 * Neither older_than or newer_than account for something that is
		 * exactly equal. Right now this doesn't matter because it only happens for
		 * a single millisecond (comparing timestamps).
		 *
		 * If used on other types in the future, we may want to re-think this and allow for >= or <= comparisons.
		 */

		$value1 = intval( $value1 );
		$value2 = intval( $value2 );

		if ( $equals ) {
			return $value1 === $value2;
		}

		if ( $less_than ) {
			return $value1 < $value2;
		}

		return $value1 > $value2;
	}

	/**
	 * Shared function for checking various "is" style conditions in TargetingContent and TargetingVisitor when ads are loaded over ajax.
	 *
	 * @param string     $is The type of check.
	 * @param array|bool $values The values to check against, if any.
	 * @param string     $type The type of is_check (e.g., is_category).
	 * @param mixed      $current_value The current value to compare against $values.
	 *
	 * @return [type]
	 */
	public static function is_check( $is, $values, $type, $current_value ) {
		/**
		 * This isn't the correct page/archive/etc.
		 */
		if ( $type !== $is ) {
			return false;
		}

		/**
		 * All?
		 */
		if ( empty( $values ) || ! $values ) {
			return true;
		}

		/**
		 * Limited?
		 */
		return in_array( $current_value, Util::arrayify( $values ), true );
	}
}
