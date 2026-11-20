<?php

namespace OCA\OrganizationFolders\Enum;

enum FilterType: string {
	case TEXT = 'text';
	case SELECT = 'select';
	case BOOLEAN = 'boolean';
}
