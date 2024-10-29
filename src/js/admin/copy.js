import * as ClipboardJS from "../../../node_modules/clipboard/dist/clipboard.min";

jQuery(document).ready(function ($) {
	const $copy = $("[data-adcmdr-copy]");

	if ($copy.length > 0) {
		$copy.on("click", function (e) {
			e.preventDefault();
		});

		const clipboard = new ClipboardJS("[data-adcmdr-copy]", {
			text: function (trigger) {
				return trigger.getAttribute("data-adcmdr-copy");
			},
		});

		clipboard.on("success", function (e) {
			const elem = e.trigger;
			elem.classList.add("adcmdr-copied");

			setTimeout(() => elem.classList.remove("adcmdr-copied"), 200, elem);
		});
	}
});
