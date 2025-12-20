# Changelog

The format of this file is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-12-20

### Added
- Added resource templating sub-system
  - Other apps can register themselves as template providers
    - First inter-app-integration interface of Organization Folders with guaranteed API stability (OCA\OrganizationFolders\Public namespace) (breaking changes only in major version updates); other APIs to follow
  - Resources created from a template start out with the template's values, but can be edited as usual
  - Currently available through occ commands; integration into the web interface will be added in future updates
 
### Changed
- Organization provider sub-system
  - Added IListAllOrganizations capability
    - Optional interface that OrganizationProviders can implement to allow recursive querying of all organizations at once
  - Providers are now allowed to extend the Organization and OrganizationRole model classes
- Translations were updated (thanks to the Nextcloud translation community ❤️)

### Fixed
- Properly showing in frontend if organization assigned to organization folder no longer exists
- Warnings now appear not only in the overall permissions report but also in the individual user report too

## [1.0.1] - 2025-11-07

### Added
- The app now emits `beforeCreate`, `beforeUpdate`, and `beforeDelete` cancellable lifecycle events for ResourceMembers, making it possible for other apps to enforce additional rules for the instance. More such events for other entities will be added in the future.
- Warnings about the permissions configuration are now not only shown in the overall permissions report, but also in the individual user permissions report

### Changed
- Translations were updated (thanks to the Nextcloud translation community ❤️)

### Fixed
- Fixed a bug where ResourceService was erroring out too late when mandatory parameters were missing leading to folder resources being half-created
