jQuery(document).ready(function ($) {
	const restricted = adcmdr_settings.restricted || [];

	restricted.forEach((r) => {
		const $targets = $(".adcmdr-mode-restrict--" + r);
		if ($targets.length > 0) {
			const $trigger = $("#" + r);

			if ($trigger.length > 0) {
				function mode_changed() {
					if ($trigger.is("select")) {
						if ($trigger.val() !== "") {
							const $toShow = $targets.filter(
								".adcmdr-mode-restrict--" + r + "-" + $trigger.val()
							);
							$toShow.show();
							$targets.not($toShow).hide();
						} else {
							$targets.hide();
						}
					} else {
						if ($trigger.prop("checked")) {
							$targets.show();
						} else {
							$targets.hide();
						}
					}
				}

				$trigger.on("change", function () {
					mode_changed();
				});

				mode_changed();
			}
		}
	});

	/**
	 * Pro license
	 */
	if (typeof adcmdr_pro_license !== "undefined") {
		if (adcmdr_pro_license.pro_status === "pending") {
			const data = {
				action: adcmdr_pro_license.actions.validate_license_key.action,
				security: adcmdr_pro_license.actions.validate_license_key.security,
				validate: "now",
			};

			$.post(adcmdr_pro_license.ajaxurl, data, function (r) {
				if (
					typeof r.data.pro_status !== "undefined" &&
					r.data.pro_status !== "pending"
				) {
					window.location.reload();
				}
			});
		}
	}
});
