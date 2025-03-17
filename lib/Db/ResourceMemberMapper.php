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
	 * @psalm-param int $resourceId
	 * @return array
	 * @psalm-return ResourceMember[]
	 */
	public function findAll(int $resourceId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId, IQueryBuilder::PARAM_INT)));
		
		return $this->findEntities($qb);
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