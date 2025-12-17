<?php

namespace OCA\OrganizationFolders\DTO;

use Respect\Validation\Validator as v;
use Respect\Validation\ChainedValidator;

readonly class CreateOrganizationFolderDto {
	public function __construct(
		public string $name,
		public ?int $quota = null,
		public ?string $organizationProviderId = null,
		public ?int $organizationId = null,
	) {}

	public static function GetValidator(): ChainedValidator {
		return v::create()
			->key('name', v::stringType())
			->key('quota', v::nullable(v::intType()))
			->oneOf(
				 v::allOf(
					v::key('organizationProviderId', v::nullType()),
					v::key('organizationId', v::nullType())
				),
				v::allOf(
					v::key('organizationProviderId', v::stringType()),
					v::key('organizationId', v::intType())
				),
			);
	}
}
