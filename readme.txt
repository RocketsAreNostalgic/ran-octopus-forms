=== RAN EmailOctopus for Jetpack Forms ===
Contributors: bnjmnrsh
Tags: contact form, jetpack, emailoctopus, newsletter
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.0
X-Release-Please-Start-Version: x-release-please-start-version
Stable tag: 1.1.0
X-Release-Please-End: x-release-please-end
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Adds one shared EmailOctopus integration to compatible saved Jetpack forms.

== Description ==

RAN EmailOctopus for Jetpack Forms lets an administrator choose several
compatible published saved Jetpack forms. Every selected form can be reused on
pages, posts, patterns, and other singular routes while sharing one success
redirect, opt-in EmailOctopus subscription, field mapping, and message set.
Unselected forms, including adjacent forms on the same route, remain unchanged.

Saved-form routing requires Jetpack to expose authoritative saved-form identity
for submitted feedback and each active target to be published and structurally
valid. There is no page-scoped fallback. One broken selection does not stop
valid peers; the health check identifies forms with invalid structure or shared
field mappings. The success destination remains page-based.

Jetpack is required. EmailOctopus is optional and remains disabled until the
administrator configures it.

== Installation ==

1. Install and activate Jetpack.
2. Upload and activate RAN EmailOctopus for Jetpack Forms.
3. Create or choose compatible published saved Jetpack forms. The Contact Newsletter
   Form pattern in the RAN Forms category is a suitable starting point.
4. Go to Settings > RAN EmailOctopus, select those saved forms, and choose the
   success page.
5. Configure recipients in Jetpack's native Form notifications settings. This
   plugin does not replace Jetpack's notification email or WordPress mail path.
6. Optionally add EmailOctopus credentials and a destination.
7. Add [ran_emailoctopus_jetpack_forms_subscription_message] in a Shortcode
   block on the configured success page to show the appropriate newsletter
   outcome message.

== Frequently Asked Questions ==

= Does it change every Jetpack form? =

No. It acts only on explicitly selected saved forms, wherever they are reused.

= Can one configuration be used on several pages? =

Yes. Reuse any selected saved form on each route. Several compatible saved
forms can share the same configuration when each one is explicitly selected.

= Why does the health check say EmailOctopus routing is disabled? =

Routing needs authoritative saved-form identity from Jetpack and a valid,
published saved-form target. The health check reports whether the installed
Jetpack version lacks that capability or a selected form is missing, draft,
the wrong post type, structurally invalid, or incompatible with the shared
field mapping. Repair or remove that form; valid selected peers continue
operating and the plugin never falls back to a page-scoped integration.

= Does the plugin send contact notification emails? =

No. Jetpack's native Form notifications remain responsible for recipients and
messages. Those notifications continue through WordPress's normal mail path.
Avoid enabling another EmailOctopus connector for the same saved forms because
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

First public release for WordPress 6.8+ and PHP 8.0+.

== Upgrade Notice ==

= 1.0.0 =

Existing EmailOctopus settings remain, but the upgrade does not infer saved
forms from page content. An existing saved-form target becomes the first member
of the selected collection; otherwise an administrator must select at least one
published saved form before EmailOctopus routing is enabled.
