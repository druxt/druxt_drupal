<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the module installs through the interface a site builder uses.
 *
 * The suite installs Druxt everywhere else through the $modules property,
 * which reaches ModuleInstaller::install() and never invokes
 * hook_requirements(). Only the Extend form and Drush do that, and both load
 * druxt.install without druxt.module, so anything hook_requirements() needs
 * has to be reachable from the install file alone. Nothing else here covers
 * that path.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   *
   * Druxt's dependencies, but not Druxt: the test installs that itself.
   *
   * JSON:API has to be among them. druxt_requirements() does nothing unless
   * jsonapi.resource_type.repository exists, so installing Druxt alongside
   * JSON:API rather than onto it never reaches the code this covers.
   */
  protected static $modules = [
    'jsonapi',
    'decoupled_router',
    'jsonapi_menu_items',
    'jsonapi_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests installing the module from the Extend page.
   */
  public function testInstallThroughTheExtendForm(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('druxt'));

    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[druxt][enable]' => TRUE], 'Install');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Call to undefined function');

    $this->rebuildContainer();
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('druxt'));
  }

}
