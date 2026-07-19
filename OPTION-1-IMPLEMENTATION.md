# Option 1 implementation: portable saved Jetpack form

## Goal

Implement one route-independent integration profile, `default`, targeting one
published Jetpack `jetpack_form` reference. The same saved form can be embedded
on any page, post, pattern, or singular route while retaining one EmailOctopus
destination, field mapping, success page, and message set.

Portable mode is capability-gated. When Jetpack exposes authoritative saved-form
identity on feedback, the selected saved form is the integration boundary. On
older compatible WordPress and Jetpack versions, the plugin retains its current
page-scoped behaviour.

## Dex tree

| ID | Task | Dependencies |
| --- | --- | --- |
| `ko985jiz` | Portable saved Jetpack form integration | Parent |
| `gf2pz4cf` | Lock Option 1 plan and saved-form contract | None |
| `jpz784hs` | Add saved-form migration and default resolver | `gf2pz4cf` |
| `iryaqzx3` | Route and verify portable submissions | `jpz784hs` |
| `u001uzwz` | Update admin discovery, diagnostics, and guidance | `jpz784hs` |
| `bv5n4uzd` | Prove compatibility and release readiness | `iryaqzx3`, `u001uzwz` |

Every child must be completed with its validation result and implementing commit
SHA before the parent is completed.

## Locked contract

- Add `target_form_id` to the existing flat settings option.
- If the raw option lacks that key, resolve the legacy contact page's single
  marked `jetpack/contact-form` saved-form reference once. Store `0` when the
  result is absent or ambiguous, preserve `contact_page_id`, and never rewrite
  post content.
- Model the current configuration internally as profile `default`, whose target
  forms are exposed as a collection containing the selected saved-form ID.
- Enable portable mode only when the selected target is a published
  `jetpack_form` and Jetpack exposes authoritative saved-form identity on
  feedback. Otherwise retain page-scoped legacy routing and report the reason.
- Bind the profile ID, saved-form reference, and marker nonce into signed form
  context. Verify that context and Jetpack's authoritative feedback form ID
  before any EmailOctopus side effect or integration redirect.
- Preserve the legacy page nonce for cached forms. When authoritative feedback
  identity is available, it must still match the configured target.
- Carry the profile ID through the one-time outcome token without changing the
  public redirect query-string format.
- Use the selected saved form as the source for field discovery and mapping
  health in portable mode. Invalid, deleted, draft, wrong-type, or structurally
  invalid targets disable EmailOctopus side effects without disrupting Jetpack's
  native notifications.
- Preserve existing shortcodes, marker classes, option history, filters,
  constants, EmailOctopus behaviour, and spam/trash rejection. Option 1 adds no
  public developer filters.

## Locked defaults

- Do not raise the WordPress 6.5 or PHP 8.0 plugin baseline.
- Do not convert inline forms or scan and rewrite routes.
- Keep one global success page and one EmailOctopus configuration.
- Keep the contact page as a compatibility fallback.
- Option 1 supports one saved form across multiple routes. Distinct form
  definitions and per-form settings remain Options 2 and 3.
- Keep this file and `ROADMAP.md` repository-only and outside release archives.

## Acceptance criteria

### Migration and resolution

- A single marked saved-form reference migrates once while legacy settings and
  content remain unchanged.
- Inline, missing, deleted, and ambiguous forms resolve to `0` and stay in
  legacy mode.
- The current site resolves contact page `6236` to saved form `6243`.

### Routing and security

- References to the selected saved form on a page and a post receive equivalent
  signed `default` context and use the same integration profile.
- Unrelated and adjacent forms remain untouched, and render context resets after
  each form.
- Missing, stale, changed, tampered, or mismatched profile/reference/nonce data
  fails closed.
- A valid marker paired with feedback for another saved form fails closed.
- Spam or trash feedback never produces an EmailOctopus request.

### Behaviour and compatibility

- Valid submissions from both routes use the existing field mappings and global
  success page while Jetpack retains route-source metadata.
- The WordPress 6.5/Jetpack 13.3.1 legacy lane remains supported.
- WordPress 6.8/Jetpack 15.5 portable coverage is added alongside the latest
  compatibility lane.
- PHPCS, PHPUnit, package checks, translation freshness, deterministic release
  verification, Plugin Check, clean-ZIP activation, and version-specific saved
  form smoke checks pass.

### Local acceptance

- Temporary page and post fixtures referencing saved form `6243` both render the
  signed integration context without submitting to EmailOctopus.
- An unrelated form remains unchanged.
- All temporary fixtures are removed after verification.
