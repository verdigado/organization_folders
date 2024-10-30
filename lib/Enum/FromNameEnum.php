<?php

namespace OCA\OrganizationFolders\Enum;

trait FromNameEnum {
    public static function fromName(string $name): string {
        foreach (self::cases() as $status) {
            if( $name === $status->name ){
                return $status->value;
            }
        }
        throw new \ValueError("$name is not a valid value for enum " . self::class );
    }
}