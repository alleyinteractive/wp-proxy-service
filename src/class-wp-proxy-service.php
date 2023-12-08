<?php
/**
 * WP_Proxy_Service class file
 *
 * @package wp-proxy-service
 */

declare(strict_types = 1);

namespace Alley\WP\Proxy_Service;

/**
 * WP_Proxy_Service class.
 */
class WP_Proxy_Service {

	public function init(): void {
		add_filter( 'rest_request_before_callbacks', [ $this, 'filter_rest_request_before_callbacks' ], 10, 3 );
	}

	public function filter_rest_request_before_callbacks( $return, $request, $route ) {
		return $response;
	}
}
