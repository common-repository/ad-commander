jQuery(document).ready(function ($) {
	const $postimgdiv = $("#postimagediv");
	if ($postimgdiv.length > 0) {
		const $inside = $postimgdiv.children(".inside");
		const $settings = $inside.children("#adcmdr-banner-settings");
		$settings.detach().appendTo($postimgdiv);
	}

	/**
	 * Featured image window
	 */
	const $displayWidth = $("#_adcmdr_display_width");
	const $displayHeight = $("#_adcmdr_display_height");

	if ($displayHeight.length > 0 && $displayWidth.length > 0) {
		const featuredImage = wp.media.featuredImage;
		const frame = featuredImage.frame();
		let originalDimensions = { width: null, height: null };
		let currentFeaturedImageId = featuredImage.get();

		/**
		 * Display original dimensions in admin interface.
		 */
		const $originalSize = $displayHeight
			.closest(".adcmdr-metaitem")
			.find(".adcmdr-display-original");

		function roundAspectRatio(x) {
			return Math.ceil(x * 100) / 100;
		}

		function displayOriginalSize() {
			let sz = "";
			if (
				originalDimensions.width !== null &&
				originalDimensions.height !== null
			) {
				sz = `<div class="adcmdr-specs">Original size: ${originalDimensions.width}x${originalDimensions.height}</div>`;

				const displayHeight = Math.round($displayHeight.val());
				const displayWidth = Math.round($displayWidth.val());

				if (
					originalDimensions.width !== displayWidth ||
					originalDimensions.height !== displayHeight
				) {
					sz +=
						'<div class="adcmdr-controls"><button id="reset_to_original" class="adcmdr-btn-link"><i class="dashicons dashicons-undo"></i><span>Reset<span></button>';

					if (originalDimensions.width > 0 && displayWidth > 0) {
						const aspectRatio =
							originalDimensions.height / originalDimensions.width;

						if (
							roundAspectRatio(aspectRatio) !==
							roundAspectRatio(displayHeight / displayWidth)
						) {
							sz +=
								'<button id="reset_aspect_ratio" class="adcmdr-btn-link"><i class="dashicons dashicons-randomize"></i><span>Sync Height to Aspect Ratio</span></button>';
						}
					}
					sz += "</div>";
				}
			}

			$originalSize.html(sz);
		}

		function updateOriginals(width, height) {
			originalDimensions.width = width;
			originalDimensions.height = height;
			displayOriginalSize();
		}

		function updateMetaSizes(width, height) {
			$displayWidth.val(width);
			$displayHeight.val(height);
		}

		if (currentFeaturedImageId && currentFeaturedImageId > 0) {
			$.get(
				adcmdr_adpost.media_rest_url + currentFeaturedImageId,
				function (response) {
					let width = null;
					let height = null;

					if (
						typeof response.media_details !== "undefined" &&
						typeof response.media_details.width !== "undefined"
					) {
						width = response.media_details.width;
						height = response.media_details.height;
					}

					updateOriginals(width, height);
				}
			);
		}

		frame.on("select", function () {
			const thisFeaturedImageId = wp.media.featuredImage.get();

			if (thisFeaturedImageId !== currentFeaturedImageId) {
				currentFeaturedImageId = thisFeaturedImageId;

				const attachment = frame.state().get("selection").first().toJSON();

				let width = null;
				let height = null;

				if (
					attachment &&
					typeof attachment !== "undefined" &&
					typeof attachment.width !== "undefined" &&
					typeof attachment.height !== "undefined"
				) {
					width = attachment.width;
					height = attachment.height;
				}

				updateMetaSizes(width, height);
				updateOriginals(width, height);
			}
		});

		$postimgdiv.on("click", "#remove-post-thumbnail", function () {
			currentFeaturedImageId = -1;
			updateOriginals(null, null);
			updateMetaSizes(null, null);
		});

		$originalSize.on("click", "#reset_to_original", function (e) {
			e.preventDefault();

			updateMetaSizes(originalDimensions.width, originalDimensions.height);
			displayOriginalSize();
		});

		$originalSize.on("click", "#reset_aspect_ratio", function (e) {
			e.preventDefault();

			if (originalDimensions.width > 0) {
				const aspectRatio =
					originalDimensions.height / originalDimensions.width;
				const displayWidth = Math.round($displayWidth.val());

				updateMetaSizes(displayWidth, Math.round(displayWidth * aspectRatio));
				displayOriginalSize();
			}
		});

		$displayWidth.on("blur", function () {
			displayOriginalSize();
		});

		$displayHeight.on("blur", function () {
			displayOriginalSize();
		});
	}
});
