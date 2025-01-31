# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.3.0 - 2024-11-11
### Added
- Enable test coverage. [#39961]

### Changed
- General: indicate compatibility with the upcoming version of WordPress - 6.6. [#37962]
- General: indicate compatibility with the upcoming version of WordPress - 6.7. [#39786]
- Only include `wp-polyfill` as a script dependency when needed. [#39629]
- Updated package dependencies. [#38132]
- Updated package dependencies. [#38228]
- Updated package dependencies. [#38235]
- Updated package dependencies. [#38662]
- Updated package dependencies. [#38822]
- Updated package dependencies. [#39004]
- Updated package dependencies. [#39111]
- Updated package dependencies. [#39176]
- Updated package dependencies. [#39278]
- Updated package dependencies. [#39288]
- Updated package dependencies. [#39302]
- Updated package dependencies. [#39332]
- Updated package dependencies. [#39594]
- Updated package dependencies. [#39653]
- Updated package dependencies. [#39707]
- Updated package dependencies. [#39999]
- Updated package dependencies. [#40060]

### Removed
- Connection: Removed deprecated method features_available [#39442]
- Connection: Removed features_enabled deprecated method [#39475]
- General: update WordPress version requirements to WordPress 6.5. [#38382]

### Fixed
- Lossless image optimization of images in projects/plugins [subdirectories from a* through social] [#38573]

## 0.2.1 - 2024-06-12
### Changed
- Dashboard: switch to a smaller and faster dependency (`clsx`) to handle class names. [#37708]
- Dashboard: update the connection screen's messaging to make our Terms of Service clearer. [#37536]
- Dependencies: remove the 'jetpack-identity-crisis' dependency. [#36968]
- Dependencies: update multiple dependencies. [#37669], [#37767], [#37776], [#37796]

## 0.2.0 - 2024-05-28
### Added
- Add autotagger and autorelease to composer.json to enable auto-tagging and auto-release actions [#37143]

### Changed
- Updated package dependencies. [#37147]

### Removed
- Remove the Plugin Installer package dependency. [#37430]

## 0.1.0 - 2024-04-30
### Added
- Added connected state content and site disconnection flow. [#36747]
- Added connection card [#36664]
- Added details about sharing information with Automattic to the connection card. [#36729]
- Added reconnection flow to the A4A client plugin. [#37036]
- Add Woocommerce event remove_order_items to Jetpack Sync [#33748]
- General: add first version of the plugin's readme and assets. [#36796]
- General: add modal displayed when deactivating the plugin. [#36523]
- Initial commit for the plugin’s infrastructure. [#36353]
- Packages: add version tracking for identity-crisis package. [#36635]

### Changed
- General: update WordPress version requirements to WordPress 6.4. [#37047]
- General: use wp_admin_notice function introduced in WP 6.4 to display notices. [#37051]
- Updated details about sharing data with WordPress.com [#37039]
- Updated package dependencies. [#36585]
- Updated package dependencies. [#36760]
- Updated package dependencies. [#36775]
- Updated package dependencies. [#36788]

### Removed
- Removed the Jetpack-branded header and footer from the plugin. [#36930]

### Fixed
- Fix post-connection redirect URL. [#36960]
