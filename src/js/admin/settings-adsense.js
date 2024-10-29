jQuery(document).ready(function ($) {
	const $connect = $("#adcmdr-adsense-connect");

	$connect.on("click", function (e) {
		e.preventDefault();

		const auth_url =
			typeof adcmdr_admin_adsense.auth_url !== "undefined"
				? adcmdr_admin_adsense.auth_url
				: null;

		if (auth_url === null) {
			return;
		}

		const $this = $(this);

		$this.attr("disabled", true);
		$('<span class="adcmdr-loader adcmdr-show"></span>').insertAfter($this);
		window.location = auth_url;
	});

	const $revoke = $("#adcmdr-adsense-revoke");
	$revoke.on("click", function (e) {
		e.preventDefault();

		const $this = $(this);

		$this.attr("disabled", true);
		$('<span class="adcmdr-loader adcmdr-show"></span>').insertAfter($this);

		const data = {
			action: adcmdr_admin_adsense.actions.revoke_api_access.action,
			security: adcmdr_admin_adsense.actions.revoke_api_access.security,
		};

		$.post(adcmdr_admin_adsense.ajaxurl, data, function (response) {
			if (response.success && response.data.action === "revoke-api-access") {
				if (typeof response.data.redirect !== "undefined") {
					window.location = response.data.redirect;
				} else {
					window.location.reload();
				}
			}
		});
	});
});
