<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the configurable list of exposed JSON:API resources.
 *
 * The list used to be a hardcoded array in druxt_access_check(). It is now
 * configuration, so a site can expose the resources its frontend needs
 * without patching the module.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtResourcesKernelTest extends KernelTestBase {

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
    'editor',
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
   * Tests that the status report checks the resources access actually grants.
   *
   * Druxt.install carried its own hardcoded list of ten, which had already
   * drifted from the twelve the access check used: it omitted
   * configurable_language and jsonapi_resource_config. Both now read the
   * same source, so the report cannot describe a set nobody is granted.
   */
  public function testRequirementsUseTheSameResourceList(): void {
    \Drupal::moduleHandler()->loadInclude('druxt', 'install');

    $this->config('druxt.settings')
      ->set('resources', ['view--view'])
      ->save();

    $this->assertSame(['view--view'], druxt_required_resources());
  }

  /**
   * Tests that a config entity resource is allowed.
   */
  public function testConfigEntityResourceIsAllowed(): void {
    $this->assertTrue(druxt_resource_is_allowed('view--view'));
    $this->assertTrue(druxt_resource_is_allowed('editor--editor'));
  }

  /**
   * Tests that a content entity resource is refused.
   *
   * The list grants a blanket entity access bypass and unrestricted
   * filtering, so a content resource would publish per-entity access and
   * unpublished content to anyone holding the permission.
   */
  public function testContentEntityResourceIsRefused(): void {
    $this->assertFalse(druxt_resource_is_allowed('user--user'));
    $this->assertFalse(druxt_resource_is_allowed('node--article'));
  }

  /**
   * Tests that the one pre-existing content resource stays allowed.
   *
   * Menu_link_content shipped in the hardcoded list, so refusing it would
   * break sites on update. It is grandfathered rather than treated as
   * precedent for the category.
   */
  public function testGrandfatheredContentResourceIsAllowed(): void {
    $this->assertTrue(druxt_resource_is_allowed('menu_link_content--menu_link_content'));
  }

  /**
   * Tests that an unknown resource is refused.
   */
  public function testUnknownResourceIsRefused(): void {
    $this->assertFalse(druxt_resource_is_allowed('nonsense--nonsense'));
  }

  /**
   * Tests that a resource stored in configuration is rechecked at runtime.
   *
   * Configuration can hold a resource that is not safe to grant: written
   * before this validation existed, or for an entity type that has appeared
   * since the value was stored. Validation on import cannot catch either,
   * so the access check has to recheck rather than trust what it reads.
   */
  public function testStoredContentEntityResourceIsNotGranted(): void {
    // Write past validation, the way an older release or a direct
    // Config::save() would have.
    \Drupal::configFactory()->getEditable('druxt.settings')
      ->set('resources', ['view--view', 'user--user'])
      ->save();

    $resources = druxt_resources();

    $this->assertContains('view--view', $resources);
    $this->assertNotContains('user--user', $resources);
  }

  /**
   * Tests that the recheck runs before the alter hook, not after.
   *
   * A module may expose a content entity deliberately, because a change in
   * code is reviewable. Filtering after the hook would take that away.
   */
  public function testAlterHookStillAddsWhatConfigurationCannot(): void {
    \Drupal::service('module_installer')->install(['druxt_resources_test']);

    // The test module adds user--user, which configuration may not.
    $this->assertContains('user--user', druxt_resources());
  }

  /**
   * Tests that a module may alter the resource list in code.
   */
  public function testAlterHookAddsAndRemoves(): void {
    \Drupal::service('module_installer')->install(['druxt_resources_test']);

    $resources = druxt_resources();

    // The hook may add a resource the user interface would refuse.
    $this->assertContains('user--user', $resources);
    // And may remove one the default ships.
    $this->assertNotContains('view--view', $resources);
  }

  /**
   * Tests that an absent configuration falls back to the shipped defaults.
   *
   * A site updating from a release that had the list hardcoded has no
   * druxt.settings until the update hook runs. Returning nothing there would
   * deny every resource the frontend asks for.
   */
  public function testAbsentConfigurationFallsBackToDefaults(): void {
    \Drupal::configFactory()->getEditable('druxt.settings')->delete();

    $this->assertSame(druxt_default_resources(), druxt_resources());
  }

  /**
   * Tests that an empty list is respected rather than treated as absent.
   */
  public function testEmptyListIsHonoured(): void {
    $this->config('druxt.settings')->set('resources', [])->save();

    $this->assertSame([], druxt_resources());
  }

  /**
   * Tests that the shipped configuration matches the code default.
   *
   * The two are separate files and would otherwise drift, which is the fault
   * this issue is fixing in the first place.
   */
  public function testShippedConfigurationMatchesTheCodeDefault(): void {
    $this->assertSame(druxt_default_resources(), druxt_resources());
  }

  /**
   * Tests that the resource list is read from configuration.
   */
  public function testResourcesComeFromConfiguration(): void {
    $this->config('druxt.settings')
      ->set('resources', ['view--view'])
      ->save();

    $this->assertSame(['view--view'], druxt_resources());
  }

  /**
   * Tests that the shipped default covers the resources a frontend needs.
   */
  public function testDefaultResourcesAreInstalled(): void {
    $resources = druxt_resources();

    // The twelve the hardcoded array carried, so an existing site sees no
    // change on update.
    $this->assertContains('block--block', $resources);
    $this->assertContains('configurable_language--configurable_language', $resources);
    $this->assertContains('entity_form_display--entity_form_display', $resources);
    $this->assertContains('entity_form_mode--entity_form_mode', $resources);
    $this->assertContains('entity_view_display--entity_view_display', $resources);
    $this->assertContains('entity_view_mode--entity_view_mode', $resources);
    $this->assertContains('field_config--field_config', $resources);
    $this->assertContains('field_storage_config--field_storage_config', $resources);
    $this->assertContains('jsonapi_resource_config--jsonapi_resource_config', $resources);
    $this->assertContains('menu--menu', $resources);
    $this->assertContains('menu_link_content--menu_link_content', $resources);
    $this->assertContains('view--view', $resources);

    // Nothing is added to the default. Making the list configurable must not
    // change what an existing site exposes. A site that wants more, such as
    // editor--editor for a frontend that renders a text format's toolbar,
    // chooses it.
    $this->assertNotContains('editor--editor', $resources);
    $this->assertCount(12, $resources);
  }

  /**
   * Tests that an access answer records where it was read from.
   *
   * The answer comes from the resource list, which is now configuration a
   * user can change. Without the dependency, a cached response keeps
   * whatever the list said when it was written: ticking a resource has no
   * visible effect until the caches are rebuilt.
   */
  public function testEntityAccessDependsOnTheSettings(): void {
    $menu = Menu::create(['id' => 'test', 'label' => 'Test']);
    $account = $this->createMock(AccountInterface::class);

    $result = druxt_entity_access($menu, 'view', $account);

    $this->assertContains('config:druxt.settings', $result->getCacheTags());
  }

}
