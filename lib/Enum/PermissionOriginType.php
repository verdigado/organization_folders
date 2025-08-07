<?php

namespace OCA\OrganizationFolders\Enum;

enum PermissionOriginType: int {
	case MEMBER = 1;
	case MANAGER = 2;
	case INHERITED_MEMBER = 3;
	case INHERITED_MANAGER = 4;
}
