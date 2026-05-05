<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Manager;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

use OCA\GroupFolders\Folder\FolderManager;

/**
 * @psalm-type GroupfolderMemberGroup = array{
 *   group_id: string,
 *   permissions: int,
 * }
 */
class GroupfolderManager {
	public function __construct(
		protected IDBConnection $db,
		protected FolderManager $folderManager,
		protected IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * @param int $id groupfolder id
	 * @return GroupfolderMemberGroup[]
	 */
	public function getMemberGroups(int $id) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('group_id', 'permissions')
			->from('group_folders_groups')
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetchAll();
	}

	// the FolderManager function for this does not allow setting the permissions, defaulting to all permissions :/
	public function addMemberGroup(int $folderId, string $groupId, int $permissions = \OCP\Constants::PERMISSION_ALL): void {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('group_folders_groups')
			->values([
				'folder_id' => $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'group_id' => $qb->createNamedParameter($groupId),
				'circle_id' => $qb->createNamedParameter(''),
				'permissions' => $qb->createNamedParameter($permissions, IQueryBuilder::PARAM_INT)
			]);
		$qb->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The group "%s" was given access to the groupfolder with id %d', [$groupId, $folderId]));
	}

	protected function memberGroupIdComparison(array $memberGroup1, array $memberGroup2): int {
		return $memberGroup1["group_id"] <=> $memberGroup2["group_id"];
	}

	protected function memberGroupComparison(array $memberGroup1, array $memberGroup2): int {
		return $memberGroup1["group_id"] <=> $memberGroup2["group_id"] ?: $memberGroup1["permissions"] <=> $memberGroup2["permissions"];
	}

	/**
	 * @param int $id groupfolder id
	 * @param GroupfolderMemberGroup[] $memberGroups
	 * @return array{
	 *   created: GroupfolderMemberGroup[],
	 *   updated: GroupfolderMemberGroup[],
	 *   removed: string[],
	 * }
	 */
	public function overwriteMemberGroups(int $id, array $memberGroups): array {
		$existingMemberGroups = $this->getMemberGroups($id);

		/** @var array<string, int> */
		$existingGroupPermissionsById = [];

		foreach($existingMemberGroups as $existingMember) {
			$existingGroupPermissionsById[$existingMember['group_id']] = $existingMember['permissions'];
		}

		/** @var array<string, int> */
		$groupPermissionsById = [];

		/** @var GroupfolderMemberGroup[] */
		$groupsToAdd = [];

		/** @var GroupfolderMemberGroup[] */
		$groupsToUpdate = [];

		foreach($memberGroups as $member) {
			$groupId = $member['group_id'];

			$groupPermissionsById[$groupId] = $member['permissions'];

			$existingGroupPermission = $existingGroupPermissionsById[$groupId] ?? null;

			if($existingGroupPermission === null) {
				$groupsToAdd[] = $member;
			} else {
				if($existingGroupPermission !== $member['permissions']) {
					$groupsToUpdate[] = $member;
				}
			}
		}

		/** @var string[] */
		$groupsToRemove = [];

		foreach($existingMemberGroups as $existingMember) {
			$groupId = $existingMember['group_id'];

			if (!isset($groupPermissionsById[$groupId])) {
        		$groupsToRemove[] = $groupId;
			}
		}

		foreach($groupsToRemove as $group) {
			$this->folderManager->removeApplicableGroup($id, $group);
		}

		foreach($groupsToAdd as $group) {
			$this->addMemberGroup($id, $group["group_id"], $group["permissions"]);
		}

		foreach($groupsToUpdate as $group) {
			$this->folderManager->setGroupPermissions($id, $group["group_id"], $group["permissions"]);
		}

		return ["created" => $groupsToAdd, "updated" => $groupsToUpdate, "removed" => $groupsToRemove];
	}
}
