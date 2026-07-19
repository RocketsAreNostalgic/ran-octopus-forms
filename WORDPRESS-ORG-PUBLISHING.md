# WordPress.org publishing checklist

This is the operational handoff for publishing **RAN EmailOctopus for Jetpack
Forms** in the WordPress.org Plugin Directory. It complements the broader
local acceptance coverage in [PRE-RELEASE-CHECKLIST.md](PRE-RELEASE-CHECKLIST.md).

GitHub releases and WordPress.org publishing are separate activities. Do not
upload a ZIP or touch WordPress.org SVN until every required item below is
complete.

## Release candidate

- [ ] Review and merge the Release Please pull request for **2.0.0**. The
      profile-based configuration is a deliberate breaking change and must not
      be published as the older `1.1.0` package.
- [ ] Confirm that the main plugin header, runtime version constant,
      `readme.txt` stable tag, `package.json`, POT project version, changelog,
      generated ZIP name, and intended WordPress.org SVN tag all say `2.0.0`.
- [ ] Build the exact committed `2.0.0` source into the ZIP that will be
      submitted. Do not submit a ZIP built from a dirty worktree.

## Store copy and service disclosure

- [ ] Review the short description, long description, FAQs, installation
      steps, upgrade notice, and changelog in `readme.txt` as public store
      copy. Keep it clear that this plugin adds newsletter subscriptions to
      selected saved Jetpack forms; it does not replace Jetpack form
      notifications or WordPress mail delivery.
- [ ] Correct the EmailOctopus external-service disclosure. It must say both:
      - an administrator can cause requests to the EmailOctopus API while
        choosing, validating, or health-checking a configured destination; and
      - a visitor's email address and deliberately mapped fields are sent only
        when that visitor opts in through an eligible configured form.
- [ ] Clarify the credentials step: the API key is configured through the
      official EmailOctopus WordPress plugin's `emailoctopus_api_key` setting;
      this plugin does not provide its own API-key field. A destination is then
      selected in each integration profile.
- [ ] Check that service URLs, privacy-policy links, consent wording, and the
      public description accurately match the final runtime behaviour. Site
      owners remain responsible for their own privacy notices and consent.
- [ ] Keep contributor usernames, five-or-fewer relevant tags, WordPress/PHP
      requirements, licence, and tested-up-to metadata valid for the final
      submission.

## Directory artwork and screenshots

Directory assets belong in `wordpress-org/assets/` locally and later in the
separate WordPress.org SVN `/assets` directory. They must not be packaged in
the plugin ZIP.

- [x] RAN icon and banner source files exist:
      `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, and
      `banner-1544x500.png`.
- [ ] Approve the final listing copy and artwork for ownership, legibility,
      final plugin name/slug, and EmailOctopus/Jetpack trademark treatment.
- [ ] Create `screenshot-1.png`: the integrations overview, showing independent
      profiles, assigned saved forms, and routing status without secrets,
      personal data, or unapproved provider branding.
- [ ] Create `screenshot-2.png`: the profile editor, showing saved-form
      assignment and profile-specific destination choices without secrets or
      personal data.
- [ ] Review both screenshots at their final size and ensure their numbering
      and captions match the `== Screenshots ==` section in `readme.txt`.

## Translation readiness

- [x] The plugin uses the `ran-emailoctopus-jetpack-forms` text domain and has
      a committed POT catalogue at
      `languages/ran-emailoctopus-jetpack-forms.pot`.
- [ ] After all final public-copy and UI changes, run:

      ```sh
      pnpm make-pot
      composer run phpcs -- --sniffs=WordPress.WP.I18n
      ```

- [ ] Review and commit any intentional POT update. Do not add `.po` or `.mo`
      files unless they are reviewed release-quality translations; after
      approval, WordPress.org can provide language packs through
      translate.wordpress.org.

## Release proof

- [ ] From a clean checkout, install the locked dependencies and run the
      project checks, PHP tests, generated-file check, deterministic release
      verification, and the supported WordPress/Jetpack compatibility lanes.
- [ ] Build the distributable with the repository release command. Confirm it
      contains only `release-contents.txt` allowlisted files and excludes
      `wordpress-org/assets/`, repository plans, tests, development tooling,
      and deprecated `ran_octopus_forms` / `ran_forms_settings` identifiers.
- [ ] Run the current WordPress Plugin Check on the unpacked final ZIP.
- [ ] Install and activate that ZIP in a clean WordPress installation with
      Jetpack active. Smoke-test zero-profile state, two-stage profile setup,
      assigned and adjacent unassigned forms, profile-specific outcome pages,
      conflict-safe saves, spam/trash rejection, and native Jetpack
      notifications.
- [ ] Validate a real or sandbox EmailOctopus opt-in with approved credentials
      before making final public claims about provider behaviour.

## Submission and first WordPress.org deploy

- [ ] Confirm the WordPress.org account, monitored submission email, final
      directory slug `ran-emailoctopus-jetpack-forms`, support ownership, and
      trademark permissions. A GitHub repository name does not reserve a
      WordPress.org directory slug.
- [ ] Submit the complete final ZIP for WordPress.org review. Do not pre-create
      a partial listing or upload development files.
- [ ] After approval, commit the reviewed source to WordPress.org SVN `trunk`.
- [ ] Copy those exact contents to WordPress.org SVN `tags/2.0.0`.
- [ ] Upload only approved banners, icons, and screenshots to WordPress.org SVN
      `/assets`.
- [ ] Verify the rendered directory page, install flow, screenshots,
      translations tab, support links, and downloaded ZIP after propagation.

## After publication

- [ ] Record the WordPress.org URL, SVN revision, submitted ZIP checksum, and
      any reviewer feedback in the release handoff or GitHub release notes.
- [ ] Treat future WordPress.org releases as a separate deployment step after
      the corresponding GitHub/Release Please release is final.
