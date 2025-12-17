<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Public\Provider;

use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Db\FolderResource;

use OCA\OrganizationFolders\Public\Errors\ResourceTemplateNotFound;

abstract class AbstractResourceTemplateProvider {
	public function __construct(protected string $id) {}

	public function getId(): string {
		return $this->id;
	}

    public function getFriendlyName(): string {
		return $this->getId();
	}

	/**
	 * @param string $id
	 * @return AbstractResourceTemplate
	 * @throws ResourceTemplateNotFound
	 */
	abstract public function getTemplateById(string $id): AbstractResourceTemplate;

	/**
	 * @return AbstractResourceTemplate[]
	 */
	abstract public function getAllTemplates(): array;

	/**
	 * Subclasses should if possible override this with faster provider-specific logic
	 * @return AbstractResourceTemplate[]
	 */
	public function getAllAvailableTemplates(OrganizationFolder $organizationFolder, ?FolderResource $parentResource): array {
		$result = [];

		foreach($this->getAllTemplates() as $template) {
			if($template->isAvailable($organizationFolder, $parentResource)) {
				$result[] = $template;
			}
		}

		return $result;
	}
}