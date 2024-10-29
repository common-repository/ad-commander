const mix = require("laravel-mix");

mix
	.sourceMaps(false, "source-map")
	.js("src/js/admin/setting-restrict.js", "dist/js/setting-restrict.js")
	.js("src/js/admin/term-meta.js", "dist/js/term-meta.js")
	.js("src/js/admin/ad-post.js", "dist/js/ad-post.js")
	.js("src/js/admin/placement-post.js", "dist/js/placement-post.js")
	.js("src/js/admin/targeting.js", "dist/js/targeting.js")
	.js("src/js/admin/reports.js", "dist/js/reports.js")
	.js("src/js/admin/settings.js", "dist/js/settings.js")
	.js("src/js/admin/notifications.js", "dist/js/notifications.js")
	.js("src/js/admin/copy.js", "dist/js/copy.js")
	.js("src/js/admin/settings-adsense.js", "dist/js/settings-adsense.js")
	.js("src/js/admin/onboarding.js", "dist/js/onboarding.js")
	.js("src/js/front/rotate.js", "dist/js/rotate.js")
	.js("src/js/front/track-local.js", "dist/js/track-local.js")
	.js("src/js/front/track.js", "dist/js/track.js")
	.js("src/js/front/front.js", "dist/js/front.js")
	.sass("src/scss/style.scss", "dist/css/")
	.sass("src/scss/admin.scss", "dist/css/")
	.sass("src/scss/admin_global.scss", "dist/css/")
	.options({
		processCssUrls: false,
	});
