<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010200Date20260404140000 extends SimpleMigrationStep {
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

		$table = $schema->getTable(self::FOLDER_RESOURCES_TABLE);
		
		/* Drop columns copied into RESOURCES_TABLE by Version010200Date20260402133000 */
		$table->dropColumn('members_acl_permission');
		$table->dropColumn('managers_acl_permission');
		$table->dropColumn('inherited_acl_permission');

		return $schema;
	}
}