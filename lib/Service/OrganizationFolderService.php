<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupfolderTags\Service\TagService;

use OCA\OrganizationFolders\Errors\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class OrganizationFolderService {
    use TTransactional;

    public function __construct(
        private IDBConnection $db,
		private FolderManager $folderManager,
        private TagService $tagService,
        private OrganizationProviderManager $organizationProviderManager,
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

    public function create(string $name, int $quota, ?string $organizationProvider = null,?int $organizationId = null): OrganizationFolder {
        return $this->atomic(function () use ($name, $quota, $organizationProvider, $organizationId) {
            $groupfolderId = $this->folderManager->createFolder($name);
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
        return $this->atomic(function () use ($id, $name, $quota, $organizationProviderId, $organizationId) {
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

            return $this->find($id);
        }, $this->db);
    }

    public function applyPermissions(int $id) {
        
    }

    public function remove($id): void {
        $organizationFolder = $this->find($id);
        $this->folderManager->removeFolder($organizationFolder->getId());
    }

}
