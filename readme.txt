=== Ad Commander - Ad Manager for Banners, AdSense, Ad Networks ===
Contributors: wildoperation, timstl
Tags: advertising, banners, rotate, adsense, amp
Requires at least: 6.2
Tested up to: 6.6
Stable tag: 1.1.8
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://wpadcommander.com/

Insert image banner ads, Google AdSense, Amazon, affiliate ad networks. Rotate and randomize ad groups. Track impressions and clicks. Create ads.txt.

== Description ==

Ad Commander is a complete ad management plugin for WordPress. With Ad Commander, users can quickly create custom banner image ads, Google AdSense ads, Amazon Associates ads, and ads for other affiliate ad networks. 

Create groups of rotating banner ads or randomly displaying ads. Insert ads with shortcodes, template tags, blocks, or automatic placements. Inject scripts into the header or footer of your site.

**AdSense users:** Ad Commander integrates directly with your AdSense account to make implementing AdSense and AMP ads quick and easy. Simply connect an account and choose from a searchable, sortable list of ad units. Alternatively, build your ads manually or paste in code.

Some key features of Ad Commander include:

* Create individual ads or groups of randomizing, rotating, or manually sorted ads
* AdSense ad type with direct AdSense account integration, manually built ad units, or simple code pasting
* Track impressions and clicks and generate reports for ads
* Support for AMP ads with amp-pixel and amp-analytics tracking
* Inject ads or groups using shortcodes, template tags, blocks, or automatic placements
* Conditionally display ads with content targeting options
* Display required labels above ads
* Add custom code before and after ads or groups
* Dynamically create an ads.txt and manage it in the WordPress admin
* Familiar WordPress interface

[Documentation](https://wpadcommander.com/documentation/?utm_source=wordpressorg&utm_medium=link&utm_campaign=readme) | [Support](https://wpadcommander.com/support/?utm_source=wordpressorg&utm_medium=link&utm_campaign=readme) 

## Demo Video
[youtube https://www.youtube.com/watch?v=dCQHwTIxfjM]

## Connecting AdSense
[youtube https://www.youtube.com/watch?v=TTR95aFhLls&t=4s]

## Creating rotating banner ads
[youtube https://www.youtube.com/watch?v=NpPgFlP0T0g]

Ad Commander core is free to download and use. Ad Commander Pro has additional advanced features. [Visit our website to learn more about Ad Commander Pro.](https://wpadcommander.com/?utm_source=wordpressorg&utm_medium=link&utm_campaign=readme)

Some Pro features include:

* Priority email support
* Automatically convert AdSense ads to AMP ads
* Google Analytics (GA4) tracking of impressions and clicks
* Expire ads by date or maximum stats
* Advanced automatic placement positions
* Popup ads
* Visitor targeting options
* Geotargeting with MaxMind IP databases
* Content and visitor targeting for groups and automatic placements
* Display groups in a grid layout
* Weighted or evenly distributed ad impressions

[All Features](https://wpadcommander.com/features/?utm_source=wordpressorg&utm_medium=link&utm_campaign=readme)

== Frequently Asked Questions ==

= Does Ad Commander work with page caching? =

Ad Commander core supports server-side rendering. Many caching plugins offer fragmented caching. This could be used to make Ad Commander’s server-side rendering compatible with page caching. The implementation of fragmented caching varies between caching plugins and is not directly supported by Ad Commander, but may be possible to implement with developer assistance.

Ad Commander Pro offers three rendering methods: Server-side rendering, Client-side rendering, and Smart (combination) rendering. Client-side and Smart rendering are designed to work with page caching and all visitor targeting methods.

= Do I need to edit my theme to insert ads? =

Nope! Ads and groups can be inserted into your site using automatic placements, shortcodes, or blocks.

= Can I manually insert ads and groups into my site or theme files? =

Yes! Individual shortcodes or PHP template tags can sometimes be a better solution than automatic placements, depending on your specific site layout and circumstances. Both are available for ads and groups, and can be added to your theme files if desired.

= Will Ad Commander work with AdSense? =

Yes. Ad Commander offers multiple ways to insert AdSense ads. These include direct integration, manually building ads, or pasting code from your account. Ad Commander also supports AdSense auto ads and AMP Ads.

= Will Ad Commander work with my ad network? =

Ad Commander supports inserting any script code using the Text or Code ad type. These ads can be inserted within your site or placed in the site head or body using automatic placements. The flexibility of this approach should allow any ad network to be used on your site. If you have trouble with a specific ad network, please reach out to support for help.

= Can Ad Commander require consent before displaying ads? =

Yes. Ad Commander does not create consent banners but can monitor a cookie and display ads after it exists.

The cookie name and value are unique to your consent management system and are specified in Ad Commander's settings. After your visitor accepts, ads will display as long as the consent cookie exists.

Consent management works best with client-side or smart rendering available in Ad Commander Pro. This feature will also work with server-side rendering, but there are some caveats (see documentation).

= Which versions of PHP is Ad Commander compatible with? =

Ad Commander and Ad Commander Pro are tested on PHP 7.4 - PHP 8.1. Support for PHP 8.2 is currently in beta but has no known issues at this time.

== Screenshots ==

1. Creating an AdSense ad using direct account integration.
2. Creating an individual ad in the WordPress admin uses a standard Add Post interface. Create your ad, assign it to groups, apply settings, and publish.
3. Ad Groups are WordPress taxonomies and are used to display a group of ads together. 
4. Placements allow you to insert ads or groups automatically without implementing shortcodes, template tags, or blocks.
5. Detailed reports are generated if local tracking is enabled. Local tracking is available in Ad Commander core (free download).
6. Reports can be displayed in line or bar graphs, and filtered by ads and time period.
7. Settings allow you to control some Ad Commander options from one central location.

== Changelog ==
= 1.1.8 =
* Adds localization support

= 1.1.7 =
* Bug fix: Fixes refresh interval on rotating ad groups
* Feature: Ability to set a 'stop tracking impressions' interval on rotating ad groups

= 1.1.6 =
* Bug fix: Blank report chart when filtered by date only
* Feature: Optional ad loading animation
* Misc: New hooks

= 1.1.5 =
* Enables compatibility with Ad Commander Tools add-on

= 1.1.4 =
* Misc UI/UX improvements
* Fixes potential error with placements that have no items
* Adds future support for Ad Commander Tools add-on

= 1.1.3 = 
* Fixes potential error with some WordPress configurations

= 1.1.2 = 
* Sync AdSense account alerts to dashboard
* Post meta and query performance improvements
* UI/UX improvements
* WordPress 6.6 compatibility

= 1.1.0 = 
* Adds AdSense ad type with direct AdSense account integration
* Adds support for AMP ads and AMP analytics
* Misc bug fixes and improvements

= 1.0.15 =
* Moves Pro CSS out of core plugin to avoid loading unnecessary styles
* Misc bug fixes and improvements

= 1.0.14 =
* New columns while managing ads, groups, and placements
* Adds sortable admin columns to ads, groups, and placements
* Adds the ability to quickly copy shortcodes and template tags

= 1.0.13 =
* Bug fixes and improvements to content targeting and visitor targeting rules

= 1.0.11 =
* Adds support for date archives to content conditions
* General improvements to content and visitor conditions
* Adds the ability to disable wrappers to some automatic placements
* Adds the ability to force server-side rendering to some automatic placements
* Misc bug fixes and UI improvements

= 1.0.9 =
* Adds support for Popup placements (Ad Commander Pro 1.0.4)
* Adds support for Max Ad/Placements Impressions & Max Ad Clicks (Ad Commander Pro 1.0.4)
* Copies ad groups when using duplicate feature
* Misc bug fixes and UI improvements

= 1.0.8 =
* Adds support for future plugin add-ons
* Misc bug fixes and UI improvements

= 1.0.7 =
* Improves visitor and content targeting logic
* Misc security improvements

= 1.0.6 =
* Improves compatibility with WordPress multisite
* Ability to add a custom prefix for class names and IDs
* Misc bug fixes and improvements

= 1.0.5 =
* Misc security improvements

= 1.0.4 =
* Adds ability to duplicate ads, placements, and groups
* Misc bug fixes and improvements

= 1.0.3 =
* Override consent requirement on individual ads, groups, and placements

= 1.0.2 =
* Adds Ad & Group block
* Misc bug fixes and UI improvements

= 1.0.1 =
* Ability to require consent before displaying ads
* Notification system on dashboard for common errors
* Updates frameworks to latest versions
* Misc bug fixes and security improvements

= 1.0.0 =
* Initial stable version

== Upgrade Notice ==
= 1.1.8 =
* Adds localization support

= 1.1.7 =
* Bug fix: Fixes refresh interval on rotating ad groups
* Feature: Ability to set a 'stop tracking impressions' interval on rotating ad groups

= 1.1.6 =
* Bug fix: Blank report chart when filtered by date only
* Feature: Optional ad loading animation

= 1.1.4 =
* Adds support for Ad Commander Tools add-on

= 1.1.3 = 
* Fixes potential error with some WordPress configurations

= 1.1.2 = 
* Sync AdSense account alerts to dashboard
* Performance and UX/UI improvements
* WordPress 6.6 compatibility

= 1.1.0 =
* AdSense ad type and direct AdSense integration
* AMP support

= 1.0.15 =
* Required for Ad Commander Pro 1.0.6
* Performance improvements and misc bug fixes

= 1.0.14 =
* Improved information and sortability in ad, group, and placement admin columns
* Adds the ability to quickly copy shortcodes and template tags

= 1.0.13 =
* Bug fixes and improvements to content targeting and visitor targeting rules

= 1.0.11 =
* Improvements to content and visitor conditions
* Adds the ability to disable wrappers to some automatic placements
* Adds the ability to force server-side rendering to some automatic placements
* Misc bug fixes and UI improvements

= 1.0.9 =
* Required for new features in Ad Commander Pro 1.0.4
* Misc bug fixes and improvements

= 1.0.8 =
* Adds support for future plugin add-ons
* Misc bug fixes and UI improvements

= 1.0.6 = 
* Improves compatibility with WordPress multisite
* Ability to add a custom prefix for class names and IDs

= 1.0.5 =
* Security improvements

= 1.0.4 =
* Adds ability to duplicate ads, placements, and groups

= 1.0.3 = 
* Override consent requirement on individual ads, groups, and placements

= 1.0.2 =
* Adds new Ad & Group block

= 1.0.1 =
* Adds the ability to require consent before displaying ads
* Bug fixes and security improvements.

= 1.0.0 =
* Initial stable version