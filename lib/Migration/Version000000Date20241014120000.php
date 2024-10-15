<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20241014120000 extends SimpleMigrationStep {
	public const GROUP_FOLDERS_TABLE = "group_folders";
	public const RESOURCES_TABLE = "organizationfolders_resources";
	public const FOLDER_RESOURCES_TABLE = "organizationfolders_folder_resources";

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(self::RESOURCES_TABLE)) {
			$table = $schema->createTable(self::RESOURCES_TABLE);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('parent_resource', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('group_folder_id', Types::BIGINT, [
				'length' => 20,
				'notnull' => true,
			]);
			$table->addColumn('type', Types::STRING, [
				'length' => 20,
				'notnull' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'length' => 64,
				'notnull' => true,
			]);
			$table->addColumn('active', Types::BOOLEAN, [
				'notnull' => true,
			]);
			$table->addColumn('last_updated_timestamp', Types::BIGINT, [
				'notnull' => true,
			]);
			
			$table->setPrimaryKey(['id']);
			$table->addForeignKeyConstraint(
				$table,
				['parent_resource'],
				['id'],
				['onDelete' => 'CASCADE'],
				'organizationfolders_resources_parent_resource_id_fk');
			$table->addForeignKeyConstraint(
				$schema->getTable(self::GROUP_FOLDERS_TABLE),
				['group_folder_id'],
				['folder_id'],
				['onDelete' => 'CASCADE'],
				'organizationfolders_resources_group_folder_id_fk');
		}

		if (!$schema->hasTable(self::FOLDER_RESOURCES_TABLE)) {
			$table = $schema->createTable(self::FOLDER_RESOURCES_TABLE);
			$table->addColumn('resource_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('members_acl_permission', Types::INTEGER, [
                'length' => 11,
				'notnull' => true,
			]);
			$table->addColumn('managers_acl_permission', Types::INTEGER, [
                'length' => 11,
				'notnull' => true,
			]);
			$table->addColumn('inherited_acl_permission', Types::INTEGER, [
                'length' => 11,
				'notnull' => true,
			]);

			$table->setPrimaryKey(['resource_id']);
			$table->addForeignKeyConstraint(
				$schema->getTable(self::RESOURCES_TABLE),
				['resource_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'organizationfolders_folder_resources_resource_id_fk');
		}

		return $schema;
	}
}