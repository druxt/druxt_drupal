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
  }

}
