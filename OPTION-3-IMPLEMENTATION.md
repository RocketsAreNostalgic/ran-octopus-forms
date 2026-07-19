# Option 3 implementation: independent integration profiles

## Goal

Replace the shared Option 2 configuration with conflict-safe independent
profiles. Each profile owns one or more compatible saved Jetpack forms and its
own EmailOctopus destination, mapping, success page, and outcome messages.

This is a deliberate 2.0.0 clean break. The plugin will not migrate, seed,
back up, or read the old flat schema. The demo integration using saved form
`6243` will be recreated manually through the new administration interface.

## Dex tree

| ID         | Task                                                     | Blocked by                         |
| ---------- | -------------------------------------------------------- | ---------------------------------- |
| `42eizuoh` | Independent EmailOctopus integration profiles            | Parent                             |
| `96b1ui04` | Lock Option 3 clean-cutover and concurrency contract     | —                                  |
| `w86e4ylm` | Replace flat settings with conflict-safe profile storage | `96b1ui04`                         |
| `i3192wmj` | Make routing subscriptions and outcomes profile-explicit | `w86e4ylm`                         |
| `12dsm4dq` | Build profile index and two-stage administration         | `w86e4ylm`, `i3192wmj`             |
| `kvollcli` | Add per-profile diagnostics and troubleshooting          | `w86e4ylm`, `i3192wmj`             |
| `rlugxc6y` | Update public contracts documentation and smoke tooling  | `12dsm4dq`, `kvollcli`             |
| `wazin69b` | Prove cross-profile isolation security and save safety   | `i3192wmj`, `12dsm4dq`, `kvollcli` |
| `a05ztx7r` | Prove release readiness and local acceptance             | `rlugxc6y`, `wazin69b`             |

Complete every child with its implementing commit SHA and exact validation
result before completing the parent.

## Canonical store

Keep the existing `ran_emailoctopus_jetpack_forms_settings` option name and
replace its contents with schema version 1:

```php
array(
	'schema_version' => 1,
	'revision'       => 1,
	'profiles'       => array(
		'<uuid-v4>' => array(
			'revision'        => 1,
			'label'           => 'Newsletter signup',
			'form_ids'        => array( 6243 ),
			'destination'     => array(
				'type' => 'form',
				'id'   => 'emailoctopus-resource-id',
			),
			'email_source'    => 'email',
			'consent_source'  => 'join_newsletter',
			'field_map'       => array(
				'FirstName' => array(
					'source'    => 'name',
					'transform' => 'first_word',
				),
			),
			'success_page_id' => 6250,
			'messages'        => array(
				'pending'    => '...',
				'subscribed' => '...',
				'existing'   => '...',
				'failed'     => '...',
			),
		),
	),
);
```

Locked storage rules:

- Generate immutable UUID v4 profile IDs. Labels are required, editable, and
  do not provide identity.
- Increment the store revision and affected profile revision on every
  successful mutation.
- Normalize form IDs to unique positive integers in ascending order. Preserve
  unavailable positive IDs already owned by a profile for diagnostics.
- One saved form may belong to at most one profile. Reject an administrator
  save that introduces a conflict without changing the option. Corrupt
  duplicate ownership resolves no profile for that form while unrelated forms
  and profiles remain available.
- Store one destination tagged union: empty type and ID, or `form|list` with a
  non-empty ID. Do not persist parallel form/list IDs or a derived list ID.
- Normalize email and consent sources. Preserve non-empty stale values. Limit
  transforms to `as_is`, `first_word`, `remaining_words`, and `lowercase`.
- Store success page ID `0` or a positive ID. Preserve stale positive IDs for
  diagnostics. Store exactly the four supported outcome messages.
- Treat a malformed or flat root as unconfigured. Do not inspect, migrate, or
  automatically rewrite it. An intentional profile create may replace it with
  the canonical empty store plus the new profile.

## Conflict-safe writes

All mutations use profile-specific `admin-post.php` handlers and repository
methods rather than posting the entire profile collection through the Settings
API.

Each edit includes the immutable profile ID and expected profile revision. A
write must:

1. Acquire a short-lived, non-autoloaded lock row with an insert-only prepared
   query. Do not use `add_option()` as the mutex: WordPress 6.8 can use an
   upsert path that lets a racing writer replace the first token.
2. Reload the latest store after acquiring the lock.
3. Reject an active competing lock. Reclaim an expired lock only with a
   compare-and-swap update that still matches the observed payload.
4. Reject a stale same-profile revision without changing stored data.
5. Merge only the submitted section into the latest profile; never rewrite an
   unrelated profile or section.
6. Revalidate form ownership, normalize the result, increment revisions, and
   persist through `update_option()`.
7. Release with a token/payload-conditional delete so one writer can never
   remove another writer's lock, including error paths.

Stage one owns label, saved-form assignment, and destination. Stage two owns
email source, consent source, custom mapping, success page, and messages. A
stage-one save preserves stage two byte-for-byte and vice versa. No field-level
merge is attempted after a same-profile revision conflict.

## Runtime contract

- A profile may be saved incomplete. It becomes routing-active when it has an
  effective success destination and at least one uniquely owned, published,
  structurally valid saved form.
- Resolve the owning profile during saved-form rendering and sign the immutable
  profile ID, exact saved-form reference, and nonce.
- Require the resolved profile explicitly for consent, email extraction,
  custom payloads, EmailOctopus destination, redirect, outcome message, and
  eligibility checks. No default-profile or global configuration fallback may
  remain.
- Incompatible forms retain their profile redirect but skip EmailOctopus.
  Invalid, unassigned, conflicted, or unresolved forms remain under native
  Jetpack handling.
- Different profiles may use different success pages or the same page. The
  canonical generic shortcode resolves the one-time token's profile and
  message. A token on the wrong page is not consumed; a deleted profile makes
  its outstanding token inert.
- Continue rejecting Jetpack feedback already classified as spam or trash.
- The same saved form has the same profile wherever it is embedded. Route- or
  embedding-specific behavior remains out of scope.

## Administration and diagnostics

- Replace the single settings form with an integrations index and separate
  add, edit, health, and delete-confirmation screens.
- The index performs no remote EmailOctopus requests. Show label, assigned
  forms, destination, routing/subscription counts, last health result, and
  actions.
- Use a server-rendered two-stage editor. Stage one saves and reloads before
  dependent field choices appear. Stage two uses only persisted stage-one
  values. No JavaScript is required.
- Disable forms owned by another profile and identify/link their owner. Reject
  crafted duplicate assignments server-side.
- Preserve destinations and mappings during EmailOctopus API errors. Keep
  unavailable assigned forms visible and removable.
- Pair every nonce with `manage_options`, sanitize explicit request fields,
  escape output late, and use an explicit POST confirmation for deletion.
- Run global prerequisites once and group form, mapping, destination, success,
  and provider results under each profile. One broken profile must not make
  healthy peers appear disabled.

## Public clean break

- Retain only
  `[ran_emailoctopus_jetpack_forms_subscription_message]`.
- Remove deprecated shortcode/filter aliases, migration options and methods,
  version upgrade state, page discovery, and global list-override constants.
- Retain the six canonical configuration filters with two arguments: effective
  value and immutable profile ID.
- Rename legacy hidden-field, nonce, result-query, transient, cache, CSS,
  settings-error, and API-error identifiers to the
  `ran_emailoctopus_jetpack_forms_*` family.
- Rename the legacy `ran-octopus-forms` block-pattern namespace/category; no
  saved content depends on a registered pattern identity after insertion.
- Require the release archive to contain no `ran_octopus_forms`,
  `ran_forms_settings`, or `ran-octopus-forms` identifier.
- Keep the EmailOctopus API key global because the official EmailOctopus plugin
  owns it.

## Orchestration

The coordinating agent owns architecture, Dex, live WordPress changes,
generated translations, integration review, staging, commits, and final
acceptance. Sub-agents receive an exact base SHA, exclusive owned files,
prohibited files, focused acceptance criteria, and required tests. They do not
commit or modify Dex.

Use high-reasoning agents for storage, locking, runtime security, and
adversarial review. Use bounded agents for disjoint tests, administration,
health reporting, documentation, and compatibility execution. Do not run
parallel edits on shared files or whole-repository formatters from delegates.

## Acceptance gates

- Schema, UUID, normalization, malformed-root, CRUD, lock expiry, active lock,
  profile revision, section preservation, and form-ownership tests.
- Sequential and interleaved Profile A/Profile B saves preserve both profiles;
  stale same-profile tabs fail without mutation.
- Two profiles on one route and separate routes receive exact signed contexts;
  adjacent unassigned forms remain native.
- Cross-profile marker/feedback swaps, deletion, reassignment, stale IDs, and
  tampering fail closed.
- Destinations, sources, custom maps, consent, success pages, and messages do
  not bleed between profiles.
- Wrong-page result tokens remain available for the correct page and are
  consumed there once.
- Spam/trash creates no EmailOctopus request.
- Admin capability, nonce, escaping, accessibility, API-failure, zero-profile,
  and deletion coverage.
- PHPCS, Composer validation, package checks, POT freshness, PHPUnit on
  WordPress 6.8/Jetpack 15.5 and the current lane, deterministic ZIP, Plugin
  Check, and clean-ZIP smoke.
- Local acceptance records current values for operator reference, deletes the
  exact old plugin settings/version options, recreates the form `6243` profile
  through the UI, exercises and removes a second stubbed profile, verifies
  conflict behavior, and never contacts EmailOctopus.

Use breaking Conventional Commits for the storage/runtime cutover so Release
Please prepares version 2.0.0.
