<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000003Date20250627001000 extends SimpleMigrationStep {
	public const RESOURCES_TABLE = "organizationfolders_resources";

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable(self::RESOURCES_TABLE);

		if(!$table->hasColumn("created_timestamp")) {
			$table->addColumn('created_timestamp', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
		}

		return $schema;
	}
}