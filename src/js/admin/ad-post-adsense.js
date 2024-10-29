import { Grid, html } from "gridjs";
import "gridjs/dist/theme/mermaid.css";
import Cookies from "js-cookie";

jQuery(document).ready(function ($) {
	/**
	 * HTML elements
	 */
	let $gridjs_head;

	const $adsense_settings = $("#adcmdradsensediv");

	const $unsupported_message = $adsense_settings.find(
		"#adcmdr_adsense_unsupported"
	);

	const $inactive_message = $adsense_settings.find("#adcmdr_adsense_inactive");

	const $adsense_ad_list = $adsense_settings.find("#adcmdr_adsense_ad_list");

	const $adsense_mode = $adsense_settings.find(
		'input[name="_adcmdr_adsense_ad_mode"]'
	);

	const $meta_enable_amp = $adsense_settings.find(
		'input[name="_adcmdr_adsense_ad_enable_amp"]'
	);

	const $meta_pub_id = $adsense_settings.find("#_adcmdr_adsense_ad_pub_id");

	const $pub_id_display = $adsense_settings.find(".adcmdr-pub-id-display");

	const $adsense_manual_meta_fields = $adsense_settings
		.find("#adcmdr-adsense-ad-fields--manual")
		.find("input, select");

	const $meta_slot_id = $adsense_manual_meta_fields.filter(
		"#_adcmdr_adsense_adslot_id"
	);

	const $meta_ad_type = $adsense_manual_meta_fields.filter(
		"#_adcmdr_adsense_ad_format"
	);

	const $meta_responsive_full_width = $adsense_manual_meta_fields.filter(
		'input[name="_adcmdr_adsense_full_width_responsive"]'
	);

	const $meta_size_width = $adsense_manual_meta_fields.filter(
		"#_adcmdr_adsense_size_width"
	);

	const $meta_size_height = $adsense_manual_meta_fields.filter(
		"#_adcmdr_adsense_size_height"
	);

	const $meta_layout_key = $adsense_manual_meta_fields.filter(
		"#_adcmdr_adsense_layout_key"
	);

	const $meta_multiplex_ui_type = $adsense_manual_meta_fields.filter(
		"#_adcmdr_adsense_multiplex_uitype"
	);

	/**
	 * Data handling variables
	 */
	const filteredCookie = "adcmdr_admin_adsense_filtered";
	let loadedAds = [];
	let cachedAds = [];
	let apiCallsRemaining = parseInt(adcmdr_adpost.adsense.quota.remaining, 10);
	let currentAd;
	let fetching = false;
	let currentGrid;
	let filtered = {
		hide_unsupported: false,
		hide_inactive: false,
	};

	/**
	 * Create the grid filter
	 */
	function grid_filter() {
		let grid_filter = `<div id="adcmdr_grid_filter">
								<div class="woforms-input-group">
									<span><input type="checkbox" id="_adcmdr_grid_hide_1" name="_adcmdr_grid_hide[]" value="hide_inactive"`;

		if (
			filtered.hide_inactive === true &&
			(!currentAd ||
				typeof currentAd.active === "undefined" ||
				currentAd.active !== false)
		) {
			grid_filter += ` checked`;
		} else if (
			currentAd &&
			typeof currentAd.active !== "undefined" &&
			currentAd.active === false
		) {
			grid_filter += ` disabled`;
		}

		grid_filter += `><label for="_adcmdr_grid_hide_1">${adcmdr_adpost.adsense.translations.hide_inactive}</label></span>
						<span><input type="checkbox" id="_adcmdr_grid_hide_2" name="_adcmdr_grid_hide[]" value="hide_unsupported"`;

		if (
			filtered.hide_unsupported === true &&
			(!currentAd ||
				typeof currentAd.unsupported === "undefined" ||
				currentAd.unsupported !== true)
		) {
			grid_filter += ` checked`;
		} else if (
			currentAd &&
			typeof currentAd.unsupported !== "undefined" &&
			currentAd.unsupported === true
		) {
			grid_filter += ` disabled`;
		}

		grid_filter += `><label for="_adcmdr_grid_hide_2">${adcmdr_adpost.adsense.translations.hide_unsupported}</label></span>
								</div>
						<a href="#adcmdrrefresh" class="button button-secondary"`;

		if (apiCallsRemaining <= 0) {
			grid_filter += ` disabled`;
		}

		grid_filter += `>${adcmdr_adpost.adsense.translations.refresh_ads}</a>
						</div>`;

		return grid_filter;
	}

	/**
	 * Display a generic error message.
	 */
	function update_generic_error(message) {
		const $adcmdr_error = $adsense_settings.find("#adcmdr_adsense_error");

		if ($adcmdr_error.length > 0) {
			$adcmdr_error.remove();
		}

		if (message) {
			$(
				`<div id="adcmdr_adsense_error" class="adcmdr-notification adcmdr-metaitem__error"><p>${message}</p></div>`
			).insertBefore($adsense_ad_list);
		}
	}

	/**
	 * Start and end data fetching.
	 * Perform some tasks that should always be done during this time.
	 */
	function start_fetching() {
		fetching = true;
		update_generic_error("");
		$unsupported_message.hide();
		$inactive_message.hide();
		$adsense_ad_list.addClass("adcmdr-busy");
		$adsense_ad_list.prepend(`<span class="adcmdr-loader adcmdr-show"></span>`);
	}

	function end_fetching() {
		const $loader = $adsense_ad_list.find(".adcmdr-loader");
		if ($loader.length > 0) {
			$loader.remove();
		}

		checkApiQuota();
		$adsense_ad_list.removeClass("adcmdr-busy");
		fetching = false;
	}

	/**
	 * Parse ads into array for use in Grid.
	 * Handle some filter logic and other misc logic during parsing.
	 */
	function parseAds() {
		let parsedAds = [];
		let currentAdSlotId = $meta_slot_id.val();

		if (currentAdSlotId) {
			currentAdSlotId = currentAdSlotId.toString().trim().toUpperCase();
		}

		let $filters;
		if (
			$gridjs_head &&
			typeof $gridjs_head !== "undefined" &&
			$gridjs_head.length > 0
		) {
			$filters = $gridjs_head.find(
				'#adcmdr_grid_filter input[type="checkbox"]'
			);
		}

		loadedAds.forEach((item) => {
			item.slot_id = item.slot_id
				? item.slot_id.toString().trim().toUpperCase()
				: "";

			if (currentAdSlotId && item.slot_id === currentAdSlotId) {
				currentAd = item;

				if (item.unsupported === true) {
					filtered.hide_unsupported = false;

					if (
						$filters &&
						typeof $filters !== "undefined" &&
						$filters.length > 0
					) {
						$filters
							.filter('[value="hide_unsupported"]')
							.prop("checked", false)
							.prop("disabled", true);
					}
				}

				if (item.active === false) {
					filtered.hide_inactive = false;

					if (
						$filters &&
						typeof $filters !== "undefined" &&
						$filters.length > 0
					) {
						$filters
							.filter('[value="hide_inactive"]')
							.prop("checked", false)
							.prop("disabled", true);
					}
				}
			}

			if (filtered.hide_unsupported === true && item.unsupported) {
				return;
			}

			if (filtered.hide_inactive === true && item.active !== true) {
				return;
			}

			parsedAds.push([
				item.display_name,
				item.slot_id,
				item.display_type,
				item.size,
				item.id, // 4
				item.status, // 5
				item.unsupported, // 6
				item.active, // 7
			]);
		});

		return parsedAds;
	}

	/**
	 * Display ads in grid
	 */
	function display_ads() {
		let parsedAds = parseAds();
		let currentAdSlotId;

		if (
			currentAd &&
			typeof currentAd !== "undefined" &&
			currentAd.slot_id &&
			typeof currentAd.slot_id !== "undefined"
		) {
			currentAdSlotId = currentAd.slot_id;

			parsedAds.sort((a, b) => {
				return a[1].toString().toUpperCase() === currentAdSlotId ? -1 : 0;
			});
		}

		const gridCellAtrributes = (cell, row, column) => {
			// add these attributes to the td elements only
			let classNames = "gridjs-td adcmdr-adsense-ad-list__cell";

			if (row && row.cells[1].data) {
				if (
					currentAdSlotId &&
					row.cells[1].data.toString().trim().toUpperCase() === currentAdSlotId
				) {
					classNames += " adcmdr-adsense-ad-list__cell--current";
				}

				if (row.cells[6].data === true) {
					classNames += " adcmdr-adsense-ad-list__cell--unsupported";
				}
			}

			if (cell) {
				return {
					onclick: () => {
						fetch_ad(row.cells[4].data, row.cells[1].data);
					},
					className: classNames,
				};
			}
		};

		let tableCols = [
			{
				name: adcmdr_adpost.adsense.ad_table_columns.name,
				attributes: (cell, row, column) =>
					gridCellAtrributes(cell, row, column),
			},
			{
				name: adcmdr_adpost.adsense.ad_table_columns.slot_id,
				formatter: (cell, row) => {
					if (row.cells[5].data !== "ACTIVE") {
						return html(
							`${cell} <span class="adcmdr-block-label">${row.cells[5].data}</span>`
						);
					}

					return cell;
				},
				attributes: (cell, row, column) =>
					gridCellAtrributes(cell, row, column),
			},
			{
				name: adcmdr_adpost.adsense.ad_table_columns.type,
				formatter: (cell, row) => {
					if (row.cells[6].data === true) {
						return html(
							`${cell} <span class="adcmdr-block-label">${adcmdr_adpost.adsense.translations.unsupported}</span>`
						);
					}

					return cell;
				},
				attributes: (cell, row, column) =>
					gridCellAtrributes(cell, row, column),
			},
			{
				name: adcmdr_adpost.adsense.ad_table_columns.size,
				formatter: (cell, row) => {
					if (typeof cell === "string") {
						return cell;
					}

					if (typeof cell === "object") {
						return cell.width + "x" + cell.height;
					}

					return "";
				},
				attributes: (cell, row, column) =>
					gridCellAtrributes(cell, row, column),
			},
			{
				name: "id",
				hidden: true,
			},
			{
				name: "status",
				hidden: true,
			},
			{
				name: "unsupported",
				hidden: true,
			},
			{
				name: "active",
				hidden: true,
			},
		];

		if (currentGrid) {
			const $list_count = $adsense_ad_list.find(".adcmdr-ad-list-count");
			if ($list_count.length > 0) {
				$list_count.remove();
			}

			currentGrid
				.updateConfig({
					columns: tableCols,
					data: parsedAds,
				})
				.forceRender();
		} else {
			$adsense_ad_list.html("");

			currentGrid = new Grid({
				columns: tableCols,
				data: parsedAds,
				height: "300px",
				width: "100%",
				fixedHeader: true,
				search: true,
				pagination: false,
				sort: {
					multiColumn: false,
				},
				style: {
					container: {
						width: "100%",
					},
				},
			}).render($adsense_ad_list[0]);
		}

		$adsense_ad_list.append(
			`<div class="adcmdr-ad-list-count">${adcmdr_adpost.adsense.translations.total_ads} ${parsedAds.length}</div>`
		);

		if (
			currentAd &&
			typeof currentAd !== "undefined" &&
			typeof currentAd.active !== "undefined"
		) {
			if (currentAd.active === false) {
				$inactive_message.show();
			} else {
				$inactive_message.hide();
			}
		}

		if (
			currentAd &&
			typeof currentAd !== "undefined" &&
			typeof currentAd.unsupported !== "undefined"
		) {
			if (currentAd.unsupported === true) {
				$unsupported_message.show();
			}
		}

		$gridjs_head = $adsense_ad_list.find(".gridjs-head");
		if ($gridjs_head.length > 0) {
			$gridjs_head.append(grid_filter());

			const $controls = $gridjs_head.find("#adcmdr_grid_filter");
			const $refreshAds = $controls.find('a[href="#adcmdrrefresh"]');
			const $filters = $controls.find('input[type="checkbox"]');

			$filters.on("change", function () {
				filtered = {
					hide_unsupported: false,
					hide_inactive: false,
				};

				$filters.each(function () {
					const $this = $(this);
					if ($this.is(":checked")) {
						filtered[$this.val()] = true;
					}
				});

				Cookies.set(filteredCookie, JSON.stringify(filtered), {
					expires: 400, // maximum allowed cookie days in Chrome
				});

				display_ads();
			});

			$refreshAds.on("click", function (e) {
				e.preventDefault();
				fetch_all_ads(true);
			});
		}
	}

	/**
	 * Fetch a specfic ad
	 */
	function fetch_ad(adId, adSlotId) {
		if (apiCallsRemaining <= 0 || fetching) {
			return false;
		}

		start_fetching();

		if (
			currentAd &&
			typeof currentAd !== "undefined" &&
			currentAd.slot_id &&
			typeof currentAd.slot_id !== "undefined" &&
			adSlotId === currentAd.slot_id
		) {
			reset_ad_meta();
			display_ads();
			end_fetching();
			return;
		}

		if (typeof cachedAds[adId] !== "undefined") {
			update_ad_meta(cachedAds[adId]);
			display_ads();
			end_fetching();
			return;
		}

		const data = {
			action: adcmdr_adpost.adsense.actions.get_ad_by_code.action,
			security: adcmdr_adpost.adsense.actions.get_ad_by_code.security,
			ad_id: adId,
			adsense_id: $meta_pub_id.val(),
		};

		$.post(
			adcmdr_adpost.ajaxurl,
			data,
			function (response) {
				reset_ad_meta();
				apiCallsRemaining = response.data.quota.remaining;

				if (response.success) {
					cachedAds[adId] = response.data.ad;
					update_adsense_id_field(response.data.adsense_id);
					update_ad_meta(response.data.ad);
				} else {
					update_generic_error(response.data.message);
				}

				display_ads();
				end_fetching();
			},
			"json"
		);
	}

	/**
	 * Fetch all ads
	 */
	function fetch_all_ads(force = false) {
		if (fetching) {
			return false;
		}

		cachedAds = [];
		$adsense_ad_list.html("");
		start_fetching();

		const data = {
			action: adcmdr_adpost.adsense.actions.get_ad_units.action,
			security: adcmdr_adpost.adsense.actions.get_ad_units.security,
			force_refresh: force,
			adsense_id: $meta_pub_id.val(),
		};

		$.post(
			adcmdr_adpost.ajaxurl,
			data,
			function (response) {
				apiCallsRemaining = response.data.quota.remaining;

				if (response.success) {
					loadedAds = response.data.ads;
					update_adsense_id_field(response.data.adsense_id);
				} else {
					loadedAds = [];
					update_generic_error(response.data.message);
				}

				display_ads();
				end_fetching();
			},
			"json"
		);
	}

	/**
	 * Interface with manual meta fields.
	 */
	function reset_ad_meta() {
		currentAd = null;
		$inactive_message.hide();
		$unsupported_message.hide();

		$adsense_manual_meta_fields.each(function () {
			const $this = $(this);
			if (
				$this.is('input[type="radio"]') ||
				$this.is('input[type="checkbox"]')
			) {
				$this.prop("checked", false);
			} else if ($this.is("select")) {
				$this.find("option").prop("selected", false);
				$this.val("");
			} else {
				$this.val("");
			}
		});
	}

	/**
	 * Update meta fields with data from a directly integrated ad.
	 */
	function update_ad_meta(data) {
		reset_ad_meta();
		$meta_slot_id.val(data.slot_id);

		if (data.type === "DISPLAY") {
			if (typeof data.size === "object") {
				$meta_ad_type.val("normal");
				$meta_size_width.val(data.size.width);
				$meta_size_height.val(data.size.height);
			} else if (
				typeof data.size === "string" &&
				data.size.trim().toLowerCase() === "responsive"
			) {
				$meta_ad_type.val("responsive");
			}

			if (data.full_width_responsive) {
				$meta_responsive_full_width.prop("checked", false);
				$meta_responsive_full_width
					.filter('[value="' + data.full_width_responsive + '"]')
					.prop("checked", true);
			}
		} else if (data.type === "MATCHED_CONTENT") {
			$meta_ad_type.val("multiplex");
			if (typeof data.size === "object") {
				$meta_size_width.val(data.size.width);
				$meta_size_height.val(data.size.height);
			}
		} else if (data.type === "ARTICLE") {
			$meta_ad_type.val("inarticle");
			$meta_responsive_full_width.prop("checked", false);
			$meta_responsive_full_width
				.filter('[value="true"]')
				.prop("checked", true);
		} else if (data.type === "FEED") {
			$meta_ad_type.val("infeed");
		}

		$meta_ad_type.change();

		if (data.active === false) {
			$inactive_message.show();
		}
	}

	/**
	 * Toggled the adsense integration mode - update some UI and fetch ads if necessary.
	 */
	function toggled_adsense_mode() {
		$unsupported_message.hide();
		$inactive_message.hide();

		const mode = $adsense_mode.filter(":checked").val();

		if (mode === "direct") {
			fetch_all_ads(false);
		} else {
			$adsense_ad_list.html("");
		}
	}

	/**
	 * Update the publisher ID fields.
	 */
	function update_adsense_id_field(adsenseId) {
		$meta_pub_id.val(adsenseId);
		$pub_id_display.text(adsenseId);
	}

	/**
	 * check the API quota and modify UI if necessary.
	 */
	function checkApiQuota() {
		if (apiCallsRemaining <= 0) {
			$adsense_ad_list.addClass("adcmdr-api-quota-reached");
		} else {
			$adsense_ad_list.removeClass("adcmdr-api-quota-reached");
		}
	}

	/**
	 *
	 * Init and events
	 *
	 */

	/**
	 * Get the user filter controls from a cookie, so that they stay consistent when adding new ads.
	 */
	const user_filtered = Cookies.get(filteredCookie);

	if (typeof user_filtered !== "undefined") {
		filtered = JSON.parse(user_filtered);
	}

	/**
	 * Check the API quota
	 */
	checkApiQuota();

	/**
	 * Links in messaging that change the adsense ad mode.
	 */
	$('a[href="#adcmdr_adsense_mode"]').on("click", function (e) {
		e.preventDefault();
		const $this = $(this);
		const $mode = $adsense_mode.filter('[value="' + $this.data("mode") + '"]');
		if ($mode.length > 0) {
			$adsense_mode.not($mode).prop("checked", false);
			$mode.prop("checked", true);
			$mode.change();
			$this.blur();
		}
	});

	/**
	 * When the adsense ad mode is manually changed.
	 */
	$adsense_mode.on("change", function () {
		toggled_adsense_mode();
	});

	/**
	 * Event fired when the adsense settings become visible.
	 */
	$adsense_settings.on("setting_restrict_is_visible", function (e) {
		toggled_adsense_mode();
	});

	/**
	 * Restrict settings to hide/show when necessary.
	 */
	$meta_ad_type.setting_restrict({
		show_event: "ad_type_settings_is_visible",
		restricted_selector: ".adcmdr-adsensetype-restrict",
	});

	$adsense_mode.setting_restrict({
		show_event: "ad_mode_settings_is_visible",
		restricted_selector: ".adcmdr-adsense_ad_mode-restrict",
	});

	$meta_multiplex_ui_type.setting_restrict({
		show_event: "multiplex_ui_type_settings_is_visible",
		restricted_selector: ".adcmdr-multiplex-restrict",
	});

	$meta_enable_amp.setting_restrict({
		show_event: "amp_settings_is_visible",
		restricted_selector: ".adcmdr-ampmode-restrict",
	});
});
