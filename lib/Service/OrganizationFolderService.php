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

use OCA\OrganizationFolders\DTO\CreateOrganizationFolderDto;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Errors\Api\OrganizationProviderNotFound;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\PrincipalBackedByGroup;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Manager\GroupfolderManager;
use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\Groups\GroupBackend;

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

	/**
	 * @param array{organizationProvider: string, organizationId: int} $filters
	 * @return array
	 * @psalm-return OrganizationFolder[]
	 */
	public function findAll(array $filters = []) {
		$result = [];

		$tagFilters = [
			["key" => "organization_folder"],
		];

		$additionalReturnTags = [];

		if(isset($filters["organizationProvider"])) {
			$tagFilters[] = ["key" => "organization_provider", "value" => $filters["organizationProvider"], "includeInOutput" => True];
		} else {
			$additionalReturnTags[] = "organization_provider";
		}

		if(isset($filters["organizationId"])) {
			$tagFilters[] = ["key" => "organization_id", "value" => $filters["organizationId"], "includeInOutput" => True];
		} else {
			$additionalReturnTags[] = "organization_id";
		}

		$groupfolders = $this->tagService->findGroupfoldersWithTagsGenerator($tagFilters, $additionalReturnTags);

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

	public function getOrganizationFolderQuotaUsed(OrganizationFolder $organizationFolder): int {
		return $this->pathManager->getOrganizationFolderNode($organizationFolder)->getSize(includeMounts: false);
	}


	public function createFromDto(CreateOrganizationFolderDto $dto): OrganizationFolder {
		return $this->create(
			name: $dto->name,
			quota: $dto->quota,
			organizationProvider: $dto->organizationProviderId,
			organizationId: $dto->organizationId,
		);
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
			
			$this->applyAllPermissions($organizationFolder);
			
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
					throw new OrganizationProviderNotFound($organizationProviderId);
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

		$organizationFolder = $this->find($id);

		$this->applyAllPermissions($organizationFolder);

		return $organizationFolder;
	}

	public function applyAllPermissionsById(int $id): void {
		$this->applyAllPermissions($this->find($id));
	}

	/**
	 * If the caller already fetched the OrganizationFolder member and manager Principals (using getMemberAndManagerPrincipals)
	 * it can provide them, so they don't have to be fetched again.
	 * 
	 * @param OrganizationFolder $organizationFolder
	 * @param ?list<PrincipalBackedByGroup> $organizationFolderMemberPrincipals
	 * @param ?list<PrincipalBackedByGroup> $organizationFolderManagerPrincipals
	 * @return void
	 */
	public function refreshGroupfolderMembers(
		OrganizationFolder $organizationFolder,
		?array $organizationFolderMemberPrincipals = null,
		?array $organizationFolderManagerPrincipals = null,
	): void {
		if(!(isset($organizationFolderMemberPrincipals) && isset($organizationFolderManagerPrincipals))) {
			[$organizationFolderMemberPrincipals, $organizationFolderManagerPrincipals] = $this->getMemberAndManagerPrincipals($organizationFolder);
		}

		$groupfolderMemberGroups = [];

		foreach([...$organizationFolderMemberPrincipals, ...$organizationFolderManagerPrincipals] as $principal) {
			$backingGroup = $principal->getBackingGroupId();

			if(isset($backingGroup)) {
				$groupfolderMemberGroups[] = $backingGroup;
			}
		}

		$impliedMemberPrincipals = $this->getImpliedMemberPrincipals($organizationFolder);

		$hasIndividualImpliedMembers = false;

		foreach($impliedMemberPrincipals as $principal) {
			if($principal instanceof PrincipalBackedByGroup) {
				$backingGroup = $principal->getBackingGroupId();

				if(isset($backingGroup)) {
					$groupfolderMemberGroups[] = $backingGroup;
				}
			} else {
				$hasIndividualImpliedMembers = true;
			}
		}

		if($hasIndividualImpliedMembers) {
			$groupfolderMemberGroups[] = GroupBackend::ORGANIZATION_FOLDER_GROUP_START . $organizationFolder->getId() . GroupBackend::IMPLIED_INDIVIDUAL_GROUP_END;
		}

		$groupfolderMemberGroups = array_unique($groupfolderMemberGroups);

		$this->setGroupsAsGroupfolderMembers($organizationFolder->getId(), $groupfolderMemberGroups);
		$this->setRootFolderACLs($organizationFolder, ["everyone", ...$groupfolderMemberGroups]);
	}

	public function applyAllPermissions(OrganizationFolder $organizationFolder): void {
		[$memberPrincipals, $managerPrincipals] = $this->getMemberAndManagerPrincipals($organizationFolder);

		$this->refreshGroupfolderMembers($organizationFolder, $memberPrincipals, $managerPrincipals);

		/** @var PermissionsService */
		$permissionsService = $this->container->get(PermissionsService::class);
		$permissionsService->applyAllResourcePermissionsInOrganizationFolder($organizationFolder, $memberPrincipals, $managerPrincipals);
	}

	/**
	 * @param OrganizationFolder $organizationFolder
	 * @psalm-return array{0: PrincipalBackedByGroup[], 1: PrincipalBackedByGroup[]}
	 */
	public function getMemberAndManagerPrincipals(OrganizationFolder $organizationFolder): array {
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
			if($member->getPermissionLevel() === OrganizationFolderMemberPermissionLevel::MEMBER->value) {
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

	/**
	 * @param OrganizationFolder $organizationFolder
	 * @return Principal[]
	 */
	protected function getImpliedMemberPrincipals(OrganizationFolder $organizationFolder): array {
		$principals = [];

		// avoids circular dependency autowire
		// TODO: find better solution
		/**
		 * @var ResourceMemberService
		 */
		$resourceMemberService = \OC::$server->get(ResourceMemberService::class);

		$topLevelResourceMembers = $resourceMemberService->findAllTopLevelResourcesMembersOfOrganizationFolder(
			organizationFolderId: $organizationFolder->getId(),
		);

		foreach($topLevelResourceMembers as $member) {
			$principals[] = $member->getPrincipal();
		}

		return $principals;
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

		$this->aclManager->overwriteACLs($fileId, $acls);
	}

	public function remove($id): void {
		$organizationFolder = $this->find($id);
		$this->folderManager->removeFolder($organizationFolder->getId());
	}

}
