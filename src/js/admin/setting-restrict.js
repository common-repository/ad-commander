(function ($) {
	$.fn.setting_restrict = function (options) {
		const opts = $.extend({}, $.fn.setting_restrict.defaults, options);
		const $mode = this;

		jQuery(document).ready(function ($) {
			/**
			 * Show settings based on mode
			 */
			const $restricted = $(opts.restricted_selector);
			if ($restricted.length > 0) {
				if ($mode.length > 0) {
					function mode_changed($this) {
						const currentMode = $this.val();
						const $toShow = $restricted.filter(
							opts.restricted_selector + "--" + currentMode
						);

						$restricted.not($toShow).hide();
						if ($toShow.length > 0) {
							$toShow.show();

							$toShow.each(function () {
								const $show = $(this);
								$show.trigger(opts.show_event, $show);
							});
						}
					}

					$mode.on("change", function () {
						mode_changed($(this));
					});

					setTimeout(function () {
						let $this = $mode;

						if ($mode.length > 1) {
							$this = $mode.filter(":checked");
						}

						mode_changed($this);
					}, opts.init_timeout);
				}
			}
		});
	};

	// default options
	$.fn.setting_restrict.defaults = {
		restricted_selector: ".adcmdr-mode-restrict",
		init_timeout: 100,
		show_event: "setting_restrict_is_visible",
	};
})(jQuery);
