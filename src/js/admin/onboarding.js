jQuery(document).ready(function ($) {
	$(".adcmdr-ob-notice").each(function () {
		const $notice = $(this);
		let dismissing = false;

		$notice.find(".adcmdr-ob-dismiss").on("click", function (e) {
			e.preventDefault();

			if (dismissing) {
				return;
			}

			dismissing = true;

			const disableOb = $(this).data("disable-ob") || "global";

			const data = {
				action: adcmdr_onboarding.actions.dismiss_onboarding.action,
				security: adcmdr_onboarding.actions.dismiss_onboarding.security,
				disableob: disableOb,
			};

			$.post(adcmdr_onboarding.ajaxurl, data, function () {});

			$notice.fadeOut("fast", function () {
				$notice.remove();
			});
		});
	});

	$(".adcmdr-ob-demo").on("click", function (e) {
		e.preventDefault();
		$(this).prop("disabled", true);

		const data = {
			action: adcmdr_onboarding.actions.create_demo_ad.action,
			security: adcmdr_onboarding.actions.create_demo_ad.security,
		};

		$.post(adcmdr_onboarding.ajaxurl, data, function (r) {
			if (!r.success || !r.data) {
				return;
			}

			if (r.data.action === "create-demo-ad" && r.data.url) {
				window.location = r.data.url;
			}
		});
	});
});
