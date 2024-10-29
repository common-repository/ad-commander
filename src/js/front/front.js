import Cookies from "js-cookie";

(function () {
	const _window = typeof window !== "undefined" ? window : this;

	/**
	 * WOUtilities instance
	 */
	const WOUtil = (_window.WOUtil = function () {
		return this;
	});

	/**
	 * Prototype functions
	 */
	const WOUtilPrototype = WOUtil.prototype;

	WOUtilPrototype.prefix = function () {
		return typeof adcmdr_front.prefix !== "undefined"
			? adcmdr_front.prefix
			: "adcmdr";
	};

	WOUtilPrototype.prefixed = function (str, sep = "-") {
		return this.prefix() + sep + str;
	};

	WOUtilPrototype.ignorePopupAds = function (elements) {
		return [...elements].filter((element) => {
			let currentElement = element;
			while (currentElement.parentElement) {
				if (
					currentElement.parentElement.classList.contains(
						this.prefixed("pop-content")
					)
				) {
					return false;
				}
				currentElement = currentElement.parentElement;
			}
			return true;
		});
	};
})();

(function () {
	const _window = typeof window !== "undefined" ? window : this;

	/**
	 * WOVisitor instance
	 */
	const WOVisitor = (_window.WOVisitor = function () {
		this.woUtil = new WOUtil();

		this.impressionCookie = adcmdr_front.cookies.i;
		this.referrerCookie = adcmdr_front.cookies.r;
		this.visitorCookie = adcmdr_front.cookies.v;
		this.adImpressionCookie = adcmdr_front.cookies.i_a;
		this.adClickCookie = adcmdr_front.cookies.c_a;

		/**
		 * Prototype functions
		 */
		const WOVisitorPrototype = WOVisitor.prototype;

		/**
		 * Get current site impressions.
		 */
		WOVisitorPrototype.impressions = function () {
			const i = Cookies.get(this.impressionCookie);

			if (!i || typeof i === "undefined") {
				return 0;
			}

			return parseInt(i, 10);
		};

		/**
		 * Track site impressions.
		 */
		WOVisitorPrototype.track_impression_cookie = function () {
			Cookies.set(this.impressionCookie, this.impressions() + 1, {
				expires: 400, // maximum allowed cookie days in Chrome
			});
		};

		/**
		 * Track placement impressions to cookie.
		 */
		WOVisitorPrototype.update_user_placement_impressions = function () {
			let current_impressions = this.get_user_ad_impressions();
			const current_placements =
				window[this.woUtil.prefixed("plids", "_")] || [];

			window[this.woUtil.prefixed("plids", "_")] = [];

			if (
				typeof current_placements !== "undefined" &&
				current_placements.length > 0
			) {
				if (typeof current_impressions.placements === "undefined") {
					current_impressions.placements = [];
				}

				current_placements.forEach((plid) => {
					const obj_idx = current_impressions.placements.findIndex(
						(obj) => obj.id === plid
					);

					if (obj_idx >= 0) {
						current_impressions.placements[obj_idx] = {
							id: plid,
							i: parseInt(current_impressions.placements[obj_idx].i, 10) + 1,
						};
					} else {
						current_impressions.placements.push({ id: plid, i: 1 });
					}
				});

				Cookies.set(
					this.adImpressionCookie,
					JSON.stringify(current_impressions),
					{
						expires: 400, // maximum allowed cookie days in Chrome
					}
				);
			}
		};

		/**
		 * Track ad impressions to cookie.
		 */
		WOVisitorPrototype.update_user_ad_impressions = function (current_ads) {
			let current_impressions = this.get_user_ad_impressions();

			if (typeof current_ads !== "undefined" && current_ads.length > 0) {
				if (typeof current_impressions.ads === "undefined") {
					current_impressions.ads = [];
				}

				current_ads.forEach((ad) => {
					if (typeof ad.adId !== "undefined") {
						const adId = parseInt(ad.adId, 10);

						const obj_idx = current_impressions.ads.findIndex(
							(obj) => obj.id === adId
						);

						if (obj_idx >= 0) {
							current_impressions.ads[obj_idx].i =
								parseInt(current_impressions.ads[obj_idx].i, 10) + 1;
						} else {
							current_impressions.ads.push({ id: adId, i: 1 });
						}
					}
				});

				Cookies.set(
					this.adImpressionCookie,
					JSON.stringify(current_impressions),
					{
						expires: 400, // maximum allowed cookie days in Chrome
					}
				);
			}
		};

		/**
		 * Track ad impressions to cookie.
		 */
		WOVisitorPrototype.update_user_ad_clicks = function (current_ads) {
			let current_clicks = this.get_user_ad_clicks();

			if (typeof current_ads !== "undefined" && current_ads.length > 0) {
				if (typeof current_clicks.ads === "undefined") {
					current_clicks.ads = [];
				}

				current_ads.forEach((ad) => {
					if (typeof ad.adId !== "undefined") {
						const adId = parseInt(ad.adId, 10);

						const obj_idx = current_clicks.ads.findIndex(
							(obj) => obj.id === adId
						);

						if (obj_idx >= 0) {
							current_clicks.ads[obj_idx].c =
								parseInt(current_clicks.ads[obj_idx].c, 10) + 1;
						} else {
							current_clicks.ads.push({ id: adId, c: 1 });
						}
					}
				});

				Cookies.set(this.adClickCookie, JSON.stringify(current_clicks), {
					expires: 400, // maximum allowed cookie days in Chrome
				});
			}
		};

		/**
		 * Get ad impressions to pass to server if loading over ajax.
		 */
		WOVisitorPrototype.get_user_ad_impressions = function () {
			const current_impressions = Cookies.get(this.adImpressionCookie);

			if (typeof current_impressions === "undefined") {
				return { ads: [], placements: [] };
			}

			return JSON.parse(current_impressions);
		};

		/**
		 * Get ad clicks to pass to server if loading over ajax.
		 */
		WOVisitorPrototype.get_user_ad_clicks = function () {
			const current_clicks = Cookies.get(this.adClickCookie);

			if (typeof current_clicks === "undefined") {
				return { ads: [] };
			}

			return JSON.parse(current_clicks);
		};

		/**
		 * Set the referrer cookie.
		 */
		WOVisitorPrototype.maybe_set_referrer_cookie = function () {
			if (typeof Cookies.get(this.referrerCookie) === "undefined") {
				Cookies.set(this.referrerCookie, document.referrer);
			}
		};

		/**
		 * Get the referrer cookie.
		 */
		WOVisitorPrototype.get_referrer = function () {
			const referrer = Cookies.get(this.referrerCookie);
			if (typeof referrer === "undefined") {
				return "";
			}

			return referrer;
		};

		/**
		 * Get visitor information cookie.
		 */
		WOVisitorPrototype.get_visitor_cookie = function () {
			const visitor = Cookies.get(this.visitorCookie);

			if (typeof visitor === "undefined") {
				return {};
			}

			return visitor;
		};

		/**
		 * Set visitor information cookie.
		 */
		WOVisitorPrototype.set_visitor_cookie = function () {
			const visitor_obj = {
				viewportWidth: window.innerWidth,
				browserLanguage: navigator.language || navigator.userLanguage,
			};

			Cookies.set(this.visitorCookie, JSON.stringify(visitor_obj));
		};
	});

	const woVisitor = new WOVisitor();
	woVisitor.maybe_set_referrer_cookie();
	woVisitor.set_visitor_cookie();

	/**
	 * DOMContentLoaded
	 */
	document.addEventListener(
		"DOMContentLoaded",
		function () {
			const woUtil = new WOUtil();

			/**
			 * Initialize rotating ads that are already loaded on the page.
			 *
			 */
			new WORotateInit(
				woUtil.ignorePopupAds(
					document.getElementsByClassName(woUtil.prefixed("rotate"))
				)
			);

			const woTracker = typeof WOTrack !== "undefined" ? new WOTrack() : null;

			/**
			 * Find all ads that are already loaded on the page and track impressions
			 */
			if (woTracker) {
				woTracker.trackImpressionsAndBindClicksBySelector(
					document,
					"." + woUtil.prefixed("ad"),
					"woslide", // ignore rotate slides,
					true // ignore popups
				);
			}

			const woFrontPro =
				typeof WOFrontPro !== "undefined" ? new WOFrontPro() : null;

			if (woFrontPro) {
				woFrontPro.loadAds({ woVisitor: woVisitor, woTracker: woTracker });
			} else {
				document.dispatchEvent(new Event("adcmdrAdsLoaded"));
			}
		},
		false
	);

	document.addEventListener("adcmdrAdsLoaded", function () {
		woVisitor.track_impression_cookie();
		woVisitor.update_user_placement_impressions();
	});
})();
