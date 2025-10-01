<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use InvalidArgumentException;
use OCA\OrganizationFolders\AppInfo\Application;
use OCP\IAppConfig;

class SettingsService {

	private static array $VALID_APP_SETTINGS = ["subresources_enabled", "hide_virtual_groups"];

	private static array $APP_SETTINGS_DEFAULTS = [
		"subresources_enabled" => true,
		"hide_virtual_groups" => false,
	];

	private static array $APP_SETTINGS_TYPES = [
		"subresources_enabled" => IAppConfig::VALUE_BOOL,
		"hide_virtual_groups" => IAppConfig::VALUE_BOOL,
	];
	
	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	public function getAppValues(): array {
		$result = [];
		foreach(self::$VALID_APP_SETTINGS as $key) {
			$type = $this->getKeyType($key);

			if($type == IAppConfig::VALUE_STRING) {
				$result[$key] = $this->appConfig->getValueString(Application::APP_ID, $key, self::$APP_SETTINGS_DEFAULTS[$key] ?? "");
			} else if($type == IAppConfig::VALUE_BOOL) {
				$result[$key] = $this->appConfig->getValueBool(Application::APP_ID, $key, self::$APP_SETTINGS_DEFAULTS[$key] ?? false);
			}
		}
		return $result;
	}

    /**
     * @param string $key
     * @return string value
     * @throws InvalidArgumentException
     */
	public function getAppValue(string $key): bool|string {
		if(in_array($key, self::$VALID_APP_SETTINGS)) {
			$type = $this->getKeyType($key);

			if($type === IAppConfig::VALUE_STRING) {
				return $this->appConfig->getValueString(Application::APP_ID, $key, self::$APP_SETTINGS_DEFAULTS[$key] ?? "");
			} else if($type === IAppConfig::VALUE_BOOL) {
				return $this->appConfig->getValueBool(Application::APP_ID, $key, self::$APP_SETTINGS_DEFAULTS[$key] ?? false);
			}
		}
        throw new InvalidArgumentException("Key '$key' is not a valid settings key.");
	}

    /**
     * @return string new value on success
     * @throws InvalidArgumentException
     */
	public function setAppValue(string $key, bool|string $value): bool|string {
		if(in_array($key, self::$VALID_APP_SETTINGS)) {
			$type = $this->getKeyType($key);

			if($type === IAppConfig::VALUE_STRING) {
				$this->appConfig->setValueString(Application::APP_ID, $key, $value);
			} else if($type === IAppConfig::VALUE_BOOL) {
				$this->appConfig->setValueBool(Application::APP_ID, $key, $value);
			}

			return $value;
		}
        throw new InvalidArgumentException("Key '$key' is not a valid settings key.");
	}

	public function getKeyType(string $key) {
		return self::$APP_SETTINGS_TYPES[$key] ?? IAppConfig::VALUE_STRING;
	}
}