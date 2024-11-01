<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupfolderTags\Service\TagService;

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
                organizationId: $groupfolder["organization_id"],
            );
        }

        return $result;
    }

    public function create(string $name, int $quota, ?string $organizationProvider = null,?int $organizationId = null) {
        $this->atomic(function () use ($name, $quota) {
            $groupfolderId = $this->folderManager->createFolder($name);
            $this->folderManager->setFolderQuota($groupfolderId, $quota);
            $this->folderManager->setFolderACL($groupfolderId, true);

            $this->tagService->update($groupfolderId, "organization_folder");

            if(isset($organizationProvider) && $this->organizationProviderManager->hasOrganizationProvider($organizationProvider) && isset($organizationId)) {
                $organization = $this->organizationProviderManager->getOrganizationProvider($organizationProvider)->getOrganization($organizationId);

                $this->tagService->update($groupfolderId, "organization_provider", $organizationProvider);
                $this->tagService->update($groupfolderId, "organization_id", $organization->getId());
            }
            
            // TODO: return Model object

        }, $this->db);
    }

    public function applyPermissions(int $id) {
        
    }

}
