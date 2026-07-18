# RAN EmailOctopus for Jetpack Forms

RAN EmailOctopus for Jetpack Forms adds an explicit EmailOctopus integration
layer to one administrator-selected
[Jetpack Forms](https://jetpack.com/support/jetpack-forms/) block. It is
portable: no theme, site path, page slug, or hard-coded provider credential is
assumed.

## Requirements

- WordPress 6.5 or later.
- PHP 8.0 or later.
- Jetpack, which supplies the required contact-form block.

EmailOctopus is optional. Until an administrator configures it, no
EmailOctopus request is made.

## Installation and use

1. Install and activate Jetpack, then activate RAN EmailOctopus for Jetpack
   Forms.
2. Choose the contact and success pages in **Settings > RAN EmailOctopus**.
3. Insert **Contact Newsletter Form** from the plugin-owned **RAN Forms**
   pattern category on the chosen contact page.
4. Configure the client's preferred recipient using Jetpack's native **Form
   notifications** settings on the contact form.
5. Configure EmailOctopus only if opted-in subscribers should be sent to a
   selected destination.
6. Add `[ran_emailoctopus_jetpack_forms_subscription_message]` in a Shortcode block on the
   chosen success page. The plugin passes a one-time result to that page and
   shows the configured confirmation, subscription, existing-email, or problem
   message.

The supplied pattern adds the `ran-octopus-forms-contact-form` marker. Redirects,
opt-in subscriptions and normal-post handling apply only to that
single marked Jetpack form. Other Jetpack forms on the page and in template
parts are unaffected.

### Existing sites

When upgrading from RAN Octopus Forms, the plugin copies its EmailOctopus and
page settings to `ran_emailoctopus_jetpack_forms_settings` without deleting the
legacy option or copying Turnstile credentials. Existing saved forms keep their
`ran-octopus-forms-contact-form` marker, and the legacy
`[ran_octopus_forms_subscription_message]` shortcode remains supported.

The 1.0.0 upgrade preserves existing page IDs. Once, it marks the configured
page's form only when it finds exactly one Jetpack form. When the page has zero
or multiple forms, it makes no content change; an administrator must select or
reinsert the intended pattern. New installations have no default contact or
success route.

### Developer compatibility

The canonical extension prefix is `ran_emailoctopus_jetpack_forms_`. Existing
`ran_octopus_forms_*` filters continue to run as deprecated aliases before
their canonical replacements. A configured list constant may use
`RAN_EMAILOCTOPUS_JETPACK_FORMS_EMAILOCTOPUS_LIST_ID`; the former
`RAN_OCTOPUS_FORMS_EMAILOCTOPUS_LIST_ID` and
`RAN_FORMS_EMAILOCTOPUS_LIST_ID` constants remain accepted as fallbacks.

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
