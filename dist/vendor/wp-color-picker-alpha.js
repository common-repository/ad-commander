/**!
 * wp-color-picker-alpha
 *
 * Overwrite Automattic Iris for enabled Alpha Channel in wpColorPicker
 * Only run in input and is defined data alpha in true
 *
 * Version: 3.0.3
 * https://github.com/kallookoo/wp-color-picker-alpha
 * Licensed under the GPLv2 license or later.
 */
!(function ($, undef) {
	var wpColorPickerAlpha = { version: 303 };
	if (
		"wpColorPickerAlpha" in window &&
		"version" in window.wpColorPickerAlpha
	) {
		var version = parseInt(window.wpColorPickerAlpha.version, 10);
		if (!isNaN(version) && version >= wpColorPickerAlpha.version) return;
	}
	if (!Color.fn.hasOwnProperty("to_s")) {
		(Color.fn.to_s = function (type) {
			"hex" === (type = type || "hex") && this._alpha < 1 && (type = "rgba");
			var color = "";
			return (
				"hex" === type
					? (color = this.toString())
					: this.error ||
						(color = this.toCSS(type)
							.replace(/\(\s+/, "(")
							.replace(/\s+\)/, ")")),
				color
			);
		}),
			(window.wpColorPickerAlpha = wpColorPickerAlpha);
		var backgroundImage =
			"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAAHnlligAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAHJJREFUeNpi+P///4EDBxiAGMgCCCAGFB5AADGCRBgYDh48CCRZIJS9vT2QBAggFBkmBiSAogxFBiCAoHogAKIKAlBUYTELAiAmEtABEECk20G6BOmuIl0CIMBQ/IEMkO0myiSSraaaBhZcbkUOs0HuBwDplz5uFJ3Z4gAAAABJRU5ErkJggg==";
		$.widget("a8c.iris", $.a8c.iris, {
			alphaOptions: { alphaEnabled: !1 },
			_getColor: function (color) {
				return (
					undefined === color && (color = this._color),
					this.alphaOptions.alphaEnabled
						? ((color = color.to_s(this.alphaOptions.alphaColorType)),
							this.alphaOptions.alphaColorWithSpace ||
								(color = color.replace(/\s+/g, "")),
							color)
						: color.toString()
				);
			},
			_create: function () {
				try {
					this.alphaOptions =
						this.element.wpColorPicker("instance").alphaOptions;
				} catch (e) {}
				$.extend({}, this.alphaOptions, {
					alphaEnabled: !1,
					alphaCustomWidth: 130,
					alphaReset: !1,
					alphaColorType: "hex",
					alphaColorWithSpace: !1,
					alphaSkipDebounce: !1,
					alphaDebounceTimeout: 100,
				}),
					this._super();
			},
			_addInputListeners: function (input) {
				var self = this,
					callback = function (event) {
						var val = input.val(),
							color = new Color(val),
							type =
								((val = val.replace(/^(#|(rgb|hsl)a?)/, "")),
								self.alphaOptions.alphaColorType);
						input.removeClass("iris-error"),
							color.error
								? "" !== val && input.addClass("iris-error")
								: ("hex" === type &&
										"keyup" === event.type &&
										val.match(/^[0-9a-fA-F]{3}$/)) ||
									(color.toIEOctoHex() !== self._color.toIEOctoHex() &&
										self._setOption("color", self._getColor(color)));
					};
				input.on("change", callback),
					self.alphaOptions.alphaSkipDebounce ||
						input.on(
							"keyup",
							self._debounce(callback, self.alphaOptions.alphaDebounceTimeout)
						),
					self.options.hide &&
						input.one("focus", function () {
							self.show();
						});
			},
			_initControls: function () {
				if ((this._super(), this.alphaOptions.alphaEnabled)) {
					var self = this,
						stripAlpha = self.controls.strip.clone(!1, !1),
						stripAlphaSlider = stripAlpha.find(".iris-slider-offset"),
						controls = {
							stripAlpha: stripAlpha,
							stripAlphaSlider: stripAlphaSlider,
						};
					stripAlpha.addClass("iris-strip-alpha"),
						stripAlphaSlider.addClass("iris-slider-offset-alpha"),
						stripAlpha.appendTo(self.picker.find(".iris-picker-inner")),
						$.each(controls, function (k, v) {
							self.controls[k] = v;
						}),
						self.controls.stripAlphaSlider.slider({
							orientation: "vertical",
							min: 0,
							max: 100,
							step: 1,
							value: parseInt(100 * self._color._alpha),
							slide: function (event, ui) {
								(self.active = "strip"),
									(self._color._alpha = parseFloat(ui.value / 100)),
									self._change.apply(self, arguments);
							},
						});
				}
			},
			_dimensions: function (reset) {
				if ((this._super(reset), this.alphaOptions.alphaEnabled)) {
					var innerWidth,
						squareWidth,
						stripWidth,
						stripMargin,
						totalWidth,
						opts = this.options,
						square = this.controls.square,
						strip = this.picker.find(".iris-strip");
					for (
						innerWidth = Math.round(
							this.picker.outerWidth(!0) - (opts.border ? 22 : 0)
						),
							squareWidth = Math.round(square.outerWidth()),
							stripWidth = Math.round((innerWidth - squareWidth) / 2),
							stripMargin = Math.round(stripWidth / 2),
							totalWidth = Math.round(
								squareWidth + 2 * stripWidth + 2 * stripMargin
							);
						totalWidth > innerWidth;

					)
						(stripWidth = Math.round(stripWidth - 2)),
							(stripMargin = Math.round(stripMargin - 1)),
							(totalWidth = Math.round(
								squareWidth + 2 * stripWidth + 2 * stripMargin
							));
					square.css("margin", "0"),
						strip.width(stripWidth).css("margin-left", stripMargin + "px");
				}
			},
			_change: function () {
				if ((this._super(), this.alphaOptions.alphaEnabled)) {
					var self = this,
						active = self.active;
					(controls = self.controls),
						(alpha = parseInt(100 * self._color._alpha)),
						(color = self._color.toRgb()),
						(gradient = [
							"rgb(" + color.r + "," + color.g + "," + color.b + ") 0%",
							"rgba(" + color.r + "," + color.g + "," + color.b + ", 0) 100%",
						]),
						(self.options.color = self._getColor()),
						controls.stripAlpha.css({
							background:
								"linear-gradient(to bottom, " +
								gradient.join(", ") +
								"), url(" +
								backgroundImage +
								")",
						}),
						active && controls.stripAlphaSlider.slider("value", alpha),
						self._color.error ||
							self.element.removeClass("iris-error").val(self.options.color),
						self.picker
							.find(".iris-palette-container")
							.on("click.palette", ".iris-palette", function () {
								var color = $(this).data("color");
								self.alphaOptions.alphaReset &&
									((self._color._alpha = 1), (color = self._getColor())),
									self._setOption("color", color);
							});
				}
			},
			_paintDimension: function (origin, control) {
				var color = !1;
				this.alphaOptions.alphaEnabled &&
					"strip" === control &&
					((color = this._color),
					(this._color = new Color(color.toString())),
					(this.hue = this._color.h())),
					this._super(origin, control),
					color && (this._color = color);
			},
			_setOption: function (key, value) {
				if ("color" !== key || !this.alphaOptions.alphaEnabled)
					return this._super(key, value);
				(value = "" + value),
					(newColor = new Color(value).setHSpace(this.options.mode)),
					newColor.error ||
						this._getColor(newColor) === this._getColor() ||
						((this._color = newColor),
						(this.options.color = this._getColor()),
						(this.active = "external"),
						this._change());
			},
			color: function (newColor) {
				return !0 === newColor
					? this._color.clone()
					: undefined === newColor
						? this._getColor()
						: void this.option("color", newColor);
			},
		}),
			$.widget("wp.wpColorPicker", $.wp.wpColorPicker, {
				alphaOptions: { alphaEnabled: !1 },
				_getAlphaOptions: function () {
					var el = this.element,
						type = el.data("type") || this.options.type,
						color = el.data("defaultColor") || el.val(),
						options = {
							alphaEnabled: el.data("alphaEnabled") || !1,
							alphaCustomWidth: 130,
							alphaReset: !1,
							alphaColorType: "rgb",
							alphaColorWithSpace: !1,
							alphaSkipDebounce: !!el.data("alphaSkipDebounce") || !1,
						};
					return (
						options.alphaEnabled &&
							(options.alphaEnabled = el.is("input") && "full" === type),
						options.alphaEnabled
							? ((options.alphaColorWithSpace = color && color.match(/\s/)),
								$.each(options, function (name, defaultValue) {
									var value = el.data(name) || defaultValue;
									switch (name) {
										case "alphaCustomWidth":
											(value = value ? parseInt(value, 10) : 0),
												(value = isNaN(value) ? defaultValue : value);
											break;
										case "alphaColorType":
											value.match(/^(hex|(rgb|hsl)a?)$/) ||
												(value =
													color && color.match(/^#/)
														? "hex"
														: color && color.match(/^hsla?/)
															? "hsl"
															: defaultValue);
											break;
										default:
											value = !!value;
									}
									options[name] = value;
								}),
								options)
							: options
					);
				},
				_create: function () {
					$.support.iris &&
						((this.alphaOptions = this._getAlphaOptions()), this._super());
				},
				_addListeners: function () {
					if (!this.alphaOptions.alphaEnabled) return this._super();
					var self = this,
						el = self.element,
						isDeprecated = self.toggler.is("a");
					(this.alphaOptions.defaultWidth = el.width()),
						this.alphaOptions.alphaCustomWidth &&
							el.width(
								parseInt(
									this.alphaOptions.defaultWidth +
										this.alphaOptions.alphaCustomWidth,
									10
								)
							),
						self.toggler.css({
							position: "relative",
							"background-image": "url(" + backgroundImage + ")",
						}),
						isDeprecated
							? self.toggler.html('<span class="color-alpha" />')
							: self.toggler.append('<span class="color-alpha" />'),
						(self.colorAlpha = self.toggler.find("span.color-alpha").css({
							width: "30px",
							height: "100%",
							position: "absolute",
							top: 0,
							"background-color": el.val(),
						})),
						"ltr" === self.colorAlpha.css("direction")
							? self.colorAlpha.css({
									"border-bottom-left-radius": "2px",
									"border-top-left-radius": "2px",
									left: 0,
								})
							: self.colorAlpha.css({
									"border-bottom-right-radius": "2px",
									"border-top-right-radius": "2px",
									right: 0,
								}),
						el.iris({
							change: function (event, ui) {
								self.colorAlpha.css({
									"background-color": ui.color.to_s(
										self.alphaOptions.alphaColorType
									),
								}),
									"function" == typeof self.options.change &&
										self.options.change.call(this, event, ui);
							},
						}),
						self.wrap.on("click.wpcolorpicker", function (event) {
							event.stopPropagation();
						}),
						self.toggler.on("click", function () {
							self.toggler.hasClass("wp-picker-open")
								? self.close()
								: self.open();
						}),
						el.on("change", function (event) {
							var val = $(this).val();
							(el.hasClass("iris-error") ||
								"" === val ||
								val.match(/^(#|(rgb|hsl)a?)$/)) &&
								(isDeprecated && self.toggler.removeAttr("style"),
								self.colorAlpha.css("background-color", ""),
								"function" == typeof self.options.clear &&
									self.options.clear.call(this, event));
						}),
						self.button.on("click", function (event) {
							var $this = $(this);
							$this.hasClass("wp-picker-default")
								? el.val(self.options.defaultColor).change()
								: $this.hasClass("wp-picker-clear") &&
									(el.val(""),
									isDeprecated && self.toggler.removeAttr("style"),
									self.colorAlpha.css("background-color", ""),
									"function" == typeof self.options.clear &&
										self.options.clear.call(this, event),
									el.trigger("change"));
						});
				},
			});
	}
})(jQuery);
