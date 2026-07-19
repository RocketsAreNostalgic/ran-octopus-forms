# Option 2 implementation: multiple compatible saved Jetpack forms

## Goal

Extend the internal `default` integration profile from one saved Jetpack form
to an explicitly selected collection. Every eligible form shares one
EmailOctopus destination, field mapping, success page, and outcome-message set
wherever the saved form is embedded.

Option 2 remains saved-form-only. It adds no page selectors, route scans,
content markers, content rewrites, profile CRUD, or public developer filters.

## Dex tree

| ID         | Task                                             | Dependencies           |
| ---------- | ------------------------------------------------ | ---------------------- |
| `mlu3qhhg` | Multiple compatible saved Jetpack forms          | Parent                 |
| `4cgwbt0u` | Lock Option 2 plan and collection contract       | None                   |
| `76jtp95h` | Add collection settings and multi-form selection | `4cgwbt0u`             |
| `9zn38vfk` | Build shared field compatibility and isolation   | `76jtp95h`             |
| `ljqx5em9` | Route and secure multiple saved forms            | `9zn38vfk`             |
| `qa5d84hv` | Update diagnostics documentation and guidance    | `9zn38vfk`, `ljqx5em9` |
| `0xqcxbew` | Prove Option 2 release readiness                 | `ljqx5em9`, `qa5d84hv` |

Complete every child with its validation result and implementing commit SHA
before completing the parent.

## Locked contract

- Replace `target_form_id` with `target_form_ids`, normalized to unique positive
  integers in ascending order.
- If the array key is absent, migrate the current positive scalar once to a
  one-item array, then delete the scalar. Never retain a dual-read path or infer
  forms from pages or content.
- Keep unavailable selected IDs stored and visible until an administrator
  explicitly removes them.
- Use an accessible server-rendered checkbox fieldset. A hidden zero value makes
  clearing every selection an intentional, representable save.
- Keep one internal profile, `default`, whose canonical form-ID collection is
  the selected array.
- A form is routing-eligible only when it is selected, published, a
  `jetpack_form`, structurally valid, and Jetpack exposes authoritative feedback
  form identity. Another invalid selection must not disable it.
- Preserve the existing signed profile/reference/nonce wire format. The exact
  signed reference must equal Jetpack's authoritative feedback form ID.
- Structurally valid selected forms retain the shared success redirect even
  when their EmailOctopus mapping needs attention. Invalid structural targets
  remain entirely under Jetpack's native handling.
- EmailOctopus subscription eligibility is evaluated per form. The configured
  email, consent, and every custom mapping source must exist unambiguously on
  that form with a compatible type. One incompatible form makes no
  EmailOctopus request but does not stop eligible peers.
- Email fields must be `email`; checkbox and consent are one compatible consent
  family; custom mappings require the same normalized key and Jetpack field
  type across participating forms. Labels may differ.
- Preserve stale mappings for repair. Never silently remap them. A mapped field
  that exists but has an empty submitted value may still be omitted from the
  payload.
- Keep the global destination, success page, messages, outcome token, spam/trash
  rejection, WordPress mail behaviour, baselines, extension filters, constants,
  and shortcodes unchanged.

## Acceptance criteria

### Settings and mapping

- The current scalar form `6243` migrates once to `[6243]`, and the scalar key
  is removed.
- Arrays deduplicate, sort, discard non-positive values, support clear-all, and
  retain unavailable positive IDs for diagnostics.
- Multiple published forms and unavailable selected IDs render accessibly.
- Shared selectors expose only compatible field candidates; stale mappings
  remain visible with per-form repair guidance.

### Routing and security

- Two selected forms work on separate routes and adjacent on one route while an
  unselected sibling is unchanged.
- Each form receives signed context for its exact reference and uses the same
  `default` profile.
- Context for form A paired with authoritative feedback for form B fails even
  when both forms are selected.
- Removed, draft, deleted, malformed, missing, stale, and tampered references
  fail closed without disabling another valid selected form.
- Spam or trash feedback from every selected form produces no EmailOctopus
  request.

### Behaviour and diagnostics

- Eligible forms share the destination, mappings, success page, and outcome
  messages.
- Mapping incompatibility skips EmailOctopus only for the affected form; its
  Jetpack notification and shared success flow remain independent.
- Health output reports selected count, routing state per form, shared mapping
  health, affected form IDs/titles, and a degraded overall state where needed.

### Release and local acceptance

- PHPCS, PHPUnit, package checks, POT freshness, deterministic release,
  Plugin Check, and clean-ZIP smoke pass on WordPress 6.8/Jetpack 15.5 and the
  current compatibility lane.
- A reversible local fixture clones form `6243`, renders both selected forms
  beside an unselected form without contacting EmailOctopus, then restores the
  original option and removes every fixture.
- This file and every `OPTION-N-IMPLEMENTATION.md` remain outside release
  archives.
