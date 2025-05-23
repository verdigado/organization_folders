![Development stage: beta](https://img.shields.io/badge/development%20stage-beta-blue)
[![Software License](https://img.shields.io/badge/license-AGPL-brightgreen.svg)](LICENSE)

# Organization Folders

Organization Folders are Team Folders (formerly Group Folders) designed for large organizations, with permissions managed via an intuitive web interface.

> [!NOTE]  
> This app is currently released as a beta.
> 
> While it is already deployed on a production system and considered stable, it is not yet feature-complete.
> Depending on your use case, you may want to wait for the first stable release before deploying it. A seamless upgrade path to stable versions is guaranteed.

## Features
  - ✨ No need to create any ACL rules manually anymore
  - :closed_lock_with_key: Fine-grained management rights delegation support
  - :wrench: Management in the web interface and using occ commands
  - :office: Support for adding your organizations structure/hierarchy, to allow roles within them to be picked in a structured and intuitive way

## Screenshots

![Organization Folder management UI](https://raw.githubusercontent.com/verdigado/organization_folders/main/screenshots/1.png)
![Resource management UI](https://raw.githubusercontent.com/verdigado/organization_folders/main/screenshots/2.png)
![Folder Resources in files app](https://raw.githubusercontent.com/verdigado/organization_folders/main/screenshots/3.png)

## How it works
  - Organization Folders are Team Folders managed by this app
  - Within Organization Folders, there are Resources
    - Currently the only type of resource is folders, but there may be others in the future (like calendars)
  - Resources can be nested within other folder resources (unless that feaure is disabled, see [here](#config-options))
  - Organization Folders have Members
    - These are the groups and roles (more on that later), that can can see the folder
    - Each of them has a permission level (Member, Manager or Admin)
  - Resources have Members too
    - These are groups, roles or individual users with specific rights in that resource
    - Each of them has a permission level (Member or Manager)
    - Managers can change the settings of the resource
    - In each resource you can choose if managers from the level above (again for top level resources that is the organization folder, otherwise it's the parent resource) should be inherited and also have management access to the resource
      - Admin members in the organization folder are not subject to this inheritance setting, they have full management rights within all resources of the Organization Folder
    - Folder/File rights:
      - For resources of the type folder you can choose for each permission level the rights people within them should have inside that folder: Read, Write, Create, Delete and Share
      - Additionally you can choose which rights people with at least read access to the folder level above (for top level resources that is the organization folder, otherwise it's the parent resource) should have within the folder
      - This permissions model is intentionally limited compared to raw ACLs, to make it easy to understand the current permissions configuration and easy to ensure at a glance, that the permissions are correctly configured.
      - We believe this permissions model still allows you to configure most if not all permissions structures commonly used in groupfolders, while being much simpler to use than ACLs
    - You can create regular folders within folder resources (not at the top-level of an organization folder though), these are called "unmanaged" folders, because all file rights for them are inherited from the nearest parent resource
  - The system that gives Organization Folders it's name: Organizations and Suborganizations allow you to model your entire organizations hierarchy/structure (perfect for highly distributed organizations like political parties with local chapters)
    - Each (sub)organization can have Roles
    - Roles are assigned to users, if they are assigned to a specific function or have a certain permission in that organization
    - Users have a role, if they are assigned to the specific nextcloud group it is backed by
    - The management of these role assignments is currently out of scope of this app.
      It is expected, that you connect your nextcloud instance to your organization members database (for example using https://github.com/nextcloud/user_saml/ or a custom group backend) or are manually assigning users to nextcloud groups
    - The structure of your organization must be provided to this app using a programmatic interface, by creating a small companion app, that registers itself as an organization provider. It can pull data for your organizations member database or just hardcoded values.
    - The usage of this system is entirely optional. The app works fine without any registered organization provider. But all members will then be individual users or regular nextcloud groups, which are unstructured and therefore not easy to work with in very large organizations.
  - If you use a filesystem with snapshot capabilities, Organization Folders can be integrated with it to offer a self-service restore-from-backups UI to folder resource managers. (This function is currently still WIP)

## How to install
- Install the [Team folders](https://apps.nextcloud.com/apps/groupfolders) app from the nextcloud app store
- Install the [Groupfolder Tags](https://apps.nextcloud.com/apps/groupfolder_tags) app from the nextcloud app store
- (OPTIONAL) Install and configure the [Groupfolder Filesystem Snapshots](https://apps.nextcloud.com/apps/groupfolder_filesystem_snapshots) app from the nextcloud app store
- Install this app from the nextcloud app store

## How to Use
1. Open the files app.
2. If you are a Nextcloud admin, you will see a management button above your file list in the home folder.
4. Click the management button to open the Organization Folder management modal.
5. In the modal, you can create your first Organization Folder.
   
   ⚠️ **Note:** This feature is not yet available in the beta release. To create Organization Folders in the beta, you must use the organization-folders:create occ command.
7. Once created, you can add members and resources to the Organization Folder.
8. When navigating to a folder whose permissions are managed by this app, the management button will also appear — if you are a Nextcloud admin or have management permissions for that Organization Folder or Resource.
9. Use the modal to configure the Resource permissions as needed.

## Config options
- Per default sub-resources are enabled. To disable the ability of users to create nested resources run:
  ```shell
  occ config:app:set --type boolean --value false organization_folders subresources_enabled
  ```

- By default the groups from the group backend of this app (named "ORGANIZATION_FOLDER_*"), that are used to invite individual users to groupfolders can also be selected by users like regular groups (for example when sharing a file). This is probably unwanted and you can hide them from users with this setting.
  ```shell
  occ config:app:set --type boolean --value true organization_folders hide_virtual_groups
  ```
  ATTENTION: This intentionally makes the group backend behave in a way that is non-conformant in order for the groups to still be useable by groupfolders, but not searchable by users. If this causes any issues for your instance turn this setting off again (but no such issues are currently known to the developers).
