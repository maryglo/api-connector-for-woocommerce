<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Tests\Jobs;

use PHPUnit\Framework\TestCase;
use WooCommerceApiConnector\Jobs\SendIntegrationJob;

final class SendIntegrationJobTest extends TestCase
{
	public function test_retry_schedule_matches_the_v01_contract(): void
	{
		self::assertSame(60, SendIntegrationJob::retry_delay_for_attempt(1));
		self::assertSame(300, SendIntegrationJob::retry_delay_for_attempt(2));
		self::assertNull(SendIntegrationJob::retry_delay_for_attempt(3));
	}

	public function test_invalid_attempt_numbers_never_schedule_a_retry(): void
	{
		self::assertNull(SendIntegrationJob::retry_delay_for_attempt(0));
		self::assertNull(SendIntegrationJob::retry_delay_for_attempt(4));
	}
}
