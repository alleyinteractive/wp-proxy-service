<?php
/**
 * Service class file
 *
 * @package wp-proxy-service
 */

declare(strict_types = 1);

namespace Alley\WP\Proxy_Service;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Service class.
 */
class Service {

	/**
	 * Handler key.
	 */
	const HANDLER_KEY = 'wp_proxy_service';

	/**
	 * Set up.
	 */
	public function init(): void {
		add_filter( 'rest_pre_dispatch', [ $this, 'dispatch' ], 10, 3 );
	}

	/**
	 * Get the handler key.
	 *
	 * @return string Handler key.
	 */
	public static function get_handler_key(): string {
		return self::HANDLER_KEY;
	}

	/**
	 * Dispatch the request.
	 *
	 * @param mixed           $result  Response to replace the requested version with. Can be anything
	 *                                 a normal endpoint can return, or null to not hijack the request.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function dispatch( $result, $server, $request ): WP_REST_Response|WP_Error {
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

		return $this->respond_to_request( $request, $route, $handler );
	}

	/**
	 * Match request to handler.
	 *
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return array|WP_Error Array containing the route and handler on success, or WP_Error object on failure.
	 */
	protected function match_request_to_handler( $server, $request ): array|WP_Error {
		$reflection_method = new \ReflectionMethod( $server, 'match_request_to_handler' );
		return $reflection_method->invoke( $server, $request );
	}

	/**
	 * Respond to request.
	 *
	 * @param WP_REST_Request       $request Request used to generate the response.
	 * @param array                 $route Route data.
	 * @param array                 $handler Handler data.
	 * @param WP_REST_Response|null $response Response object on success, or null on failure.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	protected function respond_to_request( $request, $route, $handler, $response = null ) {
		$handler_key = $this->get_handler_key();

		// Check permission specified on the route.
		if ( ! is_wp_error( $response ) && ! empty( $handler['permission_callback'] ) ) {
			$permission = call_user_func( $handler['permission_callback'], $request );

			if ( is_wp_error( $permission ) ) {
				return $permission;
			} elseif ( false === $permission || null === $permission ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'Sorry, you are not allowed to do that.', 'wp-proxy-service' ),
					[ 'status' => rest_authorization_required_code() ]
				);
			}
		}

		if ( empty( $handler[ $handler_key ]['base_url'] ) || empty( $handler[ $handler_key ]['route'] ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'A proxy base URL and route must be specified.', 'wp-proxy-service' ),
				[ 'status' => 500 ]
			);
		}

		$url = trailingslashit( $handler[ $handler_key ]['base_url'] ) . ltrim( $handler[ $handler_key ]['route'], '/\\' );

		$request_args = $request->get_params();

		if ( ! empty( $handler[ $handler_key ]['param_transformation_callback'] ) && is_callable( $handler[ $handler_key ]['param_transformation_callback'] ) ) {
			$request_args = call_user_func( $handler[ $handler_key ]['param_transformation_callback'], $request_args );
		}

		$url = add_query_arg( $request_args, $url );

		$response = vip_safe_wp_remote_get( $url );
		return rest_ensure_response( $response );

	}
}
