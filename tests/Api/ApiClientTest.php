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
			'base_url'       => 'https://api.example.com',
			'auth_method'    => 'api_key',
			'token'          => 'api-key',
			'api_key_header' => 'X-Store-Key',
		));

		$client->request('GET', 'https://api.example.com/health');

		self::assertSame('api-key', WcacHttpTestTransport::$last_request['args']['headers']['X-Store-Key']);
		self::assertArrayNotHasKey('Content-Type', WcacHttpTestTransport::$last_request['args']['headers']);
	}

	public function test_head_requests_are_allowed_without_a_json_body(): void
	{
		$client = new ApiClient(array(
			'base_url'    => 'https://api.example.com',
			'auth_method' => 'bearer',
			'token'       => 'bearer-token',
		));

		WcacHttpTestTransport::$next_response = array(
			'code'    => 200,
			'body'    => '',
			'headers' => array(),
		);

		$response = $client->request('HEAD', 'https://api.example.com/health');

		self::assertTrue($response->is_success());
		self::assertSame('HEAD', WcacHttpTestTransport::$last_request['args']['method']);
		self::assertArrayNotHasKey('body', WcacHttpTestTransport::$last_request['args']);
		self::assertArrayNotHasKey('Content-Type', WcacHttpTestTransport::$last_request['args']['headers']);
	}

	public function test_transport_errors_are_normalized(): void
	{
		WcacHttpTestTransport::$next_response = new WP_Error('Connection timed out.');

		$response = (new ApiClient(array(
			'base_url' => 'https://api.example.com',
		)))->request('GET', 'https://api.example.com/health');

		self::assertTrue($response->is_transport_error());
		self::assertSame(0, $response->status_code);
		self::assertSame('Unable to connect to the configured API.', $response->error_message);
	}

	public function test_public_http_urls_are_rejected(): void
	{
		$client = new ApiClient(array(
			'base_url' => 'http://api.example.com',
			'token'    => 'secret',
		));

		$response = $client->request('GET', 'http://api.example.com/health');

		self::assertTrue($response->is_transport_error());
		self::assertSame(array(), WcacHttpTestTransport::$last_request);
		self::assertSame('The API URL is invalid or not allowed.', $response->error_message);
	}

	public function test_local_http_urls_are_allowed(): void
	{
		$client = new ApiClient(array(
			'base_url'    => 'http://localhost:8080',
			'auth_method' => 'bearer',
			'token'       => 'local-token',
		));

		$response = $client->request('GET', 'http://localhost:8080/health');

		self::assertTrue($response->is_success());
		self::assertSame('http://localhost:8080/health', WcacHttpTestTransport::$last_request['url']);
		self::assertSame('Bearer local-token', WcacHttpTestTransport::$last_request['args']['headers']['Authorization']);
	}

	public function test_private_network_http_urls_are_allowed(): void
	{
		$client = new ApiClient(array(
			'base_url' => 'http://192.168.1.20:3000',
		));

		$response = $client->request('GET', 'http://192.168.1.20:3000/health');

		self::assertTrue($response->is_success());
		self::assertSame('http://192.168.1.20:3000/health', WcacHttpTestTransport::$last_request['url']);
	}

	public function test_logged_bodies_are_truncated_at_64kb(): void
	{
		$short = str_repeat('a', ApiClient::MAX_BODY_LENGTH);
		self::assertSame($short, ApiClient::truncate_logged_body($short));

		$truncated = ApiClient::truncate_logged_body($short . 'b');
		self::assertSame(ApiClient::MAX_BODY_LENGTH + 3, strlen($truncated));
		self::assertStringEndsWith('…', $truncated);

		WcacHttpTestTransport::$next_response = array(
			'code'    => 200,
			'body'    => str_repeat('x', ApiClient::MAX_BODY_LENGTH + 10),
			'headers' => array(),
		);

		$response = (new ApiClient(array(
			'base_url' => 'https://api.example.com',
		)))->request('GET', 'https://api.example.com/health');
		self::assertSame(ApiClient::MAX_BODY_LENGTH + 3, strlen($response->body));
	}
}
