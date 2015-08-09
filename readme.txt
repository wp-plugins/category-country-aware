=== Plugin Name ===
Contributors: wrigs1
Donate link: http://means.us.com/
Tags: Text Widget, RSS Widget, Category, Country, GeoIp, Geo-Location, Advert, Advertisement, Adverts, News Feed, RSS
Requires at least: 3.3
Tested up to: 4.2.4
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Make your posts and sidebar category and/or visitor location relevant

== Description ==

Category Country Aware (CCA) plugin: GeoIP AND the most flexible Text (and RSS) Widget available. Makes your post and sidebar content much more relevant, based on post category and visitor's locale (country).


https://www.youtube.com/watch?v=EyT-WQh39E8

&nbsp;

Make sidebar widgets (text/scripts/RSS etc) relevant to the post's category and/or visitor's location.

Display relevant Adverts/content WITHIN posts.

Auto customize post content to suit the visitor's location (country).

Make adverts within posts responsive, even on fixed width themes.

&nbsp;

**Travel Blog EXAMPLE**:

In one **CCA sidebar widget** (you can use more):

* display a hotel booking advert/form by default

* for posts in category "Equipment" display an *Amazon.COM* Travel Gadget advert;
<br>but if the visitor is located in the UK or Ireland display an *Amazon.CO.UK* equivalent;

* category "Transport": display a flight search advertisement

* category "Information": display UK Gov Travel Warnings News Feed (**RSS**) by default;
<br /> but if the visitor is from US or NZ show their Government's equivalent Feed instead

Use "**Ads within posts widget**" to display a gadget advert within posts in category "equipment".

* set widget to only display on small devices i.e. when your sidebar is not visible.

Use **shortcodes to customize your posts** for visitors from different countries e.g.

* [display only="US,GB"]some content[cca_display] will only display the enclosed content, advert etc to visitors from US and UK.

* [cca_display not="GB,AU"]some content[cca_display] will NOT display the enclosed content to visitors from the specified countries.

* auto convert temperature to the right scale for your visitor (&deg;F/&deg;C)

&nbsp;

**Features ( [more info in the CCA Plugin Guide](http://wptest.means.us.com/2014/11/category-country-aware-wordpress ))**:

* location aware **Shortcodes** for use in posts and pages (see CCA documentation).

* YOU control **widget** content based on category(s) and/or visitor's locale(s)

* select categories by name (not by unfreindly numeric id)

* YOU choose the number of characters to display for RSS News Item excerpts (unlike WP RSS widget)

* option to nofollow (good for SEO) news feed links and open RSS links in new tab (unlike WP RSS widget)

* add multiple copies of the widget to the sidebar, each with different content

* make long Widget Titles wrap (&lt;br&gt;) where YOU want

* fine tune widget look and make ads/images fit using simple check-box options to override Theme's widget style (**code free styling**)

* saves valuable sidebar space by enabling same widget to display either RSS or Text/HTML/Script content

* automatic update of IP Range to Country Maxmind GeoLite data 

* extensions (developers see below) providing additional functionality (see plugin documentation) 

&nbsp;

**CCA Goodies Extension**
Currently free for a tweet [see this post for more info](http://wptest.means.us.com/2015/03/cca-goodies-extension/ ).  More flexibility for sidebar widgets . More "Ads in Posts" widgets. Enable PHP.
Preview mode + view CCA content as if you are a visitor from specified country.  More Geolocation shortcodes. Give pages "categories" etc etc.

&nbsp;

**GeoIP Country Data (both IPv4 &amp; IPv6):**

This product includes GeoLite data created by MaxMind, available from http://www.maxmind.com .

If you use Cloudflare and have "switched on" their GeoLocation option ( see [Cloudflare's instructions](https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do- ) )
then it will be used to identify visitor country.  If not, then the Maxmind GeoLite Legacy Country Database, installed by this plugin, will be used.

Note: not tested on IPv6 (my servers are IPv4), but feedback on Stackoverflow indicates the code should work fine for IPv6. See CCA documentation for more info on GeoIP accuracy, whether you need to update the Maxmind DB, and setting up automatic update.

Experts: a hook is provided to allow you to use other GeoIP systems with this plugin.

&nbsp;

**For developers and coders: extensions and adding functionality via actions, filters and shortcodes**

A **later** release of this plugin will enable you to add functionality through your own filters, actions and shortcodes. An extension will also be released to give you an idea of the type of functionality you can add (see CCA website for details).

Yes, this version has hooks and RSS functionality is an extension BUT this was done as proof of concept only; I am still trialing and deciding the most effective easy
way for coders to add extensions and shortcodes. Classes/hooks WILL be be modified/removed/added/renamed. Any extensions you write for this release probably WON'T WORK in
subsequent versions - so hold fire on publishing your category related Twitter feed until I've finalised and documented the hooks!

== Installation ==

Requirements: Wordpress 3.3 and up, and PHP 5.3 or greater. Tested on WP 3.8.1 to 4.1 with PHP 5.3 and 5.4.

The easiest way is direct from your WP Dashboard like any other widget:

1.Plugins -> Add New -> do a search for "Category Country Aware" to find it -> click "install now"

2.Activate the plugin.

3.Use the *Dashboard->'Settings'->'CCA Goodies'* and the Dashboard->'Appearance'->'Widgets' menus to configure.


== Frequently Asked Questions ==

= Where can I find support/additional documentation =

Support questions should be posted on Wordpress.Org<br />
Additional documentation is provided at http://wptest.means.us.com/2014/11/category-country-aware-wordpress


= How does the widget decide which of my category/country entries to use? =

The most specific entry found is used; and categories have higher priority than visitor location.

If your widget has content for the following entries:
<br /> &nbsp; &nbsp; "All Categories" country "Anywhere"
<br /> &nbsp; &nbsp; "All Categories" country "France"
<br /> &nbsp; &nbsp; Category "Travel" country "Anywhere"
<br /> &nbsp; &nbsp; Category "Travel" country "US"
<br /> &nbsp; &nbsp; Category "Travel" country "Germany"

When viewing a post in Category "Travel":
<br /> &nbsp; &nbsp; a US visitor would see the widget content in entry ' Category "Travel" country "US" '
<br /> &nbsp; &nbsp; a German visitor would see the widget content in entry ' Category "Travel" country "Germany" '
<br /> &nbsp; &nbsp; visitors from any other country would see the entry for ' Category "Travel" country "Anywhere" '

When viewing a post in Category "Animals" (or any other category which does not have an entry):
<br /> &nbsp; &nbsp; a French visitor would see the entry for ' "All Categories" country "France" '
<br /> &nbsp; &nbsp; visitors from any other country would see the entry for ' "All Categories" country "Anywhere" '



= My widget title wraps over 2 lines - can I force a line break at the right point? =

Yes. Insert a &lt;br&gt; tag at the point you wish the new line to occur.

= Can the widget be made to execute PHP code? =

Short answer:yes [via the CCA goodies extension] (http://wptest.means.us.com/2015/03/cca-goodies-extension/ ) where you positively opt to allow PHP.

Long answer: any plugin enabling input of arbitrary PHP has increased security risks, however I am aware there is high demand for this feature.
To protect normal non-PHP users, you will have to positively opt to enable PHP. For security opt-in is set by a separate plugin to the widget that executes it.

= Caching plugins/services have problems with dynamic content such as GeoIP. Will the country location part of the CCA plugin work with these? =

Short answer:
<br /> &nbsp; Yes for Cloudflare (according to my tests) using their "aggressive caching" option
<br /> &nbsp; "Perfectly" for **ZenCache** and **WP Supercache** when using the country caching plugin extension [for WPSC] (https://wordpress.org/plugins/country-caching-extension-for-wp-super-cache/ ), or 
[for ZC/QC](https://wordpress.org/plugins/country-caching-extension/ ). See CCA documentation.
<br /> &nbsp; W3 Total Cache: DIY solutions (less than perfect).
<br /> &nbsp; Other caching plugins may or may not provide suitable settings.

If not then you can still use the CCA widget to display relevant content by category (ignoring visitor country).

full answer:  see CCA documentation


== Screenshots ==

1. Same 3 sidebar widgets i. on "Crime Fiction" category post (US visitor); and ii. on "Travel Guides post" (British visitor)

2. Ad in Post for category Junior fiction (smart responsive option set so ad only displays on small devices)

3. Override theme's widget styles (border, padding etc) without writing any HTML or CSS

4. Adding default content for widget:

5. Same widget, show RSS news feed for category "Travel" when visitor is from USA

6. Set up of "Ads in Posts" for category Junior Fiction:



== Changelog ==

= 0.9.2 =
* Added option to set the [Cookie Notice plugin](https://wordpress.org/plugins/cookie-notice/ ) to only display its cookie bar to European Union visitors ONLY. 
* Dashboard->Settings->Category Country Goodies->Countries  and check the "set Cookie Notice to only display its cookie bar for these countries" box

= 0.9.1 =
* Added filter for use by EU Cookie Law plugins to enable them to only display cookie confirmation banner to visitors from EU. 
* e.g. apply_filters( 'cca_is_EU',$override_list) ) or apply_filters( 'cca_is_EU',$override_list,"AB,CD,EF") ). If $override_list is set to TRUE the plugin can specify its own list of countries otherwise the followinfg country codes are assumed to be the EU:
* "BE,BG,CZ,DK,DE,EE,IE,GR,ES,FR,HR,IT,CY,LV,LT,LU,HU,MT,NL,AT,PL,PT,RO,SI,SK,FI,SE,GB"
* modified CCA widget to use a modern constructor

= 0.9.0 =
* New option to "hide" a CCA widget by default, or for specific category(s)/country(s).
* New cca_display shortcode option to only show selected content within posts to visitors from particular country(s). See online guide or e.g. in readme.txt.
* "Disable Geoip" bugfix. N.B. If you are using the Country Caching extension make sure "Disable GeoIP"  in the CCA Goodies settings form is unchecked as Country Caching no longer overrides this CCA setting.
* Prevent editing of CCA widgets using "WP Customizer" which is not category or country aware.

= 0.8.5 = 
Addition of Settings Form Diagnostics panel. Modification of extension hooks (an extension will be availabele from 19 March 15 providing major increase in functionality, flexability 
& usabilty and its currently free if you tweet it - [see Plugin Guide](http://wptest.means.us.com/2014/11/category-country-aware-wordpress )).

GeoIP data files are now installed when you first "initialize" the CCA enable Country Caching. This alteration is to meet WordPress.org repository requirments (all files stored there should be licensed 
under GPL) Whereas Maxmind's data files are provided under Creative Commons license. 

= 0.8.0 =
PHP 5.2.x is no longer supported. The CCA plugin now requires PHP version 5.3 or greater.
Bugfixes: 1. resolves compatibility issue with certain GeoIP plugins that prevented display of CCA widgets/content.
2. Maxmind data update: If your site's permissions prevent upload of latest Maxmind data to the plugins directory then the WP upload directory is used.
Maxmind Directory path is now "broadcast" so it can be used by the new "Country Caching Extension" plugin.

= 0.7.0 =
Auto update Maxmind data files option is now available via the Countries tab on the Dashboard->Settings->CCA Goodies setting form.
Maxmind Directory path is "broadcast" so it can be used by other Wordpress plugins such as the soon to be released Country Caching.

= 0.6.1 =
* First published version.

== Upgrade Notice ==

= 0.9.2 =
* Added option to set the [Cookie Notice plugin](https://wordpress.org/plugins/cookie-notice/ ) to only display its cookie bar to European Union visitors ONLY. 
* Dashboard->Settings->Category Country Goodies->Countries  and check the "set Cookie Notice to only display its cookie bar for these countries" box

= 0.9.1 =
* Added filter for use by EU Cookie Law plugins to enable them to only display cookie confirmation banner to visitors from EU. 
* e.g. apply_filters( 'cca_is_EU',$override_list) ) or apply_filters( 'cca_is_EU',$override_list,"AB,CD,EF") ). If $override_list is set to TRUE the plugin can specify its own list of countries otherwise the followinfg country codes are assumed to be the EU:
* "BE,BG,CZ,DK,DE,EE,IE,GR,ES,FR,HR,IT,CY,LV,LT,LU,HU,MT,NL,AT,PL,PT,RO,SI,SK,FI,SE,GB"
* modified CCA widget to use a modern constructor

= 0.9.0 =
* New option to "hide" a CCA widget by default, or for specific category(s)/country(s).
* New cca_display shortcode option to only show selected content within posts to visitors from particular country(s). See online guide or e.g. in readme.txt.
* "Disable Geoip" bugfix. N.B. If you are using the Country Caching extension make sure "Disable GeoIP"  in the CCA Goodies settings form is unchecked as Country Caching no longer overrides this CCA setting.
* Prevent editing of CCA widgets using "WP Customizer" which is not category or country aware.

= 0.8.5 = 
Addition of Settings Form Diagnostics panel. Modification of extension hooks (an extension will be availabele from 19 March 15 providing major increase in functionality, flexability 
& usabilty and its currently free if you tweet it - [see Plugin Guide](http://wptest.means.us.com/2014/11/category-country-aware-wordpress )).

GeoIP data files are now installed when you first "initialize" the CCA enable Country Caching. This alteration is to meet WordPress.org repository requirments (all files stored there should be licensed 
under GPL) Whereas Maxmind's data files are provided under Creative Commons license. 

= 0.8.0 =
PHP 5.2.x is no longer supported. The CCA plugin now requires PHP version 5.3 or greater. Bugfixes: a) resolves compatibility issue with some GeoIP plugins that prevented display of CCA widgets/content.
b) Maxmind data update: If your site's permissions prevent upload of latest Maxmind data to the plugins directory then the WP upload directory is used.
Maxmind Directory path is now "broadcast" so it can be used by the new "Country Caching Extension" plugin.

== License ==

This program is free software licensed under the terms of the [GNU General Public License version 2](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html) as published by the Free Software Foundation.

In particular please note the following:

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.