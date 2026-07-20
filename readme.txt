=== RAN EmailOctopus for Jetpack Forms ===
Contributors: bnjmnrsh
Tags: contact form, jetpack, emailoctopus, newsletter
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.0
X-Release-Please-Start-Version: x-release-please-start-version
Stable tag: 2.1.0
X-Release-Please-End: x-release-please-end
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Connect selected saved Jetpack forms to EmailOctopus with independent
integration profiles.

== Description ==

Connect the saved Jetpack forms that matter to EmailOctopus without taking over
every form on a site. RAN EmailOctopus for Jetpack Forms lets an administrator
create independent integration profiles for newsletter sign-ups.

* Assign one or more compatible saved Jetpack forms to a profile.
* Choose an EmailOctopus destination, opt-in field, field mappings, success
  page, and outcome messages for each profile.
* Reuse an assigned saved form on pages, posts, patterns, and other singular
  routes while it keeps the same profile.
* Keep unassigned or adjacent Jetpack forms under Jetpack's normal handling.
* Check a profile's routing, field compatibility, and provider configuration
  without disrupting unrelated profiles.

Each profile is edited independently. The two-stage editor protects mappings
and messages while form assignments or destinations are refreshed, and a stale
tab cannot silently overwrite a newer edit to the same profile.

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
3. Configure your EmailOctopus API key in the official EmailOctopus WordPress
   plugin. This plugin reads that plugin's `emailoctopus_api_key` setting and
   does not provide a separate API-key field.
4. Create or choose compatible published saved Jetpack forms. The Contact
   Newsletter Form pattern in the RAN Forms category is a suitable starting point.
5. Go to Settings > RAN EmailOctopus and create an integration profile.
6. Save the profile label, assigned forms, and optional destination, then
   configure the refreshed field choices, success page, and messages.
7. Configure recipients in Jetpack's native Form notifications settings. This
   plugin does not replace Jetpack's notification email or WordPress mail path.
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
* EmailOctopus is contacted from the administration area while an administrator
  edits destination choices, resolves custom fields, or explicitly runs a
  profile health check. Those requests use the API key configured through the
  official EmailOctopus plugin and retrieve account form or list configuration;
  they do not send visitor form values. When a visitor opts in through an
  eligible configured form, the visitor's email address and deliberately mapped
  fields are sent to https://emailoctopus.com/api/1.6/. See
  https://emailoctopus.com/legal/privacy.

Site administrators are responsible for their notices, consent, and provider
accounts before enabling external services.

== Screenshots ==

1. The integrations overview, showing independent profiles, assigned saved
   forms, routing status, and no secrets or personal data.
2. The profile editor, showing saved-form assignment and profile-specific
   EmailOctopus destination choices.

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
