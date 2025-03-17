<?php

namespace OCA\OrganizationFolders\Enum;

enum PrincipalType: int {
	use FromNameEnum;

	case USER = 1;
	case GROUP = 2;
	case ORGANIZATION_MEMBER = 3;
	case ORGANIZATION_ROLE = 4;
}
