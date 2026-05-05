<?php

namespace OCA\OrganizationFolders\Interface;

use OCP\IL10N;

interface TableSerializable {
	/**
	 * Serializes the object to a dict array to be rendered into a occ command output table
	 * @return array<string, string>
	 */
	function tableSerialize(IL10N $l10n, ?array $params = null);
}