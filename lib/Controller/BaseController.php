<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCA\OrganizationFolders\ApiPermissionsVoter\VoterSubject;
use OCA\OrganizationFolders\AppInfo\Application;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\ValidationFailedException;
use OCA\OrganizationFolders\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class BaseController extends Controller {
	public function __construct(
		protected AuthorizationService $authorizationService,
	) {
		parent::__construct(
			Application::APP_ID,
			\OC::$server->get(IRequest::class),
		);
	}

	/**
	 * Throws an exception unless the actions are granted for the current authentication user
	 *
	 * @param string[]		$actions	The actions
	 * @param VoterSubject	$subject	The subject
	 * @param string		$message    The message passed to the exception
	 *
	 * @throws AccessDenied
	 */
	protected function denyAccessUnlessGrantedAny(array $actions, $subject, array &$scratchpad = [], $message = 'Access Denied.') {
		if (!$this->authorizationService->isGrantedAny($subject, $actions, $scratchpad)) {
			throw new AccessDenied($message);
		}
	}
}
