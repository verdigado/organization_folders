<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IURLGenerator;
use OCP\IL10N;

class CalendarResourceLinkShare extends ResourceLinkShare {
    public function __construct(
        private readonly IURLGenerator $urlGenerator,
        private readonly IL10N $l10n,
        private readonly int $resourceId,
        private readonly int $id,
        private readonly string $publicUri,
    ) {}

    public function getResourceId(): int {
        return $this->id;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->l10n->t("Read-only link share");
    }

    public function getLinkURL(): string {
        return $this->urlGenerator->getAbsoluteURL("/apps/calendar/p/" . $this->publicUri);
    }

    public function jsonSerialize(): array {
		return [
			'resourceId' => $this->resourceId,
			'id' => $this->id,
            'name' => $this->getName(),
			'linkUrl' => $this->getLinkURL(),
		];
	}
}