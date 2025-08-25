<?php

namespace OCA\OrganizationFolders\Errors\Api;

use \OCP\L10N\IFactory;
use OCP\AppFramework\Http;

use OCA\OrganizationFolders\AppInfo\Application;

/**
 * Error typically thrown during an API request or occ command invocation.
 */
abstract class ApiError extends \RuntimeException {

	/**
	 * @param string $message
	 * @param int $httpCode http eror code to be returned to the client when thrown during an API request.
	 * @param mixed $id For errors that need to be recognized and handled in the frontend
	 */
	public function __construct(
		string $message,
		private string $l10nMessage,
		int $httpCode = Http::STATUS_BAD_REQUEST,
		private ?string $id = null,
	) {
		parent::__construct(message: $message, code: $httpCode);
	}

	/**
	 * return both untranslated and translated version of error message
	 * (called t to be recognized by translationtool extraction)
	 * 
	 * @param string $text The text we need a translation for
	 * @param array|string $parameters default:array() Parameters for sprintf
	 * @return array{l10nMessage: string, message: string}
	 */
	protected function t(string $text, $parameters = []): array {
		$l10nFactory = \OCP\Server::get(IFactory::class);
		$l10n = $l10nFactory->get(Application::APP_ID);

		return [
			"message" => vsprintf($text, $parameters),
			"l10nMessage" => $l10n->t($text, $parameters),
		];
	}

	public function getL10nMessage(): string {
		return $this->l10nMessage;
	}
	
	public function getId(): ?string {
		return $this->id;
	}

	public function getHttpCode(): int{
		return $this->getCode();
	}

	/** 
	 * @return array<string, mixed>|null
	 */
	public function getDetails(): ?array {
		return null;
	}
}
