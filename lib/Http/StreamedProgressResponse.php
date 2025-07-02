<?php
namespace OCA\OrganizationFolders\Http;

use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;

class StreamedProgressResponse extends Response implements ICallbackResponse {

	public function __construct(private $startStreamingCallback) {
		$this->setHeaders([
			"X-Accel-Buffering" => "no",
			"Content-Type" => "text/json; charset=utf-8",
			"Cache-Control" => "no-cache",
		]);

		parent::__construct();
	}

	public function callback(IOutput $output) {
		set_time_limit(0);
		ob_implicit_flush(1);
		while (ob_get_level() > 0) {
			ob_end_flush();
		}

		($this->startStreamingCallback)($output);
	}

}