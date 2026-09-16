<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Model\PrincipalFilter;
use OCP\DB\QueryBuilder\ICompositeExpression;

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
	 * @param array{
	 *   organizationFolderId: ?int,
	 *   resourceId: ?int,
	 *   permissionLevel: ?ResourceMemberPermissionLevel[],
	 *   principal: ?PrincipalFilter[]
	 * } $filters resourceId filter takes precedence over organizationFolderId filter
	 * @psalm-return ResourceMember[]
	 */
	public function findAll(array $filters = []): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('member.*')
			->from(self::RESOURCE_MEMBERS_TABLE, "member");

		if(isset($filters["resourceId"])) {
			$qb->where($qb->expr()->eq('member.resource_id', $qb->createNamedParameter($filters["resourceId"], IQueryBuilder::PARAM_INT)));
		} else if(isset($filters["organizationFolderId"])) {
			$qb->innerJoin("member", self::RESOURCES_TABLE, "resource", $qb->expr()->eq('member.resource_id', 'resource.id'));
			$qb->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($filters["organizationFolderId"], IQueryBuilder::PARAM_INT)));
		}

		if(isset($filters["permissionLevel"])) {
			$qb->andWhere($this->buildPermissionLevelDBFilter($qb, $filters["permissionLevel"]));
		}

		if(isset($filters["principal"])) {
			$qb->andWhere($this->buildPrincipalDBFilter($qb, $filters["principal"]));
		}
		
		return $this->findEntities($qb);
	}

	/**
	 * @param array{
	 *   organizationFolderId: ?int,
	 *   resourceId: ?int,
	 *   permissionLevel: ?ResourceMemberPermissionLevel[],
	 *   principal: ?PrincipalFilter[]
	 * } $filters resourceId filter takes precedence over organizationFolderId filter
	 * @return int
	 */
	public function count(array $filters = []): int {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(1)'), "cnt")
			->from(self::RESOURCE_MEMBERS_TABLE, "member");

		if(isset($filters["resourceId"])) {
			$qb->where($qb->expr()->eq('member.resource_id', $qb->createNamedParameter($filters["resourceId"], IQueryBuilder::PARAM_INT)));
		} else if(isset($filters["organizationFolderId"])) {
			$qb->innerJoin("member", self::RESOURCES_TABLE, "resource", $qb->expr()->eq('member.resource_id', 'resource.id'));
			$qb->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($filters["organizationFolderId"], IQueryBuilder::PARAM_INT)));
		}

		if(isset($filters["permissionLevel"])) {
			$qb->andWhere($this->buildPermissionLevelDBFilter($qb, $filters["permissionLevel"]));
		}

		if(isset($filters["principal"])) {
			$qb->andWhere($this->buildPrincipalDBFilter($qb, $filters["principal"]));
		}
		
		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param ResourceMemberPermissionLevel[] $permissionLevelFilter
	 * @return ICompositeExpression
	 */
	private function buildPermissionLevelDBFilter(IQueryBuilder $qb, array $permissionLevelFilter): ICompositeExpression {
		$permissionLevelDBFilter = $qb->expr()->orX();

		foreach($permissionLevelFilter as $permissionLevel) {
			$permissionLevelDBFilter->add(
				$qb->expr()->eq('permission_level', $qb->createNamedParameter($permissionLevel->value, IQueryBuilder::PARAM_INT))
			);
		}

		return $permissionLevelDBFilter;
	}

	/**
	 * @param PrincipalFilter[] $principalFilters
	 * @return ICompositeExpression
	 */
	private function buildPrincipalDBFilter(IQueryBuilder $qb, array $principalFilters): ICompositeExpression {
		$principalsDBFilter = $qb->expr()->orX();

		foreach($principalFilters as $principalFilter) {
			$principalDBFilter = $qb->expr()->andX(
				$qb->expr()->eq('principal_type', $qb->createNamedParameter($principalFilter->type->value, IQueryBuilder::PARAM_INT)),
			);

			if($principalFilter->id !== null) {
				$principalDBFilter->add(
					$qb->expr()->eq('principal_id', $qb->createNamedParameter($principalFilter->id, IQueryBuilder::PARAM_STR)),
				);
			}

			$principalsDBFilter->add($principalDBFilter);
		}

		return $principalsDBFilter;
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

		$qb->selectAlias($qb->createFunction('COUNT(DISTINCT `member`.`principal_id`)'), "cnt")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN);
	}

	public function hasOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId): bool {
		// This would be faster using EXISTS() and a subquery, but that does not seem possible with the QueryBuilder
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(1)'), "cnt")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN) >= 1;
	}

	public function isUserIndividualMemberOfTopLevelResourceOfOrganizationFolder(int $organizationFolderId, string $userId): bool {
		// This would be faster using EXISTS() and a subquery, but that does not seem possible with the QueryBuilder
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(1)'), "cnt")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->eq('resource.organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($qb->expr()->eq('member.principal_id', $qb->createNamedParameter($userId)));

		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN) >= 1;
	}

	public function getIdsOfOrganizationFoldersUserIsTopLevelResourceIndividualMemberIn(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectDistinct("resource.organization_folder_id")
			->from(self::RESOURCES_TABLE, "resource")
			->where($qb->expr()->isNull('resource.parent_resource'));
		
		$qb->innerJoin('resource', self::RESOURCE_MEMBERS_TABLE, 'member', $qb->expr()->eq('resource.id', 'member.resource_id'));

		$qb->andWhere($qb->expr()->eq('member.principal_type', $qb->createNamedParameter(PrincipalType::USER->value, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($qb->expr()->eq('member.principal_id', $qb->createNamedParameter($userId)));

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

		$qb->selectAlias($qb->createFunction('COUNT(1)'), "cnt")
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)));

		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN) === 1;
	}
}