<?php
namespace ADCmdr;

/**
 * Class for determining if we're dealing with a bot or real visitor.
 */
class Bots {

	/**
	 * A regex string of bot user-agents.
	 *
	 * @return string
	 */
	public static function user_agent_regex() {
		return '008|ADmantX|Accoona-AI-Agent|Apple-PubSub|Arachmo|Ask Jeeves|AspiegelBot|B-l-i-t-z-B-O-T|BUbiNG|Barkrowler|BingPreview|Cerberian Drtrs|Charlotte|Covario IDS|DDG-Android|Datanyze|DataparkSearch|Dataprovider\.com|Daum|Ecosia|Feedfetcher-Google|FindLinks|Firefly|FlyingPress|Genieo|Google-AMPHTML|GoogleAdSenseInfeed|Googlebot|Hexometer|Holmes|InfoSeek|Kraken|L\.webis|Larbin|Linguee|LinkWalker|MVAClient|Mnogosearch|Morning Paper|NG-Search|NationalDirectory|NetResearchServer|NewsGator|Nusearch|NutchCVS|Nymesis|Optimizer|Orbiter|Peew|Pompos|PostPost|PycURL|Qseero|Qwantify|Radian6|Reeder|SBIder|Scooter|ScoutJet|Scrubby|SearchSight|Seekport Crawler|Sensis|ShopWiki|Snappy|Sogou web spider|Spade|Sqworm|StackRambler|TECNOSEEK|TechnoratiSnoop|Teoma|Thumbnail\.CZ|TinEye|VYU2|Vagabondo|Vortex|WP Rocket|WeSEE:Ads|WebBug|WebIndex|Websquash\.com|Wget|WomlpeFactory|WordPress|Yahoo! Slurp|Yahoo! Slurp China|YahooSeeker|YahooSeeker-Testing|YandexBlogs|YandexBot|YandexCalendar|YandexImages|YandexMedia|YandexNews|Yeti|Zao|ZyBorg|^Mozilla\/5\.0$|alexa|appie|avira\.com|bingbot|boitho\.com-dc|bot|cosmos|crawler|curl|datanyze|ecosia|expo9|facebookexternalhit|froogle|heritrix|htdig|https:\/\/developers\.google\.com|ia_archiver|ichiro|igdeSpyder|inktomi|ips-agent|looksmart|ltx71|lwp-trivial|mabontland|mediapartners|mogimogi|oegp|okhttp|parser|proximic|rabaz|savetheworldheritage|scraper|semanticdiscovery|silk|spider|truwoGPS|updated|voltron|voyager|webcollage|wf84|yacy|yoogliFetchAgent';
	}

	/**
	 * Determine if a user-agent is a known bot.
	 *
	 * @param null $user_agent The user-agent to check against known bots.
	 *
	 * @return bool
	 */
	public static function is_bot( $user_agent = null ) {
		if ( ! $user_agent ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		}

		if ( empty( $user_agent ) || $user_agent === '' ) {
			return true;
		}

		/**
		 * Filter: adcmdr_bot_user_agent_regex
		 *
		 * Allow for modification of the bot regex string.
		 */
		$bots = apply_filters( 'adcmdr_bot_user_agent_regex', self::user_agent_regex() );

		if ( empty( $bots ) ) {
			return false;
		}

		$matches = preg_match( '/' . $bots . '/i', $user_agent );

		return ( $matches !== false && $matches > 0 ) ? true : false;
	}
}
