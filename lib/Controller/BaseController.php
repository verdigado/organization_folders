<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCA\OrganizationFolders\AppInfo\Application;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\ValidationFailedException;
use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class BaseController extends Controller {
	public function __construct(
		protected AuthorizationService $authorizationService,
		protected ValidatorService $validatorService,
	) {
		parent::__construct(
			Application::APP_ID,
			\OC::$server->get(IRequest::class),
		);
	}

	/**
	 * Throws an exception unless the attributes are granted for the current authentication user and optionally
	 * supplied subject.
	 *
	 * @param string[] $attributes The attributes
	 * @param mixed    $subject    The subject
	 * @param string[] $attributes Attributes of subject
	 * @param string   $message    The message passed to the exception
	 *
	 * @throws AccessDenied
	 */
	protected function denyAccessUnlessGranted(array $attributes, $subject, $message = 'Access Denied.') {
		if (!$this->authorizationService->isGranted($attributes, $subject)) {
			throw new AccessDenied($message);
		}
	}

	/**
	 * Throws an exception if the payload is not valid for the given DTO class
	 *
	 * @throws ValidationFailedException
	 */
	protected function validate(array $input, string $dtoClass) {
		return $this->validatorService->validateAndCreate($input, $dtoClass);
	}

	protected function createValidationException(string $message) {
		throw new ValidationFailedException($message, []);
	}

}
