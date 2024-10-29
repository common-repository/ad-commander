jQuery(document).ready(function ($) {
	$(".adcmdr-color-picker").each(function () {
		const $this = $(this);
		$this.wpColorPicker({
			change: function (e) {
				$this.val(e.target.value || "");
				console.log($this.val());
			},
		});
	});
});
