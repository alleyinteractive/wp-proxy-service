<?php
/**
 * WP Proxy Service Test Bootstrap
 */

declare(strict_types = 1);

use function Mantle\Testing\manager;

manager()
	->maybe_rsync_plugin()
	->install();
