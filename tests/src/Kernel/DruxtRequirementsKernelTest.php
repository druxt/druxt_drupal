<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests what the status report says about the exposed resources.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtRequirementsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Deliberately without language and jsonapi_extras, so the entity types
   * behind configurable_language and jsonapi_resource_config are absent. That
   * is the ordinary shape of a site, and it is the case under test.
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
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['druxt']);
    \Drupal::moduleHandler()->loadInclude('druxt', 'install');
  }

  /**
   * Tests that a resource for an uninstalled module is not called missing.
   *
   * The shipped list names resources this site cannot have, because the same
   * configuration is shared with sites that do have the module. Reporting
   * them would put a permanent warning on every site that does not run
   * Language or JSON:API Extras, saying a frontend may not work when nothing
   * is wrong.
   */
  public function testAbsentEntityTypesAreNotReportedMissing(): void {
    $absent = ['configurable_language', 'jsonapi_resource_config'];
    foreach ($absent as $entity_type_id) {
      $this->assertFalse(
        \Drupal::entityTypeManager()->hasDefinition($entity_type_id),
        sprintf('%s is absent, so the test covers what it claims to.', $entity_type_id)
      );
      $this->assertContains($entity_type_id . '--' . $entity_type_id, druxt_resources());
    }

    $requirements = druxt_requirements('runtime');

    $this->assertArrayNotHasKey('druxt_resources_missing', $requirements);
  }

}
