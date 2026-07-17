# RAN Octopus Forms

RAN Octopus Forms adds an explicit contact-form integration layer to one
administrator-selected [Jetpack Forms](https://jetpack.com/support/jetpack-forms/)
block. It is portable: no theme, site path, page slug, or hard-coded provider
credential is assumed.

## Requirements

- WordPress 6.5 or later.
- PHP 8.0 or later.
- Jetpack, which supplies the required contact-form block.

EmailOctopus and Cloudflare Turnstile are optional. Until an administrator
configures them, no EmailOctopus request or Turnstile script is made.

## Installation and use

1. Install and activate Jetpack, then activate RAN Octopus Forms.
2. Choose the contact and success pages in **Settings > RAN Octopus Forms**.
3. Insert **Contact Newsletter Form** from the plugin-owned **RAN Forms**
   pattern category on the chosen contact page.
4. Configure EmailOctopus only if opted-in subscribers should be sent to a
   selected destination. Configure Turnstile only if verification is required.
5. Add `[ran_octopus_forms_subscription_message]` in a Shortcode block on the
   chosen success page. The plugin passes a one-time result to that page and
   shows the configured confirmation, subscription, existing-email, or problem
   message.

The supplied pattern adds the `ran-octopus-forms-contact-form` marker. Redirects,
opt-in subscriptions, Turnstile, and normal-post handling apply only to that
single marked Jetpack form. Other Jetpack forms on the page and in template
parts are unaffected.

### Existing sites

The 1.0.0 upgrade preserves existing page IDs. Once, it marks the configured
page's form only when it finds exactly one Jetpack form. When the page has zero
or multiple forms, it makes no content change; an administrator must select or
reinsert the intended pattern. New installations have no default contact or
success route.

## Privacy and external services

The plugin has no bundled third-party code. See [THIRD-PARTY.md](THIRD-PARTY.md)
for the service and licence inventory.

- Jetpack Forms is a required local plugin dependency.
- EmailOctopus receives an opted-in email address and only deliberately mapped
  fields after an administrator configures an API key and destination.
- Optional Cloudflare Turnstile loads Cloudflare's public script and validates
  its response token server-side; the visitor IP is sent when available.

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

RAN Octopus Forms is licensed under [GPL-2.0-or-later](LICENSE).
