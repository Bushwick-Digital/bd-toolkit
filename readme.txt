=== BD Toolkit ===
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPL-2.0-or-later

Tools for managing content and keeping this site secure.

== Description ==

Each feature can be switched on or off under Settings > BD Toolkit.

= Content =

* Duplicate: create a draft copy of a page or post with all of its content, sections and settings.
* Drag-and-drop ordering: drag pages and posts into the order you want, or move them with the arrow keys.

= Security =

* Keep usernames private: hides account usernames from visitors so they can't be used to guess logins.
* Turn off feeds: switches off the RSS and Atom feeds most sites don't need.
* Extra login protection: turns off alternative ways of signing in that most websites don't need.

= Login =

* Custom login address: moves the login page to an address you choose.

== Changelog ==

= 1.2.1 =
* Updates are now checked without storing any login on the site.
* The daily site report's address is now set in wp-config.php instead of in the plugin. Until it's set, no report is sent.

= 1.2.0 =
* New: a daily site report sends the site address, WordPress, PHP, plugin and theme versions to Bushwick Digital, so we can keep every site up to date. It runs once a day in the background and never slows the site down. Switch it off with define( 'BDTK_REPORT_OFF', true ); in wp-config.php.

= 1.1.0 =
* New: Turn off feeds. Post and comment feeds are switched off by default, and feed addresses redirect to the page they belong to. Switch it off under Settings > BD Toolkit > Security if a newsletter tool uses the feed.
* Keep usernames private now also hides author names in feeds, which often showed the username.

= 1.0.1 =
* Drag-and-drop ordering now works alongside Admin Columns Pro: the drag handles stay visible and a remembered column sort no longer switches dragging off.

= 1.0.0 =
* Duplicate pages and posts.
* Drag-and-drop ordering.
* Keep usernames private.
* Extra login protection.
* Custom login address.
* Automatic updates.
