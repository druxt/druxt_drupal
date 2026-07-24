<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the ViewsPathTranslatorSubscriber in-process.
 *
 * Runs as a kernel test so pcov captures coverage of the subscriber, which
 * the functional test cannot do (it executes in the web-server process).
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class ViewsPathTranslationKernelTest extends KernelTestBase {

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
    'node',
    'views',
    'path_alias',
    'jsonapi',
    'jsonapi_resources',
    'jsonapi_views',
    'decoupled_router',
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'field', 'filter', 'node']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Create a View with a page display at a known path so the router will
    // hand off to ViewsPathTranslatorSubscriber.
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

    // Rebuild routes so the View page and jsonapi_views routes exist.
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Translates a path through the ViewsPathTranslatorSubscriber.
   */
  private function translatePath(string $path): array {
    $request = Request::create('/router/translate-path', 'GET');
    $event = new PathTranslatorEvent(
      $this->container->get('http_kernel'),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $path
    );
    $this->container->get('druxt.views_path_translator.subscriber')->onPathTranslation($event);

    $response = $event->getResponse();
    return [
      'status' => $response->getStatusCode(),
      'data' => json_decode((string) $response->getContent(), TRUE),
    ];
  }

  /**
   * Tests that a View-backed path translates and returns view metadata.
   */
  public function testViewPathTranslation(): void {
    $result = $this->translatePath('/druxt-test-view');

    $this->assertSame(200, $result['status']);
    $this->assertIsArray($result['data']);
    $this->assertArrayHasKey('view', $result['data']);
    $this->assertSame('druxt_test_view', $result['data']['view']['view_id']);
    $this->assertSame('page_1', $result['data']['view']['display_id']);
    $this->assertArrayHasKey('resolved', $result['data']);
    $this->assertArrayHasKey('jsonapi', $result['data']);
    $this->assertArrayHasKey('jsonapi_views', $result['data']);
  }

  /**
   * Tests that an external path resolves without routing through Drupal.
   *
   * The router wraps the path in Request::create(), which parses a full URL
   * down to its path component. To reach the external fallback the URL's path
   * must not match a route, hence the non-existent path segment.
   */
  public function testExternalPath(): void {
    $external = 'http://example.com/does-not-exist';
    $result = $this->translatePath($external);

    $this->assertSame(200, $result['status']);
    $this->assertSame($external, $result['data']['resolved']);
  }

  /**
   * Tests that a non-View route short-circuits and leaves the response as-is.
   */
  public function testNonViewPath(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'A page',
      'status' => 1,
    ]);
    $node->save();

    $result = $this->translatePath('/node/' . $node->id());

    // The subscriber returns early for non-View routes, leaving the default
    // 404 response untouched.
    $this->assertSame(404, $result['status']);
  }

}
