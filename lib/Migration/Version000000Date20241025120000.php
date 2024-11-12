<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20241025120000 extends SimpleMigrationStep {
	public const RESOURCES_TABLE = "organizationfolders_resources";

	public const RESOURCE_MEMBERS_TABLE = "organizationfolders_resource_members";

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(self::RESOURCE_MEMBERS_TABLE)) {
			$table = $schema->createTable(self::RESOURCE_MEMBERS_TABLE);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('resource_id', Types::INTEGER, [
				'notnull' => true,
			]);
            // 0: member
            // 1: manager
			$table->addColumn('permission_level', Types::INTEGER, [
				'notnull' => true,
			]);
            // 0: user
            // 1: group
            // 2: role
			$table->addColumn('principal_type', Types::INTEGER, [
				'notnull' => true,
			]);
            // for principal type user: "[user_id]"
            // for principal type group: "[group_name]"
            // for principal type role: "[organization_provider_id]:[role_id]"
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
				$schema->getTable(self::RESOURCES_TABLE),
				['resource_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'organizationfolders_resource_members_resource_id_fk');
            $table->addIndex(['resource_id'], 'organizationfolders_resource_members_resource_id_index');
			$table->addUniqueConstraint(['resource_id', 'type', 'principal'], "organizationfolders_resource_members_unique");
		}

		return $schema;
	}
}