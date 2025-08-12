<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\OrganizationFolders\Errors\Api\InvalidResourceType;

class ResourceMapper extends QBMapper {
	use TTransactional;

	public const RESOURCES_TABLE = "organizationfolders_resources";
	public const FOLDER_RESOURCES_TABLE = "organizationfolders_folder_resources";

	private const updateableResourceProperties = ["parentResource", "active", "name", "inheritManagers", "lastUpdatedTimestamp"];
	private const updateableFolderResourceProperties = ["membersAclPermission", "managersAclPermission", "inheritedAclPermission", "fileId"];
	private const tableColumnsToSelect = ['resource.*', 'folder.members_acl_permission', 'folder.managers_acl_permission', 'folder.inherited_acl_permission', 'folder.file_id'];

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::RESOURCES_TABLE, Resource::class);
	}

	protected function mapRowToEntity(array $row): Resource {
		if($row["type"] == "folder") {
			return FolderResource::fromRow($row);
		} else {
			throw new InvalidResourceType($row["type"]);
		}
	}

	/**
	 * @param int $id
	 * @return Entity|Resource
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Resource {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select(self::tableColumnsToSelect)
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$qb->leftJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $qb->expr()->eq('resource.id', 'folder.resource_id'),);

		return $this->findEntity($qb);
	}

	public function findByFileId(int $fileId): FolderResource {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select(self::tableColumnsToSelect)
			->from(self::RESOURCES_TABLE, "resource");

		$qb->innerJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $qb->expr()->eq('resource.id', 'folder.resource_id'),);

		$qb->where($qb->expr()->eq('folder.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function findByName(int $organizationFolderId, ?int $parentResourceId, string $name) {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select(self::tableColumnsToSelect)
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

		if(is_null($parentResourceId)) {
			$qb->andWhere($qb->expr()->isNull('resource.parent_resource'));
		} else {
			$qb->andWhere($qb->expr()->eq('resource.parent_resource', $qb->createNamedParameter($parentResourceId, IQueryBuilder::PARAM_INT)));
		}

		$qb->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)));

		$qb->leftJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $qb->expr()->eq('resource.id', 'folder.resource_id'),);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $organizationFolderId
	 * @psalm-param int $organizationFolderId
	 * @param int|null $parentResourceId
	 * @psalm-param int|null $parentResourceId
	 * @param array $filters
	 * @psalm-param array $filters
	 * @return array
	 * @psalm-return Resource[]
	 */
	public function findAll(int $organizationFolderId, ?int $parentResourceId = null, array $filters = []): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('resource.*', 'folder.members_acl_permission', 'folder.managers_acl_permission', 'folder.inherited_acl_permission', 'folder.file_id')
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

		if(is_null($parentResourceId)) {
			$qb->andWhere($qb->expr()->isNull('resource.parent_resource'));
		} else {
			$qb->andWhere($qb->expr()->eq('resource.parent_resource', $qb->createNamedParameter($parentResourceId, IQueryBuilder::PARAM_INT)));
		}

		$folderJoinCondition = $qb->expr()->eq('resource.id', 'folder.resource_id');
		if(isset($filters["type"]) && $filters["type"] === "folder") {
			$qb->andWhere($qb->expr()->eq('resource.type', $qb->createNamedParameter("folder")));
			$qb->innerJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $folderJoinCondition);
		} else {
			$qb->leftJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $folderJoinCondition);
		}

		return $this->findEntities($qb);
	}

	/**
	 * @param int $organizationFolderId
	 * @psalm-param int $organizationFolderId
	 * @param int|null $parentResourceId
	 * @psalm-param int|null $parentResourceId
	 * @param array $filters
	 * @psalm-param array $filters
	 * @return array
	 * @psalm-return string[]
	 */
	public function findAllNames(int $organizationFolderId, ?int $parentResourceId = null, array $filters = []): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('resource.name')
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

		if(is_null($parentResourceId)) {
			$qb->andWhere($qb->expr()->isNull('resource.parent_resource'));
		} else {
			$qb->andWhere($qb->expr()->eq('resource.parent_resource', $qb->createNamedParameter($parentResourceId, IQueryBuilder::PARAM_INT)));
		}

		if(isset($filters["type"]) && $filters["type"] === "folder") {
			$qb->andWhere($qb->expr()->eq('resource.type', $qb->createNamedParameter("folder")));
			$qb->innerJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $qb->expr()->eq('resource.id', 'folder.resource_id'));
		}

		$qb->orderBy('resource.name');

		return $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	public function existsWithName(int $organizationFolderId, ?int $parentResourceId, string $name): bool {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(1)'))
			->from(self::RESOURCES_TABLE)
			->where($qb->expr()->eq('organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

		if(is_null($parentResourceId)) {
			$qb->andWhere($qb->expr()->isNull('parent_resource'));
		} else {
			$qb->andWhere($qb->expr()->eq('parent_resource', $qb->createNamedParameter($parentResourceId, IQueryBuilder::PARAM_INT)));
		}

		$qb->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)));

		return $qb->executeQuery()->fetch()["COUNT(1)"] === 1;
	}

	/**
	 * Creates a new entry in the db from an entity
	 *
	 * @param Resource $entity the entity that should be created
	 * @psalm-param Resource $entity the entity that should be created
	 * @return Resource the saved entity with the set id
	 * @psalm-return Resource the saved entity with the set id
	 * @throws Exception
	 * @since 14.0.0
	 */
	public function insert(Entity $entity): Entity {
		$setProperties = array_keys($entity->getUpdatedFields());
		$setResourceProperties = ["type", "organizationFolderId", ...array_intersect(["id", ...self::updateableResourceProperties], $setProperties)];

		if($entity->getType() === "folder") {
			$typeSpecificTable = self::FOLDER_RESOURCES_TABLE;
			$setTypeSpecificProperties = array_intersect(self::updateableFolderResourceProperties, $setProperties);
		} else {
			throw new InvalidResourceType($entity->getType());
		}
		
		
		$setProperties = function ($qb, $properties) use ($entity) {
			foreach ($properties as $property) {
				$column = $entity->propertyToColumn($property);
				$getter = 'get' . ucfirst($property);
				$value = $entity->$getter();
	
				$type = $this->getParameterTypeForProperty($entity, $property);
				$qb->setValue($column, $qb->createNamedParameter($value, $type));
			}
		};

		$this->atomic(function () use ($setProperties, $setResourceProperties, $entity, $typeSpecificTable, $setTypeSpecificProperties) {
			// insert into resource table
			$qb = $this->db->getQueryBuilder();
			$qb->insert(self::RESOURCES_TABLE);

			$setProperties($qb, $setResourceProperties);

			$qb->executeStatement();

			if ($entity->id === null) {
				// When autoincrement is used id is always an int
				$entity->setId($qb->getLastInsertId());
			}
			
			// insert into the type specific table
			$qb = $this->db->getQueryBuilder();
			$qb->insert($typeSpecificTable);

			$setProperties($qb, $setTypeSpecificProperties);
			$qb->setValue("resource_id", $qb->createNamedParameter($entity->getId(), IQueryBuilder::PARAM_INT));

			$qb->executeStatement();
		}, $this->db);

		return $entity;
	}

	/**
	 * Updates an entry in the db from an entity
	 *
	 * @param Resource $entity the entity that should be created
	 * @psalm-param Resource $entity the entity that should be created
	 * @return Resource the saved entity with the set id
	 * @psalm-return Resource the saved entity with the set id
	 * @throws \Exception
	 * @throws \InvalidArgumentException if entity has no id
	 * @since 14.0.0
	 */
	public function update(Entity $entity): Entity {
		// entity needs an id
		$id = $entity->getId();
		if ($id === null) {
			throw new \InvalidArgumentException(
				'Entity which should be updated has no id');
		}

		$updatedProperties = array_keys($entity->getUpdatedFields());
		$updatedResourceProperties = array_intersect(self::updateableResourceProperties, $updatedProperties);

		if($entity->getType() === "folder") {
			$typeSpecificTable = self::FOLDER_RESOURCES_TABLE;
			$updatedTypeSpecificProperties = array_intersect(self::updateableFolderResourceProperties, $updatedProperties);
		} else {
			throw new InvalidResourceType($entity->getType());
		}

		$updatePropertiesInTables = function($table, $properties, $idColumn) use ($entity, $id) {
			// update resource properties if any changed
			if (\count($properties) === 0) {
				return;
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update($table);

			foreach ($properties as $property) {
				$column = $entity->propertyToColumn($property);
				$getter = 'get' . ucfirst($property);
				$value = $entity->$getter();

				$type = $this->getParameterTypeForProperty($entity, $property);
				$qb->set($column, $qb->createNamedParameter($value, $type));
			}

			$idType = $this->getParameterTypeForProperty($entity, 'id');

			$qb->where(
				$qb->expr()->eq($idColumn, $qb->createNamedParameter($id, $idType))
			);

			$qb->executeStatement();
		};

		$this->atomic(function () use ($updatePropertiesInTables, $updatedResourceProperties, $typeSpecificTable, $updatedTypeSpecificProperties) {
			$updatePropertiesInTables(self::RESOURCES_TABLE, $updatedResourceProperties, "id");
			$updatePropertiesInTables($typeSpecificTable, $updatedTypeSpecificProperties, "resource_id");
		}, $this->db);

		return $entity;
	}
}