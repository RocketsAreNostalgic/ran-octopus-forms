# Third-party services and licences

RAN EmailOctopus for Jetpack Forms contains no bundled third-party PHP,
JavaScript, CSS, fonts, or images. Its own source is GPL-2.0-or-later; see
[LICENSE](LICENSE).

At an administrator's option, it integrates with these independent services:

- **Jetpack Forms** is required and supplies the contact-form blocks.
- **EmailOctopus** receives authenticated administrative requests for account
  form or list configuration while an administrator edits a destination,
  resolves custom fields, or explicitly runs a profile health check. It receives
  an opted-in visitor's email address and explicitly mapped fields only when an
  eligible configured form is submitted.

Administrators must review and accept each provider's terms and privacy policy
before enabling it. The plugin does not bundle or redistribute provider code.
