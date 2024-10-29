import Chart from "chart.js/auto";
import { Grid, html } from "gridjs";
import "gridjs/dist/theme/mermaid.css";

(function () {
	jQuery(document).ready(function ($) {
		/**
		 * Filter
		 */
		const $filter = $("#adcmdr_report_filter");
		let $chart_type = null;
		if ($filter && $filter.length > 0) {
			const $period = $filter.find("#adcmdr_period");
			const $groupBy = $filter.find("#adcmdr_group_by");
			const $datepickers = $filter.find(".adcmdr-datepicker");
			const $restricted = $filter.find(".adcmdr-mode-restrict");
			const $autocomplete = $filter.find("#adcmdr_filter_by_ads");
			const $adIdsInput = $filter.find('input[name="filter_by_ad_ids"]');
			const $selectedAdsList = $filter.find("#selected_filter_ads");
			const $filterSubmit = $filter.find("#adcmdr_filter_submit");
			const $customDates = $restricted
				.filter(".adcmdr-mode-restrict--custom")
				.find("input");

			$chart_type = $filter.find('input[name="chart_type"]');

			function getDisabledGroupBys(period) {
				switch (period) {
					case "today":
					case "yesterday":
						return ["week", "month"];
						break;

					case "last_week":
					case "this_week":
					case "past_7_days":
					case "past_14_days":
						return ["hour", "month"];
						break;

					case "this_year":
					case "last_year":
					case "past_365_days":
						return ["hour"];
						break;

					case "custom":
						return getCustomDisabledGroupBys();
						break;

					default:
						return ["hour"];
						break;
				}
			}

			function getCustomDisabledGroupBys() {
				let vals = [];
				let hasEmpty = false;

				$datepickers.each(function () {
					const val = $.trim($(this).val());
					if (val === "") {
						hasEmpty = true;
					}
					vals.push(val);
				});

				if (!hasEmpty && [...new Set(vals)].length === 1) {
					return [];
				}

				return ["hour"];
			}

			function refreshGroupBys(period) {
				const disabledVals = getDisabledGroupBys(period);
				const currentGroupBy = $groupBy.val();
				const $options = $groupBy.find("option");

				if (disabledVals.length > 0) {
					let selectors = [];

					disabledVals.forEach((v) => {
						selectors.push('[value="' + v + '"]');
					});

					selectors = selectors.join(", ");

					const $disable = $options.filter(selectors).attr("disabled", true);
					$options.not($disable).attr("disabled", false);

					if (disabledVals.includes(currentGroupBy)) {
						$groupBy.val(
							$options
								.filter(":not([disabled]):not([value='hour']):first")
								.val()
						);
					}
				} else {
					$options.attr("disabled", false);
				}
			}

			function refreshRestricted(periodMode) {
				const $toShow = $restricted.filter(
					".adcmdr-mode-restrict--" + periodMode
				);

				$restricted.not($toShow).hide();

				if ($toShow.length > 0) {
					$toShow.css("display", "flex");
				}
			}

			$datepickers.each(function () {
				$(this).datepicker();
			});

			/**
			 * Autocomplete
			 */
			let availableAds = [];
			let selectedAds = [];

			function adIsSelected(id) {
				return (
					selectedAds.filter((item) => {
						return item.value === id;
					}).length > 0
				);
			}

			function updateSelectedAds() {
				$adIdsInput.val(selectedAds.map((ad) => ad.value).join(","));
				updateSelectedAdsList();
			}

			function updateSelectedAdsList() {
				let html = "";
				selectedAds.forEach((ad) => {
					html += `<li>
								<button class="adcmdr-filter-remove-ad adcmdr-remove" data-adid="${ad.value}">
									<span>${ad.label}</span>
									<i class="dashicons dashicons-minus"></i>
								</button>
							</li>`;
				});

				$selectedAdsList.html(html);
			}

			function initSelectedAds() {
				const adIds = $adIdsInput
					.val()
					.split(",")
					.map((id) => parseInt(id, 10));

				selectedAds = availableAds
					.filter((ad) => {
						return adIds.includes(ad.id);
					})
					.map((ad) => {
						return {
							label: ad.title,
							value: ad.id,
						};
					});

				updateSelectedAdsList();
			}

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

							if (!adIsSelected(ui.item.value)) {
								selectedAds.push(ui.item);
								updateSelectedAds();
								$filterSubmit.addClass("adcmdr-highlight");
							}
						},
					});
			}

			$.getJSON(
				adcmdr_reports.ajaxurl,
				{
					action: adcmdr_reports.actions.get_ads_for_filter.action,
					security: adcmdr_reports.actions.get_ads_for_filter.security,
				},
				function (response) {
					availableAds = response.data.ads ? response.data.ads : [];
					initSelectedAds();
					initAutoComplete();
				}
			);

			$selectedAdsList.on("click", ".adcmdr-filter-remove-ad", function (e) {
				e.preventDefault();

				const $this = $(this);
				const adid = parseInt($this.data("adid"), 10);

				selectedAds = selectedAds.filter((item) => item.value !== adid);

				updateSelectedAds();

				$filterSubmit.addClass("adcmdr-highlight");
			});

			/**
			 * Misc UI events
			 */
			$datepickers.on("change", function () {
				refreshGroupBys($period.val());
			});

			$period.on("change", function () {
				const currentMode = $(this).val();
				refreshGroupBys(currentMode);
				refreshRestricted(currentMode);
			});

			$filter.on("submit", function (e) {
				if ($period.val() === "custom") {
					let hasError = false;
					$customDates.each(function () {
						const $this = $(this);
						if ($this.val() === "") {
							if (!hasError) {
								e.preventDefault();
								hasError = true;
								$this.focus();
							}

							$this.addClass("adcmdr-error");
						}
					});
				} else {
					$customDates.val("");
				}
			});

			/**
			 * Filter init
			 */
			const periodMode = $period.val();
			refreshGroupBys(periodMode);
			refreshRestricted(periodMode);
		}

		/**
		 * Chart
		 */
		const $chart = document.getElementById("adcmdr_chart");
		let chartData = null;

		if ($chart && typeof $chart !== "undefined") {
			function buildChartArgs() {
				let chartType = "line";
				if ($chart_type.length > 0) {
					chartType = $chart_type.filter(":checked").val();
				} else {
					chartType = adcmdr_reports.chartType === "bar" ? "bar" : "line";
				}

				if (chartData === null) {
					chartData = {
						labels: adcmdr_reports.reportData.map((entry) => entry.date_alt),
						datasets: [
							{
								label: "Impressions",
								data: adcmdr_reports.reportData.map(
									(entry) => entry.impressions
								),
								//fill: false,
								backgroundColor: "rgb(75, 192, 192)",
								borderColor: "rgb(75, 192, 192)",
								tension: 0.1,
							},
							{
								label: "Clicks",
								data: adcmdr_reports.reportData.map((entry) => entry.clicks),
								//fill: false,
								backgroundColor: "rgb(1, 1, 1)",
								borderColor: "rgb(1, 1, 1)",
								tension: 0.1,
							},
						],
					};
				}

				return {
					type: chartType,
					data: chartData,
				};
			}

			let adcmdr_chart = null;

			function buildChart() {
				adcmdr_chart = new Chart($chart, buildChartArgs());
			}
			buildChart();

			if ($filter.length > 0) {
				if ($chart_type.length > 0) {
					$chart_type.on("change", function () {
						adcmdr_chart.destroy();
						buildChart();
					});
				}
			}
		}

		/**
		 * Table by Date
		 */
		const $tableByDate = document.getElementById("adcmdr_data_by_date");
		if ($tableByDate && typeof $tableByDate !== "undefined") {
			$tableByDate.innerHTML = "";

			let parsedTableData = [];
			let parsedTimestamps = [];

			adcmdr_reports.reportData.reverse().forEach((item) => {
				parsedTableData.push([
					item.date,
					item.impressions,
					item.clicks,
					item.ctr,
				]);

				parsedTimestamps[item.date] = item.timestamp;
			});

			const tableCols = [
				{
					name: adcmdr_reports.tableByDateCols.date,
					sort: {
						compare: (a, b) => {
							if (parsedTimestamps[a] > parsedTimestamps[b]) {
								return 1;
							} else if (parsedTimestamps[b] > parsedTimestamps[a]) {
								return -1;
							} else {
								return 0;
							}
						},
					},
					formatter: (cell) => {
						return typeof adcmdr_reports.reportDataGroupByPrefix !==
							"undefined" && adcmdr_reports.reportDataGroupByPrefix !== ""
							? `${adcmdr_reports.reportDataGroupByPrefix} ${cell}`
							: `${cell}`;
					},
				},
				adcmdr_reports.tableByDateCols.impressions,
				adcmdr_reports.tableByDateCols.clicks,
				adcmdr_reports.tableByDateCols.ctr,
			];

			new Grid({
				columns: tableCols,
				data: parsedTableData,
				pagination: {
					limit: 12,
					summary: false,
					resetPageOnUpdate: false,
				},
				sort: {
					multiColumn: false,
				},
				style: {
					container: {
						width: "100%",
						height: "100%",
					},
				},
				width: "100%",
				height: "100%",
			}).render($tableByDate);
		}

		/**
		 * Table by ads
		 */
		const $tableByAd = document.getElementById("adcmdr_data_by_ad");
		if ($tableByAd && typeof $tableByAd !== "undefined") {
			$tableByAd.innerHTML = "";

			let parsedTableData = [];

			adcmdr_reports.adData.forEach((item) => {
				parsedTableData.push([
					item.ad,
					item.impressions,
					item.clicks,
					item.ctr,
					item.edit_link,
				]);
			});

			const tableCols = [
				{
					name: adcmdr_reports.tableByAdCols.ad,
					formatter: (cell, row) => {
						if (row.cells[4].data) {
							return html(
								`${cell} <a href="${row.cells[4].data}" class="dashicons dashicons-edit"></a>`
							);
						} else {
							return html(`<em>${cell}</em>`);
						}
					},
				},
				adcmdr_reports.tableByAdCols.impressions,
				adcmdr_reports.tableByAdCols.clicks,
				adcmdr_reports.tableByAdCols.ctr,
				{
					name: "edit_link",
					hidden: true,
				},
			];

			new Grid({
				columns: tableCols,
				data: parsedTableData,
				pagination: {
					limit: 10,
					summary: false,
					resetPageOnUpdate: false,
				},
				sort: {
					multiColumn: false,
				},
				style: {
					container: {
						width: "100%",
						height: "100%",
					},
				},
				width: "100%",
				height: "100%",
			}).render($tableByAd);
		}
	});
})();
