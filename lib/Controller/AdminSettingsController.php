<?php

namespace OCA\OrganizationFolders\Controller;

use OCA\OrganizationFolders\Service\SettingsService;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminSettingsController extends Controller {
	public function __construct(
        $appName,
        IRequest $request,
		private SettingsService $settingsService,
	) {
		parent::__construct($appName, $request);
	}

    /**
     * @return JSONResponse
     */
    public function index(): JSONResponse {
        return new JSONResponse($this->settingsService->getAppValues());
    }

    /**
     * @param $key
     *
     * @return JSONResponse
     */
    public function show($key): JSONResponse {
        return new JSONResponse($this->settingsService->getAppValue($key));
    }

    /**
     * @param $key
     * @param $value
     *
     * @return JSONResponse
     */
    public function update($key, $value): JSONResponse {
		return new JSONResponse($this->settingsService->setAppValue($key, $value));
    }
}