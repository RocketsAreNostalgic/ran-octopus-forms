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

Adds an opt-in EmailOctopus integration to one explicitly marked Jetpack contact form.

== Description ==

RAN EmailOctopus for Jetpack Forms lets an administrator choose one contact page and one marked
Jetpack form. Only that form can receive the configured success redirect,
opt-in EmailOctopus subscription, and normal-post behaviour. Other Jetpack forms on the same page or in a
template remain unchanged.

Jetpack is required. EmailOctopus is optional and remains disabled until the
administrator configures it.

== Installation ==

1. Install and activate Jetpack.
2. Upload and activate RAN EmailOctopus for Jetpack Forms.
3. Go to Settings > RAN EmailOctopus and choose contact and success pages.
4. Insert the Contact Newsletter Form pattern from the RAN Forms category on
   the contact page.
5. Optionally add EmailOctopus credentials and a destination.
6. Add [ran_emailoctopus_jetpack_forms_subscription_message] in a Shortcode block on the
   configured success page to show the appropriate newsletter outcome message.

== Frequently Asked Questions ==

= Does it change every Jetpack form? =

No. The integration only acts on the form carrying the
`ran-octopus-forms-contact-form` marker created by the supplied pattern.

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

Existing settings are retained. A legacy contact page with exactly one Jetpack
form is marked automatically; pages with zero or multiple forms need an
administrator to insert or mark the intended form.
