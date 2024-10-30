<?php

namespace OCA\OrganizationFolders\Enum;

enum MemberPermissionLevel: int {
    use FromNameEnum;
    
    case MEMBER = 1;
    case MANAGER = 2;
}
