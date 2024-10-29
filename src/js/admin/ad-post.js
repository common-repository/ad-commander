import * as ad_post_featured_image from "./ad-post-featured-image";
import * as ad_post_adsense from "./ad-post-adsense";

jQuery(document).ready(function ($) {
	/**
	 * Mode controls
	 */
	const $ad_type = $("#_adcmdr_adtype");
	if ($ad_type.length > 0) {
		$ad_type.setting_restrict();

		$('a[href="#_adcmdr_adtype"]').on("click", function (e) {
			e.preventDefault();
			$ad_type.val($(this).data("adtype"));
			$ad_type.change();
		});
	}

	/**
	 * Date pickers
	 */
	$(".adcmdr-datepicker").each(function () {
		$(this).datepicker();
	});

	/**
	 * Clear settings
	 */
	$(".adcmdr-clear").each(function () {
		const $this = $(this);
		$this.on("click", function (e) {
			e.preventDefault();
			const $parent = $this.closest(".adcmdr-metaitem");
			$parent.find("input").val("");

			$parent.find("select").each(function () {
				const $sel = $(this);
				$sel.val($sel.children("option:first").val());
			});

			$parent.find("textarea").text("");
		});
	});
});
