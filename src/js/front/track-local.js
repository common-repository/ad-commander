(function () {
	const _window = typeof window !== "undefined" ? window : this;

	/**
	 * WOTrack instance
	 */
	const WOTrackLocal = (_window.WOTrackLocal = function () {
		const _ = this;

		_.actions = adcmdr_track.actions;
		_.ajaxurl = adcmdr_track.ajaxurl;
	});

	/**
	 * Prototype functions
	 */
	const WOTrackLocalPrototype = WOTrackLocal.prototype;

	/**
	 * Send tracking event to server
	 */
	WOTrackLocalPrototype.track = async function (ads, type) {
		const _ = this;

		try {
			const formAction =
				type === "impression"
					? _.actions.track_impression.action
					: _.actions.track_click.action;

			const formSecurity =
				type === "impression"
					? _.actions.track_impression.security
					: _.actions.track_click.security;

			const adIds = ads.map((ad) => ad.adId);

			const data = new FormData();
			data.append("ad_ids", adIds);
			data.append("action", formAction);
			data.append("security", formSecurity);

			let response = await fetch(_.ajaxurl, {
				method: "POST",
				credentials: "same-origin",
				body: data,
			});

			if (type === "click") {
				adIds.forEach((adId) => {
					_.doEvent("woClickTrackComplete", { adId: adId });
				});
			}

			return response;
		} catch (err) {
			console.log("[Ad Commander]");
			console.error(err);

			return false;
		}
	};

	WOTrackLocalPrototype.doEvent = function (name, args) {
		args.source = "local";

		document.dispatchEvent(
			new CustomEvent(name, {
				detail: args,
			})
		);
	};
})();
