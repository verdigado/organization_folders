<?php

namespace OCA\OrganizationFolders\Enum;

enum ResourceMemberPermissionLevel: int {
    use FromNameEnum;
    
    case MEMBER = 1;
    case MANAGER = 2;
}
