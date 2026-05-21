<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Psr\Log\LoggerInterface;

use OCP\IL10N;
use OCP\IURLGenerator;

use OCA\OrganizationFolders\Integration\Dav\CalendarIntegration;
use OCA\OrganizationFolders\Model\CalendarResourceLinkShare;
use OCA\OrganizationFolders\Model\ResourceLinkShare;
use OCA\OrganizationFolders\Db\CalendarResource;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Errors\Api\ResourceDoesNotSupportLinkShares;
use OCA\OrganizationFolders\Errors\Api\ResourceLinkShareLimitReached;
use OCA\OrganizationFolders\Errors\Api\ResourceLinkShareNotFound;

class ResourceLinkShareService {
	public function __construct(
		protected readonly LoggerInterface $logger,
		protected readonly IL10N $l10n,
		protected readonly IURLGenerator $urlGenerator,
		protected readonly ResourceService $resourceService,
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly CalendarIntegration $calendarIntegration,
	) {}

	public function findAllByResourceId(int $resourceId): array {
		return $this->findAll($this->resourceService->find($resourceId));
	}

	private function convertCalendarLinkShareArray(int $resourceId, array $share): CalendarResourceLinkShare {
		return new CalendarResourceLinkShare($this->urlGenerator, $this->l10n, $resourceId, $share['id'], $share['publicuri']);
	}

	/**
	 * @param Resource $resource
	 * @throws ResourceDoesNotSupportLinkShares
	 * @return CalendarResourceLinkShare[]
	 */
	public function findAll(Resource $resource): array {
		if($resource instanceof CalendarResource) {
			$share = $this->calendarIntegration->getCalendarLinkShare($resource->getCalendarId());

			if(!isset($share)) {
				return [];
			}

			return [$this->convertCalendarLinkShareArray($resource->getId(), $share)];
		} else {
			throw new ResourceDoesNotSupportLinkShares($resource);
		}
	}

	public function find(Resource $resource, int $id): ResourceLinkShare {
		if($resource instanceof CalendarResource) {
			$share = $this->calendarIntegration->getCalendarLinkShare($resource->getCalendarId());

			if(!isset($share) || $id !== $share['id']) {
				throw new ResourceLinkShareNotFound($resource->getId(), $id);
			}

			return $this->convertCalendarLinkShareArray($resource->getId(), $share);
		} else {
			throw new ResourceDoesNotSupportLinkShares($resource);
		}
	}

	/**
	 * @param Resource $resource
	 * @throws ResourceDoesNotSupportLinkShares
	 * @throws ResourceLinkShareLimitReached
	 * @return CalendarResourceLinkShare
	 */
	public function create(Resource $resource): CalendarResourceLinkShare {
		if($resource instanceof CalendarResource) {
			$existingShare = $this->calendarIntegration->getCalendarLinkShare($resource->getCalendarId());

			if(isset($existingShare)) {
				throw new ResourceLinkShareLimitReached($resource, 1);
			}

			$this->calendarIntegration->createCalendarLinkShare($resource->getCalendarId());

			$share = $this->calendarIntegration->getCalendarLinkShare($resource->getCalendarId());
			return $this->convertCalendarLinkShareArray($resource->getId(), $share);
		} else {
			throw new ResourceDoesNotSupportLinkShares($resource);
		}
	}

	/**
	 * @param Resource $resource
	 * @param int $id
	 * @throws ResourceDoesNotSupportLinkShares
	 * @throws ResourceLinkShareNotFound
	 * @return CalendarResourceLinkShare
	 */
	public function delete(Resource $resource, int $id): CalendarResourceLinkShare {
		if($resource instanceof CalendarResource) {
			$share = $this->calendarIntegration->getCalendarLinkShare($resource->getCalendarId());

			if(!isset($share) || $id !== $share['id']) {
				throw new ResourceLinkShareNotFound($resource->getId(), $id);
			}

			$this->calendarIntegration->deleteCalendarLinkShare($resource->getCalendarId());

			return $this->convertCalendarLinkShareArray($resource->getId(), $share);
		} else {
			throw new ResourceDoesNotSupportLinkShares($resource);
		}
	}
}