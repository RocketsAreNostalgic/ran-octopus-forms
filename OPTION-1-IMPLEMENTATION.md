# Option 1 implementation: portable saved Jetpack form

## Goal

Implement one route-independent integration profile, `default`, targeting one
published Jetpack `jetpack_form` reference. The same saved form can be embedded
on any page, post, pattern, or singular route while retaining one EmailOctopus
destination, field mapping, success page, and message set.

Saved-form routing is mandatory. The plugin requires WordPress 6.8+ and a
Jetpack Forms version that exposes authoritative saved-form identity on
feedback. If either the selected form or that identity is unavailable, the
EmailOctopus side effect is disabled while Jetpack's native form handling is
left alone.

## Dex tree

| ID         | Task                                              | Dependencies           |
| ---------- | ------------------------------------------------- | ---------------------- |
| `ko985jiz` | Portable saved Jetpack form integration           | Parent                 |
| `gf2pz4cf` | Lock Option 1 plan and saved-form contract        | None                   |
| `jpz784hs` | Add saved-form migration and default resolver     | `gf2pz4cf`             |
| `iryaqzx3` | Route and verify portable submissions             | `jpz784hs`             |
| `u001uzwz` | Update admin discovery, diagnostics, and guidance | `jpz784hs`             |
| `bv5n4uzd` | Prove compatibility and release readiness         | `iryaqzx3`, `u001uzwz` |

Every child must be completed with its validation result and implementing commit
SHA before the parent is completed.

The original tree above records the implementation work. A corrective Dex tree
removed the page-scoped compatibility layer after the site owner confirmed that
no legacy support was required:

| ID         | Task                                         | Dependencies |
| ---------- | -------------------------------------------- | ------------ |
| `vdq547qo` | Remove legacy page-scoped form support       | Parent       |
| `stmyn8vo` | Remove page settings and legacy routing      | None         |
| `2c8hpl0a` | Simplify admin diagnostics and documentation | `stmyn8vo`   |
| `isdhhbs1` | Raise baseline and reprove release           | `2c8hpl0a`   |

## Locked contract

- Add `target_form_id` to the existing flat settings option.
- Do not retain, migrate, or infer a target from `contact_page_id`. Remove that
  obsolete key from stored settings during upgrade and never rewrite content.
- Model the current configuration internally as profile `default`, whose target
  forms are exposed as a collection containing the selected saved-form ID.
- Enable EmailOctopus routing only when the selected target is a published
  `jetpack_form` and Jetpack exposes authoritative saved-form identity on
  feedback. Otherwise report why the integration is paused.
- Bind the profile ID, saved-form reference, and marker nonce into signed form
  context. Verify that context and Jetpack's authoritative feedback form ID
  before any EmailOctopus side effect or integration redirect.
- Carry the profile ID through the one-time outcome token without changing the
  public redirect query-string format.
- Use the selected saved form as the sole source for field discovery and mapping
  health. Invalid, deleted, draft, wrong-type, or structurally
  invalid targets disable EmailOctopus side effects without disrupting Jetpack's
  native notifications.
- Preserve existing shortcodes, option history, filters,
  constants, EmailOctopus behaviour, and spam/trash rejection. Option 1 adds no
  public developer filters.

## Locked defaults

- Require WordPress 6.8+ and PHP 8.0+.
- Do not convert inline forms or scan and rewrite routes.
- Keep one global success page and one EmailOctopus configuration.
- Provide no contact-page selector or page-scoped compatibility fallback.
- Option 1 supports one saved form across multiple routes. Distinct form
  definitions and per-form settings remain Options 2 and 3.
- Keep this file and `ROADMAP.md` repository-only and outside release archives.

## Acceptance criteria

### Resolution

- The configured `target_form_id` resolves only when it references one published
  `jetpack_form`.
- Inline, missing, deleted, draft, wrong-type, and structurally invalid targets
  pause EmailOctopus processing without altering content.
- The current site selects saved form `6243` directly; page `6236` has no role
  in integration identity.

### Routing and security

- References to the selected saved form on a page and a post receive equivalent
  signed `default` context and use the same integration profile.
- Unrelated and adjacent forms remain untouched, and render context resets after
  each form.
- Missing, stale, changed, tampered, or mismatched profile/reference/signature data
  fails closed.
- A valid marker paired with feedback for another saved form fails closed.
- Spam or trash feedback never produces an EmailOctopus request.

### Behaviour and compatibility

- Valid submissions from both routes use the existing field mappings and global
  success page while Jetpack retains route-source metadata.
- WordPress 6.8/Jetpack 15.5 and the latest WordPress/Jetpack compatibility lanes
  cover saved-form routing.
- PHPCS, PHPUnit, package checks, translation freshness, deterministic release
  verification, Plugin Check, clean-ZIP activation, and version-specific saved
  form smoke checks pass.

### Local acceptance

- Temporary page and post fixtures referencing saved form `6243` both render the
  signed integration context without submitting to EmailOctopus.
- An unrelated form remains unchanged.
- All temporary fixtures are removed after verification.
