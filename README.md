# RAN EmailOctopus for Jetpack Forms

RAN EmailOctopus for Jetpack Forms adds an explicit EmailOctopus integration
layer to an administrator-selected collection of compatible saved
[Jetpack Forms](https://jetpack.com/support/jetpack-forms/) forms. Every selected
form shares one EmailOctopus destination, field mapping, success page, and
message set wherever it is reused: pages, posts, patterns, and other singular
routes can all reference them. No theme, site path, page slug, or hard-coded
provider credential is assumed.

## Requirements

- WordPress 6.8 or later.
- PHP 8.0 or later.
- Jetpack, which supplies saved contact forms and authoritative saved-form
  identity for submitted feedback.

EmailOctopus is optional. Until an administrator configures it, no
EmailOctopus request is made.

## Installation and use

1. Install and activate Jetpack, then activate RAN EmailOctopus for Jetpack
   Forms.
2. Create or choose compatible published saved Jetpack forms. The supplied **Contact
   Newsletter Form** pattern in the **RAN Forms** category is a suitable
   starting point.
3. In **Settings > RAN EmailOctopus**, select those saved forms and choose the
   page that should show the success outcome.
4. Reuse the selected saved forms on any routes that should share this
   integration. Forms are included only when explicitly selected.
5. Configure the client's preferred recipients using Jetpack's native **Form
   notifications** settings on the saved form. This plugin does not send the
   notification email or replace WordPress mail handling.
6. Configure EmailOctopus only if opted-in subscribers should be sent to a
   selected destination.
7. Add `[ran_emailoctopus_jetpack_forms_subscription_message]` in a Shortcode
   block on the chosen success page. The plugin passes a one-time result to that
   page and shows the configured confirmation, subscription, existing-email, or
   problem message.

The selected saved forms are the sole integration boundaries. Redirects,
opt-in subscriptions, normal-post handling, and source-field discovery follow
each form across routes. Unselected forms, including another form on the same
page, remain under Jetpack's normal behaviour. The success destination remains
one page for every selected form.

### Saved-form routing requirements

EmailOctopus routing runs only when Jetpack provides an authoritative saved-form
identity for submitted feedback and each active target is a published,
structurally valid `jetpack_form`. There is no page-scoped fallback. One broken
selection does not stop valid peers; the settings page and health check identify
which forms need attention.

An unavailable, deleted, draft, wrong-type, structurally invalid, or
mapping-incompatible form cannot receive EmailOctopus side effects. Jetpack's
native notification and feedback handling continues, while the health check
explains how to repair each target. Every configured source must exist
unambiguously and compatibly on a form before that form can subscribe a visitor.

### Existing sites

When upgrading from RAN Octopus Forms, the plugin copies its EmailOctopus
settings to `ran_emailoctopus_jetpack_forms_settings` without deleting the
source option or copying Turnstile credentials. The legacy
`[ran_octopus_forms_subscription_message]` shortcode remains supported.

The upgrade does not infer saved-form targets from pages or inspect and rewrite
content. An existing `target_form_id` is converted once to the one-item
`target_form_ids` collection and the scalar key is removed. Otherwise
EmailOctopus routing stays disabled until an administrator selects at least one
valid published saved form. Former page settings remain irrelevant. New
installations have no default forms or success route.

### Developer compatibility

The canonical extension prefix is `ran_emailoctopus_jetpack_forms_`. Existing
`ran_octopus_forms_*` filters continue to run as deprecated aliases before
their canonical replacements. A configured list constant may use
`RAN_EMAILOCTOPUS_JETPACK_FORMS_EMAILOCTOPUS_LIST_ID`; the former
`RAN_OCTOPUS_FORMS_EMAILOCTOPUS_LIST_ID` and
`RAN_FORMS_EMAILOCTOPUS_LIST_ID` constants remain accepted as fallbacks.

Saved-form targeting adds no new public filters. Existing compatibility
filters and constants retain their previous contracts.

### Notifications and competing integrations

Jetpack remains responsible for form notifications, feedback storage, and its
spam pipeline. Because notifications use WordPress's normal mail path, an SMTP
or transactional-email plugin that integrates with WordPress mail can continue
to handle them.

Do not configure a second EmailOctopus connector to subscribe the same saved
forms: both connectors could act on one opt-in. Turnstile or Akismet may protect
the form independently because they validate spam or interaction rather than
performing the EmailOctopus subscription. Review any provider that also changes
Jetpack redirects or forces AJAX submission, because the configured success
page requires this integration's normal-post handling.

## Privacy and external services

The plugin has no bundled third-party code. See [THIRD-PARTY.md](THIRD-PARTY.md)
for the service and licence inventory.

- Jetpack Forms is a required local plugin dependency.
- EmailOctopus receives an opted-in email address and only deliberately mapped
  fields after an administrator configures an API key and destination.

Site administrators are responsible for provider accounts, legal notices, and
consent before enabling external services.

## Development

Run commands from this plugin directory:

```sh
pnpm install --frozen-lockfile
composer install
pnpm make-pot
pnpm check
composer run phpcs
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer test
pnpm release
```

`pnpm release` creates a clean ZIP from the explicit
[release allowlist](release-contents.txt), verifies its archive integrity, and
never overwrites an existing archive. The GitHub workflow checks PHP 8.0 and
the current supported PHP combination, translation freshness, PHPCS, PHPUnit,
Plugin Check, and release archive contents.

## Agent workflow

See [AGENTS.md](AGENTS.md) for the local Dex workflow, WordPress skills,
generated-asset rules, quality checks, and release guidance.

## WordPress.org preparation

The public directory readme is [readme.txt](readme.txt). GitHub remains the
development source; [RELEASE.md](RELEASE.md) documents the manual SVN
`trunk`, `tags/1.0.0`, and separate `/assets` handoff. Do not submit before the
final directory slug, contributor account, trademarks, and third-party terms
are confirmed.

## License

RAN EmailOctopus for Jetpack Forms is licensed under
[GPL-2.0-or-later](LICENSE).
