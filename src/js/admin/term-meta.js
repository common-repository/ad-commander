/**
 * Term is added.
 */
jQuery(document).ajaxComplete(function (event, xhr, options) {
	function shouldParseResponse() {
		return (
			adcmdr_term_meta &&
			adcmdr_term_meta !== "undefined" &&
			xhr &&
			xhr.readyState === 4 &&
			xhr.status === 200 &&
			options.data &&
			options.data.indexOf("action=add-tag") >= 0 &&
			options.data.indexOf("taxonomy=" + adcmdr_term_meta.tax_name) >= 0
		);
	}

	if (shouldParseResponse()) {
		const res = wpAjax.parseAjaxResponse(xhr.responseXML, "ajax-response");

		if (!res || res.errors) {
			return;
		}

		const termData = res.responses.find(function (element) {
			return element.what === "term";
		});

		if (termData && termData.supplemental) {
			let ref = window.location.pathname;
			if (window.location.search) {
				ref = ref + window.location.search;
			}

			const urlParts = [
				"taxonomy=" + adcmdr_term_meta.tax_name,
				"tag_ID=" + parseInt(termData.supplemental.term_id, 10),
				"post_type=" + adcmdr_term_meta.post_type,
				"wp_http_referer=" + encodeURIComponent(ref),
			];

			window.location = adcmdr_term_meta.terms_url + "?" + urlParts.join("&");
		}
	}
});

jQuery(document).ready(function ($) {
	const $group_ad_list = $(".adcmdr-group-ad-list");

	/**
	 * Single term
	 */
	if ($group_ad_list.length > 0) {
		/**
		 * Show preview rotate if we have one.
		 */
		new WORotateInit(
			document
				.getElementById("adcmdr-group-preview")
				.getElementsByClassName("adcmdr-rotate")
		);

		/**
		 * Show settings based on mode
		 */
		const $restricted = $(".adcmdr-mode-restrict");
		if ($restricted.length > 0) {
			const $mode = $("#_adcmdr_mode");

			if ($mode.length > 0) {
				function mode_changed() {
					const currentMode = $mode.val();
					const $toShow = $restricted.filter(
						".adcmdr-mode-restrict--" + currentMode
					);

					$restricted.not($toShow).hide();
					if ($toShow.length > 0) {
						$toShow.show();
					}
				}

				$mode.on("change", function () {
					mode_changed();
				});

				mode_changed();
			}
		}

		/**
		 * Group ad list management
		 */
		const $primary_submit = $('.edit-tag-actions input[type="submit"]');

		if ($primary_submit.length > 0) {
			$primary_submit.val(adcmdr_term_meta.save_button_text);
		}

		const $secondary_submit = $group_ad_list.find('input[type="submit"]');
		const $order_method = $('input[name="_adcmdr_order_method"]');
		const $table = $group_ad_list.find("table");
		const $noads = $table.siblings(".adcmdr-no-ads");
		const groupid = $group_ad_list.data("groupid");
		const $autocomplete = $group_ad_list.find("#adcmdr_search_ads");
		let $sortable = $group_ad_list.find("tbody");
		let selectedAds = [];

		/**
		 * Toggle some UI elements if 'weighted' method is selected.
		 */
		function toggle_weighted() {
			const $weighted_col = $group_ad_list.find(".adcmdr-weight");

			if ($weighted_col.length <= 0) {
				return;
			}

			if ($order_method.filter(":checked").val() === "weighted") {
				$weighted_col.show();
				$secondary_submit.css("display", "inline-block");
			} else {
				$weighted_col.hide();
				$secondary_submit.hide();
			}
		}

		/**
		 * Update the currently selected ads array.
		 */
		function updateSelectedAds() {
			selectedAds = [];
			$sortable.find("[data-adid]").each(function () {
				selectedAds.push($(this).data("adid"));
			});
		}

		/**
		 * Refresh the current ad order and post it to the server.
		 */
		function refresh_ad_order() {
			let adorder = [];

			if ($sortable.length > 0) {
				$sortable.children("tr").each(function () {
					const $this = $(this);
					adorder.push($this.data("adid"));
				});
			}

			post_update_ad_order(adorder);
		}

		/**
		 * Post the ad order to the server.
		 *
		 */
		function post_update_ad_order(adorder) {
			do_saving();

			const data = {
				action: adcmdr_term_meta.actions.update_ad_order.action,
				security: adcmdr_term_meta.actions.update_ad_order.security,
				group_id: groupid,
				ad_order: adorder,
			};

			$.post(adcmdr_term_meta.ajaxurl, data, function (r) {
				end_saving();
			});
		}

		/**
		 * Start saving
		 */
		function do_saving() {
			$group_ad_list.addClass("adcmdr-saving");

			if ($primary_submit.length > 0) {
				$primary_submit.attr("disabled", true);
			}

			if ($secondary_submit.length > 0) {
				$secondary_submit.attr("disabled", true);
			}
		}

		/**
		 * End saving
		 */
		function end_saving() {
			$group_ad_list.removeClass("adcmdr-saving");

			if ($primary_submit.length > 0) {
				$primary_submit.attr("disabled", false);
			}

			if ($secondary_submit.length > 0) {
				$secondary_submit.attr("disabled", false);
			}
		}

		/**
		 *  Hide or show sortable list.
		 */
		function toggle_list_visibility() {
			if ($sortable.children("tr").length <= 0) {
				$table.hide();
				$noads.show();
			} else {
				$table.show();
				$noads.hide();
			}
		}

		/**
		 * Delete an ad from a group
		 */
		function delete_ad(adid, $elem) {
			do_saving();

			var data = {
				action: adcmdr_term_meta.actions.delete_ad_from_group.action,
				security: adcmdr_term_meta.actions.delete_ad_from_group.security,
				group_id: groupid,
				ad_id: adid,
			};

			$.post(adcmdr_term_meta.ajaxurl, data, function (response) {
				if (
					response.success &&
					response.data.action === "delete-ad-from-group"
				) {
					$elem.closest("tr").remove();
					updateSelectedAds();
					refresh_ad_order();
					toggle_list_visibility();
					end_saving();
				}
			});
		}

		/**
		 * Autocomplete
		 */
		if ($autocomplete.length > 0) {
			let availableAds = [];

			/**
			 * Deetermine if an ad is already selected
			 */
			function adIsSelected(id) {
				return (
					selectedAds.filter((item) => {
						return item === id;
					}).length > 0
				);
			}

			/**
			 * Update the ad list with new HTML, trigger UI updates and save to the server.
			 */
			function updateSelectedAdsList(id, title, status, post_url) {
				do_saving();

				let after_title = "";
				if (status !== "publish") {
					after_title =
						'<span class="adcmdr-ad-status">&mdash; ' + status + "</span>";
				}

				const row = `<tr data-adid="${id}" class="adcmdr-ad-has-status--${status}">
						<td class="adcmdr-handle">
							<img src="${adcmdr_term_meta.sort_handle_url}" alt="" class="adcmdr-ui-sort-icon wometa-repeater-sort-icon ui-sortable-handle">					
						</td>
						<td class="adcmdr-weight" style="display: none;">
							<input type="number" value="1" name="_adcmdr_ad_weights[${id}]" id="_adcmdr_ad_weights[${id}]">					
						</td>
						<td class="adcmdr-title">
							<a href="${post_url}">${title}</a> ${after_title}
						</td>
						<td class="adcmdr-action">
							<button title="Remove from group" class="adcmdr-del">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 101 101">
									<path fill="#ac0101" d="M50.5 16.4c-18.8 0-34.1 15.3-34.1 34.1s15.3 34.1 34.1 34.1 34.1-15.3 34.1-34.1-15.3-34.1-34.1-34.1zm0 63.4c-16.1 0-29.3-13.1-29.3-29.3s13.1-29.3 29.3-29.3 29.3 13.1 29.3 29.3-13.2 29.3-29.3 29.3z"></path>
									<path fill="#ac0101" d="M66.2 47.8H34.8c-1.3 0-2.4 1.1-2.4 2.4s1.1 2.4 2.4 2.4h31.4c1.3 0 2.4-1.1 2.4-2.4s-1.1-2.4-2.4-2.4z"></path>
								</svg>
							</button>
						</td>
					</tr>`;

				$.post(
					adcmdr_term_meta.ajaxurl,
					{
						action: adcmdr_term_meta.actions.add_ad_to_term.action,
						security: adcmdr_term_meta.actions.add_ad_to_term.security,
						group_id: groupid,
						ad_id: id,
					},
					function (response) {
						if (
							typeof response.success !== "undefined" &&
							response.success === true
						) {
							$sortable.prepend($(row));
							toggle_list_visibility();
							toggle_weighted();
							$sortable.sortable("refresh");
							updateSelectedAds();
							refresh_ad_order();
							end_saving();
						} else {
							end_saving();
						}
					}
				);
			}

			/**
			 * Initialize the autocomplete
			 */
			function initAutoComplete() {
				$autocomplete
					.on("keydown", function (event) {
						if (
							event.keyCode === $.ui.keyCode.TAB &&
							$(this).autocomplete("instance").menu.active
						) {
							event.preventDefault();
						}
					})
					.autocomplete({
						minLength: 0,
						multiselect: true,
						source: function (request, response) {
							response(
								$.map(availableAds, function (obj, key) {
									const title = obj.title.toUpperCase();
									if (
										title.indexOf(request.term.toUpperCase()) != -1 &&
										!adIsSelected(obj.id)
									) {
										return {
											label: obj.title,
											value: obj.id,
											status: obj.status,
										};
									} else {
										return null;
									}
								})
							);
						},
						focus: function () {
							return false;
						},
						select: function (event, ui) {
							event.preventDefault();
							updateSelectedAdsList(
								ui.item.value,
								ui.item.label,
								ui.item.status
							);
							$autocomplete.val("");
						},
					});
			}

			/**
			 * UI triggers
			 */
			$order_method.on("change", function () {
				toggle_weighted();
			});

			$table.on("click", ".adcmdr-del", function () {
				const $this = $(this);
				$this.attr("disabled", true);
				delete_ad($this.closest("tr").data("adid"), $this);
			});

			/**
			 * Init
			 */
			(function () {
				/**
				 * Sortable
				 */
				$sortable
					.sortable()
					.sortable("option", "handle", ".adcmdr-ui-sort-icon")
					.on("sortupdate", function (event, ui) {
						refresh_ad_order();
					});

				/**
				 * Setup weighted
				 */
				toggle_weighted();

				/**
				 * Get available ads for autocomplete
				 */
				$.getJSON(
					adcmdr_term_meta.ajaxurl,
					{
						action: adcmdr_term_meta.actions.get_ads_for_search.action,
						security: adcmdr_term_meta.actions.get_ads_for_search.security,
					},
					function (response) {
						availableAds = response.data.ads ? response.data.ads : [];
						updateSelectedAds();
						initAutoComplete();
					}
				);
			})();
		}
	}
});
