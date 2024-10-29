jQuery(document).ready(function ($) {
	/**
	 * Create select options from object.
	 *
	 * @param object object Object with values and labels.
	 *
	 * @return string
	 */
	function objectToOptions(object) {
		let options = "";
		for (const [key, value] of Object.entries(object)) {
			let optVal = key;
			if (key !== "" && key.substring(0, 16) === "woadmin_divider:") {
				optVal = "";
			}

			options += `<option value="${optVal}">${value}</option>`;
		}

		return options;
	}

	/**
	 * Create a select from parameters.
	 *
	 * @param string name The name of the element.
	 * @param string id The id of the element.
	 * @param object object Object with values and labels.
	 *
	 * @return string
	 */
	function makeSelect(object, name, id) {
		return `<select name="${name}" id="${id}">${objectToOptions(
			object
		)}</select>`;
	}

	/**
	 * Create checkbox group from object.
	 *
	 * @param object object Object with values and labels.
	 * @param string name The name of the element.
	 * @param string id The id of the element.
	 *
	 * @return string
	 */
	function objectToCheckgroup(object, name, id) {
		let checks = "";

		if (!$.isEmptyObject(object)) {
			name += "[]";

			for (const [key, value] of Object.entries(object)) {
				const thisId = id + "_" + key;
				checks += `<span>${makeInput(
					"checkbox",
					name,
					thisId,
					key
				)} ${makeLabel(thisId, value)}</span>`;
			}
		} else {
			checks = `<span class="woforms-notfound adcmdr-block-label">${adcmdr_targeting.notfound}</span>`;
		}

		return `<div class="woforms-input-group">${checks}</div>`;
	}

	/**
	 * Create an input from parameters.
	 *
	 * @param string type The type of input.
	 * @param string name The name of the element.
	 * @param string id The id of the element.
	 * @param mixed value The value of the input.
	 *
	 * @return string
	 */
	function makeInput(type, name, id, value, args = []) {
		let input = `<input type="${type}" name="${name}" id="${id}" value="${value}"`;

		if (args && args.length > 0) {
			args.forEach((arg) => {
				input += ` ${arg.key}="${arg.value}"`;
			});
		}

		input += ` />`;

		return input;
	}

	/**
	 * Create a label for an input.
	 *
	 * @param string forId The label for attribute.
	 * @param string text The text of the label.
	 *
	 * @return string
	 */
	function makeLabel(forId, text) {
		return `<label for="${forId}">${text}</label>`;
	}

	function makeWords(text) {
		return `<span class="adcmdr-block-label">${text}</span>`;
	}

	/**
	 * The Targeting meta box.
	 */
	const $targeting_p = $("#adcmdrtargetingdiv");

	if ($targeting_p.length > 0) {
		/**
		 * The tables with targeting settings.
		 */
		$targeting_p.find(".adcmdr-targeting").each(function () {
			const $table = $(this);
			const targeting_type = $table.parent().data("targetingtype") || null;

			/**
			 * Modify UI if 'and' or 'or' is selected.
			 */
			function andor() {
				$table.find(".targeting-andor").each(function () {
					const $this = $(this);
					const andor = $this.val();
					const $row = $this.closest("tr");

					if (andor === "and") {
						$row.removeClass("or-divide");
					} else {
						$row.addClass("or-divide");
					}
				});
			}

			$table.on("change", ".targeting-andor", function () {
				andor();
			});

			$table.on("resetrows", function () {
				andor();
			});

			$table.find("tbody").on("sortupdate", function () {
				andor();
			});

			/**
			 * Trigger andor UI modifications on load.
			 */
			andor();

			/**
			 * Setup autocomplete
			 */
			function createAutoComplete($conditionsCell) {
				const loadResults = adcmdr_targeting.actions.load_ac_results;
				let selectedPosts = [];

				/**
				 * Find the current row's target dropdown and related attributes.
				 */
				const $target = $conditionsCell
					.closest("tr")
					.find('select[name*="[target]"]:first');

				if (!$target || $target.length <= 0) {
					return;
				}

				const targetVal = $target.val();
				const targetName = $target.attr("name");
				const targetId = $target.attr("name");

				/**
				 * Find the autocomplete input.
				 */
				const $autocomplete = $conditionsCell.find('input[name*="[values]"]');

				/**
				 * Find the selectedPostsList or create it.
				 */
				let $selectedPostsList = $autocomplete.siblings(".selected_posts_list");

				if ($selectedPostsList.length <= 0) {
					$selectedPostsList = $(
						'<ul class="selected_posts_list adcmdr-remove-controls"></ul>'
					).insertAfter($autocomplete);
				} else {
					$selectedPostsList.children("li").each(function () {
						const $this = $(this);
						selectedPosts.push({
							label: $this.find("span").text(),
							value: parseInt($this.find("button").data("postid"), 10),
						});
					});
				}

				/**
				 * Find the hidden post IDs input, or create it.
				 */
				let $postIdsInput = $autocomplete.siblings(
					'input[name*="[selected_post_ids]"]'
				);

				if ($postIdsInput.length <= 0) {
					$postIdsInput = $(
						makeInput(
							"hidden",
							targetName.replace("[target]", "[selected_post_ids]"),
							targetId.replace("[target]", "[selected_post_ids]"),
							""
						)
					).insertAfter($autocomplete);
				}

				/**
				 * Setup selection of auto complete items.
				 */
				function postIsSelected(id) {
					return (
						selectedPosts.filter((item) => {
							return item.value === id;
						}).length > 0
					);
				}

				function updateSelectedPosts() {
					$postIdsInput.val(selectedPosts.map((p) => p.value).join(","));
					updateSelectedPostsList();
				}

				function updateSelectedPostsList() {
					let html = "";
					selectedPosts.forEach((p) => {
						html += `<li>
									<button class="adcmdr-remove-post adcmdr-remove" data-postid="${p.value}">
										<span>${p.label}</span>
										<i class="dashicons dashicons-minus"></i>
									</button>
								</li>`;
					});

					$selectedPostsList.html(html);
				}

				/**
				 * Remove auto complete selected item.
				 */
				$conditionsCell.on("click", ".adcmdr-remove-post", function (e) {
					e.preventDefault();

					const $this = $(this);
					const postid = parseInt($this.data("postid"), 10);

					selectedPosts = selectedPosts.filter((item) => item.value !== postid);
					updateSelectedPosts();
				});

				/**
				 * Init the autocomplete.
				 */
				$autocomplete.autocomplete({
					minLength: 0,
					multiselect: true,
					source: function (request, response) {
						if (!request || !request.term) {
							return response();
						}

						$.getJSON(
							adcmdr_targeting.ajaxurl,
							{
								search_term: request.term,
								target: targetVal,
								action: loadResults.action,
								security: loadResults.security,
							},
							function (r) {
								if (!r.success) {
									return response();
								}

								response(
									$.map(r.data.results, function (obj, key) {
										return {
											label: obj.title,
											value: obj.id,
										};
									})
								);
							}
						);
					},
					focus: function () {
						return false;
					},
					select: function (event, ui) {
						event.preventDefault();

						if (!postIsSelected(ui.item.value)) {
							selectedPosts.push(ui.item);
							updateSelectedPosts();
							$autocomplete.val("");
						}
					},
				});
			}

			/**
			 * Perform actions when a new target is selected.
			 */
			$table.on("change", ".targeting-target", function () {
				const $this = $(this);
				const target = $this.val();
				const $conditionsCell = $this.closest("td").next("td");

				if (!target) {
					// clear it
					$conditionsCell.html("");
					return;
				}

				$conditionsCell.html('<span class="adcmdr-loader adcmdr-show"></span>');

				/**
				 * Load the conditions (is, is not, etc) and available values from ajaxurl
				 */
				const loadAction = adcmdr_targeting.actions.load_conditions;

				$.getJSON(
					adcmdr_targeting.ajaxurl,
					{
						target: target,
						action: loadAction.action,
						security: loadAction.security,
						targeting_type: targeting_type,
					},
					function (response) {
						if (!response.success) {
							$conditionsCell.html("error");
							return;
						}

						const currentName = $this.attr("name");
						const currentId = $this.attr("id");
						const targetName = currentName.replace("[target]", "[condition]");
						const targetId = currentId.replace("[target]", "[condition]");
						const valuesName = currentName.replace("[target]", "[values]");
						const valuesId = currentId.replace("[target]", "[values]");
						let html = "";
						let hasAutoComplete = false;

						if (typeof response.data.conditions !== "undefined") {
							const targetOptions = objectToOptions(response.data.conditions);

							/**
							 * Create the dropdown for selecting a condition.
							 */
							html = `<select name="${targetName}" id="${targetId}" class="targeting-conditions">${targetOptions}</select>`;
						}

						if (
							typeof response.data.value_type !== "undefined" &&
							typeof response.data.values !== "undefined"
						) {
							function makeFieldsFromResponse(values, valueType, subKey = "") {
								let thisName = valuesName;
								let thisId = valuesId;
								let thisArgs = [];

								if (subKey && subKey !== "") {
									thisName += "[" + subKey + "]";
									thisId += "[" + subKey + "]";

									if (
										typeof response.data.args !== "undefined" &&
										typeof response.data.args[subKey] !== "undefined"
									) {
										let args = [];
										args[thisId] = [];

										for (const [key, value] of Object.entries(
											response.data.args[subKey]
										)) {
											args[thisId].push({
												key: key,
												value: value,
											});
										}

										thisArgs = args[thisId];
									}
								}

								switch (valueType) {
									case "words":
										html += makeWords(values);
										break;

									case "checkgroup":
										html += objectToCheckgroup(values, thisName, thisId);
										break;

									case "select":
										html += makeSelect(values, thisName, thisId);
										break;

									case "text":
										html += makeInput("text", thisName, thisId, "");
										break;

									case "autocomplete":
										hasAutoComplete = true;
										if (adcmdr_targeting.page_ac_placeholder) {
											thisArgs.push({
												key: "placeholder",
												value: adcmdr_targeting.page_ac_placeholder,
											});
										}

										html += makeInput("text", thisName, thisId, "", thisArgs);
										break;

									case "number":
										html += makeInput("number", thisName, thisId, "", thisArgs);
										break;
								}
							}

							const respValues = response.data.values;
							const respValueType = response.data.value_type;

							if (typeof respValueType === "object") {
								for (const [key, valueType] of Object.entries(respValueType)) {
									let values = "";
									if (typeof respValues[key] !== "undefined") {
										values = respValues[key];
									}

									if (
										typeof response.data.value_type_labels === "object" &&
										typeof response.data.value_type_labels[key] !== "undefined"
									) {
										makeFieldsFromResponse(
											response.data.value_type_labels[key],
											"words",
											key
										);
									}

									makeFieldsFromResponse(values, valueType, key);
								}
							} else {
								makeFieldsFromResponse(respValues, respValueType);
							}
						}

						/**
						 * Wrap both the condition and the values in a div for styling purposes.
						 */
						$conditionsCell.html(
							`<div class="adcmdr-targeting-conditions">${html}</div>`
						);

						/**
						 * If we have an autocomplete, set it all up.
						 */
						if (hasAutoComplete) {
							createAutoComplete($conditionsCell);
						}
					}
				);
			});

			/**
			 * Init autocompletes on load.
			 */
			$table.find(".init-ac").each(function () {
				createAutoComplete($(this).closest("td"));
			});
		});
	}
});
