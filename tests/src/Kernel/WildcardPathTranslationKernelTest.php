<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\druxt\EventSubscriber\WildcardPathTranslatorSubscriber;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
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

  use UserCreationTrait;

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
    // The entity resolver answers only what the reader may view.
    $this->setUpCurrentUser([], ['access content', 'access user profiles']);

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * A translation event for a path.
   */
  private function event(string $path): PathTranslatorEvent {
    return new PathTranslatorEvent(
      $this->container->get('http_kernel'),
      Request::create('/router/translate-path', 'GET'),
      HttpKernelInterface::MAIN_REQUEST,
      $path
    );
  }

  /**
   * Translates a path through the WildcardPathTranslatorSubscriber.
   */
  private function translatePath(string $path): array {
    $event = $this->event($path);
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
   * Tests that an entity's path is left to the entity resolver.
   */
  public function testEntityPathStandsAside(): void {
    $node = Node::create(['type' => 'page', 'title' => 'A page', 'status' => 1]);
    $node->save();

    $account = $this->createUser();

    foreach (['/node/' . $node->id(), '/user/' . $account->id(), '/user/999'] as $path) {
      $event = $this->event($path);
      $this->container->get('druxt.wildcard_path_translator.subscriber')->onPathTranslation($event);

      $this->assertFalse($event->isPropagationStopped(), $path);
      $this->assertArrayNotHasKey('resolved', (array) json_decode((string) $event->getResponse()->getContent(), TRUE), $path);
    }
  }

  /**
   * Tests that the whole event answers an entity's path with the entity.
   */
  public function testEntityResolverAnswersAnEntityPath(): void {
    $node = Node::create(['type' => 'page', 'title' => 'A page', 'status' => 1]);
    $node->save();

    $event = $this->event('/node/' . $node->id());
    $this->container->get('event_dispatcher')->dispatch($event, PathTranslatorEvent::TRANSLATE);
    $data = json_decode((string) $event->getResponse()->getContent(), TRUE);

    $this->assertSame(200, $event->getResponse()->getStatusCode());
    $this->assertSame($node->uuid(), $data['entity']['uuid'] ?? NULL);
  }

  /**
   * Tests that the whole event keeps the entity resolver's access denied.
   */
  public function testAccessDeniedEntityStaysDenied(): void {
    $node = Node::create(['type' => 'page', 'title' => 'A page', 'status' => 0]);
    $node->save();

    $event = $this->event('/node/' . $node->id());
    $this->container->get('event_dispatcher')->dispatch($event, PathTranslatorEvent::TRANSLATE);
    $data = json_decode((string) $event->getResponse()->getContent(), TRUE);

    $this->assertSame(403, $event->getResponse()->getStatusCode());
    $this->assertSame('Access denied for entity.', $data['message'] ?? NULL);
  }

  /**
   * Tests that this resolver runs after every other resolver.
   */
  public function testRunsLast(): void {
    // Service order also puts it last, so the priority needs its own check.
    $this->assertSame(['onPathTranslation', -100], WildcardPathTranslatorSubscriber::getSubscribedEvents()[PathTranslatorEvent::TRANSLATE][0]);
    $listeners = $this->container->get('event_dispatcher')->getListeners(PathTranslatorEvent::TRANSLATE);
    $last = end($listeners);
    $this->assertIsArray($last);
    $this->assertInstanceOf(WildcardPathTranslatorSubscriber::class, $last[0]);
  }

  /**
   * Tests that a route with parameters that are not entities resolves.
   */
  public function testRouteParametersAreKept(): void {
    $result = $this->translatePath('/user/reset/1/1234567890/abcdef');

    $this->assertSame(200, $result['status']);
    $this->assertStringEndsWith('/user/reset/1/1234567890/abcdef', $result['data']['resolved']);
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
