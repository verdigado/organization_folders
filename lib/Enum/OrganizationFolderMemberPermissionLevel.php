<?php

namespace OCA\OrganizationFolders\Enum;

enum OrganizationFolderMemberPermissionLevel: int {
    use FromNameEnum;
    
    case MEMBER = 1;
    case MANAGER = 2;
    case ADMIN = 3;
}
