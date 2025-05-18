# Organization Folders

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
