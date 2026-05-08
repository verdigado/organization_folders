<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Integration\Dav;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Db\TTransactional;

use OCA\DAV\CalDAV\Sharing\Service;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\OrganizationFolders\Model\CalendarShareList;
use OCA\OrganizationFolders\Model\CalendarSharesUpdatePlan;

/**
 * @psalm-type CalendarInfo = array{
 *     id: int,
 *     uri: string,
 *     principaluri: string,
 *     '{http://calendarserver.org/ns/}getctag': string,
 *     '{http://sabredav.org/ns}sync-token': int,
 *     '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set': \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet,
 *     '{urn:ietf:params:xml:ns:caldav}schedule-calendar-transp': \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp,
 *     '{DAV:}displayname': string,
 *     '{urn:ietf:params:xml:ns:caldav}calendar-timezone': ?string,
 *     '{http://nextcloud.com/ns}owner-displayname': string,
 * }
 */
class CalendarIntegration {
	use TTransactional;

	public function __construct(
		private readonly CalDavBackend $calDavBackend,
		private readonly Service $sharingService,
		private readonly IDBConnection $db,
	) {}

	/**
	 * @psalm-return ?CalendarInfo
	 */
	public function getCalendarById(int $calendarId): ?array {
		return $this->calDavBackend->getCalendarById($calendarId);
	}

	/**
	 * Get all shares of a calenar (includes unshares) (does not include link shares)
	 * @param int $calendarId
	 * @return array{id: int, principaluri: string, access: int}[]
	 */
	public function getCalendarShares(int $calendarId) {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'principaluri', 'access'])
			->from('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($calendarId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter("calendar", IQueryBuilder::PARAM_STR)))
			// exclude link shares
			->andWhere($query->expr()->neq('access', $query->createNamedParameter(CalDavBackend::ACCESS_PUBLIC, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * If it exists get the link share of a calenar
	 * @param int $calendarId
	 * @return array{id: int, publicuri: string}[]
	 */
	public function getCalendarLinkShare(int $calendarId) {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'publicuri'])
			->from('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($calendarId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter("calendar", IQueryBuilder::PARAM_STR)))
			// exclude link shares
			->andWhere($query->expr()->eq('access', $query->createNamedParameter(CalDavBackend::ACCESS_PUBLIC, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	public function createSharesUpdatePlanFromShareList(CalendarShareList $calendarShareList): CalendarSharesUpdatePlan {
		return $this->createSharesUpdatePlan($calendarShareList->getCalendarId(), $calendarShareList->getShares());
	}

	/**
	 * @param int $calendarId
	 * @param list<array{principaluri: string, access: int}> $shares
	 * @return CalendarSharesUpdatePlan
	 */
	public function createSharesUpdatePlan(int $calendarId, array $shares): CalendarSharesUpdatePlan {
		$existingShares = $this->getCalendarShares($calendarId);

		/** @var array<string, array{id: int, principaluri: string, access: int}> */
		$existingSharesByKey = array_column($existingShares, null, "principaluri");

		/** @var list<array{principaluri: string, access: int}> */
		$sharesToCreate = [];

		/** @var list<array{id: int, principaluri: string, access: int}> */
		$sharesToUpdate = [];

		foreach($shares as $share) {
			$principaluri = $share['principaluri'];
			
			$existingShare = $existingSharesByKey[$principaluri] ?? null;

			if($existingShare === null) {
				$sharesToCreate[] = $share;
			} else if($existingShare['access'] !== $share['access']) {
				$sharesToUpdate[] = [
					"id" => $existingShare['id'],
					"principaluri" => $principaluri,
					"access" => $share['access'],
				];
			}
		}

		/** @var array<string, array{principaluri: string, access: int}> */
		$sharesByKey = array_column($shares, null, "principaluri");

		/** @var list<array{id: int, principaluri: string, access: int}> */
		$sharesToRemove = [];

		foreach($existingShares as $existingShare) {
			if (!isset($sharesByKey[$existingShare['principaluri']])) {
        		$sharesToRemove[] = $existingShare;
			}
		}

		return new CalendarSharesUpdatePlan(toCreate: $sharesToCreate, toUpdate: $sharesToUpdate, toRemove: $sharesToRemove);
	}

	public function applySharesUpdatePlan(int $calendarId, CalendarSharesUpdatePlan $plan) {
		$this->atomic(function () use ($calendarId, $plan) {
			foreach($plan->toRemove as $share) {
				$this->sharingService->deleteShare($calendarId, $share['principaluri']);
			}

			foreach($plan->toCreate as $share) {
				// TODO: using own sql query would be more efficient, we know this row does not exist yet, this function tries deleting first
				$this->sharingService->shareWith($calendarId, $share['principaluri'], $share['access']);
			}

			foreach($plan->toUpdate as $share) {
				$this->sharingService->shareWith($calendarId, $share['principaluri'], $share['access']);
			}
		}, $this->db);
	}
}