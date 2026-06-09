<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010200Date20260609174400 extends SimpleMigrationStep {
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
		
		/* Modifies parent_resource foreign key constraint onDelete from CASCADE to RESTRICT by re-creating it */
		$table->removeForeignKey('organizationfolders_resources_parent_resource_id_fk');
		$table->addForeignKeyConstraint(
			$table,
			['parent_resource'],
			['id'],
			['onDelete' => 'RESTRICT'],
			'organizationfolders_resources_parent_resource_id_fk',
		);
		

		return $schema;
	}
}