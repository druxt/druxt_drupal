<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests CORS provided by DruxtJS.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class CorsIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['druxt'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test CORS is enabled by default.
   */
  public function testCrossSiteRequestEnabled(): void {
    $cors_config = $this->container->getParameter('cors.config');
    $this->assertIsArray($cors_config);
    $this->assertTrue($cors_config['enabled']);
    $this->assertContains('*', $cors_config['allowedHeaders']);
    $this->assertContains('*', $cors_config['allowedMethods']);
  }

  /**
   * Test a simple cross-origin request is allowed.
   */
  public function testSimpleRequest(): void {
    $response = $this->getHttpClient()->request('GET', $this->buildUrl('user/login'), [
      'headers' => ['Origin' => 'http://frontend.test'],
      'http_errors' => FALSE,
    ]);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
  }

  /**
   * Test preflighted requests are allowed.
   *
   * Writes, and reads carrying an Authorization header, are preflighted by
   * the browser and fail unless the response allows the method and header.
   */
  public function testPreflightRequest(): void {
    $cases = [
      'authenticated read' => ['GET', 'authorization'],
      'write' => ['POST', 'content-type'],
    ];
    foreach ($cases as $label => [$method, $header]) {
      $response = $this->getHttpClient()->request('OPTIONS', $this->buildUrl('user/login'), [
        'headers' => [
          'Origin' => 'http://frontend.test',
          'Access-Control-Request-Method' => $method,
          'Access-Control-Request-Headers' => $header,
        ],
        'http_errors' => FALSE,
      ]);
      $this->assertEquals(204, $response->getStatusCode(), $label);
      $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'), $label);
      $this->assertEquals($method, $response->getHeaderLine('Access-Control-Allow-Methods'), $label);
      $this->assertEquals($header, $response->getHeaderLine('Access-Control-Allow-Headers'), $label);
    }
  }

}
