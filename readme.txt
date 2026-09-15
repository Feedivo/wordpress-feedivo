=== Feedivo ===
Contributors: feedivo
Tags: instagram feed, facebook feed, social media feed, youtube feed, elementor
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.18.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build beautiful social media feeds for Instagram, Facebook, Threads, Pinterest and YouTube—fast, privacy-friendly and easy to add anywhere.

== Description ==

Turn your social media posts into a polished WordPress feed that feels at home on your website.

Feedivo brings together Instagram, Facebook, Threads, Pinterest and YouTube in one flexible social media feed. Choose the content you want, match the design to your brand and publish it without API keys or complicated setup.

= Add your feed anywhere =

Use the **Feedivo Feed** block in Gutenberg, the *Feedivo Feed* widget in Elementor or a ready-made shortcode for any other theme or page builder. Pick a feed and it is ready to go.

= Five networks, one feed =

Instagram, Facebook, Threads, Pinterest and YouTube — mixed into a single feed or kept apart. Filter by network, hashtag, media type or date. Reels, Shorts and Stories are recognised as what they are, so you can include or exclude them on purpose.

= Made to match your brand =

Choose a grid, masonry layout, list or social media carousel. Fine-tune columns, spacing, aspect ratios, corners and your accent colour once, and Feedivo keeps the design consistent everywhere.

= Fast, private and reliable =

* Posts and media are delivered from your own WordPress website.
* Feedivo adds no visitor tracking.
* Local media keeps your feeds fast and dependable.
* YouTube's privacy-enhanced player loads only after a visitor clicks play.

= Simple setup =

Create your feeds in a Feedivo account, paste one connection ID into WordPress and start publishing. No social network API keys are needed in WordPress.

== Installation ==

1. Install and activate the plugin.
2. In your Feedivo account, create a WordPress connection and copy its **connection ID** from the connection's page.
3. In WordPress, go to **Feedivo → Settings**, paste the ID and press **Connect**.
4. Your first update starts right away. Add the feed with the **Feedivo Feed** block, the Elementor widget or a ready-made shortcode.

== Screenshots ==

1. A feed on the front end: posts from Instagram, Facebook, Pinterest and Threads side by side, served from your own site.
2. Clicking a post opens it in the built-in lightbox, with the caption, date and a link to the original.
3. The Feedivo Feed block in the block editor: pick a feed and see it right on the canvas, exactly as visitors will.
4. The Elementor widget: search for Feedivo, drag it in, pick a feed.
5. The settings page after connecting: your connections, your feeds and the ready-made shortcode for each one.
6. Imported social posts in WordPress, with their feed, network and a link to the original.

== Frequently Asked Questions ==

= Do I need a Feedivo account? =

Yes. The plugin shows feeds that are built and hosted in Feedivo; it does not connect to Instagram, Facebook, Threads, Pinterest or YouTube on its own.

= Where do I find my connection ID? =

In your Feedivo account, create a WordPress connection. Its ID is shown on that connection's page. Every connection has its own ID, and you can connect several to the same site.

= Is this good for privacy? =

Your visitors' browsers never contact a social network. Posts and media are served from your own site, and the plugin adds no tracking of any kind. The one exception is a YouTube video: its player loads only when a visitor actually clicks play, and it uses YouTube's no-cookie domain.

= How quickly do new posts appear? =

Within minutes. The plugin checks for new content every five minutes in the background.

= Can I edit the posts in WordPress? =

No. Posts are managed in Feedivo and remain read-only in WordPress, keeping every update consistent. Change captions and filters in Feedivo.

= What happens when I remove a post or feed in Feedivo? =

Posts deleted or hidden in Feedivo disappear from your website automatically. When you remove a feed, the plugin also cleans up the content and media that are no longer needed.

= What happens if I uninstall? =

Everything created by Feedivo is removed: imported posts, feeds and downloaded media. Your own content is untouched.

= Why is my feed not displayed correctly? =

Clear your website cache and reload the page first. If the problem remains, exclude Feedivo from CSS and JavaScript optimization in your performance plugin or visit the Feedivo documentation.

== External services ==

This plugin connects your site to **Feedivo** (https://feedivo.de), a social media feed service. The plugin requires a Feedivo account and does not work without one. All communication happens from your server — never from your visitors' browsers — and no data about your visitors is ever sent.

Your site contacts the Feedivo API at `https://feedivo.de` in these situations:

* **When you connect your site** (pressing "Connect" on the settings page): your site sends the connection ID you entered, your site address and the plugin version. This binds the connection to your site and returns an access token. Feedivo stores the site address and plugin version so you can tell in your account which site a connection belongs to.
* **When feeds are updated** (every five minutes in the background, and when you press "Update now"): your site sends the access token and the plugin version, and receives your feed settings and posts — captions, dates, links and media addresses — which are then stored locally on your site. Feedivo stores the plugin version so your account shows the version your site is actually running.
* **When media is downloaded** (during an update): your site downloads the images and videos belonging to those posts into your own media library. From then on, visitors load them from your site only.
* **When the settings page is opened** (at most once per minute): your site sends the access token and the plugin version to check whether each connection is still valid, so a connection removed or paused in Feedivo shows up here immediately.
* **When you disconnect** (pressing "Disconnect"): your site sends the access token of that connection and the plugin version so the binding is released in your Feedivo account.

Use of the service is subject to its terms and privacy policy:
Terms of Service: https://feedivo.de/agb
Privacy Policy: https://feedivo.de/datenschutz

== Changelog ==

= 1.18.2 =
* Masonry card layouts show each image at its original height again instead of cropping it to a square.
* Posts from Website sources now show their platform badge in your feeds.

= 1.18.1 =
* Clearer message when a connection is paused by your Feedivo plan.

= 1.18.0 =
* Added an option to hide Feedivo archive pages while keeping individual post links available.

= 1.17.4 =
* Improved cleanup after pausing a feed or removing a connection.

= 1.17.3 =
* Improved lightbox design, content controls and compatibility with performance plugins.

= 1.17.2 =
* Improved video embed security and simplified feed assets.

= 1.17.1 =
* Manual updates now refresh your content immediately.

= 1.17.0 =
* Added single-column feeds and improved styling, carousels and the mobile lightbox.

= 1.16.1 =
* Made the first import faster and improved video and page-builder compatibility.

= 1.16.0 =
* Added the Feedivo Feed block for Gutenberg and improved masonry layouts.

= 1.15.4 =
* Improved video security, disconnection handling and browser compatibility.

= 1.15.3 =
* Improved carousel scrolling, autoplay and navigation.

= 1.15.2 =
* Improved compatibility with caching and performance plugins.

= 1.15.1 =
* Improved feed layouts after CSS optimization.

= 1.15.0 =
* Added a carousel preview of the next post.

= 1.14.1 =
* Fixed emoji and special characters in captions.

= 1.14.0 =
* Improved performance-plugin compatibility, feed links and setup guidance.

= 1.13.1 =
* Improved shortcode guidance.

= 1.13.0 =
* Added quick links to settings, documentation and support.

= 1.12.0 =
* Improved post order, update speed, Stories and connection feedback.

= 1.11.0 =
* Added new layouts, aspect ratios and design options.

= 1.10.0 =
* Added pinned posts and improved feed sorting.

= 1.9.0 =
* Added Feedivo branding for feeds on the Free plan.

= 1.8.0 =
* Paused feeds keep their existing content and show a clear notice.

= 1.7.1 =
* Added clearer messages when something needs attention.

= 1.7.0 =
* Added privacy-friendly YouTube playback.

= 1.6.0 =
* Pinterest support.

= 1.5.1 =
* Improved compatibility with WordPress themes.

= 1.5.0 =
* Added individual connection IDs for easier setup.

= 1.4.0 =
* Improved video playback in the lightbox.

= 1.3.0 =
* Added multiple connections per website.

= 1.2.0 =
* Added a redesigned feed lightbox.

= 1.1.0 =
* Threads support.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.18.2 =
Masonry cards keep their original image height; Website posts show their badge.

= 1.18.1 =
Clarifies the message for connections paused by your plan.

= 1.18.0 =
Adds control over Feedivo archive pages.

= 1.17.4 =
Improves cleanup for paused feeds and removed connections.

= 1.17.3 =
Improves the lightbox and performance-plugin compatibility.

= 1.17.2 =
Improves video embed security.

= 1.17.1 =
Manual updates refresh content immediately.

= 1.17.0 =
Adds single-column feeds and improves mobile display.
