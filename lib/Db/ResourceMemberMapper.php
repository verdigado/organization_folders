<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\PrincipalFactory;

class ResourceMemberMapper extends QBMapper {
	public const RESOURCES_TABLE = "organizationfolders_resources";
	public const RESOURCE_MEMBERS_TABLE = "organizationfolders_resource_members";

	public function __construct(
		protected PrincipalFactory $principalFactory,
		IDBConnection $db,
	) {
		parent::__construct($db, self::RESOURCE_MEMBERS_TABLE, ResourceMember::class);
	}

	/**
	 *
	 * @param array $row the row which should be converted to an entity
	 * @return ResourceMember the entity
	 * @psalm-return ResourceMember the entity
	 */
	protected function mapRowToEntity(array $row): ResourceMember {
		$resourceMember = new ResourceMember();

		$resourceMember->setId($row["id"]);
		$resourceMember->setResourceId($row["resource_id"]);
		$resourceMember->setPermissionLevel($row["permission_level"]);

		$principalType = PrincipalType::from($row["principal_type"]);
		$principal = $this->principalFactory->buildPrincipal($principalType, $row["principal_id"]);
		$resourceMember->setPrincipal($principal);

		$resourceMember->setCreatedTimestamp($row["created_timestamp"]);
		$resourceMember->setLastUpdatedTimestamp($row["last_updated_timestamp"]);

		$resourceMember->resetUpdatedFields();

		return $resourceMember;
	}

	/**
	 * @param int $id
	 * @return Entity|ResourceMember
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): ResourceMember {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		
		return $this->findEntity($qb);
	}

	/**
	 * @param int $resourceId
	 * @param int $principalType
	 * @param string $principalId
	 * @return Entity|ResourceMember
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function findByPrincipal(int $resourceId, int $principalType, string $principalId): ResourceMember {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)));
		
		return $this->findEntity($qb);
	}

	/**
	 * @param int $resourceId
	 * @param array{permissionLevel: int, principalType: int} $filters
	 * @return array
	 * @psalm-return ResourceMember[]
	 */
	public function findAll(int $resourceId, array $filters = []): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId, IQueryBuilder::PARAM_INT)));

		if(isset($filters["permissionLevel"])) {
			$qb->andWhere($qb->expr()->eq('permission_level', $qb->createNamedParameter($filters["permissionLevel"], IQueryBuilder::PARAM_INT)));
		}

		if(isset($filters["principalType"])) {
			$qb->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($filters["principalType"], IQueryBuilder::PARAM_INT)));
		}
		
		return $this->findEntities($qb);
	}

	/**
	 * @param int $organizationFolderId
	 * @param array{principalType: int} $filters
	 * @return array
	 * @psalm-return ResourceMember[]
	 */
	public function findAllTopLevelResourcesMembersOfOrganizationFolder(int $organizationFolderId, array $filters = []): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('member.*')
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		if(isset($filters["principalType"])) {
			$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter($filters["principalType"], IQueryBuilder::PARAM_INT)));
		}

		return $this->findEntities($qb);
	}

	public function countOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(DISTINCT `member`.`principal_id`)'))
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetch(\PDO::FETCH_NUM)[0];
	}

	public function hasOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId): bool {
		// This would be faster using EXISTS() and a subquery, but that does not seem possible with the QueryBuilder
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(1)'))
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetch()["COUNT(1)"] >= 1;
	}

	public function isUserIndividualMemberOfTopLevelResourceOfOrganizationFolder(int $organizationFolderId, string $userId): bool {
		// This would be faster using EXISTS() and a subquery, but that does not seem possible with the QueryBuilder
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(1)'))
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($qb->expr()->eq('member.principal_id', $qb->createNamedParameter($userId)));

		return $qb->executeQuery()->fetch()["COUNT(1)"] >= 1;
	}

	public function getIdsOfOrganizationFoldersUserIsTopLevelResourceIndividualMemberIn(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectDistinct("resource.organization_folder_id")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($qb->expr()->eq('member.principal_id', $qb->createNamedParameter($userId)));

		//return $qb->getSQL();
		return $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	public function getIdsOfOrganizationFoldersWithTopLevelResourceIndividualMembers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectDistinct("resource.organization_folder_id")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	public function getUserIdsOfOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId, ?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectDistinct("member.principal_id")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));

		$qb->setMaxResults($limit);
		$qb->setFirstResult($offset);

		return $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	public function exists(int $resourceId, int $principalType, string $principalId): bool {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(1)'))
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)));

		return $qb->executeQuery()->fetch()["COUNT(1)"] === 1;
	}
}