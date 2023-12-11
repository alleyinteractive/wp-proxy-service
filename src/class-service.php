<?php
/**
 * Service class file
 *
 * @package wp-proxy-service
 */

declare(strict_types = 1);

namespace Alley\WP\Proxy_Service;

use ReflectionMethod;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Service class.
 */
class Service {

	/**
	 * Set up.
	 */
	public function init(): void {
		add_filter( 'rest_pre_dispatch', [ $this, 'dispatch' ], 10, 3 );
	}

	/**
	 * Dispatch the request.
	 *
	 * @param WP_REST_Response|WP_Error|null $result Response to replace the requested version with. Can be anything
	 *                                               a normal endpoint can return, or null to not hijack the request.
	 * @param WP_REST_Server                 $server  Server instance.
	 * @param WP_REST_Request                $request Request used to generate the response.
	 * @return WP_REST_Response|WP_Error|null Response object on success, or WP_Error object on failure. Null if returning without proxying.
	 */
	public function dispatch( $result, $server, $request ): WP_REST_Response|WP_Error|null {
		/**
		 * Filter whether to proxy the request.
		 *
		 * @param bool $should_proxy_request Whether to proxy the request.
		 * @param WP_REST_Request $request The request.
		 */
		$should_proxy_request = apply_filters( 'wp_proxy_service_should_proxy_request', false, $request );

		if ( ! $should_proxy_request ) {
			return $result;
		}

		// Match request to route and handler.
		$matched = $this->match_request_to_handler( $server, $request );

		if ( is_wp_error( $matched ) ) {
			return $matched;
		}

		list( $route, $handler ) = $matched;

		// Validate params.
		$check_required = $request->has_valid_params();
		if ( is_wp_error( $check_required ) ) {
			return $check_required;
		}

		// Sanitize params.
		$check_sanitized = $request->sanitize_params();
		if ( is_wp_error( $check_sanitized ) ) {
			return $check_sanitized;
		}

		// Check permission.
		$check_permission = $this->has_permission( $handler, $request );
		if ( is_wp_error( $check_permission ) ) {
			return $check_permission;
		}

		// Build URL.
		$url = $this->get_url( $request );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		// Get request args.
		$request_args = $this->get_request_args( $request );

		// Get response.
		$response = $this->get_response( $request, $url, $request_args );

		return $response;
	}

	/**
	 * Match request to handler.
	 *
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed[]|WP_Error Array containing the route and handler on success, or WP_Error object on failure.
	 */
	protected function match_request_to_handler( WP_REST_Server $server, WP_REST_Request $request ): array|WP_Error {
		$method = new ReflectionMethod( $server, 'match_request_to_handler' );
		return $method->invoke( $server, $request );
	}

	/**
	 * Check permission for request.
	 *
	 * @param mixed[]         $handler The handler.
	 * @param WP_REST_Request $request The request.
	 * @return bool|WP_Error True if the request has permission, WP_Error object otherwise.
	 */
	protected function has_permission( array $handler, WP_REST_Request $request ): bool|WP_Error {
		if ( empty( $handler['permission_callback'] ) ) {
			return true;
		}

		$permission = call_user_func( $handler['permission_callback'], $request );

		if ( is_wp_error( $permission ) ) {
			return $permission;
		}

		if ( false === $permission || null === $permission ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'wp-proxy-service' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Build the destination URL.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return string|WP_Error The URL or WP_Error object on failure.
	 */
	protected function get_url( WP_REST_Request $request ): string|WP_Error {
		/**
		 * Filter the destination URL.
		 *
		 * @param string $url URL.
		 * @param WP_REST_Request $request The request.
		 */
		$url = apply_filters( 'wp_proxy_service_url', '', $request );

		if ( empty( $url ) ) {
			return new WP_Error(
				'missing_destination_url',
				__( 'A destination URL must be specified.', 'wp-proxy-service' ),
				[ 'status' => 500 ]
			);
		}

		$request_params = $this->get_request_params( $request );
		return add_query_arg( $request_params, $url );
	}

	/**
	 * Get request args.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array The request args.
	 */
	protected function get_request_args( WP_REST_Request $request ): array {
		$defaults = [
			'headers' => [],
		];

		/**
		 * Filter the request args.
		 *
		 * @param array $defaults The request args.
		 * @param WP_REST_Request $request The request.
		 */
		return apply_filters( 'wp_proxy_service_request_args', $defaults, $request );
	}

	/**
	 * Get request params.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array Request params.
	 */
	protected function get_request_params( WP_REST_Request $request ): array {
		/**
		 * Filter the request params.
		 *
		 * @param array $params The request params.
		 */
		return apply_filters( 'wp_proxy_service_request_params', $request->get_params() );
	}

	/**
	 * Get response.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $url The URL.
	 * @param array           $args The args. Optional.
	 * @return WP_REST_Response|WP_Error The response.
	 */
	protected function get_response( WP_REST_Request $request, string $url, array $args = [] ): WP_REST_Response|WP_Error {
		$response = vip_safe_wp_remote_get( url: $url, args: $args );

		/**
		 * Filter the response.
		 *
		 * @param array|WP_Error $response The response.
		 * @param WP_REST_Request $request The request.
		 */
		$response = apply_filters( 'wp_proxy_service_response', $response, $request );

		return rest_ensure_response( $response );
	}
}
