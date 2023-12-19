<?php
/**
 * This file contains the Mock_Http_Response class
 *
 * @package wp-proxy-service
 */

declare(strict_types = 1);

namespace Alley\WP\Proxy_Service\Tests\Mocks;

/**
 * This class provides a mock HTTP response to be able to simulate HTTP requests
 * in WordPress.
 *
 * Example:
 *
 *     $mock = new Mock_Http_Response();
 *     $mock->intercept_next_request()
 *         ->with_response_code( 404 )
 *         ->with_body( '{"error":true}' )
 *         ->with_header( 'Content-Type', 'application/json' );
 */
class Mock_Http_Response {
	/**
	 * Response data.
	 *
	 * @var array
	 */
	public $response = [];

	/**
	 * Mock_Http_Response constructor.
	 */
	public function __construct() {
		$this->response = [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 200,
				'message' => get_status_header_desc( 200 ),
			],
			'cookies'  => [],
			'filename' => '',
		];

		$this->intercept_next_request();
	}

	/**
	 * Add a header to the response.
	 *
	 * @param string $key   Header key.
	 * @param string $value Header value.
	 * @return Mock_Http_Response This object.
	 */
	public function with_header( string $key, string $value ): Mock_Http_Response {
		$this->response['headers'][ $key ] = $value;

		return $this;
	}

	/**
	 * Set the response code. The response message will be inferred from that.
	 *
	 * @param int $code HTTP response code.
	 * @return Mock_Http_Response This object.
	 */
	public function with_response_code( int $code ): Mock_Http_Response {
		$this->response['response'] = [
			'code'    => $code,
			'message' => get_status_header_desc( $code ),
		];

		return $this;
	}

	/**
	 * Set the response body.
	 *
	 * @param string $body Response body.
	 * @return Mock_Http_Response This object.
	 */
	public function with_body( string $body ): Mock_Http_Response {
		$this->response['body'] = $body;

		return $this;
	}

	/**
	 * Set a response cookie.
	 *
	 * @param \WP_Http_Cookie $cookie Cookie.
	 * @return Mock_Http_Response This object.
	 */
	public function with_cookie( \WP_Http_Cookie $cookie ): Mock_Http_Response {
		$this->response['cookies'][] = $cookie;

		return $this;
	}

	/**
	 * Set the filename value for the mock response.
	 *
	 * @param string $filename Filename.
	 * @return Mock_Http_Response This object.
	 */
	public function with_filename( string $filename ): Mock_Http_Response {
		$this->response['filename'] = $filename;

		return $this;
	}

	/**
	 * Filters pre_http_request to intercept the request, mock a response, and
	 * return it. If the response has already been preempted, the preempt will
	 * be returned instead. Regardless, this object unhooks itself from the
	 * pre_http_request filter.
	 *
	 * @param false|array|\WP_Error $preempt      Whether to preempt an HTTP request's return value. Default false.
	 * @param array                 $request_args HTTP request arguments.
	 * @param string                $url          The request URL.
	 * @return mixed Array if the request has been preempted, any value that's
	 *               not false otherwise.
	 */
	public function pre_http_request( $preempt, $request_args, $url ) {
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request' ], PHP_INT_MAX );
		return false === $preempt
			? $this->with_header( 'x-req-url', $url )->to_array()
			: $preempt;
	}

	/**
	 * Returns the combined response array.
	 *
	 * @return array WP_Http response array, per WP_Http::request().
	 */
	public function to_array() {
		return $this->response;
	}

	/**
	 * Add the filter to intercept the next request.
	 *
	 * @return Mock_Http_Response This object.
	 */
	public function intercept_next_request(): Mock_Http_Response {
		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], PHP_INT_MAX, 3 );

		return $this;
	}
}
