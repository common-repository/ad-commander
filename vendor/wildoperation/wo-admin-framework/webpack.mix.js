const mix = require("laravel-mix");

mix
	.sourceMaps(false, "source-map")
	.webpackConfig({ devtool: "source-map" })
	.js("src/js/repeater.js", "dist/js/repeater.js")
	.sass("src/scss/admin.scss", "dist/css/")
	.options({
		processCssUrls: false,
	});
