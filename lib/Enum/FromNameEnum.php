<?php

namespace OCA\OrganizationFolders\Enum;

trait FromNameEnum {
	public static function fromName(string $name) {
		foreach(self::cases() as $status) {
			if($name === $status->name){
				return $status;
			}
		}
		throw new \ValueError("$name is not a valid value for enum " . self::class );
	}

	public static function fromNameOrValue(int|string $scalar) {
		if(gettype($scalar) == "integer") {
			return self::tryFrom($scalar);
		} else {
			return self::fromName($scalar);
		}
	}
}