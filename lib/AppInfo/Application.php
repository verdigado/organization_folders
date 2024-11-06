<?php

namespace OCA\OrganizationFolders\AppInfo;

use Psr\Container\ContainerInterface;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\IUserSession;

use OCA\DAV\Events\SabrePluginAddEvent;

use OCA\OrganizationFolders\Listener\SabrePluginAddListener;
use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Security\ResourceVoter;

class Application extends App implements IBootstrap {
	public const APP_ID = 'organization_folders';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(SabrePluginAddEvent::class, SabrePluginAddListener::class);

		$context->registerService(AuthorizationService::class, function (ContainerInterface $c) {
			$service = new AuthorizationService($c->get(IUserSession::class));
			$service->registerVoter($c->get(ResourceVoter::class));
			return $service;
		});
	}

	public function boot(IBootContext $context): void {
	}
}