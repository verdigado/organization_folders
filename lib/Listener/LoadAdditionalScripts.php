<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Jonathan Treffler <jonathan.treffler@verdigado.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\OrganizationFolders\Listener;

use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\Services\IInitialState;
use OCP\App\IAppManager;
use OCP\Util;

use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCA\OrganizationFolders\AppInfo\Application;
use OCA\OrganizationFolders\Service\SettingsService;

class LoadAdditionalScripts implements IEventListener {
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly IInitialState $initialState,
		private readonly SettingsService $settingsService,
	) {}

	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		Util::addScript(Application::APP_ID, 'organization_folders-main', 'files');

		$this->initialState->provideInitialState('snapshot_integration_active', $this->appManager->isEnabledForUser("groupfolder_filesystem_snapshots"));
		$this->initialState->provideInitialState('subresources_enabled', $this->settingsService->getAppValue('subresources_enabled'));
	}
}
