<?php

namespace OCA\OrganizationFolders\Model;

use OCP\IUserManager;
use \OCP\IUser;

use OCA\OrganizationFolders\Enum\PrincipalType;

class UserPrincipal extends Principal {
	private ?IUser $user;

	public function __construct(
		private IUserManager $userManager,
		private string $id,
	) {
		try {
			$this->user = $this->userManager->get($id);
			$this->valid = !is_null($this->user);
		} catch (\Exception $e) {
			$this->valid = false;
		}
	}

	public function getType(): PrincipalType {
		return PrincipalType::USER;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getFriendlyName(): string {
		return $this->user?->getDisplayName() ?? $this->getId();
	}
}