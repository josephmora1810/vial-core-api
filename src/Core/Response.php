<?php

namespace App\Core;

class Response {
	public static bool $shouldExit = true;
	public static function json(
		array $data = [],
		int $code = 200, 
		string $status = 'success',
		string $message = "",
		?array $meta = null
	){

		http_response_code($code);
		if (!headers_sent()) {
			header('Content-type: application/json; charset=utf-8');
		}

		$response = [
			'status' => $status,
			'code' => $code,
			'message' => $message,
			'data' => $data
		];

		if($meta) $response['meta'] = $meta;

		echo json_encode($response);
		if (self::$shouldExit) {
			exit;
		}
	}

	public static function rawJson(array $data, int $code = 200): void
	{
		http_response_code($code);
		if (!headers_sent()) {
			header('Content-type: application/json; charset=utf-8');
		}

		echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (self::$shouldExit) {
			exit;
		}
	}
}
