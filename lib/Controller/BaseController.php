<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCA\OrganizationFolders\AppInfo\Application;
use OCA\OrganizationFolders\Errors\AccessDenied;
use OCA\OrganizationFolders\Security\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class BaseController extends Controller {
	protected AuthorizationService $authorizationService;

	public function __construct(
	) {
		parent::__construct(
			Application::APP_ID,
			\OC::$server->get(IRequest::class),
		);

		$this->authorizationService = \OC::$server->get(AuthorizationService::class);
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
}
