# AGENTS.md

## Release automation

Use the global `$release-please` skill before configuring, changing, or
operating this repository's release workflow.

This is a standalone GitHub repository. Release Please should run from `main`
with a manifest-driven PHP release configuration. The WordPress plugin header
and runtime constant in `ran-octopus-forms.php`, `readme.txt` stable tag,
`package.json`, tracked POT project version, and `CHANGELOG.md` must agree
with the release version. The normal PHP strategy does not update those
WordPress-specific sources automatically; configure and test explicit
extra-file updates.

Do not enable automated releases until `scripts/build-release.sh` and the
quality workflow derive archive names from the plugin metadata rather than the
current hard-coded `1.0.0` value. Keep packaging or WordPress.org deployment
separate from Release Please.

Treat the existing initial-release preparation commit as the bootstrap
boundary, preserve version `1.0.0` in the initial manifest, and review the
first generated release PR before merging it.
