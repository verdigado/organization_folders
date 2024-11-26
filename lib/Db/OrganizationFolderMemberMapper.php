<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\PrincipalFactory;

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
     * @param array{permissionLevel: int, principalType: int} $filters
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
            $qb->andWhere($qb->expr()->eq('permission_level', $qb->createNamedParameter($filters["permissionLevel"], IQueryBuilder::PARAM_INT)));
        }

        if(isset($filters["principalType"])) {
            $qb->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($filters["principalType"], IQueryBuilder::PARAM_INT)));
        }
		
		return $this->findEntities($qb);
	}

	public function exists(int $organizationFolderId, int $principalType, string $principalId): bool {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(1)'))
			->from(self::ORGANIZATIONFOLDER_MEMBERS_TABLE)
			->where($qb->expr()->eq('organization_folder_id', $qb->createNamedParameter($organizationFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)));

		return $qb->executeQuery()->fetch()["COUNT(1)"] === 1;
	}
}