<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\Principal;

/**
 * Emitted before a ResourceMember is created.
 *
 * Listeners can cancel the operation by calling cancel(), which also stops
 * propagation. They can optionally provide an error message (and id) that
 * will be returned to the client by the caller.
 */
class BeforeResourceMemberCreatedEvent extends Event {
	public function __construct(
		private readonly int $resourceId,
		private readonly ResourceMemberPermissionLevel $permissionLevel,
		private readonly Principal $principal,
	) {
		parent::__construct();
	}

	// cancellation state
	private bool $cancelled = false;
	private ?string $errorMessage = null;
	private ?string $errorL10nMessage = null;
	private ?string $errorId = null;

	public function getResourceId(): int {
		return $this->resourceId;
	}

	public function getPermissionLevel(): ResourceMemberPermissionLevel {
		return $this->permissionLevel;
	}

	public function getPrincipal(): Principal {
		return $this->principal;
	}

	/**
	 * Cancel the creation with an optional error message that will be surfaced
	 * to the frontend. Also stops further propagation.
	 */
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

