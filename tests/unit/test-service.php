<?php
/**
 * Test_Service class file.
 *
 * @package wp-proxy-service
 */

declare(strict_types = 1);

namespace Alley\WP\Proxy_Service\Tests\Unit;

use Alley\WP\Proxy_Service\Service;
use Alley\WP\Proxy_Service\Tests\Mocks\Mock_Http_Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Test_Service extends TestCase {
	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'namespace/v1';

	/**
	 * Route.
	 *
	 * @var string
	 */
	protected $route = '/test';

	/**
	 * Set up.
	 */
	public function setup(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		parent::setup();
	}

	/**
	 * Tear down.
	 */
	public function teardown(): void {
		remove_all_actions( 'rest_api_init' );
		remove_all_filters( 'wp_proxy_service_should_proxy_request' );
		remove_all_filters( 'wp_proxy_service_url' );
		remove_all_filters( 'wp_proxy_service_response' );
		remove_all_filters( 'wp_proxy_service_request_params' );

		parent::teardown();
	}

	/**
	 * Register the REST routes.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			$this->namespace,
			$this->route,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => '',
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Data provider for test_dispatch method.
	 *
	 * @return array Array of data.
	 */
	public function data_test_dispatch(): array {
		return [
			'should not proxy' => [
				[
					'should_proxy_callback' => '__return_false',
				],
				'is_null',
			],
			'should proxy' => [
				[
					'should_proxy_callback' => '__return_true',
				],
				fn( $value ): bool => $value instanceof WP_REST_Response,
			],
		];
	}

	/**
	 * Test the functionality of the dispatch method.
	 *
	 * @dataProvider data_test_dispatch
	 *
	 * @param array $original The context to test.
	 * @param mixed $expected The expected result.
	 */
	public function test_dispatch( array $original, $expected ): void {
		$server  = rest_get_server();
		$service = new Service();
		$service->init();

		$mock = new Mock_Http_Response();
		$mock->intercept_next_request()
			->with_body( 'pear' );

		add_filter( 'wp_proxy_service_should_proxy_request', $original['should_proxy_callback'] );
		add_filter( 'wp_proxy_service_url', fn() => 'https://example.org' );

		$request = new WP_REST_Request( 'GET', "/{$this->namespace}{$this->route}" );

		$result = $service->dispatch( null, $server, $request );

		$this->assertTrue( $expected( $result ) );
	}

	/**
	 * Data provider for test_get_response method.
	 *
	 * @return array Array of data.
	 */
	public function data_test_get_response(): array {
		return [
			'unfiltered response' => [
				[
					'body'            => 'apple',
					'filter_callback' => fn( $value ) => $value,
				],
				'apple',
			],
			'filtered response'   => [
				[
					'body'            => 'orange',
					'filter_callback' => function ( $value ) {
						$value['body'] = 'peach';
						return $value;
					},
				],
				'peach',
			],
		];
	}

	/**
	 * Test the functionality of the get_response method.
	 *
	 * @dataProvider data_test_get_response
	 *
	 * @param array  $original The context to test.
	 * @param string $expected The expected result.
	 */
	public function test_get_response( array $original, string $expected ): void {
		$service = new Service();
		$service->init();

		$class  = new ReflectionClass( 'Alley\WP\Proxy_Service\Service' );
		$method = $class->getMethod( 'get_response' );
		$method->setAccessible( true );

		$mock = new Mock_Http_Response();
		$mock->intercept_next_request()
			->with_body( $original['body'] );

		$request = new WP_REST_Request( 'GET', "/{$this->namespace}{$this->route}" );

		add_filter( 'wp_proxy_service_response', $original['filter_callback'] );

		$result = $method->invoke( $service, $request, 'https://example.org' );
		$body   = $result->get_data()['body'] ?? '';

		$this->assertEquals( $expected, $body );
	}

	/**
	 * Data provider for test_get_url method.
	 *
	 * @return array Array of data.
	 */
	public function data_test_get_url(): array {
		return [
			'unfiltered url' => [
				[
					'filter_callback' => fn( string $value ): string => $value,
				],
				'is_wp_error',
			],
			'filtered url'   => [
				[
					'filter_callback' => fn( string $value ): string => 'https://example.com',
				],
				fn( $value ): bool => 'https://example.com' === $value,
			],
		];
	}

	/**
	 * Test the functionality of the get_url method.
	 *
	 * @dataProvider data_test_get_url
	 *
	 * @param array  $original The context to test.
	 * @param callable $expected Booleann callback to test the expected result.
	 */
	public function test_get_url( array $original, $expected ): void {
		$service = new Service();
		$service->init();

		$class  = new ReflectionClass( 'Alley\WP\Proxy_Service\Service' );
		$method = $class->getMethod( 'get_url' );
		$method->setAccessible( true );

		$request = new WP_REST_Request( 'GET', "/{$this->namespace}{$this->route}" );

		add_filter( 'wp_proxy_service_url', $original['filter_callback'] );
		$result = $method->invoke( $service, $request );

		$this->assertTrue( $expected( $result ) );
	}

	/**
	 * Data provider for test_get_request_params method.
	 *
	 * @return array Array of data.
	 */
	public function data_test_get_request_params(): array {
		return [
			'unfiltered params' => [
				[
					'key'             => 'poultry',
					'value'           => 'chicken',
					'filter_callback' => fn( array $value ): array => $value,
				],
				[
					'poultry' => 'chicken',
				],
			],
			'filtered params'   => [
				[
					'key'             => 'poultry',
					'value'           => 'chicken',
					'filter_callback' => fn( array $value ): array => [ 'poultry' => 'turkey' ],
				],
				[
					'poultry' => 'turkey',
				],
			],
		];
	}

	/**
	 * Test the functionality of the get_request_params method.
	 *
	 * @dataProvider data_test_get_request_params
	 *
	 * @param array $original The context to test.
	 * @param array $expected The expected result.
	 */
	public function test_get_request_params( array $original, array $expected ): void {
		$service = new Service();
		$service->init();

		$class  = new ReflectionClass( 'Alley\WP\Proxy_Service\Service' );
		$method = $class->getMethod( 'get_request_params' );
		$method->setAccessible( true );

		$request = new WP_REST_Request( 'GET', "/{$this->namespace}{$this->route}" );
		$request->set_param( $original['key'], $original['value'] );

		add_filter( 'wp_proxy_service_request_params', $original['filter_callback'] );

		$result = $method->invoke( $service, $request );

		$this->assertEquals( $expected, $result );
	}
}
