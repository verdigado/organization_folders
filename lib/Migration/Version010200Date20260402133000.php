<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use Psr\Log\LoggerInterface;

class Version010200Date20260402133000 extends SimpleMigrationStep {
	public const RESOURCES_TABLE = "organizationfolders_resources";
	public const FOLDER_RESOURCES_TABLE = "organizationfolders_folder_resources";

	public function __construct(
		private readonly IDBConnection $db,
	) {}

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

		$table->addColumn('member_permissions_bitfield', Types::INTEGER, [
			'length' => 11,
			'notnull' => true,
		]);
		$table->addColumn('manager_permissions_bitfield', Types::INTEGER, [
			'length' => 11,
			'notnull' => true,
		]);
		$table->addColumn('inherited_member_permissions_bitfield', Types::INTEGER, [
			'length' => 11,
			'notnull' => true,
		]);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$databaseProvider = $this->db->getDatabaseProvider();

		if($databaseProvider === IDBConnection::PLATFORM_MYSQL || $databaseProvider === IDBConnection::PLATFORM_MARIADB) {
			$sql = <<<'SQL'
			UPDATE `*PREFIX*organizationfolders_resources` AS `resource`
			INNER JOIN `*PREFIX*organizationfolders_folder_resources` AS `folder_resource`
				ON `resource`.`id` = `folder_resource`.`resource_id`
			SET
				`resource`.`member_permissions_bitfield` = `folder_resource`.`members_acl_permission`,
				`resource`.`manager_permissions_bitfield` = `folder_resource`.`managers_acl_permission`,
				`resource`.`inherited_member_permissions_bitfield` = `folder_resource`.`inherited_acl_permission`
			WHERE
				`resource`.`type` = 'folder';
			SQL;
		} else if($databaseProvider === IDBConnection::PLATFORM_POSTGRES || $databaseProvider === IDBConnection::PLATFORM_SQLITE) {
			$sql = <<<'SQL'
			UPDATE "*PREFIX*organizationfolders_resources" AS "resource"
			SET
				"member_permissions_bitfield" = "folder_resource"."members_acl_permission",
				"manager_permissions_bitfield" = "folder_resource"."managers_acl_permission",
				"inherited_member_permissions_bitfield" = "folder_resource"."inherited_acl_permission"
			FROM "*PREFIX*organizationfolders_folder_resources" AS "folder_resource"
			WHERE
				"resource"."id" = "folder_resource"."resource_id"
				AND "resource"."type" = 'folder';
			SQL;
		} else {
			throw new \Exception("Unsupported database provider: " . $databaseProvider);
		}

		$query = $this->db->prepare($sql);
		$query->execute();
	}
}