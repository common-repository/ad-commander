<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Class for determining if an ad or group should display in the current content scenario.
 */
class TargetingContent extends Targeting {
	/**
	 * The current WP_Post.
	 *
	 * @var WP_Post
	 */
	private $current_post;

	/**
	 * Provide context for ajax ad loading.
	 *
	 * TODO: Re-test all of these contexts.
	 *
	 * @return array
	 */
	public static function current_context() {
		global $post;

		$content = array();

		/**
		 * Home/Index
		 */
		if ( is_home() ) {
			$content['is_home'] = array(
				'value' => true,
			);
		}

		if ( is_front_page() ) {
			$content['is_front_page'] = array(
				'value' => true,
			);
		}

		/**
		 * Archives or single post?
		 */
		if ( is_post_type_archive() ) {
			$content['is_post_type_archive'] = array(
				'value' => get_query_var( 'post_type' ),
			);
		}

		if ( is_archive() ) {
			$content['is_archive'] = array(
				'value' => true,
			);
		} elseif ( is_singular() && isset( $post->ID ) ) {
			$content['is_singular'] = array(
				'value'     => $post->ID,
				'post_type' => $post->post_type,
			);
		}

		if ( is_category() ) {
			$content['is_category'] = array(
				'value' => get_query_var( 'cat' ),
			);
		} elseif ( is_tag() ) {

			$tag_id   = null;
			$tag_slug = get_query_var( 'tag' );

			if ( $tag_slug ) {
				$tag = get_term_by( 'slug', $tag_slug, 'post_tag' );
				if ( $tag && isset( $tag->term_id ) ) {
					$tag_id = $tag->term_id;
				}
			}

			$content['is_tag'] = array(
				'value' => $tag_id,
			);

		} elseif ( is_search() ) {
			$content['is_search'] = array(
				'value' => true,
			);
		} elseif ( is_author() ) {
			$content['is_author'] = array(
				'value' => get_query_var( 'author' ),
			);
		} elseif ( is_tax() ) {
			$content['is_tax'] = array(
				'value' => get_query_var( 'taxonomy' ),
			);
		} elseif ( is_date() ) {
			$content['is_date'] = array(
				'value' => true,
			);
		}

		/**
		 * Paged
		 */
		if ( is_paged() ) {
			$content['is_paged'] = array(
				'value' => get_query_var( 'paged' ),
			);
		}

		/**
		 * Specific singular types
		 */
		if ( is_attachment() ) {
			$content['is_attachment'] = array(
				'value' => true,
			);
		} elseif ( is_404() ) {
			$content['is_404'] = array(
				'value' => true,
			);
		}

		return array(
			'current_url'     => Util::current_url(),
			'content_context' => $content,
		);
	}

	/**
	 * Get post by ID, either from the stored variable or a query.
	 *
	 * @param int         $id The post ID.
	 * @param string|null $post_type The post type.
	 *
	 * @return WP_Post
	 */
	public function get_post( $id, $post_type = null ) {
		if ( isset( $this->current_post->ID ) && $id === $this->current_post->ID ) {
			return $this->current_post;
		}

		$this->current_post = Query::by_id( $id, Util::any_post_status(), $post_type );

		return $this->current_post;
	}

	/**
	 * Get an array of values for a target.
	 *
	 * @param string $target The current target.
	 *
	 * @return mixed
	 */
	public static function values( $target ) {
		switch ( $target ) {
			case 'archive_category':
			case 'category':
				return self::parse_categories_to_values();
				break;

			case 'archive_tag':
			case 'post_tag':
				return self::parse_tags_to_values();
				break;

			case 'archive_taxonomy':
				return self::parse_taxonomies_to_values();
				break;

			case 'archive_post_type':
				return self::parse_post_types_to_values( array( 'has_archive' => true ) );
				break;

			case 'post_type':
				return self::parse_post_types_to_values(
					array(
						'public' => true,
					),
					array( 'wp_router_page' ),
				);
				break;

			case 'archive_author':
			case 'author':
				return self::parse_authors_to_values();
				break;

			case 'page_template':
				return self::parse_page_templates_to_values();
				break;

			case 'amp':
			case 'attachment':
			case 'archive_date':
			case 'front_page':
			case 'blog_index':
			case '404':
			case 'search_results':
				return __( 'true', 'ad-commander' );
				break;

			default:
				return '';
			break;
		}
	}

	/**
	 * Get the type of value expected for a target.
	 *
	 * @param string $target The current target.
	 *
	 * @return string
	 */
	public static function value_type( $target ) {
		switch ( $target ) {
			case 'post_content':
			case 'url':
				return 'text';
				break;

			case 'specific_post':
			case 'parent_page':
				return 'autocomplete';
				break;

			case 'content_age':
			case 'pagination':
				return 'number';
				break;

			case 'amp':
			case 'attachment':
			case 'front_page':
			case 'archive_date':
			case 'blog_index':
			case '404':
			case 'search_results':
				return 'words';
				break;

			default:
				return 'checkgroup';
			break;
		}
	}

	public static function args( $target ) {
		switch ( $target ) {
			default:
				return array();
			break;
		}
	}

	public static function value_type_labels( $target ) {
		switch ( $target ) {
			default:
				return array();
			break;
		}
	}

	/**
	 * Determines if a target needs the current WP_Post.
	 *
	 * @param string $target The target to check.
	 *
	 * @return bool
	 */
	public function needs_post( $target ) {
		return in_array( $target, array( 'post_tag', 'category', 'parent_page', 'page_template', 'post_content', 'content_age', 'post_type' ), true );
	}

	/**
	 * Checks a content condition for pass/fail.
	 *
	 * @param mixed  $condition The condition array.
	 * @param string $object_type The type of object to check (ad or placement).
	 * @param int    $object_id The ID of the object to check.
	 *
	 * @return bool
	 */
	public function condition_check( $condition, $object_type, $object_id ) {
		/**
		 * Post information may be set later.
		 */
		$post_id   = false;
		$this_post = null;
		if ( wp_doing_ajax() ) {
			$is_ajax = true;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This ajax request is from front-end visitors and we don't want a nonce in this situation.
			if ( ! isset( $_REQUEST['content_context'] ) ) {
				return true;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This ajax request is from front-end visitors and we don't want a nonce in this situation
			$request_context = json_decode( sanitize_text_field( wp_unslash( $_REQUEST['content_context'] ) ), true );

			if ( ! $request_context || empty( $request_context ) ) {
				return true;
			}

			/**
			 * Sanitize all content context from the request.
			 */
			$content_context = array();

			foreach ( $request_context as $key => $context ) {
				$key                     = sanitize_key( $key );
				$content_context[ $key ] = array();

				foreach ( $context as $subkey => $value ) {
					$content_context[ $key ][ sanitize_key( $subkey ) ] = sanitize_text_field( $value );
				}
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This ajax request is from front-end visitors and we don't want a nonce in this situation.
			$current_url = isset( $_REQUEST['current_url'] ) ? sanitize_url( wp_unslash( $_REQUEST['current_url'] ) ) : wp_get_referer();

			/**
			 * Get a post if we need one.
			 */
			if ( isset( $content_context['is_singular'] ) ) {
				$post_id = absint( $content_context['is_singular']['value'] );

				if ( self::needs_post( $condition['target'] ) ) {
					$post_type = $content_context['is_singular']['post_type'];

					$this_post = $this->get_post( $post_id, $post_type );
					if ( ! $this_post ) {
						$post_id = false;
					}
				}
			}
		} else {
			/**
			 * These values will force standard WordPress checks for non-ajax requests.
			 * e.g., by using $content_type === false, self::is_front_page() will run WP's is_front_page() instead of our custom is_check()
			 */
			$is_ajax         = false;
			$content_context = array();
			$content_value   = '';
			$content_type    = false;
			$content_paged   = false;
			$current_url     = Util::current_url();

			global $post;
			if ( is_singular() && isset( $post->ID ) ) {
				$post_id   = $post->ID;
				$this_post = $post;
			}
		}

		/**
		 * In most cases, the values stored in our condition are what we will check against.
		 * In some cases below, we'll check against a boolean instead.
		 */
		if ( self::value_type( $condition['target'] ) === 'autocomplete' ) {
			$pass_values = isset( $condition['selected_post_ids'] ) ? $condition['selected_post_ids'] : array();
		} else {
			$pass_values = $condition['values'];
		}

		/**
		 * Setup the check and pass values for each target.
		 *
		 * TODO: Ability to target specific types of archives?
		 * TODO: Do we need a logic revamp for and/or scenarios?
		*/
		switch ( $condition['target'] ) {
			case 'archive_category':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_category', $content_context );
					$content_type           = $content_context_parsed['content_type'];
					$content_value          = $content_context_parsed['content_value'];
				}

				$check_value = self::is_category( $condition['values'], $content_type, absint( $content_value ) );
				$pass_values = true;
				break;

			case 'archive_tag':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_tag', $content_context );
					$content_type           = $content_context_parsed['content_type'];
					$content_value          = $content_context_parsed['content_value'];
				}

				$check_value = self::is_tag( $condition['values'], $content_type, absint( $content_value ) );
				$pass_values = true;
				break;

			case 'archive_author':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_author', $content_context );
					$content_type           = $content_context_parsed['content_type'];
					$content_value          = $content_context_parsed['content_value'];
				}

				$check_value = self::is_author( $condition['values'], $content_type, absint( $content_value ) );
				$pass_values = true;
				break;

			case 'archive_taxonomy':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_tax', $content_context );
					$content_type           = $content_context_parsed['content_type'];
					$content_value          = $content_context_parsed['content_value'];
				}

				$check_value = self::is_tax( $condition['values'], $content_type, $content_value );
				$pass_values = true;
				break;

			case 'archive_post_type':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_post_type_archive', $content_context );
					$content_type           = $content_context_parsed['content_type'];
					$content_value          = $content_context_parsed['content_value'];
				}

				$check_value = self::is_post_type_archive( $condition['values'], $content_type, $content_value );
				$pass_values = true;
				break;

			case '404':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_404', $content_context );
					$content_type           = $content_context_parsed['content_type'];
				}

				$check_value = self::is_404( $content_type );
				$pass_values = true;
				break;

			case 'search_results':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_search', $content_context );
					$content_type           = $content_context_parsed['content_type'];
				}

				$check_value = self::is_search( $content_type );
				$pass_values = true;
				break;

			case 'attachment':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_attachment', $content_context );
					$content_type           = $content_context_parsed['content_type'];
				}

				$check_value = self::is_attachment( $content_type );
				$pass_values = true;
				break;

			case 'front_page':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_front_page', $content_context );
					$content_type           = $content_context_parsed['content_type'];
				}

				$check_value = self::is_front_page( $content_type );
				$pass_values = true;
				break;

			case 'amp':
				/**
				 * AMP not served over AJAX
				 */
				if ( $is_ajax ) {
					$check_value = false;
				} else {
					$check_value = Amp::instance()->is_amp();
				}

				$pass_values = true;
				break;

			case 'blog_index':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_home', $content_context );
					$content_type           = $content_context_parsed['content_type'];
				}

				$check_value = self::is_home( $content_type );
				$pass_values = true;
				break;

			case 'archive_date':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_date', $content_context );
					$content_type           = $content_context_parsed['content_type'];
				}

				$check_value = self::is_date( $content_type );
				$pass_values = true;
				break;

			case 'author':
				$check_value = isset( $this_post->post_author ) ? intval( $this_post->post_author ) : null;
				break;

			case 'category':
			case 'post_tag':
				$check_value = false;
				$terms       = get_the_terms( $post_id, $condition['target'] );

				if ( $terms && ! is_wp_error( $terms ) ) {
					$check_value = wp_list_pluck( $terms, 'term_id' );
				}
				break;

			case 'parent_page':
				$check_value = ( isset( $this_post->post_parent ) ) ? $this_post->post_parent : null;
				break;

			case 'post_type':
				$check_value = ( isset( $this_post->post_type ) ) ? $this_post->post_type : null;
				break;

			case 'specific_post':
				$check_value = $post_id;
				break;

			case 'page_template':
				$check_value = get_page_template_slug( $post_id );
				break;

			case 'post_content':
				$check_value = self::prep_content_for_comparison( get_the_content( null, false, $post_id ) );
				break;

			case 'url':
				$check_value = $current_url;
				break;

			case 'content_age':
				$check_value = ( ( time() - get_post_time( 'U', false, $post_id ) ) / DAY_IN_SECONDS );
				break;

			case 'pagination':
				if ( $is_ajax ) {
					$content_context_parsed = $this->parse_context_for_target( 'is_paged', $content_context );
					$content_type           = $content_context_parsed['content_type'];
					$content_paged          = $content_context_parsed['content_value'];
				}

				$check_value = self::is_paged( $content_type, absint( $content_paged ) );
				break;
		}

		/**
		 * Check for a passfail result, or return true if we don't have a proper check value.
		 */
		return isset( $check_value ) ? self::passfail_condition( $check_value, $pass_values, $condition['condition'] ) : true;
	}

	/**
	 * Create content type and content value from ajax context.
	 *
	 * @param mixed $context_key The key to check.
	 * @param mixed $content_context The complete context array.
	 *
	 * @return array
	 */
	public function parse_context_for_target( $context_key, $content_context ) {

		if ( isset( $content_context[ $context_key ] ) ) {
			$content_type  = $context_key;
			$content_value = isset( $content_context[ $context_key ]['value'] ) ? $content_context[ $context_key ]['value'] : '';
		} else {
			$content_type  = 'not_' . $context_key;
			$content_value = '';
		}

		return array(
			'content_type'  => $content_type,
			'content_value' => $content_value,
		);
	}

	/**
	 * Is the current page an attachment?
	 *
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_attachment( $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_attachment();
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_attachment', false, $content_type, $content_value );
	}

	/**
	 * Are we currently on a page number?
	 *
	 * @param mixed  $condition_values The values to check (if limited).
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_paged( $content_type, $content_paged = false ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			if ( ! is_paged() ) {
				return 1;
			}

			$paged = absint( get_query_var( 'paged' ) );
			if ( ! $paged || $paged < 1 ) {
				return 1;
			}

			return $paged;
		}

		/**
		 * Ajax
		 */
		if ( $content_type !== 'is_paged' || ! $content_paged || $content_paged < 1 ) {
			return 1;
		}

		return absint( $content_paged );
	}

	/**
	 * Is the current page the front page?
	 *
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_front_page( $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_front_page();
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_front_page', false, $content_type, $content_value );
	}

	/**
	 * Is the current page the blog index?
	 *
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_home( $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_home();
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_home', false, $content_type, $content_value );
	}

	/**
	 * Is the current page a date archive?
	 *
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_date( $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_date();
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_date', false, $content_type, $content_value );
	}

	/**
	 * Is the current page a 404?
	 *
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_404( $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_404();
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_404', false, $content_type, $content_value );
	}

	/**
	 * Is the current page search results?
	 *
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_search( $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_search();
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_search', false, $content_type, $content_value );
	}

	/**
	 * Is the current page a category page?
	 *
	 * @param mixed  $condition_values The values to check (if limited).
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_category( $condition_values, $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_category( $condition_values );
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_category', $condition_values, $content_type, $content_value );
	}

	/**
	 * Is the current page a tag page?
	 *
	 * @param mixed  $condition_values The values to check (if limited).
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_tag( $condition_values, $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_tag( $condition_values );
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_tag', $condition_values, $content_type, $content_value );
	}

	/**
	 * Is the current page an author page?
	 *
	 * @param mixed  $condition_values The values to check (if limited).
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_author( $condition_values, $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_author( $condition_values );
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_author', $condition_values, $content_type, $content_value );
	}

	/**
	 * Is the current page a taxonomy page?
	 *
	 * @param mixed  $condition_values The values to check (if limited).
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_tax( $condition_values, $content_type = false, $content_value = false ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			if ( ! empty( $condition_values ) ) {
				foreach ( $condition_values as $value ) {
					if ( is_tax( $value ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_tax', $condition_values, $content_type, $content_value );
	}

	/**
	 * Is the current page a post type archive?
	 *
	 * @param mixed  $condition_values The values to check (if limited).
	 * @param bool   $content_type If the content type is specified (ajax).
	 * @param string $content_value The content value to check (ajax).
	 *
	 * @return bool
	 */
	public static function is_post_type_archive( $condition_values, $content_type = false, $content_value = '' ) {
		/**
		 * Not ajax
		 */
		if ( ! $content_type ) {
			return is_post_type_archive( $condition_values );
		}

		/**
		 * Ajax
		 */
		return self::is_check( 'is_post_type_archive', $condition_values, $content_type, $content_value );
	}

	/**
	 * Convert categories into key/value pairs.
	 *
	 * @return array
	 */
	public static function parse_categories_to_values() {
		return apply_filters( 'adcmdr_targeting_categories', Util::object_to_array( get_categories( array( 'hide_empty' => false ) ) ) );
	}

	/**
	 * Convert tags into key/value pairs.
	 *
	 * @return array
	 */
	public static function parse_tags_to_values() {
		return apply_filters( 'adcmdr_targeting_tags', Util::object_to_array( get_tags( array( 'hide_empty' => false ) ) ) );
	}

	/**
	 * Convert authors into key/value pairs.
	 *
	 * @return array
	 */
	public static function parse_authors_to_values() {
		return apply_filters( 'adcmdr_targeting_authors', Util::object_to_array( Util::get_authors(), 'id', 'display_name' ) );
	}

	/**
	 * Convert taxonomies into key/value pairs.
	 *
	 * @return array
	 */
	public static function parse_taxonomies_to_values() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		$taxonomies = array_filter(
			$taxonomies,
			function ( $tax ) {
				return ! in_array( $tax->name, array( 'category', 'post_tag', 'post_format' ) );
			}
		);

		return apply_filters( 'adcmdr_targeting_taxonomies', Util::object_to_array( $taxonomies, 'name', 'label' ) );
	}

	/**
	 * Convert post_types into key/value pairs.
	 *
	 * @return array
	 */
	public static function parse_post_types_to_values( $args = array(), $exclude = array( 'post', 'page', 'wp_router_page' ) ) {
		$post_types = get_post_types( $args, 'objects' );

		$post_types = array_filter(
			$post_types,
			function ( $post_type ) use ( $exclude ) {
				return ! in_array( $post_type->name, $exclude );
			}
		);

		return apply_filters( 'adcmdr_targeting_post_types', Util::object_to_array( $post_types, 'name', 'label' ) );
	}

	/**
	 * Get all page templates and format into key/value pairs.
	 *
	 * @return array
	 */
	public static function parse_page_templates_to_values() {
		$templates = get_page_templates();

		if ( empty( $templates ) ) {
			return array();
		}

		$parsed = array();
		foreach ( $templates as $name => $slug ) {
			$parsed[ $slug ] = $name;
		}

		return apply_filters( 'adcmdr_targeting_page_templates', $parsed );
	}

	/**
	 * Prepare string content for comparison.
	 * Removes HTML, linebreaks, shortcodes, and trims.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	private static function prep_content_for_comparison( $content ) {
		$content = strip_shortcodes( wp_strip_all_tags( $content ) );
		$content = str_replace( '  ', ' ', str_replace( array( "\r", "\n" ), ' ', $content ) );

		return trim( $content );
	}
}
