<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that configuration cannot expose a content entity resource.
 *
 * The settings form only offers configuration entities, but configuration
 * can also arrive by import or by hand, which the form never sees. Being on
 * the list grants a blanket entity access result, so the rule has to hold
 * wherever the value comes from.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtResourceValidationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'serialization',
    'field',
    'file',
    'text',
    'filter',
    'path_alias',
    'decoupled_router',
    'jsonapi',
    'views',
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['druxt']);
  }

  /**
   * Validates a resource list against the configuration schema.
   *
   * @param string[] $resources
   *   The resource list to validate.
   *
   * @return int
   *   The number of constraint violations.
   */
  private function violations(array $resources): int {
    $typed = \Drupal::service('config.typed')
      ->createFromNameAndData('druxt.settings', ['resources' => $resources]);
    return count($typed->validate());
  }

  /**
   * Tests that a configuration entity resource validates.
   */
  public function testConfigEntityResourceValidates(): void {
    $this->assertSame(0, $this->violations(['view--view']));
  }

  /**
   * Tests that a content entity resource does not validate.
   */
  public function testContentEntityResourceDoesNotValidate(): void {
    $this->assertSame(1, $this->violations(['user--user']));
  }

  /**
   * Tests that the grandfathered resource validates.
   */
  public function testGrandfatheredResourceValidates(): void {
    $this->assertSame(0, $this->violations(['menu_link_content--menu_link_content']));
  }

  /**
   * Tests that a resource for an uninstalled module validates.
   *
   * The form cannot offer it and saving must not drop it, so validation
   * must not reject it either. It grants nothing, because a route for it
   * does not exist on this site.
   */
  public function testResourceForUninstalledModuleValidates(): void {
    $this->assertSame(0, $this->violations(['comment_type--comment_type']));
  }

  /**
   * Tests that an empty entry validates.
   *
   * It names no entity type, so it grants nothing. Refusing it would block
   * an import over a value that does nothing.
   */
  public function testEmptyResourceValidates(): void {
    $this->assertSame(0, $this->violations(['']));
  }

  /**
   * Tests that the shipped default validates.
   */
  public function testShippedDefaultValidates(): void {
    $this->assertSame(0, $this->violations(druxt_default_resources()));
  }

}
