<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings form for the exposed JSON:API resources.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'druxt',
    'block',
    'editor',
    'menu_link_content',
    'node',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The settings form path.
   */
  private const PATH = '/admin/config/services/druxt';

  /**
   * Tests that the form needs its own permission.
   */
  public function testFormRequiresPermission(): void {
    $this->drupalGet(self::PATH);
    $this->assertSession()->statusCodeEquals(403);

    // Access to the resources is not permission to change which are exposed.
    $this->drupalLogin($this->drupalCreateUser(['access druxt resources']));
    $this->drupalGet(self::PATH);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer druxt']));
    $this->drupalGet(self::PATH);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the form offers config resources and refuses content ones.
   */
  public function testFormOffersConfigResourcesOnly(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer druxt']));
    $this->drupalGet(self::PATH);

    // A config resource a site may want is offered, but not chosen for it.
    $this->assertSession()->fieldExists('resources[editor--editor]');
    $this->assertSession()->checkboxNotChecked('resources[editor--editor]');

    // One that ships enabled stays ticked.
    $this->assertSession()->checkboxChecked('resources[view--view]');

    // A content resource is not offered at all. Being on the list grants a
    // blanket entity access result, so this one would publish every
    // account's mail to anyone holding the access permission.
    $this->assertSession()->fieldNotExists('resources[user--user]');

    // The one grandfathered content resource stays offered, because it
    // shipped in the list this configuration replaced.
    $this->assertSession()->fieldExists('resources[menu_link_content--menu_link_content]');
  }

  /**
   * Tests that unchecking a resource is saved and takes effect.
   */
  public function testSavingUpdatesTheResourceList(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer druxt']));
    $this->drupalGet(self::PATH);

    $this->submitForm(['resources[editor--editor]' => TRUE], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    $resources = $this->config('druxt.settings')->get('resources');
    $this->assertContains('editor--editor', $resources);
    $this->assertContains('view--view', $resources);
  }

  /**
   * Tests that saving keeps resources the form cannot offer.
   *
   * A resource belonging to a module this site does not have is not on the
   * form. Saving must not drop it, or a site without that module would
   * quietly delete the entry for every site sharing the configuration.
   */
  public function testSavingKeepsResourcesNotOnTheForm(): void {
    $this->config('druxt.settings')
      ->set('resources', array_merge(druxt_default_resources(), ['comment_type--comment_type']))
      ->save();

    $this->drupalLogin($this->drupalCreateUser(['administer druxt']));
    $this->drupalGet(self::PATH);

    // The comment module is not installed, so the resource cannot be offered.
    $this->assertSession()->fieldNotExists('resources[comment_type--comment_type]');

    $this->submitForm([], 'Save configuration');

    $this->assertContains('comment_type--comment_type', $this->config('druxt.settings')->get('resources'));
  }

}
