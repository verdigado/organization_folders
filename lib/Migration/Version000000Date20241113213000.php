<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20241113213000 extends SimpleMigrationStep {
    public const GROUP_FOLDERS_TABLE = "group_folders";
	public const ORGANIZATIONFOLDER_MEMBERS_TABLE = "organizationfolders_members";

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(self::ORGANIZATIONFOLDER_MEMBERS_TABLE)) {
			$table = $schema->createTable(self::ORGANIZATIONFOLDER_MEMBERS_TABLE);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('organization_folder_id', Types::BIGINT, [
				'length' => 20,
				'notnull' => true,
			]);
			// 1: MEMBER
			// 2: MANAGER
			// 3: ADMIN
			$table->addColumn('permission_level', Types::INTEGER, [
				'notnull' => true,
			]);
			// 2: GROUP
			// 3: ROLE
			$table->addColumn('principal_type', Types::INTEGER, [
				'notnull' => true,
			]);
            // for principal type GROUP: "[group_name]"
            // for principal type ROLE: "[organization_provider_id]:[role_id]"
			$table->addColumn('principal_id', Types::STRING, [
                'length' => 128,
				'notnull' => true,
			]);
			$table->addColumn('created_timestamp', Types::BIGINT, [
				'notnull' => true,
			]);
            $table->addColumn('last_updated_timestamp', Types::BIGINT, [
				'notnull' => true,
			]);
			
			$table->setPrimaryKey(['id']);
			$table->addForeignKeyConstraint(
				$schema->getTable(self::GROUP_FOLDERS_TABLE),
				['organization_folder_id'],
				['folder_id'],
				['onDelete' => 'CASCADE'],
				'organizationfolders_members_organization_folder_id_fk');
            $table->addIndex(['organization_folder_id'], 'organizationfolders_members_organization_folder_id_index');
			$table->addUniqueConstraint(['organization_folder_id', 'principal_type', 'principal_id'], "organizationfolders_members_unique");
		}

		return $schema;
	}
}