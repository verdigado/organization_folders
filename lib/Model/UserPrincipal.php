<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUserManager;
use OCP\IUser;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;

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

	public function getNumberOfAccountsContained(): int {
		if($this->valid) {
			return 1;
		} else {
			return 0;
		}
	}

	public function toGroupfolderAclMapping(): ?IUserMapping {
		if($this->id != '') {
			return new UserMapping(type: "user", id: $this->id, displayName: null);
		} else {
			return null;
		}
	}
}