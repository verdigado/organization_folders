<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use Closure;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

use OCA\OrganizationFolders\Errors\Api\ApiError;

trait Errors {
	protected function errorResponse(\Exception $e, $status = Http::STATUS_BAD_REQUEST): JSONResponse {
		$response = ['error' => get_class($e), 'message' => $e->getMessage()];
		return new JSONResponse($response, $status);
	}

	protected function handleErrors(Closure $callback): JSONResponse {
		try {
			return new JSONResponse($callback());
		} catch (ApiError $e) {
			return $this->errorResponse($e, $e->getHttpCode());
		} catch (\Exception $e) {
			return $this->errorResponse($e);
		}
	}

	protected function handleErrorsWithoutResponseWrapping(Closure $callback) {
		try {
			return $callback();
		} catch (ApiError $e) {
			return $this->errorResponse($e, $e->getHttpCode());
		} catch (\Exception $e) {
			return $this->errorResponse($e);
		}
	}
}