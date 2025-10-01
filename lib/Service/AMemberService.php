<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use OCP\IUser;
use OCP\IGroup;

use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\UserPrincipal;

abstract class AMemberService {
    /**
	 * @param IUser|UserPrincipal object1
	 * @param IUser|UserPrincipal object2
	 */
	protected function iUserUserPrincipalComparison($object1, $object2): int {
		$value1 = method_exists($object1, "getUID") ? $object1?->getUID() : $object1?->getId();
		$value2 = method_exists($object2, "getUID") ? $object2?->getUID() : $object2?->getId();

		return $value1 <=> $value2;
	}

    /**
     * Returns all users in array1, that are not in array2. Takes both IUser and UserPrincipal objects.
     * 
     * @param IUser[]|UserPrincipal[] $array1
     * @param IUser[]|UserPrincipal[] $array2
     * @return IUser[]|UserPrincipal[]
     */
    protected function userDiff(array $array1, array $array2): array {
        return array_values(array_udiff($array1, $array2, $this->iUserUserPrincipalComparison(...)));
    }

    /**
	 * @param IGroup|GroupPrincipal object1
	 * @param IGroup|GroupPrincipal object2
	 */
	protected function iGroupGroupPrincipalComparison($object1, $object2): int {
		$value1 = method_exists($object1, "getGID") ? $object1?->getGID() : $object1?->getId();
		$value2 = method_exists($object2, "getGID") ? $object2?->getGID() : $object2?->getId();

		return $value1 <=> $value2;
	}

    /**
     * Returns all groups in array1, that are not in array2. Takes both IGroup and GroupPrincipal objects.
     * 
     * @param IGroup[]|GroupPrincipal[] $array1
     * @param IGroup[]|GroupPrincipal[] $array2
     * @return IGroup[]|GroupPrincipal[]
     */
    protected function groupDiff(array $array1, array $array2): array {
        return array_values(array_udiff($array1, $array2, $this->iGroupGroupPrincipalComparison(...)));
    }
}

