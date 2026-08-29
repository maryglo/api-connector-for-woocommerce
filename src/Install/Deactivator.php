<?php

declare(strict_types=1);

namespace WooCommerceApiConnector\Install;

use WooCommerceApiConnector\Plugin;

defined('ABSPATH') || exit;

/**
 * Runs on plugin deactivation. Leaves options and logs intact.
 */
final class Deactivator
{
	public static function deactivate(): void
	{
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions(Plugin::JOB_HOOK);
			as_unschedule_all_actions(Plugin::PRUNE_HOOK);
		}
	}
}
