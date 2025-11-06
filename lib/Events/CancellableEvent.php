<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

abstract class CancellableEvent extends Event {
    private bool $cancelled;
    private ?string $errorMessage;

    public function __construct() {
        parent::__construct();
        $this->cancelled = false;
        $this->errorMessage = null;
    }

    /**
     * Cancel the operation with an optional error message that will be surfaced
     * to the frontend. Also stops further propagation.
     */
    public function cancel(?string $message = null): void {
        $this->cancelled = true;
        $this->errorMessage = $message;
        $this->stopPropagation();
    }

    public function isCancelled(): bool {
        return $this->cancelled;
    }

    public function getErrorMessage(): ?string {
        return $this->errorMessage;
    }
}
