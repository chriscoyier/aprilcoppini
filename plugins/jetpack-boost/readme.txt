=== Jetpack Boost ===
Contributors: automattic, xwp, thingalon, pyronaur, davidlonjon, danwalmsley, luchad0res, ebinnion, jpolakovic, rheinardkorf, scruffian, exelero
Donate link: https://automattic.com
Tags: performance, speed, pagespeed, web vitals, critical css, optimize, defer
Requires at least: 5.5
Tested up to: 5.7
Requires PHP: 7.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Speed up your website by optimizing page performance with Jetpack Boost!

== Description ==

Jetpack Boost provides one-click optimizations that supercharge your WordPress site’s performance and improve web vitals
 scores for better SEO.

### Performance Modules

Optimize your site with the same techniques used on the world's most successful websites. Each technique is packaged up as a module that you can activate and try out.

Currently the plugin has 3 performance modules available:

1. *Optimize CSS Loading* generates Critical CSS for your homepage, posts and pages. This can allow your content to show up on the screen much faster, particularly for viewers using mobile devices.

   Read more about critical CSS generation at [web.dev](https://web.dev/extract-critical-css/)

1. *Defer Non-Essential Javascript* moves some tasks to after the page loads, so that important visual information can be seen sooner.

   Read more about deferring javascript at [web.dev](https://web.dev/efficiently-load-third-party-javascript/)

1. *Lazy Image Loading* only loads the images the user can see. As the user scrolls, images are loaded just before they show up on the page. This simple optimization makes sites faster and saves bandwidth for your host and your customers.

   Read more about lazy image loading at [web.dev](https://web.dev/lazy-loading-images/)

### Easy Setup

There's nothing to configure - the setup process is as easy as:

 1. Install the plugin
 2. Activate Jetpack Connection
 3. Turn on performance modules one by one and observe how the performance score changes

 Google PageSpeed API is used to measure the performance score of a site. It's important to look at the PageSpeed score because Core Web Vitals are going to be used as a ranking factor in search engines.

### With 💚 by Jetpack

This is just the start!

We are working hard to bring more features and improvements to Jetpack Boost. Let us know your thoughts and ideas!

We'd also like to give a special THANK YOU to the XWP team who provided help with initial research and scoping of the plugin and were engaged with our team throughout the project.

== Frequently Asked Questions ==


= What are Web Vitals? =

Web Vitals are the measurements that Google uses to better understand the user experience on a website. By improving Web Vitals scores you're also improving the user experience on your site.

You can read more about Web Vitals on [web.dev](https://web.dev/vitals/)


= How do I know if it's working? =

Every site is different and so performance benefits for each module may vary from site to site. That's why we recommend that you measure the performance improvements on your site by enabling the performance modules one by one. There are many tools out there that you can use for free to measure performance improvements:

* [WebPageTest.org](https://www.webpagetest.org/easy)
* [web.dev/measure](https://web.dev/measure/)
* [PageSpeed Insights](https://developers.google.com/speed/pagespeed/insights/)
* [GTMetrix](https://gtmetrix.com/)

Google PageSpeed measurements are built-in the Jetpack Boost dashboard.

= Does it work with static page cache? =
Absolutely! If you have plugins like WP Super Cache or W3 Total Cache installed - Jetpack Boost is only going to help increase the performance benefits! Keep in mind that you need to wait for the cache to clear for Jetpack Boost improvements to show up.

= Does this plugin require Jetpack? =

Jetpack Boost is a part of the Jetpack brand, but it doesn’t require Jetpack plugin to run. This is a separate plugin from Jetpack and it will always remain that way.


== Installation ==

1. Install Jetpack Boost via the plugin directory, and activate it.
2. Visit the "Jetpack Boost" section of your site's WP Admin.
3. Turn on the performance features you would like to try out on your site.

== Screenshots ==

1. Manage your Jetpack Boost settings

== Changelog ==

= 1.1.0 =
* Update: User connection is no longer required for Speed Scores.
* Update: Completely revamped how site speed scores are retreived.
* Update: Reduced backend dashboard JavaScript bundle size.
* Update: Added a message to explain how site score is calculated.
* Update: Added "Offline Mode" to allow testing Jetpack Boost on local environments easily.
* Update: Improved error handling and the error messages provided.
* Update: Improved Critical CSS Generation stability.
* Update: Remove animations from Critical CSS.
* Fix: Incompatibility with UsersWP and similar plugins that might introduce redirects during Critical CSS Generation.

= 1.0.6 =
* Fix: Failed to execute 'json' errors
* Fix: Connection UI Border issues
* Update: Improve Jetpack compatibility
* Update: Improve Critical CSS Compatibility with caching and minification plugins
* Update: Clean up JavaScript dependencies

= 1.0.5 =
* Fixed: Defer JavaScript compatibility with XML Requests

= 1.0.4 =
* Fixed: Web Stories compatibility
* Improved: "Defer Non-Essential Javascript" module compatibility with other plugins

= 1.0.3 =
* Updated: Support for AMP Plugin 2.0+
* Updated: No longer defer JavaScript on POST, AJAX, Cron requests and sitemaps.

= 1.0.2 =
* Improved: HTML Media tag handling
* Fixed: Metrics timeout caused by caching in the REST API

= 1.0.1 =
* Fixed: An issue where the connection iframe would sometimes break
* Updated: On connection: showing an XML RPC Error instead of HTTP 500 when XML-RPC is disabled

= 1.0.0 =
- This update brings a lot of stability improvements.
- We've been hard at work to get here and Jetpack Boost v1.0.0 is finally here! 🎉

= 0.9.19 =

- We've refactored the plugin quite a bit, starting from the UI to stability fixes.

= 0.9.1 =

- First public alpha release
