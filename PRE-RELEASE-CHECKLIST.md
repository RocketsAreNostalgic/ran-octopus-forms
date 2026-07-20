# Pre-release checklist

Use this as the final outstanding-work list before publishing RAN EmailOctopus
for Jetpack Forms to WordPress.org. The plugin already has release guidance in
`RELEASE.md`; this file separates the remaining publish blockers from the
normal release steps.

## Publish blockers

- [x] Keep WordPress.org source artwork in `wordpress-org/assets/`, outside the
      release ZIP. Retain dated concept work under `wordpress-org/assets/drafts/`
      until final listing artwork is approved.
- [ ] Confirm the WordPress.org plugin slug
      `ran-emailoctopus-jetpack-forms`, contributor
      account, support ownership, and public repository links are final.
- [ ] Confirm the banner and icon files in `wordpress-org/assets/` are
      licence-cleared, project-owned, and approved for public directory use.
- [ ] Create the screenshots referenced by `readme.txt` and place them in
      `wordpress-org/assets/`: `screenshot-1.png` for the integrations overview
      and `screenshot-2.png` for the profile editor's saved-form assignment and
      destination choices. Use a clean configuration with no secrets or visitor
      data.
- [ ] Review `readme.txt` against the current WordPress.org readme validator,
      including tags, Jetpack/EmailOctopus external-service
      disclosures, the Release Please-managed stable tag, screenshots, and the declared
      `Tested up to` value.
- [ ] Run the full local release gate from a clean work tree:

```sh
pnpm install --frozen-lockfile
composer install
pnpm check
pnpm run check:generated
composer run phpcs
pnpm run release:verify
pnpm run release:assets
```

- [ ] Confirm routine deployment remains disabled in `wordpress-org/deployment.json` until the WordPress.org slug is assigned and the first approved submission is complete.
- [ ] Confirm the protected deployment workflow downloads the GitHub release assets before SVN staging and that `/assets` sync is only used deliberately.

- [ ] Run Plugin Check against the unpacked release ZIP, matching the
      `.github/workflows/quality.yml` release job.
- [ ] Install the generated ZIP into a fresh WordPress site with Jetpack active
      and verify activation, pattern insertion, zero-profile state, profile
      creation, both editor stages, multiple assigned forms beside an unassigned
      form, profile/form signed context, profile-specific success redirects and
      messages, normal Jetpack submission behavior, and profile deletion.
- [ ] Verify conflict-safe administration in two browser tabs: different-profile
      saves preserve each other; a stale same-profile save is rejected with its
      submitted values available for review; an active lock produces a retry
      notice; and an expired lock can be reclaimed.
- [ ] Record any required settings from the demo site, delete the exact former
      plugin settings/version options, and recreate saved form `6243` through the
      profile editor. Do not expect or advertise automatic migration.
- [ ] Create a second temporary profile using HTTP stubs, confirm that separate
      destinations, mappings, success pages, messages, health results, and
      failures do not bleed, then remove every fixture without contacting
      EmailOctopus.
- [ ] Verify EmailOctopus opt-in mapping with real or sandbox provider
      credentials before making the public service claims final.
- [ ] Confirm the release ZIP is built only from `release-contents.txt` and does
      not include development-only files or WordPress.org directory assets.
- [ ] Confirm the ZIP contains no old `ran_octopus_forms`,
      `ran_forms_settings`, or `ran-octopus-forms` identifiers and excludes every
      repository-only `OPTION-N-IMPLEMENTATION.md` plan.
- [ ] Copy the validated release contents to WordPress.org SVN `trunk`, tag the
      Release Please version, and upload only approved directory artwork/screenshots to
      `/assets`.

## Translation readiness

- [ ] Confirm all user-facing PHP strings are wrapped in the appropriate
      WordPress i18n function with the `ran-emailoctopus-jetpack-forms` text
      domain.
      Serialized pattern labels must be translated before insertion into the
      pattern content.
- [ ] Run the WordPress i18n coding-standard sniff:
      `composer run phpcs -- --sniffs=WordPress.WP.I18n`.
- [ ] Regenerate `languages/ran-emailoctopus-jetpack-forms.pot` with
      `pnpm make-pot` after
      all final user-facing copy changes.
- [ ] Confirm the POT file has no stale source references and is committed with
      the release.
- [ ] Do not bundle `.po` or `.mo` files unless they are reviewed,
      release-ready translations. Translators should normally handle release
      strings through the official translation workflow after approval.
- [ ] Treat launch translations as optional. Only add `.po` and `.mo` files if
      a fluent reviewer has approved them and there is a specific release reason
      to ship them before WordPress.org language packs exist.

## Nice-to-have before first public launch

- [ ] Capture a short manual QA note covering disabled and EmailOctopus-enabled
      states.
- [ ] Confirm the integrations index, two-stage editor, stale-save notice, and
      per-profile health wording are clear enough for a site owner without
      developer support.
