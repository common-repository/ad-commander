(function () {
	const _window = typeof window !== "undefined" ? window : this;

	/**
	 * WORotate instance
	 */
	const WORotate = (_window.WORotate = function (element, settings) {
		const _ = this;

		/**
		 * Avoid initializing twice on the same element.
		 */
		if (element._worotate) {
			return element._worotate;
		}

		_.ele = element;
		_.ele._worotate = _;

		/**
		 * Merge settings with defaults.
		 */
		_.opt = Object.assign(
			{},
			{ interval: 5000, heightMode: "fixed", stopTrack: 0 },
			settings
		);

		/**
		 * Iniit
		 */
		_.init();
	});

	/**
	 * Prototype functions
	 */
	const WORotatePrototype = WORotate.prototype;

	/**
	 * Interface with WOTrack if it is present.
	 */
	WORotatePrototype.getTrackingAdId = function (element) {
		const _ = this;

		if (_.tracker && typeof _.tracker.getTrackingAdId !== "undefined") {
			return _.tracker.getTrackingAdId(element);
		}
	};

	WORotatePrototype.bindTrackClick = function (element) {
		const _ = this;

		if (_.tracker && typeof _.tracker.bindTrackClick !== "undefined") {
			// click binding will be blocked by tracker if disabled.
			_.tracker.bindTrackClick(element);
		}
	};

	WORotatePrototype.trackImpression = function (element) {
		const _ = this;

		if (
			_.tracker &&
			typeof _.tracker.trackImpressionByElement !== "undefined"
		) {
			// impression tracking will be blocked by tracker if disabled.
			if (
				_.opt.stopTrack <= 0 ||
				Date.now() - _.initTimestamp <= _.opt.stopTrack
			) {
				_.tracker.trackImpressionByElement(element);
			}
		}
	};

	/**
	 * Set slide to active
	 */
	WORotatePrototype.setActive = function (idx) {
		const _ = this;
		_.active = idx;

		/**
		 * Remove active class from slides
		 */
		Array.from(_.slides).forEach((el, i) => {
			if (_.active !== i) {
				el.classList.remove("woactive");
			}
		});

		/**
		 * Add active class to slide
		 */
		_.slides[_.active].classList.add("woactive");

		/**
		 * Track impression
		 */
		_.trackImpression(_.slides[_.active]);
	};

	WORotatePrototype.next = function () {
		const _ = this;

		/**
		 * Change active slide and start the timeout.
		 */
		_.setActive(_.active < _.slides.length - 1 ? _.active + 1 : 0);
		_.startInterval();
	};

	WORotatePrototype.startInterval = function () {
		const _ = this;

		/**
		 * Set a new timeout, bound to this instance
		 */
		_.timeout = setTimeout(_.next.bind(_), _.opt.interval);
	};

	WORotatePrototype.outerHeight = function (element) {
		if (typeof window.getComputedStyle === "undefined") {
			return 0;
		}

		const height = element.offsetHeight,
			style = window.getComputedStyle(element);

		return ["top", "bottom"]
			.map((side) => parseInt(style[`margin-${side}`]))
			.reduce((total, side) => total + side, height);
	};

	WORotatePrototype.init = function () {
		const _ = this;
		let slideHeight = 0;

		/**
		 * Add class to container.
		 */
		_.ele.classList.add("worotate");

		/**
		 * Add all children as slides.
		 */
		_.slides = _.ele.children;

		/**
		 * No slides? Stop init.
		 */
		if (_.slides.length <= 0) {
			return false;
		}

		/**
		 * Initiate tracker
		 */
		_.initTimestamp = Date.now();

		_.tracker = typeof WOTrack !== "undefined" ? new WOTrack() : null;

		/**
		 * Store tracking ID so DOM can't be manipulated later.
		 * Also grab the slide height if needed.
		 */
		Array.from(_.slides).forEach((slide, idx) => {
			_.slides[idx].trackId = _.getTrackingAdId(slide);
			_.bindTrackClick(slide);

			if (_.opt.heightMode === "fixed") {
				const thisH = _.outerHeight(slide);

				if (thisH > slideHeight) {
					slideHeight = thisH;
				}
			}
		});

		/**
		 * Determine if we have an active slide from the slide markup.
		 */
		let active = Array.from(_.slides).findIndex((e) => {
			return e.classList.contains("woactive");
		});

		if (!active || active < 0) {
			active = 0;
		}

		/**
		 * Set the active slide.
		 */
		_.setActive(active);
		_.ele.classList.add("woloaded");

		/**
		 * If we have more than 1 slide, start the rotation interval.
		 */
		if (_.slides.length > 1) {
			/**
			 * Maybe set a min-height.
			 */
			if (_.opt.heightMode === "fixed" && slideHeight > 0) {
				_.ele.style.minHeight = slideHeight + "px";
			}

			_.startInterval();
		}
	};

	const WORotateInit = (_window.WORotateInit = function (elements) {
		/**
		 * Loop through all rotating ad groups on the page and initialize a WORotate instance.
		 */
		if (elements && typeof elements !== "undefined" && elements.length > 0) {
			for (const element of elements) {
				/**
				 * Get timeout from data attribute, if one exists.
				 */
				let refresh = parseInt(element.getAttribute("data-interval"), 10);

				if (!refresh || refresh === undefined || refresh < 1000) {
					refresh = 5000;
				}

				let stopTracking = parseInt(element.getAttribute("data-stoptrack"), 10);

				if (
					typeof stopTracking === "undefined" ||
					stopTracking < 0 ||
					isNaN(stopTracking)
				) {
					stopTracking = 0;
				}

				new WORotate(element, { interval: refresh, stopTrack: stopTracking });
			}
		}
	});
})();
