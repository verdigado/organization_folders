<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Manager;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

use OCA\GroupFolders\Folder\FolderManager;

class GroupfolderManager {
	public function __construct(
		protected IDBConnection $db,
		protected FolderManager $folderManager,
		protected IEventDispatcher $eventDispatcher,
	) {
	}

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

	public function overwriteMemberGroups(int $id, array $memberGroups): array {
		$existingMemberGroups = $this->getMemberGroups($id);

		// new members to be added
		$newMemberGroups = array_udiff($memberGroups, $existingMemberGroups, $this->memberGroupIdComparison(...));

		// old members to be deleted
		$removedMemberGroups = array_udiff($existingMemberGroups, $memberGroups, $this->memberGroupIdComparison(...));

		$potentiallyUpdatedMemberGroups = array_uintersect($memberGroups, $existingMemberGroups, $this->memberGroupIdComparison(...));
		$notUpdatedMemberGroups = array_uintersect($memberGroups, $existingMemberGroups, $this->memberGroupComparison(...));

		// member groups with changed permissions
		$updatedMemberGroups = array_udiff($potentiallyUpdatedMemberGroups, $notUpdatedMemberGroups, $this->memberGroupIdComparison(...));

		foreach($removedMemberGroups as $removedMemberGroup) {
			$this->folderManager->removeApplicableGroup($id, $removedMemberGroup["group_id"]);
		}

		foreach($newMemberGroups as $newMemberGroup) {
			$this->addMemberGroup($id, $newMemberGroup["group_id"], $newMemberGroup["permissions"]);
		}

		foreach($updatedMemberGroups as $updatedMemberGroup) {
			$this->folderManager->setGroupPermissions($id, $updatedMemberGroup["group_id"], $updatedMemberGroup["permissions"]);
		}

		return ["created" => $newMemberGroups, "removed" => $removedMemberGroups, "updated" => $updatedMemberGroups];
	}
}
