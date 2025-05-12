<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Listener;

use Psr\Container\ContainerInterface;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

use OCA\DAV\Events\SabrePluginAddEvent;

use OCA\OrganizationFolders\Dav\PropFindPlugin;
use OCA\OrganizationFolders\Dav\PermissionsPlugin;

class SabrePluginAddListener implements IEventListener {
	public function __construct(
		private readonly ContainerInterface $container,
	) {}

	public function handle(Event $event): void {
		if ($event instanceof SabrePluginAddEvent) {
			$propFindPlugin = $this->container->get(PropFindPlugin::class);
			$permissionsPlugin = $this->container->get(PermissionsPlugin::class);

			$event->getServer()->addPlugin($propFindPlugin);
			$event->getServer()->addPlugin($permissionsPlugin);
		}
	}
}