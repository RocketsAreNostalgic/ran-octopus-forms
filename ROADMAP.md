# Roadmap

This roadmap records the possible paths for expanding RAN EmailOctopus for
Jetpack Forms beyond its current single-saved-form configuration. It
is a planning document rather than a commitment to deliver every option.

## Current baseline

The implemented Option 1 baseline stores one integration configuration
containing:

- one published saved Jetpack form and one success page;
- one EmailOctopus form or list destination;
- one email source, newsletter consent source, and custom-field map; and
- one set of visitor-facing subscription outcome messages.

The saved form is the integration identity wherever it is embedded. Runtime
routing signs that identity and verifies it against Jetpack's authoritative
feedback metadata. There is no contact-page selector or page-scoped fallback.

Routes should not become permanent form identifiers. Permalinks can change,
and a saved Jetpack form can be embedded in more than one place. Future work
should identify the saved form or an explicit integration marker and carry a
signed identifier through submission handling.

## Option 1: One saved form on multiple routes

Implementation is now governed by
[`OPTION-1-IMPLEMENTATION.md`](OPTION-1-IMPLEMENTATION.md), which records the
locked contract, Dex tasks, compatibility gates, and acceptance criteria.

Allow one configured reusable Jetpack form to use the existing EmailOctopus
configuration wherever that form is embedded.

### Intended behaviour

- Select or resolve one saved Jetpack form as the integration target.
- Recognise that form on pages, posts, patterns, and other singular routes.
- Add a signed hidden marker to every rendered instance.
- Keep the existing EmailOctopus destination, field mapping, success page,
  messages, and settings option.
- Leave unrelated Jetpack forms untouched.

### Implemented work

- Decoupled render and submission checks from any embedding page.
- Bound signed context to the selected saved-form target and default profile.
- Read mapping candidates directly from the saved Jetpack form.
- Generalised success-result checks beyond `is_page()`.
- Updated health checks and added route, post, tampering, and regression tests.
- Preserve unrelated public extension contracts.

### Limitation

Every embedding is the same saved form and therefore uses the same fields and
EmailOctopus configuration.

## Option 2: Several compatible forms sharing one configuration

Implementation is governed by
[`OPTION-2-IMPLEMENTATION.md`](OPTION-2-IMPLEMENTATION.md), which records the
locked collection, compatibility, isolation, security, and acceptance contract.

Allow several explicitly selected saved Jetpack forms to use one shared
EmailOctopus destination and field map.

### Intended behaviour

- Any deliberately selected published saved form can participate, regardless
  of its embedding route.
- Multiple participating forms may appear on the same page.
- Automatically signed form context distinguishes participating forms from
  neighbouring Jetpack forms.
- The existing destination, mappings, success page, and messages remain
  global.

### Likely work

- Replace the scalar target with an explicitly selected saved-form collection.
- Resolve each submitted target from the existing signed saved-form context.
- Build shared mapping candidates from the compatible intersection of selected
  saved-form definitions.
- Validate the shared email, consent, and custom-field mappings against every
  participating form.
- Report health-check results per participating form.
- Cover multiple routes, multiple forms on one page, sibling unselected forms,
  stale mappings, and invalid markers in integration tests.

### Complexity

Low to medium. Approximately one to two focused development days.

### Limitation

All participating forms must expose compatible normalized field keys. For
example, every form must contain the configured email and newsletter-consent
sources. Forms with different fields or destinations need Option 3.

## Option 3: Independent integration profiles

Create a genuine multi-integration model in which each Jetpack form can have
its own EmailOctopus behaviour.

Each profile would contain:

- a stable profile identifier;
- the source post and explicitly selected or marked Jetpack form;
- an EmailOctopus form or list destination;
- email and newsletter-consent sources;
- custom-field mappings and transforms;
- a success destination; and
- visitor-facing outcome messages.

### Likely work

- Replace the flat settings record with an array of integration profiles.
- Add an integrations index and add/edit/delete administration flow.
- Bind the profile ID and submitted form identity into the signed marker.
- Make the field mapper, subscriber, redirects, outcome tokens, and health
  checks resolve a profile rather than global getters.
- Migrate the current configuration into the first profile without deleting
  rollback data.
- Preserve the existing marker as the default profile where possible.
- Add optional profile context to extension filters without breaking existing
  one-argument callbacks.
- Add migration, CRUD, isolation, and per-profile submission coverage.

### Complexity

Medium to high. Approximately four to seven focused development days,
including the administration UI and migration coverage.

### Trade-off

This provides the most flexibility but turns the plugin into a small
integration-management platform with correspondingly greater maintenance and
support costs.

## Recommended sequence

1. Use the implemented Option 1 saved form across routes without duplicating
   its EmailOctopus configuration.
2. Consider Option 2 only when a real need arises for several compatible form
   definitions.
3. Adopt Option 3 only when at least two forms demonstrably need different
   destinations, mappings, redirects, or messages.

This sequence gives the current integration route portability without
committing prematurely to profile management and a larger administration UI.

## Compatibility requirements

Any future implementation should:

- preserve the existing saved-form configuration or provide an explicit
  migration;
- retain the current and deprecated shortcodes, filters, and constants;
- keep unmarked Jetpack forms isolated;
- reject tampered or stale submission markers;
- continue skipping Jetpack feedback already classified as spam or trash; and
- avoid requiring URL scans or hard-coded page slugs.

## Decision trigger

Option 2 is the active implementation. Before starting Option 3, capture the
actual forms, destinations, mappings, and success flows that cannot share the
single `default` profile.
