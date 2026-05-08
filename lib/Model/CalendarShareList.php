<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\DAV\DAV\Sharing\Backend;

class CalendarShareList {
	/** @var array<string, array{principaluri: string, access: int}> */
	private array $shares = [];

	public function __construct(private int $calendarId) {}

	public function getCalendarId(): int {
		return $this->calendarId;
	}

	public function addShare(string $principaluri, int $access) {
		$share = $this->shares[$principaluri] ?? null;

		if(is_null($share)) {
			// If no share for principal exists yet, add it
			$share = [
				"principaluri" => $principaluri,
				"access" => $access,
			];
			$this->shares[$principaluri] = $share;
		} else if ($share["access"] === Backend::ACCESS_READ && $access === Backend::ACCESS_READ_WRITE) {
			// If new access is higher than previous, overwrite
			$share["access"] = $access;
		}

		return $share;
	}

	/**
	 * @psalm-return list<array{principaluri: string, access: int}>
	 */
	public function getShares(): array {
		return array_values($this->shares);
	}
}