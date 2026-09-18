<?php

class Response
{
	public static function json(
		bool $success,
		string $message = '',
		array $data = [],
		int $statusCode = 200
	): void {
		http_response_code($statusCode);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'success' => $success,
			'message' => $message,
			'data' => $data
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		exit;
	}

	public static function success(
		string $message = 'Success',
		array $data = [],
		int $statusCode = 200
	): void {
		self::json(true, $message, $data, $statusCode);
	}

	public static function error(
		string $message = 'Error',
		int $statusCode = 400,
		array $data = []
	): void {
		self::json(false, $message, $data, $statusCode);
	}

	public static function file(
		string $filePath,
		string $contentType = 'image/jpeg'
	): void {
		if (!file_exists($filePath)) {
			self::error('File not found', 404);
			return;
		}

		header('Access-Control-Allow-Origin: *');
		header('Content-Type: ' . $contentType);
		header('Content-Length: ' . filesize($filePath));
		header('Cache-Control: public, max-age=86400');

		readfile($filePath);
		exit;
	}
}