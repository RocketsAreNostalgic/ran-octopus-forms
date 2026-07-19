# Changelog

## [1.1.0](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/compare/v1.0.0...v1.1.0) (2026-07-19)


### Features

* add newsletter outcome messages ([596499c](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/596499c3ae034d1f2ad5d7047ebac48b76585a12))
* add pre-commit hook for translation template validation ([a2b6a84](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/a2b6a84cd4463bb55147121ca1c8b80a19711fbd))
* extract Turnstile and rebrand connector ([3fd4a41](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/3fd4a413edba300031deae3ac0a8c82925bd6730))
* implement release automation and update workflow scripts ([ba59b2f](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/ba59b2fd0f90f61bcdfdafb9dc7f8eaee188d404))


### Bug Fixes

* **ci:** install pnpm before enabling cache ([be10e2f](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/be10e2f8978fbacea364d2c62690bc43ac39106e))
* **ci:** pass MySQL host without port ([7e84a73](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/7e84a73ae1cdb504b8ca5d3e24f32ff2f18fff90))
* **ci:** pin Jetpack for WordPress 6.5 ([3ffc5c3](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/3ffc5c3542ee1cdda87127efa3c6239d99d349d2))
* configure hooks during dependency install ([843e27a](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/843e27ab5026de0dea9b9ff32b5888b4be55887f))
* correct prepare script to use pnpm run for hooks installation ([3dce7a6](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/3dce7a6bb0707d021fb30b56bcb43a981d94af45))
* **deps:** lock tooling for PHP 8.0 ([8e7f33c](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/8e7f33c9d7f7b0d83fb59eed0943bb4b2818d809))
* make newsletter and email source mappings explicit ([4499421](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/44994210da0c1bc51750c52be950fde5932c2448))
* prevent subscriptions from rejected feedback ([9a6d7e8](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/9a6d7e8b3366d39af94d0d4a41eb2e2e2ab6367f))
* provide release please token ([f8a30e4](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/f8a30e46926d579b36cb08dcf5d27d34618811c0))
* **release:** preserve readme metadata parsing ([79d9449](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/79d944938379deb29f7b683eb3280727b8e37bf8))


### Miscellaneous Chores

* add project agent workflow ([524ac6c](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/524ac6cd49497787750070551acde4ffb7203542))
* **ci:** restore release please v4 ([95fa0d3](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/95fa0d35172b60bab3c7e2524897982886567b45))
* complete Octopus Forms release preparation ([25ff0b0](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/25ff0b0424491e60e4ef6ded16298ef715291817))
* ignore generated changelog ([b9eae5f](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/b9eae5f6a0c4651cba3c59082ef8139ea15d3906))
* ignore generated changelog ([e94edde](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/e94edde3b2b6160092dfbce286b46142da3a3262))
* standardize checks and release automation ([6390a83](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/6390a833f8aefeb53b07b895f90dbe21ce025b7c))
* update release please action ([f4a9d26](https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms/commit/f4a9d2673b07082df07db8dcdb71bac97228d906))

## 1.0.0

- First public release for WordPress 6.8+ and PHP 8.0+.
- Added saved-form targeting so integrations apply only to the selected
  reusable Jetpack form across routes.
- Added WordPress.org metadata, translations, release validation and quality
  tooling.
- Kept routing independent of page paths and embedding routes.
