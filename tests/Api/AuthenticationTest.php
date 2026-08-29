<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Tests\Api;

use PHPUnit\Framework\TestCase;
use WooCommerceApiConnector\Api\Authentication\ApiKeyAuth;
use WooCommerceApiConnector\Api\Authentication\BearerAuth;

final class AuthenticationTest extends TestCase
{
	public function test_bearer_auth_builds_authorization_header(): void
	{
		self::assertSame(
			array( 'Authorization' => 'Bearer secret-token' ),
			BearerAuth::headers(' secret-token ')
		);
	}

	public function test_empty_bearer_token_produces_no_header(): void
	{
		self::assertSame(array(), BearerAuth::headers('   '));
	}

	public function test_api_key_auth_uses_the_configured_header(): void
	{
		self::assertSame(
			array( 'X-Store-Key' => 'key-123' ),
			ApiKeyAuth::headers(' key-123 ', ' X-Store-Key ')
		);
	}

	public function test_empty_api_key_produces_no_header(): void
	{
		self::assertSame(array(), ApiKeyAuth::headers('', 'X-API-Key'));
	}
}
