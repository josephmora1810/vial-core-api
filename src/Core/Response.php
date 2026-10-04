<?php

namespace App\Core;

class Response {
	public static function json(
		array $data = [],
		int $code = 200, 
		string $status = 'success',
		string $message = "",
		?array $meta = null
	){

		http_response_code($code);
		header('Content-type: application/json; charset=utf-8');

		$response = [
			'status' => $status,
			'code' => $code,
			'message' => $message,
			'data' => $data
		];

		if($meta) $response['meta'] = $meta;

		echo json_encode($response);
		exit;
	}
} 
