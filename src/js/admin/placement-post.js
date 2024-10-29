import * as colorPickers from "./color-pickers";

jQuery(document).ready(function ($) {
	$("#_adcmdr_placement_position").setting_restrict();

	$("input[name='_adcmdr_popup_display_when']").setting_restrict({
		restricted_selector: ".adcmdr-popup-restrict",
	});
});
