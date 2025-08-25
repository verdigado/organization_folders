<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use Closure;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

use OCA\OrganizationFolders\Errors\Api\ApiError;

trait Errors {
	protected function errorResponse(
		string $class,
		string $message,
		string $l10nMessage,
		$status = Http::STATUS_BAD_REQUEST,
		?array $details = null,
		?string $id = null,
	): JSONResponse {
		$response = [
			'class' => $class,
			'message' => $message,
			'l10nMessage' => $l10nMessage,
		];

		if(isset($id)) {
			$response["id"] = $id;
		}

		if(isset($details)) {
			$response["details"] = $details;
		}

		return new JSONResponse($response, $status);
	}

	protected function handleErrors(Closure $callback): JSONResponse {
		try {
			return new JSONResponse($callback());
		} catch (ApiError $e) {
			return $this->errorResponse(get_class($e), $e->getMessage(), $e->getL10nMessage(), $e->getHttpCode(), $e->getDetails(), $e->getId());
		} catch (\Exception $e) {
			return $this->errorResponse(get_class($e), $e->getMessage(), $e->getMessage());
		}
	}

	protected function handleErrorsWithoutResponseWrapping(Closure $callback) {
		try {
			return $callback();
		} catch (ApiError $e) {
			return $this->errorResponse(get_class($e), $e->getMessage(), $e->getL10nMessage(), $e->getHttpCode(), $e->getDetails(), $e->getId());
		} catch (\Exception $e) {
			return $this->errorResponse(get_class($e), $e->getMessage(), $e->getMessage());
		}
	}
}