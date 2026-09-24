<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010201Date20260923190000 extends SimpleMigrationStep {
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

		$table = $schema->getTable(self::CALENDAR_RESOURCES_TABLE);
		
		if ($table->hasIndex('organizationfolders_calendar_resources_calendar_id_index')) {
			$table->dropIndex('organizationfolders_calendar_resources_calendar_id_index');
		}
		$table->addUniqueIndex(['calendar_id'], 'organizationfolders_calendar_resources_calendar_id_index');

		return $schema;
	}
}

