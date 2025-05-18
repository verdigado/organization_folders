<?php

namespace OCA\OrganizationFolders\Groups;

use OCP\Group\Backend\ABackend;
use OCP\Group\Backend\ICountUsersBackend;
use OCP\ICacheFactory;
use OCP\IUserManager;
use OCP\IUser;

use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\SettingsService;

class GroupBackend extends ABackend implements ICountUsersBackend {
	public const EVERYONE_GROUP = "everyone";

	public const ORGANIZATION_FOLDER_GROUP_START = "ORGANIZATION_FOLDER_";
	public const ORGANIZATION_FOLDER_GROUP_START_LENGTH = 20;
	public const IMPLIED_INDIVIDUAL_GROUP_END = "_IMPLIED_INDIVIDUAL_MEMBER";
	public const IMPLIED_INDIVIDUAL_GROUP_END_LENGTH = 27;

	public function __construct(
		protected readonly IUserManager $userManager,
		protected readonly ResourceMemberService $resourceMemberService,
		protected readonly SettingsService $settingsService,
		protected ICacheFactory $cacheFactory,
	) {
	}

	/**
	 * Checks whether the user is member of a group or not.
	 * 
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 */
	public function inGroup($uid, $gid): bool {
		if ($gid === self::EVERYONE_GROUP) {
			return true;
		}

		if(str_starts_with($gid, self::ORGANIZATION_FOLDER_GROUP_START)) {
			if(str_ends_with($gid, self::IMPLIED_INDIVIDUAL_GROUP_END)) {
				$organizationFolderId = (int)substr($gid, self::ORGANIZATION_FOLDER_GROUP_START_LENGTH, - self::IMPLIED_INDIVIDUAL_GROUP_END_LENGTH);

				if($organizationFolderId === 0) {
					return false;
				}
				
				return $this->resourceMemberService->isUserIndividualMemberOfTopLevelResourceOfOrganizationFolder($organizationFolderId, $uid);
			}
		}

		return false;
	}


	/**
	 * This function fetches all groups a user belongs to. It does not check
	 * if the user exists at all.
	 * 
	 * @param string $uid Name of the user
	 * @return array an array of group names
	 *
	 */
	public function getUserGroups($uid) {
		if ($uid === null) {
			return [];
		}

		$cache = $this->cacheFactory->createLocal('organization_folders_group_backend_get_user_groups');
		$result = $cache->get($uid);
		if ($result !== null) {
			return json_decode($result);
		}

		$result = [self::EVERYONE_GROUP];

		$organizationFolderIds = $this->resourceMemberService->getIdsOfOrganizationFoldersUserIsTopLevelResourceIndividualMemberIn($uid);

		foreach($organizationFolderIds as $organizationFolderId) {
			$result[] = self::ORGANIZATION_FOLDER_GROUP_START . $organizationFolderId . self::IMPLIED_INDIVIDUAL_GROUP_END;
		}

		$cache->set($uid, json_encode($result), 300);
		return $result;
	}


	/**
	 * Returns a list with all groups meeting criteria
	 * 
	 * @return array an array of group names
	 */
	public function getGroups(string $search = '', ?int $limit = -1, int $offset = 0): array {
		$limit = ($limit < 0) ? null : $limit;

		if($limit === 0) {
			return [];
		}

		$results = [];

		if (strpos(self::EVERYONE_GROUP, $search) !== false) {
			$results[] = self::EVERYONE_GROUP;
		}

		if($this->settingsService->getAppValue("hide_virtual_groups")) {
			// do not check if any of the virtual groups match
			return $results;
		}

		$parts = array_values(array_filter(explode("_", $search)));

		$partsCount = count($parts);

		if($partsCount === 0) {
			return $results;
		}

		$startsWithUnderscore = substr($search, 0, 1) === "_";
		$endsWithUnderscore = substr($search, -1) === "_";

		$idPrefix = false;
		$fullId = false;
		$idSuffix = false;

		$rightmostPartRecognizedSoFar = -1;

		if(!$this->matchPartFuzzy($rightmostPartRecognizedSoFar, $parts[0])) {
			if ((int) $parts[0] !== 0) {
				$rightmostPartRecognizedSoFar = 2;
				if($startsWithUnderscore) {
					$fullId = (int)$parts[0];
				} else {
					$idSuffix = $parts[0];
				}
			} else {
				return $results;
			}
		}
		
		if($partsCount > 1) {
			if($partsCount > 2) {
				for($i = 1; $i < $partsCount - 1; $i++) {
					if(!$this->matchPart($rightmostPartRecognizedSoFar, $parts[$i])) {
						if((int) $parts[$i] !== 0) {
							if($rightmostPartRecognizedSoFar === -1) {
								$rightmostPartRecognizedSoFar = 2;
								$idSuffix = $parts[$i];
							} else if ($rightmostPartRecognizedSoFar === 1) {
								$rightmostPartRecognizedSoFar = 2;
								$fullId = (int)$parts[$i];
							} else {
								return $results;
							}
						} else {
							return $results;
						}
					}
				}
			}

			$lastpart = end($parts);

			if(!$this->matchPartFuzzy($rightmostPartRecognizedSoFar, $lastpart)) {
				if (($rightmostPartRecognizedSoFar === -1 || $rightmostPartRecognizedSoFar === 1) && (int) $lastpart !== 0) {
					$rightmostPartRecognizedSoFar = 2;
					if($endsWithUnderscore) {
						$fullId = (int)$lastpart;
					} else {
						$idPrefix = $lastpart;
					}
				} else {
					return $results;
				}
			}
		}

		if($fullId) {
			if($this->resourceMemberService->hasOrganizationFolderTopLevelResourceIndividualMembers($fullId)) {
				$results[] =  self::ORGANIZATION_FOLDER_GROUP_START . $fullId . self::IMPLIED_INDIVIDUAL_GROUP_END;
			}
		} else {
			$allApplicableIds = $this->resourceMemberService->getIdsOfOrganizationFoldersWithTopLevelResourceIndividualMembers();

			if($idPrefix) {
				foreach($allApplicableIds as $id) {
					if(str_starts_with($id, $idPrefix)) {
						$results[] =  self::ORGANIZATION_FOLDER_GROUP_START . $id . self::IMPLIED_INDIVIDUAL_GROUP_END;
					}
				}
			} else if ($idSuffix) {
				foreach($allApplicableIds as $id) {
					if(str_ends_with($id, $idSuffix)) {
						$results[] =  self::ORGANIZATION_FOLDER_GROUP_START . $id . self::IMPLIED_INDIVIDUAL_GROUP_END;
					}
				}
			} else {
				foreach($allApplicableIds as $id) {
					$results[] =  self::ORGANIZATION_FOLDER_GROUP_START . $id . self::IMPLIED_INDIVIDUAL_GROUP_END;
				}
			}
		}

		return array_slice($results, $offset, $limit);
	}

	private function matchPartFuzzy(int &$rightmostPartRecognizedSoFar, string $part): bool {
		if((strpos("ORGANIZATION", $part) !== false) && ($rightmostPartRecognizedSoFar === -1)) {
			$rightmostPartRecognizedSoFar = 0;
			return true;
		} else if ((strpos("FOLDER", $part) !== false) && ($rightmostPartRecognizedSoFar === -1 || $rightmostPartRecognizedSoFar === 0)) {
			$rightmostPartRecognizedSoFar = 1;
			return true;
		} else if ((strpos("IMPLIED", $part) !== false) && ($rightmostPartRecognizedSoFar === -1 || $rightmostPartRecognizedSoFar === 2)) {
			$rightmostPartRecognizedSoFar = 3;
			return true;
		} else if ((strpos("INDIVIDUAL", $part) !== false) && ($rightmostPartRecognizedSoFar === -1 || $rightmostPartRecognizedSoFar === 3)) {
			$rightmostPartRecognizedSoFar = 4;
			return true;
		} else if ((strpos("MEMBER", $part) !== false) && ($rightmostPartRecognizedSoFar === -1 || $rightmostPartRecognizedSoFar === 4)) {
			$rightmostPartRecognizedSoFar = 5;
			return true;
		} else {
			return false;
		}
	}

	private function matchPart(int &$rightmostPartRecognizedSoFar, $part) {
		if($part === "ORGANIZATION") {
			if($rightmostPartRecognizedSoFar === -1) {
				$rightmostPartRecognizedSoFar = 0;
				return true;
			} else {
				return false;
			}
		} else if ($part === "FOLDER") {
			if($rightmostPartRecognizedSoFar === 0) {
				$rightmostPartRecognizedSoFar = 1;
				return true;
			} else {
				return false;
			}
		} else if ($part === "IMPLIED") {
			if($rightmostPartRecognizedSoFar === 2) {
				$rightmostPartRecognizedSoFar = 3;
				return true;
			} else {
				return false;
			}
		} else if ($part === "INDIVIDUAL") {
			if($rightmostPartRecognizedSoFar === 3) {
				$rightmostPartRecognizedSoFar = 4;
				return true;
			} else {
				return false;
			}
		} else if ($part === "MEMBER") {
			if($rightmostPartRecognizedSoFar === 4) {
				$rightmostPartRecognizedSoFar = 5;
				return true;
			} else {
				return false;
			}
		}
	}


	/**
	 * check if a group exists
	 * @param string $gid
	 * @return bool
	 */
	public function groupExists($gid) {
		if($gid == self::EVERYONE_GROUP) {
			return true;
		}

		if(str_starts_with($gid, self::ORGANIZATION_FOLDER_GROUP_START)) {
			if(str_ends_with($gid, self::IMPLIED_INDIVIDUAL_GROUP_END)) {
				$organizationFolderId = (int)substr($gid, self::ORGANIZATION_FOLDER_GROUP_START_LENGTH, - self::IMPLIED_INDIVIDUAL_GROUP_END_LENGTH);

				if($organizationFolderId === 0) {
					return false;
				}
				
				return $this->resourceMemberService->hasOrganizationFolderTopLevelResourceIndividualMembers($organizationFolderId);
			}
		}

		return false;
	}

	/**
	 * get a list of all users in a group
	 * 
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of user ids
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0): array {
		$limit = ($limit < 0) ? null : $limit;

		if ($gid === self::EVERYONE_GROUP) {
			$users = $this->userManager->search($search, $limit, $offset);

			return array_map(function (IUser $user) {
				return $user->getUID();
			}, $users);
		} else {
			if(str_starts_with($gid, self::ORGANIZATION_FOLDER_GROUP_START)) {
				if(str_ends_with($gid, self::IMPLIED_INDIVIDUAL_GROUP_END)) {
					$organizationFolderId = (int)substr($gid, self::ORGANIZATION_FOLDER_GROUP_START_LENGTH, - self::IMPLIED_INDIVIDUAL_GROUP_END_LENGTH);

					return $this->resourceMemberService->getUserIdsOfOrganizationFolderTopLevelResourceIndividualMembers($organizationFolderId, $limit, $offset);
				}
			}

			return [];
		}
	}

	public function countUsersInGroup(string $gid, string $search = ''): int {
		if ($gid === self::EVERYONE_GROUP) {
			return (int)array_sum($this->userManager->countUsers());
		} else {
			if(str_starts_with($gid, self::ORGANIZATION_FOLDER_GROUP_START)) {
				if(str_ends_with($gid, self::IMPLIED_INDIVIDUAL_GROUP_END)) {
					$organizationFolderId = (int)substr($gid, self::ORGANIZATION_FOLDER_GROUP_START_LENGTH, - self::IMPLIED_INDIVIDUAL_GROUP_END_LENGTH);

					return $this->resourceMemberService->countOrganizationFolderTopLevelResourceIndividualMembers($organizationFolderId);
				}
			}

			return 0;
		}
	}
}
