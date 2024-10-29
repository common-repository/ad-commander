import { useBlockProps } from "@wordpress/block-editor";

/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from "@wordpress/i18n";

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { SelectControl, Placeholder } from "@wordpress/components";

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import "./editor.scss";

import { adcmdr_icon } from "./icon.js";

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit(props) {
	return (
		<div {...useBlockProps()}>
			<Placeholder
				label={__("Ad Commander", "ad-commander")}
				icon={adcmdr_icon}
				instructions={__("Select an ad or a group:", "ad-commander")}
				className="adcmdr-ad-group-block"
			>
				<SelectControl
					value={props.attributes.adcmdrId} // e.g: value = 'a'
					onChange={(id) => props.setAttributes({ adcmdrId: id })}
					__nextHasNoMarginBottom
				>
					<option value="">
						{__("Select an ad or a group", "ad-commander")}
					</option>
					<optgroup label={__("Groups:", "ad-commander")}>
						{adcmdr_ad_group_block_editor_script.groups.map((group) => (
							<option value={group.value}>{group.label}</option>
						))}
					</optgroup>
					<optgroup label={__("Ads:", "ad-commander")}>
						{adcmdr_ad_group_block_editor_script.ads.map((ad) => (
							<option value={ad.value}>{ad.label}</option>
						))}
					</optgroup>
				</SelectControl>
			</Placeholder>
		</div>
	);
}
