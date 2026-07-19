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

Adds independent EmailOctopus integration profiles to saved Jetpack forms.

== Description ==

RAN EmailOctopus for Jetpack Forms lets an administrator create independent
integration profiles. Each profile owns one or more compatible published saved
Jetpack forms and defines its own EmailOctopus destination, field mappings,
success page, and outcome messages. An assigned form can be reused on pages,
posts, patterns, and other singular routes. Unassigned forms, including adjacent
forms on the same route, remain unchanged.

Saved-form routing requires Jetpack to expose authoritative saved-form identity
for submitted feedback and each active target to be published and structurally
valid. There is no page-scoped fallback. One broken profile or form does not
stop unrelated valid profiles; per-profile health checks identify invalid
structure, field mappings, and provider configuration.

Jetpack is required. EmailOctopus is optional and remains disabled until the
administrator configures it.

== Installation ==

1. Install and activate Jetpack.
2. Upload and activate RAN EmailOctopus for Jetpack Forms.
3. Create or choose compatible published saved Jetpack forms. The Contact
   Newsletter Form pattern in the RAN Forms category is a suitable starting point.
4. Go to Settings > RAN EmailOctopus and create an integration profile.
5. Save the profile label, assigned forms, and optional destination, then
   configure the refreshed field choices, success page, and messages.
6. Configure recipients in Jetpack's native Form notifications settings. This
   plugin does not replace Jetpack's notification email or WordPress mail path.
7. Optionally add EmailOctopus credentials and a destination.
8. Add [ran_emailoctopus_jetpack_forms_subscription_message] in a Shortcode
   block on the profile's success page to show its newsletter outcome message.
   A result presented on another page remains inert.

== Frequently Asked Questions ==

= Does it change every Jetpack form? =

No. It acts only on saved forms assigned to an integration profile, wherever
they are reused.

= Can one configuration be used on several pages? =

Yes. Reuse an assigned saved form on each route. Several compatible forms may
share a profile; forms needing different destinations, mappings, success pages,
or messages belong to separate profiles.

= Why does the health check say EmailOctopus routing is disabled? =

Routing needs authoritative saved-form identity from Jetpack and a valid,
published saved-form target. The health check reports whether the installed
Jetpack version lacks that capability or a selected form is missing, draft,
the wrong post type, structurally invalid, or incompatible with its profile's
field mapping. Repair or unassign that form. Unrelated profiles continue
operating and the plugin never falls back to a page-scoped integration.

= How are simultaneous edits handled? =

Each editor saves only one profile section. A short write lock serializes exact
simultaneous changes, and a stale tab editing the same profile is rejected
instead of overwriting newer values. Editing one profile never submits or
rewrites another profile.

= Does version 2 migrate the old shared settings? =

No. Version 2 is a deliberate clean break. Recreate the required integrations
as profiles after upgrading. It does not scan pages, rewrite content, retain a
default profile, or provide legacy selectors and aliases.

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

1. The integrations index and profile-specific routing status.
2. The Contact Newsletter Form pattern in the RAN Forms category.

== Changelog ==

= 2.0.0 =

Introduces independent, conflict-safe integration profiles. This is a breaking
settings-schema change with no automatic migration.

= 1.0.0 =

First public release for WordPress 6.8+ and PHP 8.0+.

== Upgrade Notice ==

= 2.0.0 =

Version 2 does not migrate the former shared configuration. Record any settings
you still need, upgrade, and recreate each integration as a profile.

= 1.0.0 =

First public release.
