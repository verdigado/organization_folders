<?php

namespace OCA\OrganizationFolders\Enum;

enum MemberType: int {
    use FromNameEnum;

    case USER = 1;
    case GROUP = 2;
    case ROLE = 3;
}
