jQuery(document).ready(function ($) {
	$(".adcmdr-notification").each(function () {
		const $this = $(this);
		const $btn = $this.find("button");

		$btn.on("click", function () {
			const $icon = $this.find(".dashicons");

			$.getJSON(
				adcmdr_notifications.ajaxurl,
				{
					action: adcmdr_notifications.actions.notification_visibility.action,
					security:
						adcmdr_notifications.actions.notification_visibility.security,
					notification_key: $btn.data("n-key"),
				},
				function (response) {
					if (parseInt(response.data.hide, 10) === 1) {
						$this.addClass("adcmdr-ignored");
						$icon
							.removeClass("dashicons-visibility")
							.addClass("dashicons-hidden");
					} else {
						$this.removeClass("adcmdr-ignored");
						$icon
							.removeClass("dashicons-hidden")
							.addClass("dashicons-visibility");
					}
				}
			);
		});
	});

	const $hidden = $(".adcmdr-hidden-notifications");

	if ($hidden.length > 0) {
		const $trigger = $hidden.find(".adcmdr-toggle-visibility");
		$trigger.on("click", function () {
			$hidden.toggleClass("show");
		});
	}
});
