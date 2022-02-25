=== GitSync ===
Contributors: rubengarzajr
Tags: github, themes, sync, release
Requires at least: 5.3.2
Tested up to: 5.9.1
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Download and sync themes from github.com or private github using release tags.

== Description ==

GitSync adds a new option to the admin dashboard that allows you to add a Theme from Github.
When properly configured, you can then pick a release to be downloaded to your instance of Wordpress.

Just enter the URI of the repo and your personal token and then click Add Theme.
You'll then see the repo list below in the Installed Themes area.

Just choose a release and click on "Sync to the release."

== Installation ==

1. Upload `gitsync` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.1.0 =
* Fixed issues with token capitalization.
* Check connection before adding repo.
* Fixed modal status not showing up.

= 1.0 =
* First Release version.
