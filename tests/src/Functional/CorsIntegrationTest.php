<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CORS provided by DruxtJS.
 *
 * @group druxt
 */
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
  public function testCrossSiteRequestEnabled() {
    $cors_config = $this->container->getParameter('cors.config');
    $this->assertTrue($cors_config['enabled']);
  }

}
