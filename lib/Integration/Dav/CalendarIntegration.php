<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Integration\Dav;

use Psr\Log\LoggerInterface;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Db\TTransactional;
use OCP\L10N\IFactory;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IConfig;

use Sabre\DAV\PropPatch;

use OCA\DAV\CalDAV\Sharing\Service;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;
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

	private const PROPERTY_DISPLAYNAME = "{DAV:}displayname";

	private const PROPERTY_DESCRIPTION = "{urn:ietf:params:xml:ns:caldav}calendar-description";

	private IL10N $davL10n;

	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IUserSession $userSession,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
		private readonly CalDavBackend $calDavBackend,
		private readonly Service $sharingService,
		private readonly IDBConnection $db,
	) {
		$this->davL10n = $l10nFactory->get(\OCA\DAV\AppInfo\Application::APP_ID, $l10nFactory->getUserLanguage($userSession->getUser()));
	}

	/**
	 * @psalm-return ?CalendarInfo
	 */
	public function getCalendarById(int $calendarId): ?array {
		return $this->calDavBackend->getCalendarById($calendarId);
	}

	/**
	 * @param CalendarInfo $calendarInfo
	 * @return Calendar
	 */
	private function calendarInfoToCalendar(array $calendarInfo): Calendar {
		return new Calendar($this->calDavBackend, $calendarInfo, $this->davL10n, $this->config, $this->logger);
	}

/**
	 * @psalm-return CalendarInfo
	 */
	public function createCalendar(string $principalUri, string $calendarUri, string $displayname, string $description = ""): array {
		$calendarId = $this->calDavBackend->createCalendar(
			$principalUri,
			$calendarUri,
			[
				self::PROPERTY_DISPLAYNAME => $displayname,
				self::PROPERTY_DESCRIPTION => $description,
			],
		);

		return $this->calDavBackend->getCalendarById($calendarId);
	}

	public function updateCalendar(int $calendarId, ?string $displayname = null, ?string $description = null): void {
		$mutations = [];

		if(isset($displayname)) {
			$mutations[self::PROPERTY_DISPLAYNAME] = $displayname;
		}

		if(isset($description)) {
			$mutations[self::PROPERTY_DESCRIPTION] = $description;
		}

		if(!empty($mutations)) {
			$propPatch = new PropPatch($mutations);
			$this->calDavBackend->updateCalendar($calendarId, $propPatch);
			if(!$propPatch->commit()) {
				throw new \Exception("Calendar update failed");
			}
		}
	}

	public function deleteCalendar(int $calendarId): void {
		$this->calDavBackend->deleteCalendar($calendarId);
	}

	/**
	 * Get all shares of a calendar (includes unshares) (does not include link shares)
	 * @param int $calendarId
	 * @return array{id: int, principaluri: string, access: int}[]
	 */
	public function getCalendarShares(int $calendarId): array {
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
	 * If it exists get the link share of a calendar
	 * @param int $calendarId
	 * @return ?array{id: int, publicuri: string}
	 */
	public function getCalendarLinkShare(int $calendarId): ?array {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'publicuri'])
			->from('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($calendarId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter("calendar", IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('access', $query->createNamedParameter(CalDavBackend::ACCESS_PUBLIC, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if($row) {
			return $row;
		} else {
			return null;
		}
	}

	public function createCalendarLinkShare(int $calendarId): string {
		$calendarInfo = $this->getCalendarById($calendarId);
		$calendar = $this->calendarInfoToCalendar($calendarInfo);
		return $this->calDavBackend->setPublishStatus(true, $calendar);
	}

	public function deleteCalendarLinkShare(int $calendarId): void {
		$calendarInfo = $this->getCalendarById($calendarId);
		$calendar = $this->calendarInfoToCalendar($calendarInfo);
		$this->calDavBackend->setPublishStatus(false, $calendar);
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