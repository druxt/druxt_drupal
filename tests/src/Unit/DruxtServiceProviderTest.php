<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\druxt\DruxtServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the DruxtServiceProvider CORS defaults.
 *
 * @group druxt
 */
#[Group('druxt')]
#[CoversClass(DruxtServiceProvider::class)]
class DruxtServiceProviderTest extends TestCase {

  /**
   * Tests that a non-array cors.config is normalized before defaults apply.
   */
  public function testAlterWithNonArrayCorsConfig(): void {
    $container = new ContainerBuilder();
    $container->setParameter('cors.config', NULL);

    (new DruxtServiceProvider())->alter($container);

    $config = $container->getParameter('cors.config');
    $this->assertIsArray($config);
    $this->assertTrue($config['enabled']);
    $this->assertSame(['*'], $config['allowedHeaders']);
  }

  /**
   * Tests that CORS is enabled when the 'enabled' key is missing from config.
   */
  public function testAlterEnablesCorsWhenEnabledKeyIsMissing(): void {
    $container = new ContainerBuilder();
    $container->setParameter('cors.config', ['allowedHeaders' => ['x-custom']]);

    (new DruxtServiceProvider())->alter($container);

    $config = $container->getParameter('cors.config');
    \assert(is_array($config));
    $this->assertTrue($config['enabled']);
    $this->assertSame(['x-custom'], $config['allowedHeaders']);
  }

  /**
   * Tests that CORS defaults are applied when disabled.
   */
  public function testAlterEnablesCorsWhenDisabled(): void {
    $container = new ContainerBuilder();
    $container->setParameter('cors.config', ['enabled' => FALSE]);

    (new DruxtServiceProvider())->alter($container);

    $config = $container->getParameter('cors.config');
    \assert(is_array($config));
    $this->assertTrue($config['enabled']);
    $this->assertSame(['*'], $config['allowedHeaders']);
  }

  /**
   * Tests that a site-owned empty allowedMethods list is left alone.
   *
   * The defaults only apply where the site has not turned CORS on itself. A
   * site that has, and has deliberately left the method list empty, keeps it,
   * because the whole block is skipped rather than each value being filled in.
   */
  public function testAlterDoesNotSetMethodsWhenAlreadyEnabled(): void {
    $container = new ContainerBuilder();
    $container->setParameter('cors.config', ['enabled' => TRUE, 'allowedMethods' => []]);

    (new DruxtServiceProvider())->alter($container);

    $config = $container->getParameter('cors.config');
    \assert(is_array($config));
    $this->assertSame([], $config['allowedMethods']);
  }

  /**
   * Tests that an already-enabled config is left untouched.
   */
  public function testAlterDoesNotOverrideWhenAlreadyEnabled(): void {
    $container = new ContainerBuilder();
    $container->setParameter('cors.config', ['enabled' => TRUE, 'allowedHeaders' => ['x-custom']]);

    (new DruxtServiceProvider())->alter($container);

    $config = $container->getParameter('cors.config');
    \assert(is_array($config));
    $this->assertTrue($config['enabled']);
    $this->assertSame(['x-custom'], $config['allowedHeaders']);
  }

}
