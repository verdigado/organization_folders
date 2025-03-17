<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Psr\Container\ContainerInterface;

use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupfolderTags\Service\TagService;
use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;
use OCA\GroupFolders\ACL\Rule;

use OCA\OrganizationFolders\Errors\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Manager\GroupfolderManager;
use OCA\OrganizationFolders\Manager\ACLManager;

class OrganizationFolderService {
    use TTransactional;

    public function __construct(
        protected IDBConnection $db,
		protected FolderManager $folderManager,
        protected UserMappingManager $userMappingManager,
        protected TagService $tagService,
        protected OrganizationProviderManager $organizationProviderManager,
        protected PathManager $pathManager,
        protected GroupfolderManager $groupfolderManager,
        protected ACLManager $aclManager,
		protected ContainerInterface $container,
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
            throw new OrganizationFolderNotFound($id);
        }

        return new OrganizationFolder(
            id: $groupfolder["id"],
            name: $groupfolder["mount_point"],
            quota: $groupfolder["quota"],
            organizationProvider: $groupfolder["organization_provider"],
            organizationId: (int)$groupfolder["organization_id"],
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
        return $this->atomic(function () use ($name, $quota, $organizationProvider, $organizationId, $existingGroupfolderId) {
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
            
            $this->applyPermissions($groupfolderId);
            
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

        $this->applyPermissions($id);

        return $this->find($id);
    }

    public function applyPermissions(int $id) {
        $organizationFolder = $this->find($id);

        $memberGroups = $this->getMemberGroups($organizationFolder);

        $this->setGroupsAsGroupfolderMembers($organizationFolder->getId(), $memberGroups);
        $this->setRootFolderACLs($organizationFolder, $memberGroups);

        /** @var ResourceService */
		$resourceService = $this->container->get(ResourceService::class);
        return $resourceService->setAllFolderResourceAclsInOrganizationFolder($organizationFolder, $memberGroups);
    }

    protected function getMemberGroups(OrganizationFolder $organizationFolder) {
        // TODO: fetch member groups, for now only use organization members
        $memberGroups = [];

        if(!is_null($organizationFolder->getOrganizationProvider()) && !is_null($organizationFolder->getOrganizationId())) {
            $organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationFolder->getOrganizationProvider());
            $organization = $organizationProvider->getOrganization($organizationFolder->getOrganizationId());

            $memberGroups[] = $organization->getMembersGroup();
        }

        return $memberGroups;
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
                userMapping: $this->userMappingManager->mappingFromId("group", $group),
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
