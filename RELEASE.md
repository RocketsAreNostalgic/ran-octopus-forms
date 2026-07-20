# Release and WordPress.org submission checklist

GitHub is the development source. Before a manual directory submission:

1. Confirm the final plugin slug, WordPress.org contributor account and
   trademark/service permissions.
2. Run `pnpm check`, `pnpm run check:generated`, `composer run phpcs`, and
   `pnpm run release:verify`.
3. Run `pnpm run release:assets` to produce the canonical ZIP, SHA-256 file,
   and JSON manifest from `release-contents.txt`.
4. Install and activate that ZIP in a clean WordPress installation with Jetpack
   active, then run Plugin Check against the unpacked release.
5. Copy the reviewed source to WordPress.org SVN `trunk`, copy that exact
   release to `tags/<version>`, and upload approved directory assets separately
   to `/assets`.
6. Confirm `readme.txt` stable tag, main plugin header version, runtime version
   constant, package metadata, POT project version, and SVN tag all use the same
   `<version>`; do not submit until they agree.

The repository's `wordpress-org/assets/` folder is intentionally separate from
the release ZIP, matching the WordPress.org SVN layout. Routine deployment is
disabled until `wordpress-org/deployment.json` is switched on and the real
WordPress.org slug is assigned.
