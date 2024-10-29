(function () {
	const _window = typeof window !== "undefined" ? window : this;

	/**
	 * WOTrack instance
	 */
	const WOTrack = (_window.WOTrack = function () {
		const _ = this;

		_.args = adcmdr_track;

		/**
		 * All disabling of tracking should be handled in this plugin.
		 * Other scripts assume we are tracking, and we'll stop it here either globally or within an individual element.
		 */
		_.shouldTrackLocal = false;
		_.shouldTrackGA = false;
		_.shouldTrackImpressions = false;
		_.shouldTrackClicks = false;
		_.activeClicks = [];
		_.clickInProgressTimer = [];
		_.woUtil = new WOUtil();
		_.woVisitor = new WOVisitor();

		if (_.args.user_events.includes("impressions")) {
			_.shouldTrackImpressions = true;
		}

		if (_.args.user_events.includes("clicks")) {
			_.shouldTrackClicks = true;
		}

		if (_.shouldTrackImpressions || _.shouldTrackClicks) {
			if (
				_.args.methods.includes("local") &&
				typeof WOTrackLocal !== "undefined" &&
				(typeof _.args.actions.track_impression !== "undefined" ||
					typeof _.args.actions.track_click !== "undefined")
			) {
				_.trackerLocal = new WOTrackLocal();
				_.shouldTrackLocal = true;
			}

			if (_.args.methods.includes("ga") && typeof WOTrackGA !== "undefined") {
				_.trackerGA = new WOTrackGA();
				_.shouldTrackGA = true;
			}
		}

		document.addEventListener("woClickTrackComplete", _.clickComplete.bind(_));
	});

	/**
	 * Prototype functions
	 */
	const WOTrackPrototype = WOTrack.prototype;

	/**
	 * Send tracking event to server
	 */
	WOTrackPrototype.track = async function (ads, type) {
		const _ = this;

		if (
			!ads ||
			ads.length <= 0 ||
			(type !== "impression" && type !== "click") ||
			(type === "impression" && !_.shouldTrackImpressions) ||
			(type === "click" && !_.shouldTrackClicks)
		) {
			return;
		}

		try {
			if (_.shouldTrackGA) {
				_.trackerGA.track(ads, type);
			}

			if (_.shouldTrackLocal) {
				_.trackerLocal.track(ads, type);
			}
		} catch (err) {
			console.log("[Ad Commander]");
			console.error(err);

			return false;
		}
	};

	/**
	 * Get tracking ID from element, if one exists.
	 */
	WOTrackPrototype.getTrackingAdId = function (element) {
		if (typeof element.woTrackId == "undefined") {
			element.woTrackId = element.getAttribute("data-t-id") || null;
		}

		return element.woTrackId;
	};

	/**
	 * Get the ad title if one existrs
	 */
	WOTrackPrototype.getAdTitle = function (element) {
		if (typeof element.woAdTitle == "undefined") {
			element.woAdTitle = element.getAttribute("data-t-title") || null;
		}

		return element.woAdTitle;
	};

	/**
	 * Check if tracking is disabled on this element
	 */
	WOTrackPrototype.isAdTrackingDisabled_i = function (element) {
		if (typeof element.adTrackingDisabled_i == "undefined") {
			element.adTrackingDisabled_i = element.getAttribute("data-ti-disabled")
				? true
				: false;
		}

		return element.adTrackingDisabled_i;
	};

	WOTrackPrototype.isAdTrackingDisabled_c = function (element) {
		if (typeof element.adTrackingDisabled_c == "undefined") {
			element.adTrackingDisabled_c = element.getAttribute("data-tc-disabled")
				? true
				: false;
		}

		return element.adTrackingDisabled_c;
	};

	/**
	 * Track ad impressions and also bind a click event in one function.
	 * This will loop through a parent and a selector (provided by another script).
	 * Combining avoids having to loop through the same selector multiple times for impressions + clicks.
	 */
	WOTrackPrototype.trackImpressionsAndBindClicksBySelector = function (
		parent,
		selector,
		ignoreImpressionClass = null,
		ignorePopupAds = true
	) {
		const _ = this;

		/**
		 * Is tracking disabled globally?
		 * If so, we can skip querying the elements.
		 */
		if (!_.shouldTrackImpressions && !_.shouldTrackClicks) {
			return;
		}

		let impressionAds = [];
		let elements = parent.querySelectorAll(selector);

		if (ignorePopupAds) {
			elements = _.woUtil.ignorePopupAds(elements);
		}

		if (elements && typeof elements !== "undefined" && elements.length > 0) {
			for (const element of elements) {
				const trackingId = _.getTrackingAdId(element);

				if (trackingId) {
					if (
						_.shouldTrackImpressions &&
						!_.isAdTrackingDisabled_i(element) &&
						(!ignoreImpressionClass ||
							(ignoreImpressionClass &&
								!element.classList.contains(ignoreImpressionClass)))
					) {
						const ad = {
							adId: trackingId,
							title: _.getAdTitle(element),
						};

						impressionAds.push(ad);
					}

					if (_.shouldTrackClicks && !_.isAdTrackingDisabled_c(element)) {
						_.bindTrackClick(element);
					}
				}
			}
		}

		/**
		 * Track impressions for all ads found in query
		 */
		_.trackImpressions(impressionAds);
	};

	WOTrackPrototype.trackImpressionAndBindClickByElement = function (element) {
		const _ = this;

		/**
		 * Is tracking disabled globally?
		 * If so, we can skip querying the elements.
		 */
		if (!_.shouldTrackImpressions && !_.shouldTrackClicks) {
			return;
		}

		let impressionAds = [];

		const trackingId = _.getTrackingAdId(element);

		if (trackingId) {
			if (_.shouldTrackImpressions && !_.isAdTrackingDisabled_i(element)) {
				const ad = {
					adId: trackingId,
					title: _.getAdTitle(element),
				};

				impressionAds.push(ad);
			}

			if (_.shouldTrackClicks && !_.isAdTrackingDisabled_c(element)) {
				_.bindTrackClick(element);
			}
		}

		/**
		 * Track impressions for all ads found in query
		 */
		_.trackImpressions(impressionAds);
	};

	/**
	 * Send impression to track function
	 */
	WOTrackPrototype.trackImpressionByElement = function (element) {
		const _ = this;
		const trackingId = _.getTrackingAdId(element);

		if (
			_.shouldTrackImpressions &&
			trackingId &&
			!_.isAdTrackingDisabled_i(element)
		) {
			const ad = [
				{
					adId: trackingId,
					title: _.getAdTitle(element),
				},
			];

			_.trackImpressions(ad);
		}
	};

	WOTrackPrototype.trackImpressions = function (ads) {
		const _ = this;
		if (_.shouldTrackImpressions) {
			_.track(ads, "impression");
			_.woVisitor.update_user_ad_impressions(ads);
		}
	};

	/**
	 * Send click to track function
	 */
	WOTrackPrototype.trackClicks = function (ads) {
		const _ = this;
		if (_.shouldTrackClicks) {
			_.track(ads, "click");
			_.woVisitor.update_user_ad_clicks(ads);
		}
	};

	/**
	 * Set an element to click-in-progress to avoid tracking double clicks.
	 * Mostly an issue with same-window links that have to wait for an ajax tracking response.
	 */
	WOTrackPrototype.setClickInProgressTimer = function (element, adId) {
		const _ = this;

		element.clickInProgress = true;

		_.clearClickInProgressTimer(adId);

		_.clickInProgressTimer[adId] = setTimeout(
			_.removeClickInProgressTimer.bind(_),
			3000,
			{
				element: element,
				adId: adId,
			}
		);
	};

	WOTrackPrototype.clearClickInProgressTimer = function (adId) {
		const _ = this;
		if (typeof _.clickInProgressTimer[adId] !== "undefined") {
			clearTimeout(_.clickInProgressTimer[adId]);
		}
	};

	/**
	 * Remove the click-in-progress flag from an element.
	 */
	WOTrackPrototype.removeClickInProgressTimer = function (args) {
		const _ = this;
		const wrapper = args.wrapper;
		const adId = args.adId;

		_.clearClickInProgressTimer(adId);
		wrapper.clickInProgress = false;

		if (
			typeof _.activeClicks === "undefined" ||
			typeof _.activeClicks[adId] === "undefined"
		) {
			return;
		}

		const url = _.activeClicks[adId].href;
		_.activeClicks[adId] = { ga: false, local: false, href: null };

		if (typeof url !== "undefined" && url !== null) {
			window.location = url;
		}
	};

	/**
	 * Set active click data
	 */
	WOTrackPrototype.setActiveClick = function (wrapper, adId, url) {
		const _ = this;
		_.activeClicks[adId] = {
			ga: _.shouldTrackGA,
			local: _.shouldTrackLocal,
			href: url,
			wrapper: wrapper,
		};
	};

	/**
	 * Dispatched whenever a click is complete
	 */
	WOTrackPrototype.clickComplete = function (e) {
		const _ = this;
		const adId = e.detail.adId;
		const source = e.detail.source;

		if (
			typeof _.activeClicks === "undefined" ||
			typeof _.activeClicks[adId] === "undefined"
		) {
			return;
		}

		_.activeClicks[adId][source] = false;

		if (
			_.activeClicks[adId].local === false &&
			_.activeClicks[adId].ga === false
		) {
			if (_.activeClicks[adId].wrapper) {
				_.activeClicks[adId].wrapper.clickInProgress = false;
			}

			_.clearClickInProgressTimer(adId);

			if (
				typeof _.activeClicks[adId].href !== "undefined" &&
				_.activeClicks[adId].href !== null
			) {
				window.location = _.activeClicks[adId].href;
			}
		}
	};

	WOTrackPrototype.isValidUrl = function (urlString) {
		try {
			return Boolean(new URL(urlString));
		} catch (e) {
			return false;
		}
	};

	/**
	 * Bind click events to ads
	 */
	WOTrackPrototype.bindTrackClick = function (wrapper) {
		const _ = this;
		const adId = _.getTrackingAdId(wrapper);

		if (!adId || _.isAdTrackingDisabled_c(wrapper)) {
			return;
		}

		if (wrapper) {
			wrapper.clickInProgress = false;

			["click", "touchend", "auxclick"].forEach(function (event) {
				/**
				 * Add each event to the target element
				 */
				wrapper.addEventListener(
					event,
					function (e) {
						if (
							wrapper.clickInProgress ||
							(e.type === "auxclick" && e.which !== 2 && e.which !== 1)
						) {
							return;
						}

						wrapper.clickInProgress = true;

						/**
						 * We DO still track clicks on the wrapper, even if there isn't a real link inside.
						 * This is incase someone drops in a script that opens a window instead of a standard link.
						 */
						let clickTarget = wrapper;

						/**
						 * Traverse up the dom from the target to the wrapper to find a link if we have one.
						 */
						for (let t = e.target; t && t !== this; t = t.parentNode) {
							if (["a", "iframe", "button"].indexOf(t.localName) !== -1) {
								clickTarget = t;
								break;
							}
						}

						/**
						 * The ad array that will get passed to trackers.
						 */
						const ad = [
							{
								adId: adId,
								title: _.getAdTitle(wrapper),
								wrapper: wrapper,
								trackInstance: _,
							},
						];

						/**
						 * Determine if we're opening in the same window and we also know the URL.
						 * If so, we'll stop the click to track.
						 */
						const linkTarget = clickTarget.getAttribute("target") || null;
						let newWindow = true;
						let href = null;

						if (
							linkTarget === null ||
							(linkTarget !== null && linkTarget.toLowerCase() !== "_blank")
						) {
							newWindow = false;
							href = clickTarget.getAttribute("href") || null;

							if (href && !_.isValidUrl(href)) {
								href = null;
							}
						}

						_.setActiveClick(wrapper, adId, href);

						if (!newWindow && href !== null) {
							e.preventDefault();
						}

						_.setClickInProgressTimer(wrapper, adId);
						_.trackClicks(ad);
					},
					{ capture: true }
				);
			});
		}
	};
})();
