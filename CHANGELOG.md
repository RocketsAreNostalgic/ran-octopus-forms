# Changelog

## [1.1.0](https://github.com/RocketsAreNostalgic/ran-octopus-forms/compare/v1.0.0...v1.1.0) (2026-07-17)


### Features

* add newsletter outcome messages ([596499c](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/596499c3ae034d1f2ad5d7047ebac48b76585a12))
* add pre-commit hook for translation template validation ([a2b6a84](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/a2b6a84cd4463bb55147121ca1c8b80a19711fbd))
* implement release automation and update workflow scripts ([ba59b2f](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/ba59b2fd0f90f61bcdfdafb9dc7f8eaee188d404))


### Bug Fixes

* configure hooks during dependency install ([843e27a](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/843e27ab5026de0dea9b9ff32b5888b4be55887f))
* correct prepare script to use pnpm run for hooks installation ([3dce7a6](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/3dce7a6bb0707d021fb30b56bcb43a981d94af45))
* make newsletter and email source mappings explicit ([4499421](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/44994210da0c1bc51750c52be950fde5932c2448))
* provide release please token ([f8a30e4](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/f8a30e46926d579b36cb08dcf5d27d34618811c0))


### Miscellaneous Chores

* add project agent workflow ([524ac6c](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/524ac6cd49497787750070551acde4ffb7203542))
* **ci:** restore release please v4 ([95fa0d3](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/95fa0d35172b60bab3c7e2524897982886567b45))
* complete Octopus Forms release preparation ([25ff0b0](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/25ff0b0424491e60e4ef6ded16298ef715291817))
* standardize checks and release automation ([6390a83](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/6390a833f8aefeb53b07b895f90dbe21ce025b7c))
* update release please action ([f4a9d26](https://github.com/RocketsAreNostalgic/ran-octopus-forms/commit/f4a9d2673b07082df07db8dcdb71bac97228d906))

## 1.0.0

- First public release for WordPress 6.5+ and PHP 8.0+.
- Added an explicit RAN marker so integrations apply only to the intended
  Jetpack form.
- Added WordPress.org metadata, translations, release validation and quality
  tooling.
- Removed legacy site-path defaults for new installations.
