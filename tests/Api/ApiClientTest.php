<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Tests\Api;

use PHPUnit\Framework\TestCase;
use WcacHttpTestTransport;
use WP_Error;
use WooCommerceApiConnector\Api\ApiClient;

final class ApiClientTest extends TestCase
{
	protected function setUp(): void
	{
		WcacHttpTestTransport::$last_request  = array();
		WcacHttpTestTransport::$next_response = array(
			'code'    => 201,
			'body'    => '{"id":"remote-1"}',
			'headers' => array( 'content-type' => 'application/json' ),
		);
	}

	public function test_request_sends_a_json_body_with_bearer_auth(): void
	{
		$client   = new ApiClient(array(
			'base_url'    => 'https://api.example.com/',
			'auth_method' => 'bearer',
			'token'       => 'bearer-token',
		));
		$response = $client->request('post', $client->endpoint_url('/orders'), array( 'order_id' => 123 ));

		self::assertTrue($response->is_success());
		self::assertSame(201, $response->status_code);
		self::assertSame('https://api.example.com/orders', WcacHttpTestTransport::$last_request['url']);
		self::assertSame('POST', WcacHttpTestTransport::$last_request['args']['method']);
		self::assertSame('application/json', WcacHttpTestTransport::$last_request['args']['headers']['Content-Type']);
		self::assertSame('Bearer bearer-token', WcacHttpTestTransport::$last_request['args']['headers']['Authorization']);
		self::assertSame('{"order_id":123}', WcacHttpTestTransport::$last_request['args']['body']);
	}

	public function test_request_sends_the_configured_api_key_header(): void
	{
		$client = new ApiClient(array(
			'auth_method'    => 'api_key',
			'token'          => 'api-key',
			'api_key_header' => 'X-Store-Key',
		));

		$client->request('GET', 'https://api.example.com/health');

		self::assertSame('api-key', WcacHttpTestTransport::$last_request['args']['headers']['X-Store-Key']);
		self::assertArrayNotHasKey('Content-Type', WcacHttpTestTransport::$last_request['args']['headers']);
	}

	public function test_transport_errors_are_normalized(): void
	{
		WcacHttpTestTransport::$next_response = new WP_Error('Connection timed out.');

		$response = (new ApiClient(array()))->request('GET', 'https://api.example.com/health');

		self::assertTrue($response->is_transport_error());
		self::assertSame(0, $response->status_code);
		self::assertSame('Connection timed out.', $response->error_message);
	}
}
