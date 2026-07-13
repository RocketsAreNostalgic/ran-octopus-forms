# RAN Octopus Forms

RAN Octopus Forms is a site-owned WordPress integration for Jetpack contact
forms, EmailOctopus newsletter opt-ins, success redirects, and optional
Cloudflare Turnstile verification.

## Features

- Registers the **Contact Newsletter Form** pattern in the `PNS Layouts`
  category.
- Redirects a configured Jetpack contact form to a configured success page.
- Adds opted-in subscribers to an EmailOctopus list, with configurable field
  mapping.
- Supports Cloudflare Turnstile and blocks its always-pass test keys in
  production.
- Provides a read-only configuration health check.

## Requirements

- WordPress with Jetpack Forms available.
- The EmailOctopus plugin, when newsletter opt-ins should create subscribers.
- Cloudflare Turnstile site and secret keys, when Turnstile is enabled.

## Installation

1. Install the plugin in `wp-content/plugins/ran-octopus-forms`.
2. Activate **RAN Octopus Forms** in WordPress administration.
3. Open **Settings > RAN Octopus Forms** and configure the contact and success
   pages.
4. Configure EmailOctopus and, if required, Turnstile before publishing the
   contact form.

On first activation, the plugin copies saved `ran_forms_settings` into
`ran_octopus_forms_settings`. The legacy setting remains as a rollback
safeguard, and legacy constants continue to work until environment bootstrap
code is updated.

## Usage

Insert the **Contact Newsletter Form** pattern on the configured contact page.
It provides required name and email fields, a message field, and a newsletter
opt-in checkbox. The plugin targets the configured page rather than a reusable
form post ID, so exactly one Jetpack contact form must exist on that page.

Select an EmailOctopus form for normal use: the plugin resolves its connected
list automatically. A direct list override is available only when a form is
not the intended destination. Newsletter opt-ins are sent through the
EmailOctopus list contacts API; custom list fields require explicit mappings in
**Settings > RAN Octopus Forms > EmailOctopus field mapping**.

For local Turnstile testing, the settings page exposes Cloudflare's documented
always-pass and always-fail test pairs. WordPress's `production` environment
never renders or accepts the always-pass pair.

## Development

Run commands from this plugin directory:

```sh
pnpm install --frozen-lockfile
pnpm format:check
pnpm check
php -l ran-octopus-forms.php
```

The shared WordPress configuration lives in `.eslintrc.json`,
`.stylelintrc.json`, `.prettierignore`, and `package.json`. PHP follows the
WordPress coding style; lint each changed PHP file before release.

## Extensibility and compatibility

The plugin provides these filters:

```php
ran_octopus_forms_contact_page_slug
ran_octopus_forms_emailoctopus_form_id
ran_octopus_forms_emailoctopus_list_id
ran_octopus_forms_contact_success_url
ran_octopus_forms_environment_type
```

The settings health check verifies Jetpack Forms, EmailOctopus read-only API
access, page/form shape, redirect hooks, Turnstile configuration, and frontend
rendering without sending mail, creating feedback posts, or adding contacts.

RAN Octopus Forms owns the modern contact/newsletter path. It does not own
legacy hosted EmailOctopus embeds retained in site content.

## Accessibility and security

The starter pattern uses explicit required fields and an opt-in checkbox.
Subscription occurs only for an opted-in submission. Keep API and Turnstile
secrets in WordPress configuration or environment-specific bootstrap code;
never expose them in public markup or logs.

## License

RAN Octopus Forms is licensed under the [GNU General Public License v2.0 or
later](LICENSE) (`GPL-2.0-or-later`).

## Support and contributing

Report reproducible issues at
[RocketsAreNostalgic/ran-octopus-forms](https://github.com/RocketsAreNostalgic/ran-octopus-forms/issues).
Contributions should include relevant lint and health-check evidence.
