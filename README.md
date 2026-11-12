# BD Toolkit

Theme-independent features shared across BD sites. Install it on any site, old theme or new.

`readme.txt` is shown to clients in wp-admin ("View details"), so keep it plain and client-friendly. Dev notes go here.

Don't edit this plugin per site. Configure it on Settings > BD Toolkit or with filters from the theme, so it can be updated safely.

## Releasing

Sites update from this repo's GitHub releases.

1. Bump `Version` and `BDTK_VERSION` in `bd-toolkit.php`, and `Stable tag` in `readme.txt`.
2. Add a `= x.y.z =` entry under `== Changelog ==` in `readme.txt`. It becomes the release notes, which clients see.
3. Commit, then run `bin/release.sh`. It builds `bd-toolkit.zip`, tags `vx.y.z`, pushes, and creates the release with the zip attached.

`bin/release.sh --build` only builds `build/bd-toolkit.zip`, which you can use for a manual install.

Install sites from the release's `bd-toolkit.zip`, not GitHub's "Source code" zips, which unpack into a differently named folder.

**This repo is public.** Never commit passwords, keys or private addresses, or add them to a release zip: anything in the plugin ends up on every client site. Site-specific settings go in each site's `wp-config.php`.

### Update library

`vendor/plugin-update-checker/` is [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5.7 (MIT), used unchanged. `includes/updater.php` allows updates only from releases that have `bd-toolkit.zip` attached.

## Features

### Duplicate
"Duplicate" link on list rows, a bulk action, a link in the classic editor publish box and in the admin bar. The copy is a draft titled "(Copy)" with the same content, parent, order, terms and all custom fields, including ACF flexible content.

### Drag-and-drop ordering
Off by default. Adds a drag handle column in admin lists for posts, pages and CPTs. Rows can also be moved with the up and down arrow keys, which are announced to screen readers. Child pages move with their parent. It works on paginated and filtered lists. Optionally, front-end queries without their own `orderby` follow the saved order.

### Custom login address (Login tab, off by default)
Request handling ported from [WPS Hide Login](https://wordpress.org/plugins/wps-hide-login/) 1.9.19 (GPLv2 or later), with multisite handling left out.

- Serves the login page at `/{address}/`.
- Logged-out visitors get the theme's 404 at wp-login.php, wp-register.php, /wp-admin, /login, /admin and /dashboard.
- AJAX, cron, admin-post.php and password-protected posts keep working.
- Every login, logout, lost-password and reset-email link uses the new address.
- The site admin is emailed whenever the address is set or changed.
- Reserved WordPress words and existing page/post slugs are rejected.

Forgot the address? Any of these works:

- `wp bdtk login-url` prints the current login address.
- `define( 'BDTK_LOGIN_URL_OFF', true );` in wp-config.php switches only this feature off, and the standard wp-login.php comes back.
- Renaming the plugin folder over SFTP.

If the login page is ever served stale from Kinsta or WP Engine page caching, ask the host to exclude `/{address}/` from the cache.

### Keep usernames private
Ported from [Stop User Enumeration](https://wordpress.org/plugins/stop-user-enumeration/) 1.7.10 by Fullworks (GPLv2 or later). Each check can be switched off:

- Logged-out `?author=N` scans get a 403.
- The REST users endpoint is limited to logged-in users.
- The author link is removed from oEmbed.
- The users sitemap is removed.
- Login and lost-password messages are generic (BD addition).
- Author archives are turned off (BD addition).

Left out from the original: comment-author number stripping (BD sites disable comments) and syslog/fail2ban logging.

### Extra login protection
Disables XML-RPC, which stops pingbacks and password guessing through xmlrpc.php. Untick it for Jetpack or the mobile app.

Also disables Application Passwords. Untick it if an external service logs in with one.

### Daily site report
Once a day, at a random time per site, WP-Cron sends the site's WordPress, PHP, plugin and theme versions to the address set in `wp-config.php`. It's non-blocking with a 3 second timeout and never runs on a visitor's page load.

The report stays off until the address is set:

```php
define( 'BDTK_REPORT_URL', 'https://...' );
define( 'BDTK_REPORT_AUTH', 'user:password' );
```

Over SSH: `wp config set BDTK_REPORT_URL 'https://...'` and `wp config set BDTK_REPORT_AUTH 'user:password'`. `wp bdtk report` sends one now and shows the response; `--dry-run` prints the data. `define( 'BDTK_REPORT_OFF', true );` turns it off.

## Filters

- `bdtk_duplicate_post_types`: array of post types that get Duplicate.
- `bdtk_duplicate_excluded_meta`: meta keys that are not copied.
- `bdtk_reorder_post_types`: array of post types that get drag-and-drop ordering.
- Action `bdtk_duplicate_post( $new_post_id, $original_post )`: runs after a duplicate is created.
- Action `bdtk_sortable_saved( $post_type, $ordered_ids )`: runs after an order is saved.
- `bdtk_enum_rest_match` / `bdtk_enum_rest_allowed_match`: REST route regexes to block or allow.

## Themes that already include these features

bd-theme-2024 commit 98f1a49 shipped Duplicate and ordering inside the theme. If a theme still has them, BD Toolkit pauses its own copy of that feature and shows "Provided by your theme" on the settings page, so nothing loads twice. Remove the theme copy to switch over.
