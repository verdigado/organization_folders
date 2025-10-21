<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\Principal;

/**
 * Emitted before a ResourceMember is updated.
 *
 * Listeners can cancel the operation by calling cancel(), which also stops
 * propagation. They can optionally provide an error message (and id) that
 * will be returned to the client by the caller.
 */
class BeforeResourceMemberUpdatedEvent extends Event {
	public function __construct(
		private readonly ResourceMember $member,
		private readonly ?ResourceMemberPermissionLevel $newPermissionLevel,
		private readonly ?Principal $newPrincipal,
	) {
		parent::__construct();
	}

	// cancellation state
	private bool $cancelled = false;
	private ?string $errorMessage = null;
	private ?string $errorL10nMessage = null;
	private ?string $errorId = null;

	public function getMember(): ResourceMember {
		return $this->member;
	}

	public function getNewPermissionLevel(): ?ResourceMemberPermissionLevel {
		return $this->newPermissionLevel;
	}

	public function getNewPrincipal(): ?Principal {
		return $this->newPrincipal;
	}

	public function cancel(?string $message = null, ?string $l10nMessage = null, ?string $id = null): void {
		$this->cancelled = true;
		$this->errorMessage = $message;
		$this->errorL10nMessage = $l10nMessage ?? $message;
		$this->errorId = $id;
		$this->stopPropagation();
	}

	public function isCancelled(): bool {
		return $this->cancelled;
	}

	public function getErrorMessage(): ?string {
		return $this->errorMessage;
	}

	public function getErrorL10nMessage(): ?string {
		return $this->errorL10nMessage;
	}

	public function getErrorId(): ?string {
		return $this->errorId;
	}
}

