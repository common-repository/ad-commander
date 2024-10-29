<?php
namespace ADCmdr;

/**
 * Misc data used throughout this plugin.
 */
class Geo {

	/**
	 * Frequent countries for use at top of admin list.
	 *
	 * @return array
	 */
	public static function frequent_countries() {
		return apply_filters(
			'adcmdr_frequent_countries',
			array(
				'US' => __( 'United States', 'ad-commander' ),
				'GB' => __( 'United Kingdom', 'ad-commander' ),
				'EU' => __( 'European Union', 'ad-commander' ),
			)
		);
	}

	/**
	 * Return an array of 'continents.'
	 *
	 * Note: Antartica and Australia not included here. Oceania included.
	 *
	 * @return array
	 */
	public static function continents() {
		return apply_filters(
			'adcmdr_continents',
			array(
				'cont_NA' => __( 'North America', 'ad-commander' ),
				'cont_SA' => __( 'South America', 'ad-commander' ),
				'cont_EU' => __( 'Europe', 'ad-commander' ),
				'cont_AF' => __( 'Africa', 'ad-commander' ),
				'cont_AS' => __( 'Asia', 'ad-commander' ),
				'cont_OC' => __( 'Oceania', 'ad-commander' ),
			)
		);
	}

	public static function eu_country_codes() {
		return array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );
	}

	/**
	 * Return list of countries and their 2-digit codes.
	 * https://www.iban.com/country-codes
	 *
	 * @return array
	 */
	public static function countries() {
		return apply_filters(
			'adcmdr_countries',
			array(
				'AF' => __( 'Afghanistan', 'ad-commander' ),
				'AL' => __( 'Albania', 'ad-commander' ),
				'DZ' => __( 'Algeria', 'ad-commander' ),
				'AS' => __( 'American Samoa', 'ad-commander' ),
				'AD' => __( 'Andorra', 'ad-commander' ),
				'AO' => __( 'Angola', 'ad-commander' ),
				'AI' => __( 'Anguilla', 'ad-commander' ),
				'AQ' => __( 'Antarctica', 'ad-commander' ),
				'AG' => __( 'Antigua and Barbuda', 'ad-commander' ),
				'AR' => __( 'Argentina', 'ad-commander' ),
				'AM' => __( 'Armenia', 'ad-commander' ),
				'AW' => __( 'Aruba', 'ad-commander' ),
				'AU' => __( 'Australia', 'ad-commander' ),
				'AT' => __( 'Austria', 'ad-commander' ),
				'AZ' => __( 'Azerbaijan', 'ad-commander' ),
				'BS' => __( 'Bahamas', 'ad-commander' ),
				'BH' => __( 'Bahrain', 'ad-commander' ),
				'BD' => __( 'Bangladesh', 'ad-commander' ),
				'BB' => __( 'Barbados', 'ad-commander' ),
				'BY' => __( 'Belarus', 'ad-commander' ),
				'BE' => __( 'Belgium', 'ad-commander' ),
				'BZ' => __( 'Belize', 'ad-commander' ),
				'BJ' => __( 'Benin', 'ad-commander' ),
				'BM' => __( 'Bermuda', 'ad-commander' ),
				'BT' => __( 'Bhutan', 'ad-commander' ),
				'BO' => __( 'Bolivia (Plurinational State of)', 'ad-commander' ),
				'BQ' => __( 'Bonaire, Sint Eustatius and Saba', 'ad-commander' ),
				'BA' => __( 'Bosnia and Herzegovina', 'ad-commander' ),
				'BW' => __( 'Botswana', 'ad-commander' ),
				'BV' => __( 'Bouvet Island', 'ad-commander' ),
				'BR' => __( 'Brazil', 'ad-commander' ),
				'IO' => __( 'British Indian Ocean Territory', 'ad-commander' ),
				'BN' => __( 'Brunei Darussalam', 'ad-commander' ),
				'BG' => __( 'Bulgaria', 'ad-commander' ),
				'BF' => __( 'Burkina Faso', 'ad-commander' ),
				'BI' => __( 'Burundi', 'ad-commander' ),
				'CV' => __( 'Cabo Verde', 'ad-commander' ),
				'KH' => __( 'Cambodia', 'ad-commander' ),
				'CM' => __( 'Cameroon', 'ad-commander' ),
				'CA' => __( 'Canada', 'ad-commander' ),
				'KY' => __( 'Cayman Islands', 'ad-commander' ),
				'CF' => __( 'Central African Republic', 'ad-commander' ),
				'TD' => __( 'Chad', 'ad-commander' ),
				'CL' => __( 'Chile', 'ad-commander' ),
				'CN' => __( 'China', 'ad-commander' ),
				'CX' => __( 'Christmas Island', 'ad-commander' ),
				'CC' => __( 'Cocos (Keeling) Islands', 'ad-commander' ),
				'CO' => __( 'Colombia', 'ad-commander' ),
				'KM' => __( 'Comoros', 'ad-commander' ),
				'CD' => __( 'Congo (the Democratic Republic of the)', 'ad-commander' ),
				'CG' => __( 'Congo', 'ad-commander' ),
				'CK' => __( 'Cook Islands', 'ad-commander' ),
				'CR' => __( 'Costa Rica', 'ad-commander' ),
				'HR' => __( 'Croatia', 'ad-commander' ),
				'CU' => __( 'Cuba', 'ad-commander' ),
				'CW' => __( 'Curaçao', 'ad-commander' ),
				'CY' => __( 'Cyprus', 'ad-commander' ),
				'CZ' => __( 'Czechia', 'ad-commander' ),
				'CI' => __( 'Côte d\'Ivoire', 'ad-commander' ),
				'DK' => __( 'Denmark', 'ad-commander' ),
				'DJ' => __( 'Djibouti', 'ad-commander' ),
				'DM' => __( 'Dominica', 'ad-commander' ),
				'DO' => __( 'Dominican Republic', 'ad-commander' ),
				'EC' => __( 'Ecuador', 'ad-commander' ),
				'EG' => __( 'Egypt', 'ad-commander' ),
				'SV' => __( 'El Salvador', 'ad-commander' ),
				'GQ' => __( 'Equatorial Guinea', 'ad-commander' ),
				'ER' => __( 'Eritrea', 'ad-commander' ),
				'EE' => __( 'Estonia', 'ad-commander' ),
				'SZ' => __( 'Eswatini', 'ad-commander' ),
				'ET' => __( 'Ethiopia', 'ad-commander' ),
				'FK' => __( 'Falkland Islands [Malvinas]', 'ad-commander' ),
				'FO' => __( 'Faroe Islands', 'ad-commander' ),
				'FJ' => __( 'Fiji', 'ad-commander' ),
				'FI' => __( 'Finland', 'ad-commander' ),
				'FR' => __( 'France', 'ad-commander' ),
				'GF' => __( 'French Guiana', 'ad-commander' ),
				'PF' => __( 'French Polynesia', 'ad-commander' ),
				'TF' => __( 'French Southern Territories', 'ad-commander' ),
				'GA' => __( 'Gabon', 'ad-commander' ),
				'GM' => __( 'Gambia', 'ad-commander' ),
				'GE' => __( 'Georgia', 'ad-commander' ),
				'DE' => __( 'Germany', 'ad-commander' ),
				'GH' => __( 'Ghana', 'ad-commander' ),
				'GI' => __( 'Gibraltar', 'ad-commander' ),
				'GR' => __( 'Greece', 'ad-commander' ),
				'GL' => __( 'Greenland', 'ad-commander' ),
				'GD' => __( 'Grenada', 'ad-commander' ),
				'GP' => __( 'Guadeloupe', 'ad-commander' ),
				'GU' => __( 'Guam', 'ad-commander' ),
				'GT' => __( 'Guatemala', 'ad-commander' ),
				'GG' => __( 'Guernsey', 'ad-commander' ),
				'GN' => __( 'Guinea', 'ad-commander' ),
				'GW' => __( 'Guinea-Bissau', 'ad-commander' ),
				'GY' => __( 'Guyana', 'ad-commander' ),
				'HT' => __( 'Haiti', 'ad-commander' ),
				'HM' => __( 'Heard Island and McDonald Islands', 'ad-commander' ),
				'VA' => __( 'Holy See', 'ad-commander' ),
				'HN' => __( 'Honduras', 'ad-commander' ),
				'HK' => __( 'Hong Kong', 'ad-commander' ),
				'HU' => __( 'Hungary', 'ad-commander' ),
				'IS' => __( 'Iceland', 'ad-commander' ),
				'IN' => __( 'India', 'ad-commander' ),
				'ID' => __( 'Indonesia', 'ad-commander' ),
				'IR' => __( 'Iran (Islamic Republic of)', 'ad-commander' ),
				'IQ' => __( 'Iraq', 'ad-commander' ),
				'IE' => __( 'Ireland', 'ad-commander' ),
				'IM' => __( 'Isle of Man', 'ad-commander' ),
				'IL' => __( 'Israel', 'ad-commander' ),
				'IT' => __( 'Italy', 'ad-commander' ),
				'JM' => __( 'Jamaica', 'ad-commander' ),
				'JP' => __( 'Japan', 'ad-commander' ),
				'JE' => __( 'Jersey', 'ad-commander' ),
				'JO' => __( 'Jordan', 'ad-commander' ),
				'KZ' => __( 'Kazakhstan', 'ad-commander' ),
				'KE' => __( 'Kenya', 'ad-commander' ),
				'KI' => __( 'Kiribati', 'ad-commander' ),
				'KP' => __( 'Korea (the Democratic People\'s Republic of)', 'ad-commander' ),
				'KR' => __( 'Korea (the Republic of)', 'ad-commander' ),
				'KW' => __( 'Kuwait', 'ad-commander' ),
				'KG' => __( 'Kyrgyzstan', 'ad-commander' ),
				'LA' => __( 'Lao People\'s Democratic Republic', 'ad-commander' ),
				'LV' => __( 'Latvia', 'ad-commander' ),
				'LB' => __( 'Lebanon', 'ad-commander' ),
				'LS' => __( 'Lesotho', 'ad-commander' ),
				'LR' => __( 'Liberia', 'ad-commander' ),
				'LY' => __( 'Libya', 'ad-commander' ),
				'LI' => __( 'Liechtenstein', 'ad-commander' ),
				'LT' => __( 'Lithuania', 'ad-commander' ),
				'LU' => __( 'Luxembourg', 'ad-commander' ),
				'MO' => __( 'Macao', 'ad-commander' ),
				'MG' => __( 'Madagascar', 'ad-commander' ),
				'MW' => __( 'Malawi', 'ad-commander' ),
				'MY' => __( 'Malaysia', 'ad-commander' ),
				'MV' => __( 'Maldives', 'ad-commander' ),
				'ML' => __( 'Mali', 'ad-commander' ),
				'MT' => __( 'Malta', 'ad-commander' ),
				'MH' => __( 'Marshall Islands', 'ad-commander' ),
				'MQ' => __( 'Martinique', 'ad-commander' ),
				'MR' => __( 'Mauritania', 'ad-commander' ),
				'MU' => __( 'Mauritius', 'ad-commander' ),
				'YT' => __( 'Mayotte', 'ad-commander' ),
				'MX' => __( 'Mexico', 'ad-commander' ),
				'FM' => __( 'Micronesia (Federated States of)', 'ad-commander' ),
				'MD' => __( 'Moldova (the Republic of)', 'ad-commander' ),
				'MC' => __( 'Monaco', 'ad-commander' ),
				'MN' => __( 'Mongolia', 'ad-commander' ),
				'ME' => __( 'Montenegro', 'ad-commander' ),
				'MS' => __( 'Montserrat', 'ad-commander' ),
				'MA' => __( 'Morocco', 'ad-commander' ),
				'MZ' => __( 'Mozambique', 'ad-commander' ),
				'MM' => __( 'Myanmar', 'ad-commander' ),
				'NA' => __( 'Namibia', 'ad-commander' ),
				'NR' => __( 'Nauru', 'ad-commander' ),
				'NP' => __( 'Nepal', 'ad-commander' ),
				'NL' => __( 'Netherlands', 'ad-commander' ),
				'NC' => __( 'New Caledonia', 'ad-commander' ),
				'NZ' => __( 'New Zealand', 'ad-commander' ),
				'NI' => __( 'Nicaragua', 'ad-commander' ),
				'NE' => __( 'Niger', 'ad-commander' ),
				'NG' => __( 'Nigeria', 'ad-commander' ),
				'NU' => __( 'Niue', 'ad-commander' ),
				'NF' => __( 'Norfolk Island', 'ad-commander' ),
				'MP' => __( 'Northern Mariana Islands', 'ad-commander' ),
				'NO' => __( 'Norway', 'ad-commander' ),
				'OM' => __( 'Oman', 'ad-commander' ),
				'PK' => __( 'Pakistan', 'ad-commander' ),
				'PW' => __( 'Palau', 'ad-commander' ),
				'PS' => __( 'Palestine, State of', 'ad-commander' ),
				'PA' => __( 'Panama', 'ad-commander' ),
				'PG' => __( 'Papua New Guinea', 'ad-commander' ),
				'PY' => __( 'Paraguay', 'ad-commander' ),
				'PE' => __( 'Peru', 'ad-commander' ),
				'PH' => __( 'Philippines', 'ad-commander' ),
				'PN' => __( 'Pitcairn', 'ad-commander' ),
				'PL' => __( 'Poland', 'ad-commander' ),
				'PT' => __( 'Portugal', 'ad-commander' ),
				'PR' => __( 'Puerto Rico', 'ad-commander' ),
				'QA' => __( 'Qatar', 'ad-commander' ),
				'MK' => __( 'Republic of North Macedonia', 'ad-commander' ),
				'RO' => __( 'Romania', 'ad-commander' ),
				'RU' => __( 'Russian Federation', 'ad-commander' ),
				'RW' => __( 'Rwanda', 'ad-commander' ),
				'RE' => __( 'Réunion', 'ad-commander' ),
				'BL' => __( 'Saint Barthélemy', 'ad-commander' ),
				'SH' => __( 'Saint Helena, Ascension and Tristan da Cunha', 'ad-commander' ),
				'KN' => __( 'Saint Kitts and Nevis', 'ad-commander' ),
				'LC' => __( 'Saint Lucia', 'ad-commander' ),
				'MF' => __( 'Saint Martin (French part)', 'ad-commander' ),
				'PM' => __( 'Saint Pierre and Miquelon', 'ad-commander' ),
				'VC' => __( 'Saint Vincent and the Grenadines', 'ad-commander' ),
				'WS' => __( 'Samoa', 'ad-commander' ),
				'SM' => __( 'San Marino', 'ad-commander' ),
				'ST' => __( 'Sao Tome and Principe', 'ad-commander' ),
				'SA' => __( 'Saudi Arabia', 'ad-commander' ),
				'SN' => __( 'Senegal', 'ad-commander' ),
				'RS' => __( 'Serbia', 'ad-commander' ),
				'SC' => __( 'Seychelles', 'ad-commander' ),
				'SL' => __( 'Sierra Leone', 'ad-commander' ),
				'SG' => __( 'Singapore', 'ad-commander' ),
				'SX' => __( 'Sint Maarten (Dutch part)', 'ad-commander' ),
				'SK' => __( 'Slovakia', 'ad-commander' ),
				'SI' => __( 'Slovenia', 'ad-commander' ),
				'SB' => __( 'Solomon Islands', 'ad-commander' ),
				'SO' => __( 'Somalia', 'ad-commander' ),
				'ZA' => __( 'South Africa', 'ad-commander' ),
				'GS' => __( 'South Georgia and the South Sandwich Islands', 'ad-commander' ),
				'SS' => __( 'South Sudan', 'ad-commander' ),
				'ES' => __( 'Spain', 'ad-commander' ),
				'LK' => __( 'Sri Lanka', 'ad-commander' ),
				'SD' => __( 'Sudan', 'ad-commander' ),
				'SR' => __( 'Suriname', 'ad-commander' ),
				'SJ' => __( 'Svalbard and Jan Mayen', 'ad-commander' ),
				'SE' => __( 'Sweden', 'ad-commander' ),
				'CH' => __( 'Switzerland', 'ad-commander' ),
				'SY' => __( 'Syrian Arab Republic', 'ad-commander' ),
				'TW' => __( 'Taiwan', 'ad-commander' ),
				'TJ' => __( 'Tajikistan', 'ad-commander' ),
				'TZ' => __( 'Tanzania, United Republic of', 'ad-commander' ),
				'TH' => __( 'Thailand', 'ad-commander' ),
				'TL' => __( 'Timor-Leste', 'ad-commander' ),
				'TG' => __( 'Togo', 'ad-commander' ),
				'TK' => __( 'Tokelau', 'ad-commander' ),
				'TO' => __( 'Tonga', 'ad-commander' ),
				'TT' => __( 'Trinidad and Tobago', 'ad-commander' ),
				'TN' => __( 'Tunisia', 'ad-commander' ),
				'TR' => __( 'Turkey', 'ad-commander' ),
				'TM' => __( 'Turkmenistan', 'ad-commander' ),
				'TC' => __( 'Turks and Caicos Islands', 'ad-commander' ),
				'TV' => __( 'Tuvalu', 'ad-commander' ),
				'UG' => __( 'Uganda', 'ad-commander' ),
				'UA' => __( 'Ukraine', 'ad-commander' ),
				'AE' => __( 'United Arab Emirates', 'ad-commander' ),
				'GB' => __( 'United Kingdom', 'ad-commander' ),
				'UM' => __( 'United States Minor Outlying Islands', 'ad-commander' ),
				'US' => __( 'United States', 'ad-commander' ),
				'UY' => __( 'Uruguay', 'ad-commander' ),
				'UZ' => __( 'Uzbekistan', 'ad-commander' ),
				'VU' => __( 'Vanuatu', 'ad-commander' ),
				'VE' => __( 'Venezuela (Bolivarian Republic of)', 'ad-commander' ),
				'VN' => __( 'Viet Nam', 'ad-commander' ),
				'VG' => __( 'Virgin Islands (British)', 'ad-commander' ),
				'VI' => __( 'Virgin Islands (U.S.)', 'ad-commander' ),
				'WF' => __( 'Wallis and Futuna', 'ad-commander' ),
				'EH' => __( 'Western Sahara', 'ad-commander' ),
				'YE' => __( 'Yemen', 'ad-commander' ),
				'ZM' => __( 'Zambia', 'ad-commander' ),
				'ZW' => __( 'Zimbabwe', 'ad-commander' ),
				'AX' => __( 'Åland Islands', 'ad-commander' ),
			)
		);
	}
}
