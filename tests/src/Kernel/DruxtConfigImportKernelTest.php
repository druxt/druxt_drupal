<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\StorageComparer;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a configuration import cannot expose an unsafe resource.
 *
 * The schema carries the rule, but Drupal does not run schema constraints
 * on save or on import, so the rule needs enforcing here as well. A sync
 * directory applied by a deploy job is not reviewed code, and it reaches
 * the same place the settings form does.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtConfigImportKernelTest extends KernelTestBase {

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
    // Core refuses an import with no system.site, before druxt is consulted.
    $this->installConfig(['system', 'druxt']);
    $this->copyConfig(
      $this->container->get('config.storage'),
      $this->container->get('config.storage.sync')
    );
  }

  /**
   * Builds an importer for the current sync storage.
   */
  private function importer(): ConfigImporter {
    $comparer = new StorageComparer(
      $this->container->get('config.storage.sync'),
      $this->container->get('config.storage')
    );
    $comparer->createChangelist();

    return new ConfigImporter(
      $comparer,
      $this->container->get('event_dispatcher'),
      $this->container->get('config.manager'),
      $this->container->get('lock'),
      $this->container->get('config.typed'),
      $this->container->get('module_handler'),
      $this->container->get('module_installer'),
      $this->container->get('theme_handler'),
      $this->container->get('string_translation'),
      $this->container->get('extension.list.module'),
      $this->container->get('extension.list.theme')
    );
  }

  /**
   * Returns the validation errors an import reports.
   */
  private function validationErrors(ConfigImporter $importer): array {
    try {
      $importer->validate();
    }
    catch (ConfigImporterException) {
      // The errors are what is under test, so a refusal is expected here.
    }

    return $importer->getErrors();
  }

  /**
   * Writes a resource list into the sync storage.
   *
   * @param string[] $resources
   *   The resource list to stage.
   * @param string|null $collection
   *   A collection name, or NULL for the default collection.
   */
  private function stage(array $resources, ?string $collection = NULL): void {
    $sync = $this->container->get('config.storage.sync');
    if ($collection !== NULL) {
      $sync = $sync->createCollection($collection);
    }
    $sync->write('druxt.settings', ['resources' => $resources]);
  }

  /**
   * Tests that importing a content entity resource is refused.
   */
  public function testImportOfContentEntityResourceIsRefused(): void {
    $this->stage(['view--view', 'user--user']);

    $errors = $this->validationErrors($this->importer());
    $this->assertNotEmpty($errors, 'The import reported an error.');
    $this->assertStringContainsString('user--user', implode("\n", $errors));
  }

  /**
   * Tests that a valid import is not refused.
   */
  public function testValidImportIsAccepted(): void {
    $this->stage(['view--view', 'menu--menu']);

    $this->assertSame([], $this->validationErrors($this->importer()));
  }

  /**
   * Tests that a non-default collection is checked too.
   *
   * A language collection holds configuration overrides, so a resource
   * staged there reaches the site without passing through the default
   * collection.
   */
  public function testNonDefaultCollectionIsChecked(): void {
    $this->stage(['view--view']);
    $this->stage(['user--user'], 'language.es');

    $errors = $this->validationErrors($this->importer());
    $this->assertNotEmpty($errors, 'The import reported an error.');
    $this->assertStringContainsString('language.es', implode("\n", $errors));
  }

  /**
   * Tests that a resource for an uninstalled module imports.
   *
   * Configuration is shared between sites, so it may legitimately name a
   * resource this one cannot serve.
   */
  public function testResourceForUninstalledModuleIsAccepted(): void {
    $this->stage(['view--view', 'comment_type--comment_type']);

    $this->assertSame([], $this->validationErrors($this->importer()));
  }

}
