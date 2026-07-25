<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the WildcardPathTranslatorSubscriber in-process.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class WildcardPathTranslationKernelTest extends KernelTestBase {

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
    'path_alias',
    'decoupled_router',
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'node', 'filter']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Translates a path through the WildcardPathTranslatorSubscriber.
   */
  private function translatePath(string $path): array {
    $request = Request::create('/router/translate-path', 'GET');
    $event = new PathTranslatorEvent(
      $this->container->get('http_kernel'),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $path
    );
    $this->container->get('druxt.wildcard_path_translator.subscriber')->onPathTranslation($event);

    $response = $event->getResponse();
    return [
      'status' => $response->getStatusCode(),
      'data' => json_decode((string) $response->getContent(), TRUE),
    ];
  }

  /**
   * Tests that a generic (non-entity) route resolves to its URL.
   */
  public function testGenericPath(): void {
    $result = $this->translatePath('/user/login');

    $this->assertSame(200, $result['status']);
    $this->assertIsArray($result['data']);
    $this->assertArrayHasKey('resolved', $result['data']);
  }

  /**
   * Tests that a parameterized route with missing params returns a 500.
   */
  public function testParameterizedPath(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'A page',
      'status' => 1,
    ]);
    $node->save();

    $result = $this->translatePath('/node/' . $node->id());

    $this->assertSame(500, $result['status']);
  }

  /**
   * Tests that an external path resolves without routing through Drupal.
   */
  public function testExternalPath(): void {
    $external = 'http://example.com/does-not-exist';
    $result = $this->translatePath($external);

    $this->assertSame(200, $result['status']);
    $this->assertSame($external, $result['data']['resolved']);
  }

}
