<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Listener;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\OrganizationFolders\Dav\PropFindPlugin;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

use Psr\Container\ContainerInterface;

class SabrePluginAddListener implements IEventListener {
	public function __construct(private ContainerInterface $container) {}

	public function handle(Event $event): void {
		if ($event instanceof SabrePluginAddEvent) {
			$propFindPlugin = $this->container->get(PropFindPlugin::class);

			$event->getServer()->addPlugin($propFindPlugin);
		}
	}
}