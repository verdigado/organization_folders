<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\IDBConnection;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Model\PrincipalFilter;

class OrganizationFolderMemberMapper extends QBMapper {
	public const ORGANIZATIONFOLDER_MEMBERS_TABLE = "organizationfolders_members";

	public function __construct(
		protected PrincipalFactory $principalFactory,
		IDBConnection $db,
	) {
		parent::__construct($db, self::ORGANIZATIONFOLDER_MEMBERS_TABLE, OrganizationFolderMember::class);
	}

	/**
	 *
	 * @param array $row the row which should be converted to an entity
	 * @return OrganizationFolderMember the entity
	 * @psalm-return OrganizationFolderMember the entity
	 */
	protected function mapRowToEntity(array $row): OrganizationFolderMember {
		$member = new OrganizationFolderMember();

		$member->setId($row["id"]);
		$member->setOrganizationFolderId($row["organization_folder_id"]);
		$member->setPermissionLevel($row["permission_level"]);

		$principalType = PrincipalType::from($row["principal_type"]);
		$principal = $this->principalFactory->buildPrincipal($principalType, $row["principal_id"]);
		$member->setPrincipal($principal);

		$member->setCreatedTimestamp($row["created_timestamp"]);
		$member->setLastUpdatedTimestamp($row["last_updated_timestamp"]);

		$member->resetUpdatedFields();

		return $member;
	}

	/**
	 * @param int $id
	 * @return Entity|OrganizationFolderMember
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function find(int $id): OrganizationFolderMember {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::ORGANIZATIONFOLDER_MEMBERS_TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		
		return $this->findEntity($qb);
	}

	/**
	 * @param int $organizationFolderId
	 * @param array{
	 * 	permissionLevel: ?OrganizationFolderMemberPermissionLevel[],
	 * 	principal: ?PrincipalFilter[]
	 * } $filters
	 * @return array
	 * @psalm-return OrganizationFolderMember[]
	 */
	public function findAll(int $organizationFolderId, array $filters = []): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::ORGANIZATIONFOLDER_MEMBERS_TABLE)
			->where($qb->expr()->eq('organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

		if(isset($filters["permissionLevel"])) {
			$qb->andWhere($this->buildPermissionLevelDBFilter($qb, $filters["permissionLevel"]));
		}

		if(isset($filters["principal"])) {
			$qb->andWhere($this->buildPrincipalDBFilter($qb, $filters["principal"]));
		}
		
		return $this->findEntities($qb);
	}

	/**
	 * @param int $organizationFolderId
	 * @param array{
	 * 	permissionLevel: OrganizationFolderMemberPermissionLevel[],
	 * 	principal: PrincipalFilter[]
	 * } $filters
	 * @return int
	 */
	public function count(int $organizationFolderId, array $filters = []): int {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(1)'), "cnt")
			->from(self::ORGANIZATIONFOLDER_MEMBERS_TABLE)
			->where($qb->expr()->eq('organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)));

		if(isset($filters["permissionLevel"])) {
			$qb->andWhere($this->buildPermissionLevelDBFilter($qb, $filters["permissionLevel"]));
		}

		if(isset($filters["principal"])) {
			$qb->andWhere($this->buildPrincipalDBFilter($qb, $filters["principal"]));
		}
		
		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param OrganizationFolderMemberPermissionLevel[] $permissionLevelFilter
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

	public function exists(int $organizationFolderId, int $principalType, string $principalId): bool {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(1)'), "cnt")
			->from(self::ORGANIZATIONFOLDER_MEMBERS_TABLE)
			->where($qb->expr()->eq('organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)));

		return $qb->executeQuery()->fetch(\PDO::FETCH_COLUMN) === 1;
	}
}