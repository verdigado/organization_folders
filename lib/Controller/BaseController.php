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
	 * Throws an exception unless the action is granted for the current authentication user
	 *
	 * @param VoterSubject $subject The subject
	 * @param string $action The actions
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 *
	 * @throws AccessDenied
	 */
	protected function denyAccessUnlessGranted(VoterSubject $subject, string $action, array &$scratchpad = [], array $criterionTypeBlocklist = []) {
		if (!$this->authorizationService->isGranted($subject, $action, $scratchpad, $criterionTypeBlocklist)) {
			throw new AccessDenied();
		}
	}

	/**
	 * Throws an exception unless the any of the actions are granted for the current authentication user
	 *
	 * @param VoterSubject $subject The subject
	 * @param string[] $actions The actions
	 *
	 * @throws AccessDenied
	 */
	protected function denyAccessUnlessGrantedAny(VoterSubject $subject, array $actions, array &$scratchpad = []) {
		if (!$this->authorizationService->isGrantedAny($subject, $actions, $scratchpad)) {
			throw new AccessDenied();
		}
	}
}
