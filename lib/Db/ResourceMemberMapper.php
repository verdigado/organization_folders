<?php

namespace OCA\OrganizationFolders\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ResourceMemberMapper extends QBMapper {
	public const RESOURCE_MEMBERS_TABLE = "organizationfolders_resource_members";

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::RESOURCE_MEMBERS_TABLE, ResourceMember::class);
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
	 * @return array
	 */
	public function findAll(int $resourceId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::RESOURCE_MEMBERS_TABLE)
			->where($qb->expr()->eq('resource_id', $qb->createNamedParameter($resourceId, IQueryBuilder::PARAM_INT)));
		
		return $this->findEntities($qb);
	}
}