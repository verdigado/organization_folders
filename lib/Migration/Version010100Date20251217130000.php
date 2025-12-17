<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010100Date20251217130000 extends SimpleMigrationStep {
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

		if(!$table->hasColumn("created_from_template_id")) {
			$table->addColumn('created_from_template_id', Types::STRING, [
				'length' => 128,
				'notnull' => false,
				'default' => null,
			]);
		}

		// A common template isAvailable() check will be to enforce only one use of the template per organization folder, so make that query efficient
		$table->addIndex(['organization_folder_id', 'created_from_template_id'], 'organizationfolders_resources_template_index');

		return $schema;
	}
}