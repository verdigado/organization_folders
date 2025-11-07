# Changelog

The format of this file is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-11-07

### Added
- The app now emits `beforeCreate`, `beforeUpdate`, and `beforeDelete` cancellable lifecycle events for ResourceMembers, making it possible for other apps to enforce additional rules for the instance. More such events for other entities will be added in the future.
- Warnings about the permissions configuration are now not only shown in the overall permissions report, but also in the individual user permissions report

### Changed
- Translations were updated (thanks to the Nextcloud translation community ❤️)

### Fixed
- Fixed a bug where ResourceService was erroring out too late when mandatory parameters were missing leading to folder resources being half-created
