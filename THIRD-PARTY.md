# Third-party services and licences

RAN Octopus Forms contains no bundled third-party PHP, JavaScript, CSS, fonts,
or images. Its own source is GPL-2.0-or-later; see [LICENSE](LICENSE).

At an administrator's option, it integrates with these independent services:

- **Jetpack Forms** is required and supplies the contact-form blocks.
- **EmailOctopus** receives an opted-in visitor's email address and explicitly
  mapped fields when a destination and API key are configured.
- **Cloudflare Turnstile** loads Cloudflare's widget and sends the response
  token and, when available, the visitor IP address to Cloudflare for server
  validation when enabled.

Administrators must review and accept each provider's terms and privacy policy
before enabling it. The plugin does not bundle or redistribute provider code.
