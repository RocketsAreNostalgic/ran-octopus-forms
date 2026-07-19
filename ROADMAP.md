# Roadmap

This roadmap records the completed progression from portable saved-form
targeting to independent EmailOctopus integrations. It is a repository planning
document and is not included in release archives.

## Current baseline: Option 3

Option 3 is the implemented 2.0 baseline. The canonical settings option contains
revisioned profiles keyed by immutable UUIDs. Each profile contains:

- an editable label and one or more exclusively assigned saved Jetpack forms;
- an optional EmailOctopus form or list destination;
- explicit email and consent sources plus custom mappings and transforms;
- one success page; and
- pending, subscribed, existing-address, and failure messages.

Several compatible forms may share a profile. Forms that need different
destinations, mappings, success pages, or messages use separate profiles. A
saved form can belong to only one profile, and corrupt duplicate ownership fails
closed for that form without disabling unrelated profiles.

The server-rendered administration interface uses an integrations index and a
two-stage editor. Stage one owns identity, form assignment, and destination;
stage two owns fields, mappings, success page, and messages. Profile-specific
save handlers, a short database write lock, and profile revisions prevent
cross-profile clobbering and reject stale same-profile tabs.

Runtime routing signs the immutable profile UUID and exact saved-form reference,
then verifies them against Jetpack's authoritative feedback identity. Outcome
tokens carry the profile, and the canonical generic shortcode resolves that
profile's message only on its configured success page.

Implementation and acceptance evidence is governed by
[`OPTION-3-IMPLEMENTATION.md`](OPTION-3-IMPLEMENTATION.md).

## Completed foundation: Option 1

Option 1 made one saved Jetpack form portable across routes. It established the
authoritative saved-form identity requirement, signed exact-form context,
route-independent redirects, and native handling for unrelated forms.

Its locked plan and historical evidence remain in
[`OPTION-1-IMPLEMENTATION.md`](OPTION-1-IMPLEMENTATION.md).

## Completed foundation: Option 2

Option 2 extended the internal collection to several compatible saved forms
sharing one configuration. It established field-intersection discovery,
per-form mapping compatibility, isolation of malformed forms, and multiple
participating forms on one route.

Its locked plan and historical evidence remain in
[`OPTION-2-IMPLEMENTATION.md`](OPTION-2-IMPLEMENTATION.md).

## Version 2 compatibility policy

Option 3 is a deliberate clean break. It does not migrate or dual-read the
Option 1 or Option 2 shared settings schema. It does not infer targets from
pages, scan or rewrite content, create a default profile, or retain legacy page
selectors, shortcodes, filter aliases, constants, prefixes, and global getters.

The retained public surface is intentionally small:

- the canonical subscription-message shortcode;
- six canonical configuration filters, each receiving the effective value and
  immutable profile UUID; and
- native Jetpack behaviour for unassigned, invalid, spam, and trash feedback.

## Future candidates

Further work should be justified by real integrations rather than compatibility
with unreleased schemas. Potential candidates include:

- client-side field-choice refresh while retaining the same revision and lock
  save contract;
- profile duplication for administrators configuring similar forms;
- import/export with explicit schema validation and ownership-conflict review;
- richer transforms backed by concrete EmailOctopus mapping requirements; and
- optional form search or pagination if the saved-form list becomes unwieldy.

JavaScript must not weaken conflict detection or turn the profile editor into a
single nested save. Route-specific behaviour remains out of scope: the saved
form, not its embedding page, owns the integration.
