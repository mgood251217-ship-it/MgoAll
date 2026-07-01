<?php

class AuthMiddleware
{
	public function handle($permission = null): void
	{
		require_once __DIR__ . '/../session.php';
	}
}