<?php

namespace OCA\OrganizationFolders\Traits;

trait ApiObjectController {
	public const MODEL_INCLUDE = 'model';

	/**
	 * @param string $include
	 *
	 * @return array
	 */
	public function parseIncludesString(?string $include = null): array {
		if (isset($include)) {
			$includes = array_filter(explode('+', $include));

			if (!!$includes) {
				return $includes;
			}
		}

		return [self::MODEL_INCLUDE];
	}

	/**
	 * @param string $test
	 * @param array $includes
	 *
	 * @return boolean
	 */
	public function shouldInclude(string $test, array $includes): bool {
		return in_array($test, $includes);
	}
}