<?php

namespace OCA\OrganizationFolders\AppInfo;

use Psr\Container\ContainerInterface;
use Respect\Validation\Factory;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\IUserSession;
use OCP\IGroupManager;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCA\OrganizationFolders\Listener\SabrePluginAddListener;
use OCA\OrganizationFolders\Listener\LoadAdditionalScripts;
use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Security\ResourceVoter;
use OCA\OrganizationFolders\Security\OrganizationFolderVoter;
use OCA\OrganizationFolders\Groups\GroupBackend;

class Application extends App implements IBootstrap {
	public const APP_ID = 'organization_folders';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScripts::class);
		$context->registerEventListener(SabrePluginAddEvent::class, SabrePluginAddListener::class);

		$context->registerService(AuthorizationService::class, function (ContainerInterface $c) {
			$service = new AuthorizationService($c->get(IUserSession::class));
			$service->registerVoter($c->get(OrganizationFolderVoter::class));
			$service->registerVoter($c->get(ResourceVoter::class));
			return $service;
		});

		$this->setupValidation();
	}

	private function setupValidation() {
		Factory::setDefaultInstance(
			(new Factory())
				->withRuleNamespace('OCA\\OrganizationFolders\\Validation\\Rules')
				->withExceptionNamespace('OCA\\OrganizationFolders\\Validation\\Exceptions')
		);
	}


	public function boot(IBootContext $context): void {
		$context->injectFn([$this, 'registerGroupManager']);
	}

	public function registerGroupManager(IGroupManager $groupManager, GroupBackend $backend) {
		$groupManager->addBackend($backend);
	}
}