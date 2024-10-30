<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\OrganizationFolders\Errors\InvalidResourceType;

class ResourceMapper extends QBMapper {
    use TTransactional;

	public const RESOURCES_TABLE = "organizationfolders_resources";
	public const FOLDER_RESOURCES_TABLE = "organizationfolders_folder_resources";

    private const updateableResourceProperties = ["parentResource", "active", "lastUpdatedTimestamp"];
    private const updateableFolderResourceProperties = ["membersAclPermission", "managersAclPermission", "inheritedAclPermission"];

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

		$qb->select('resource.*', 'folder.members_acl_permission', 'folder.managers_acl_permission', 'folder.inherited_acl_permission')
			->from(self::RESOURCES_TABLE, "resource")
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        $qb->leftJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $qb->expr()->eq('resource.id', 'folder.resource_id'),);

        return $this->findEntity($qb);
	}

    /**
	 * @param int $organizationFolderId
     * @param int $parentResourceId
	 * @return array
	 */
	public function findAll(int $organizationFolderId, ?int $parentResourceId = null): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('resource.*', 'folder.members_acl_permission', 'folder.managers_acl_permission', 'folder.inherited_acl_permission')
			->from(self::RESOURCES_TABLE, "resource")
            ->where($qb->expr()->eq('organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

        if(is_null($parentResourceId)) {
            $qb->andWhere($qb->expr()->isNull('parent_resource'));
        } else {
            $qb->andWhere($qb->expr()->eq('parent_resource', $qb->createNamedParameter($parentResourceId, IQueryBuilder::PARAM_INT)));
        }

        $qb->leftJoin('resource', self::FOLDER_RESOURCES_TABLE, 'folder', $qb->expr()->eq('resource.id', 'folder.resource_id'),);

        return $this->findEntities($qb);
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
        $setResourceProperties = array_intersect(["id", ...self::updateableResourceProperties], $setProperties);

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