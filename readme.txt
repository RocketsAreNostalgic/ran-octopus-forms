=== RAN EmailOctopus for Jetpack Forms ===
Contributors: bnjmnrsh
Tags: contact form, jetpack, emailoctopus, newsletter
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
X-Release-Please-Start-Version: x-release-please-start-version
Stable tag: 1.1.0
X-Release-Please-End: x-release-please-end
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Adds an opt-in EmailOctopus integration to one saved Jetpack form across routes.

== Description ==

RAN EmailOctopus for Jetpack Forms lets an administrator choose one published
saved Jetpack form. On supported Jetpack versions, the same form can be reused
on pages, posts, patterns, and other singular routes while retaining one
success redirect, opt-in EmailOctopus subscription, field mapping, and message
set. Other saved forms, including adjacent forms on the same route, remain
unchanged.

Portable targeting requires Jetpack to expose authoritative saved-form identity
for submitted feedback. Older compatible Jetpack versions retain page-scoped
legacy behaviour using the configured contact page and marked form. The admin
screen and health check show which mode is active and why. The contact page is
kept as a compatibility fallback; the success destination remains page-based.

Jetpack is required. EmailOctopus is optional and remains disabled until the
administrator configures it.

== Installation ==

1. Install and activate Jetpack.
2. Upload and activate RAN EmailOctopus for Jetpack Forms.
3. Create or choose one published saved Jetpack form. The Contact Newsletter
   Form pattern in the RAN Forms category is a suitable starting point.
4. Go to Settings > RAN EmailOctopus, select that saved form, retain a contact
   page as the compatibility fallback, and choose the success page.
5. Configure recipients in Jetpack's native Form notifications settings. This
   plugin does not replace Jetpack's notification email or WordPress mail path.
6. Optionally add EmailOctopus credentials and a destination.
7. Add [ran_emailoctopus_jetpack_forms_subscription_message] in a Shortcode
   block on the configured success page to show the appropriate newsletter
   outcome message.

== Frequently Asked Questions ==

= Does it change every Jetpack form? =

No. In portable mode it acts only on the selected saved form, wherever that
form is reused. In legacy mode it acts only on the marked form on the configured
contact page.

= Can one configuration be used on several pages? =

Yes. Reuse the same selected saved Jetpack form on each route. A different
saved form is a separate definition and is not included automatically.

= Why does the health check report legacy compatibility mode? =

Portable mode needs authoritative saved-form identity from Jetpack and a valid,
published saved-form target. The health check reports whether the installed
Jetpack version lacks that capability or the selected form is unavailable,
draft, the wrong post type, or structurally invalid. Legacy mode keeps the
configured contact-page integration working where it is safe to do so.

= Does the plugin send contact notification emails? =

No. Jetpack's native Form notifications remain responsible for recipients and
messages. Those notifications continue through WordPress's normal mail path.
Avoid enabling another EmailOctopus connector for the same saved form because
both connectors could process one opt-in.

= What happens when EmailOctopus is not configured? =

The contact form still works through Jetpack. No subscription request is made.

== External services ==

This plugin uses or can be configured to use third-party services:

* Jetpack Forms provides the required form blocks. Its terms and privacy policy
  are available at https://automattic.com/legal/.
* EmailOctopus is contacted only after a visitor opts in and an administrator
  configures a destination. The visitor's email address and deliberately mapped
  fields are sent to https://emailoctopus.com/api/1.6/. See
  https://emailoctopus.com/legal/privacy.

Site administrators are responsible for their notices, consent, and provider
accounts before enabling external services.

== Screenshots ==

1. RAN EmailOctopus settings, including optional integration status.
2. The Contact Newsletter Form pattern in the RAN Forms category.

== Changelog ==

= 1.0.0 =

First public release for WordPress 6.5+ and PHP 8.0+.

== Upgrade Notice ==

= 1.0.0 =

Existing settings remain. One marked saved-form reference is recorded without
rewriting content. Inline, unmarked, missing, or ambiguous forms remain in safe
page-scoped compatibility mode.
