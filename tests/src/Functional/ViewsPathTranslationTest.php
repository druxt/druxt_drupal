<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Serialization\Json;
use Drupal\user\UserInterface;
use Drupal\views\Entity\View;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Druxt Views path translation via the decoupled router.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class ViewsPathTranslationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'druxt',
    'node',
    'views',
    'decoupled_router',
    'jsonapi',
    'jsonapi_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Consumer user.
   */
  protected UserInterface $consumer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'page']);

    $this->consumer = $this->createUser([
      'access druxt resources',
      'access content',
    ]);

    // Create a View with a page display at a known path so the decoupled
    // router will match it and hand off to ViewsPathTranslatorSubscriber.
    $view = View::create([
      'id' => 'druxt_test_view',
      'label' => 'Druxt test view',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'status' => TRUE,
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_title' => 'Page',
          'position' => 1,
          'display_options' => [
            'path' => 'druxt-test-view',
          ],
        ],
      ],
    ]);
    $view->save();

    // Rebuild routes so the new View page and jsonapi_views routes exist.
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Test that a View-backed path translates without a TypeError.
   */
  public function testViewsPathTranslation(): void {
    $this->drupalLogin($this->consumer);

    $this->drupalGet('/router/translate-path', [
      'query' => [
        'path' => '/druxt-test-view',
        '_format' => 'json',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);

    $output = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertNotNull($output, 'Response should be valid JSON.');
    $this->assertArrayHasKey('view', $output);
    $this->assertSame('druxt_test_view', $output['view']['view_id']);
    $this->assertSame('page_1', $output['view']['display_id']);
  }

}
