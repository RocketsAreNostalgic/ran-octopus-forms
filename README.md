# RAN EmailOctopus for Jetpack Forms

RAN EmailOctopus for Jetpack Forms adds independent EmailOctopus integration
profiles to saved [Jetpack Forms](https://jetpack.com/support/jetpack-forms/).
Each profile owns one or more compatible forms and defines its own EmailOctopus
destination, field mappings, success page, and visitor messages. A saved form
keeps its profile wherever it is reused: pages, posts, patterns, and other
singular routes can all reference it. No theme, route, page slug, or hard-coded
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
2. Create or choose compatible published saved Jetpack forms. The supplied
   **Contact Newsletter Form** pattern in the **RAN Forms** category is a suitable
   starting point.
3. In **Settings > RAN EmailOctopus**, create an integration profile. First save
   its label, saved-form assignments, and optional EmailOctopus destination.
4. After the field choices refresh, configure the profile's email and consent
   sources, custom mappings, success page, and outcome messages.
5. Reuse an assigned saved form on any route that should use its profile. A
   saved form can belong to only one profile.
6. Configure the client's preferred recipients using Jetpack's native **Form
   notifications** settings on the saved form. This plugin does not send the
   notification email or replace WordPress mail handling.
7. Configure EmailOctopus only if opted-in subscribers should be sent to a
   selected destination.
8. Add `[ran_emailoctopus_jetpack_forms_subscription_message]` in a Shortcode
   block on each profile's success page. The one-time result identifies its
   profile and displays that profile's confirmation, subscription,
   existing-email, or problem message. A result presented on another page stays
   inert and is not consumed.

Assigned saved forms are the integration boundaries. Redirects, opt-in
subscriptions, normal-post handling, and source-field discovery follow each
form across routes. Unassigned forms, including another form on the same page,
remain under Jetpack's normal behaviour. Several forms in one profile share
that profile's behaviour; forms needing different behaviour belong to separate
profiles.

### Conflict-safe editing

The administration interface saves one profile section at a time. The identity
and assignment step never submits mappings or messages, and the behaviour step
never submits ownership or destination fields. A short database write lock
serializes exact simultaneous changes. If another tab has already changed the
same profile, the stale save is rejected without overwriting the newer values;
the submitted values remain available for review and retry.

### Saved-form routing requirements

EmailOctopus routing runs only when Jetpack provides an authoritative saved-form
identity for submitted feedback and each active target is a published,
structurally valid `jetpack_form` uniquely owned by one profile. There is no
page-scoped fallback. One broken profile or form does not stop unrelated valid
profiles; the integrations index and profile health check identify what needs
attention.

An unavailable, deleted, draft, wrong-type, structurally invalid, or
mapping-incompatible form cannot receive EmailOctopus side effects. Jetpack's
native notification and feedback handling continues, while the health check
explains how to repair each target. Every configured source must exist
unambiguously and compatibly on a form before that form can subscribe a visitor.

### Version 2 clean break

Version 2 replaces the former shared settings record with UUID-keyed integration
profiles. It deliberately performs no schema migration, dual reads, page
inspection, or content rewriting. Existing shared settings are not used at
runtime. Recreate each required integration through the profile editor after
upgrading. There is no default profile, page selector, or legacy shortcode.

### Developer compatibility

The canonical shortcode is
`[ran_emailoctopus_jetpack_forms_subscription_message]`. The six public
configuration filters receive the effective value as their first argument and
the immutable profile UUID as their second:

- `ran_emailoctopus_jetpack_forms_contact_success_url`
- `ran_emailoctopus_jetpack_forms_emailoctopus_form_id`
- `ran_emailoctopus_jetpack_forms_emailoctopus_list_id`
- `ran_emailoctopus_jetpack_forms_emailoctopus_email_source`
- `ran_emailoctopus_jetpack_forms_emailoctopus_field_map`
- `ran_emailoctopus_jetpack_forms_newsletter_source`

Version 2 provides no deprecated filter aliases, destination constants, or
global/default-profile getters.

### Health checks

The integrations index calculates routing and subscription counts from local
configuration only; loading it makes no EmailOctopus request. Run **Health** for
an individual profile when provider validation is required. The stored result
is tied to that profile and its current revision, so a later settings change
invalidates stale results. A failing profile remains isolated from unrelated
profiles.

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
`trunk`, tagged-release, and separate `/assets` handoff. Do not submit before
the final directory slug, contributor account, trademarks, and third-party
terms are confirmed.

## License

RAN EmailOctopus for Jetpack Forms is licensed under
[GPL-2.0-or-later](LICENSE).
