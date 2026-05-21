<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010200Date20260413140300 extends SimpleMigrationStep {
	private const RESOURCES_TABLE = "organizationfolders_resources";
	private const CALENDAR_RESOURCES_TABLE = "organizationfolders_calendar_resources";

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(self::CALENDAR_RESOURCES_TABLE)) {
			$table = $schema->createTable(self::CALENDAR_RESOURCES_TABLE);
			$table->addColumn('resource_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('calendar_id', Types::BIGINT, [
				'notnull' => true,
                'length' => 11,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['resource_id']);
			$table->addForeignKeyConstraint(
				$schema->getTable(self::RESOURCES_TABLE),
				['resource_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'organizationfolders_calendar_resources_resource_id_fk');
			$table->addIndex(['calendar_id'], 'organizationfolders_calendar_resources_calendar_id_index');
		}

		return $schema;
	}
}