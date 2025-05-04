<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Psr\Container\ContainerInterface;

use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use OCP\Files\Node;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupfolderTags\Service\TagService;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Errors\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\PrincipalBackedByGroup;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Manager\GroupfolderManager;
use OCA\OrganizationFolders\Manager\ACLManager;

class OrganizationFolderService {
	use TTransactional;

	public function __construct(
		protected readonly IDBConnection $db,
		protected readonly FolderManager $folderManager,
		protected readonly TagService $tagService,
		protected readonly OrganizationProviderManager $organizationProviderManager,
		protected readonly PathManager $pathManager,
		protected readonly GroupfolderManager $groupfolderManager,
		protected readonly ACLManager $aclManager,
		protected readonly ContainerInterface $container,
		protected readonly PrincipalFactory $principalFactory,
	) {
	}

	public function findAll() {
		$result = [];

		$groupfolders = $this->tagService->findGroupfoldersWithTagsGenerator([
			["key" => "organization_folder"],
		], ["organization_provider", "organization_id"]);

		foreach ($groupfolders as $groupfolder) {
			$result[] = new OrganizationFolder(
				id: $groupfolder["id"],
				name: $groupfolder["mount_point"],
				quota: $groupfolder["quota"],
				organizationProvider: $groupfolder["organization_provider"],
				organizationId: (int)$groupfolder["organization_id"],
			);
		}

		return $result;
	}

	public function find(int $id): OrganizationFolder {
		$groupfolder = $this->tagService->findGroupfolderWithTags($id,[
			["key" => "organization_folder"],
		], ["organization_provider", "organization_id"]);

		if(is_null($groupfolder)) {
			throw new OrganizationFolderNotFound(["id" => $id]);
		}

		return new OrganizationFolder(
			id: $groupfolder["id"],
			name: $groupfolder["mount_point"],
			quota: $groupfolder["quota"],
			organizationProvider: $groupfolder["organization_provider"],
			organizationId: (int)$groupfolder["organization_id"],
		);
	}

	/**
	 * Get an OrganizationFolder by it's filesystem node (or of any folder or file within it)
	 * Important: The node needs to be within the groupfolder jail (meaning it has a path like "/some_user/files/groupfoler_mountpoint/" not "/__groupfolders/9/")
	 * @param Node $node
	 * @throws OrganizationFolderNotFound
	 * @return OrganizationFolder
	 */
	public function findByFilesystemNode(Node $node): OrganizationFolder {
		$mountPoint = $node->getMountPoint();

		if ($mountPoint instanceof GroupMountPoint) {
			$groupFolderId = $mountPoint->getFolderId();

			return $this->find($groupFolderId);
		} else {
			throw new OrganizationFolderNotFound(["path" => $node->getPath()]);
		}
	}

	public function create(
		string $name,
		int $quota,
		?string $organizationProvider = null,
		?int $organizationId = null,

		// special mode, that re-uses an existing groupfolder
		?int $existingGroupfolderId = null,
	): OrganizationFolder {
		return $this->atomic(function () use ($name, $quota, $organizationProvider, $organizationId, $existingGroupfolderId): OrganizationFolder {
			if(!isset($existingGroupfolderId)) {
				$groupfolderId = $this->folderManager->createFolder($name);
			} else {
				$groupfolderId = $existingGroupfolderId;
			}
			
			$this->folderManager->setFolderQuota($groupfolderId, $quota);
			$this->folderManager->setFolderACL($groupfolderId, true);

			$this->tagService->update($groupfolderId, "organization_folder");

			if(isset($organizationProvider) && $this->organizationProviderManager->hasOrganizationProvider($organizationProvider) && isset($organizationId)) {
				$organization = $this->organizationProviderManager->getOrganizationProvider($organizationProvider)->getOrganization($organizationId);

				$this->tagService->update($groupfolderId, "organization_provider", $organizationProvider);
				$this->tagService->update($groupfolderId, "organization_id", (string)$organization->getId());
			}

			$organizationFolder = new OrganizationFolder(
				id: $groupfolderId,
				name: $name,
				quota: $quota,
				organizationProvider: $organizationProvider,
				organizationId: $organizationId,
			);
			
			$this->applyPermissions($organizationFolder);
			
			return $organizationFolder;
		}, $this->db);
	}

	public function update(
		int $id,
		?string $name = null,
		?int $quota = null,
		?string $organizationProviderId = null,
		?int $organizationId = null
	): OrganizationFolder {
		$organizationFolder = $this->find($id);

		$this->atomic(function () use ($id, $name, $quota, $organizationProviderId, $organizationId) {
			if(isset($name)) {
				$this->folderManager->renameFolder($id, $name);
			}

			if(isset($quota)) {
				$this->folderManager->setFolderQuota($id, $quota);
			}
			
			if(isset($organizationProviderId) || isset($organizationId)) {
				if(!isset($organizationProviderId)) {
					$organizationProviderId = $this->tagService->find($id, "organization_provider")->getTagValue();
				}
	
				if(!$this->organizationProviderManager->hasOrganizationProvider($organizationProviderId)) {
					throw new \Exception("organization provider not found");
				}
	
				$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationProviderId);
	
				if(!isset($organizationId)) {
					$organizationId = (int)$this->tagService->find($id, "organization_id")->getTagValue();
				}
	
				$organization = $organizationProvider->getOrganization($organizationId);
	
				$this->tagService->update($id, "organization_provider", $organizationProviderId);
				$this->tagService->update($id, "organization_id", (string)$organization->getId());
			}
		}, $this->db);

		$this->applyPermissions($organizationFolder);

		return $organizationFolder;
	}

	public function applyPermissionsById(int $id): void {
		$this->applyPermissions($this->find($id));
	}

	public function applyPermissions(OrganizationFolder $organizationFolder): void {

		[$memberPrincipals, $managerPrincipals] = $this->getMemberAndManagerPrincipals($organizationFolder);

		$memberGroups = [];

		foreach($memberPrincipals as $memberPrincipal) {
			$backingGroup = $memberPrincipal->getBackingGroup();

			if(isset($backingGroup)) {
				$memberGroups[] = $backingGroup;
			}
		}

		$this->setGroupsAsGroupfolderMembers($organizationFolder->getId(), $memberGroups);
		$this->setRootFolderACLs($organizationFolder, $memberGroups);

		/** @var ResourceService */
		$resourceService = $this->container->get(ResourceService::class);
		$resourceService->setAllFolderResourceAclsInOrganizationFolder($organizationFolder, $memberPrincipals, $managerPrincipals);
	}

	/**
	 * @param OrganizationFolder $organizationFolder
	 * @psalm-return array{0: PrincipalBackedByGroup[], 1: PrincipalBackedByGroup[]}
	 */
	protected function getMemberAndManagerPrincipals(OrganizationFolder $organizationFolder): array {
		$memberPrincipals = [];
		$managerPrincipals = [];

		// avoids circular dependency autowire
		// TODO: find better solution
		/**
		 * @var OrganizationFolderMemberService
		 */
		$organizationFolderMemberService = \OC::$server->get(OrganizationFolderMemberService::class);

		$members = $organizationFolderMemberService->findAll(organizationFolderId: $organizationFolder->getId());

		foreach($members as $member) {
			if($member->getPermissionLevel() === OrganizationFolderMemberPermissionLevel::MEMBER) {
				// MEMBER
				$memberPrincipals[] = $member->getPrincipal();
			} else {
				// MANAGER or ADMIN
				$managerPrincipals[] = $member->getPrincipal();
			}
		}

		if(!is_null($organizationFolder->getOrganizationProvider()) && !is_null($organizationFolder->getOrganizationId())) {
			$memberPrincipals[] = $this->principalFactory->buildOrganizationMemberPrincipal(
				organizationProviderId: $organizationFolder->getOrganizationProvider(),
				organizationId: $organizationFolder->getOrganizationId(),
			);
		}

		return [$memberPrincipals, $managerPrincipals];
	}

	protected function setGroupsAsGroupfolderMembers($groupfolderId, array $groups) {
		$groupfolderMembers = [];

		foreach($groups as $group) {
			$groupfolderMembers[] = [
				"group_id" => $group,
				"permissions" => \OCP\Constants::PERMISSION_ALL,
			];
		}

		return $this->groupfolderManager->overwriteMemberGroups($groupfolderId, $groupfolderMembers);
	}
	/**
	 * In the root folder of an organization folder only resource folders can exist
	 * To prevent adding files there all member groups of the groupfolder need to have a read-only ACL rule on the root folder
	 */
	protected function setRootFolderACLs(OrganizationFolder $organizationFolder, $groups) {
		$folderNode = $this->pathManager->getOrganizationFolderNode($organizationFolder);
		$fileId = $folderNode->getId();

		$acls = [];
		foreach($groups as $group) {
			$acls[] = new Rule(
				userMapping: new UserMapping(type: "group", id: $group, displayName: null),
				fileId: $fileId,
				mask: 31,
				permissions: 1,
			);
		}

		$this->aclManager->overwriteACLsForFileId($fileId, $acls);
	}

	public function remove($id): void {
		$organizationFolder = $this->find($id);
		$this->folderManager->removeFolder($organizationFolder->getId());
	}

}
