# Changelog

The format of this file is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2026-10-13

### Added
- Added support for **Nextcloud v33**/Team Folders v21
- Added support for a second type of resource: **Calendars**
  - Calendar resources are part of the same graph as folder resources and can inherit their permissions from parent folder resources. Calendars can not parents of other resources.
  - The creation of link shares to calendar resources is supported
  - Since there is currently no groupfolders equivalent for calendars this requires setting up a service-account where the calendars are stored and configuring the organization folder to use that account though occ. You can use one service account for all organization folders or set up a separate user for every organization folder.
  - You can import existing calendars by exporting them as an .ics file and importing that into a new organization-folder managed calendar resource

### Removed
- Removed support for Nextcloud v32
- Removed support for SQLite
  - The limited support for foreign keys/the missing support for altering foreign keys in SQLite causes DBAL to recreate tables instead of altering them in place in the migration to v1.2, which causes data loss. This cannot be fixed in organization_folders, it would require changes to SQLite.

### Changed
- **Big performance improvements**
  - The entire API Permissions subsystem was rewritten to be more performant and extensible
- The output of multiple occ commands was improved
- Translations were improved (thanks to the Nextcloud translation community on Transifex and @NohanV)

## [1.1.1] - 2026-02-10

### Added
- Added support for Nextcloud v32/Team Folders v20

### Removed
- Removed support for Nextcloud v30 and v31

### Fixed
- Fixed bug causing managers of organization folder to not qualify for member inheritance into top level resources

### Changed
- Translations were updated (thanks to the Nextcloud translation community ❤️)
- Dependencies were updated

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
