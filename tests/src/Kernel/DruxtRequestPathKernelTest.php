<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\druxt\Plugin\Condition\DruxtRequestPath;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * Tests the DruxtRequestPath condition plugin in-process.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class DruxtRequestPathKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path_alias',
    'serialization',
    'field',
    'file',
    'text',
    'filter',
    'decoupled_router',
    'jsonapi',
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'user']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $stack = $this->container?->get('request_stack');
    if ($stack) {
      $main = $stack->getMainRequest();
      while ($stack->getCurrentRequest() !== NULL && $stack->getCurrentRequest() !== $main) {
        $stack->pop();
      }
    }
    parent::tearDown();
  }

  /**
   * Tests that the plugin manager instantiates a DruxtRequestPath.
   */
  public function testPluginInstance(): void {
    $plugin = $this->container->get('plugin.manager.condition')->createInstance('request_path', [
      'id' => 'request_path',
      'pages' => '<front>',
      'negate' => FALSE,
    ]);
    $this->assertInstanceOf(DruxtRequestPath::class, $plugin);
  }

  /**
   * Tests evaluate() falls through to parent when not on a Druxt route.
   */
  public function testEvaluateWithoutDruxtRoute(): void {
    $request = Request::create('/some/path');
    $this->container->get('request_stack')->push($request);

    $plugin = $this->container->get('plugin.manager.condition')->createInstance('request_path', [
      'id' => 'request_path',
      'pages' => '/some/path',
      'negate' => FALSE,
    ]);
    \assert($plugin instanceof DruxtRequestPath);
    $this->assertTrue($plugin->evaluate());
  }

  /**
   * Tests evaluate() bypasses the condition on a Druxt JSON:API route.
   */
  public function testEvaluateWithDruxtRoute(): void {
    // Create a role with the Druxt permission.
    $role_storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $role_storage->create([
      'id' => 'druxt_consumer',
      'label' => 'Druxt Consumer',
      'permissions' => ['access druxt resources'],
    ])->save();

    $user = User::create([
      'name' => 'consumer',
      'mail' => 'consumer@example.com',
      'status' => 1,
    ]);
    $user->addRole('druxt_consumer');
    $user->enforceIsNew();
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    // Simulate a JSON:API route for a Druxt resource.
    $route = new Route('/jsonapi/block/block');
    $route->setMethods(['GET']);
    $route->addDefaults([
      '_controller' => 'jsonapi.entity_resource:getCollection',
      'resource_type' => 'block--block',
    ]);

    $request = Request::create('/jsonapi/block/block');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'jsonapi.block--block.collection');
    $this->container->get('request_stack')->push($request);

    $manager = $this->container->get('plugin.manager.condition');

    // Not negated → TRUE (bypass).
    $plugin = $manager->createInstance('request_path', [
      'pages' => '/nonexistent',
      'negate' => FALSE,
    ]);
    \assert($plugin instanceof DruxtRequestPath);
    $this->assertTrue($plugin->evaluate());

    // Negated → FALSE (bypass still applies, but negated).
    $plugin = $manager->createInstance('request_path', [
      'pages' => '/nonexistent',
      'negate' => TRUE,
    ]);
    \assert($plugin instanceof DruxtRequestPath);
    $this->assertFalse($plugin->evaluate());
  }

  /**
   * Tests the Contact subscriber early-returns when contact is disabled.
   */
  public function testContactSubscriberWithoutContactModule(): void {
    $event = $this->createPathTranslatorEvent('/contact');
    $this->container->get('druxt.contact_path_translator.subscriber')->onPathTranslation($event);
    $this->assertSame(404, $event->getResponse()->getStatusCode());
  }

  /**
   * Tests the Views subscriber early-returns when jsonapi_views is disabled.
   */
  public function testViewsSubscriberWithoutJsonapiViewsModule(): void {
    $event = $this->createPathTranslatorEvent('/some-path');
    $this->container->get('druxt.views_path_translator.subscriber')->onPathTranslation($event);
    $this->assertSame(404, $event->getResponse()->getStatusCode());
  }

  /**
   * Tests all subscribers guard against non-CacheableJsonResponse events.
   */
  public function testNonCacheableResponseGuard(): void {
    $subscribers = [
      'druxt.contact_path_translator.subscriber',
      'druxt.views_path_translator.subscriber',
      'druxt.wildcard_path_translator.subscriber',
    ];

    foreach ($subscribers as $service_id) {
      $event = $this->createPathTranslatorEvent('/test');
      $ref = new \ReflectionProperty(PathTranslatorEvent::class, 'response');
      $ref->setValue($event, new Response());
      $this->container->get($service_id)->onPathTranslation($event);
      $this->assertFalse($event->isPropagationStopped());
    }
  }

  /**
   * Creates a PathTranslatorEvent for testing.
   */
  private function createPathTranslatorEvent(string $path): PathTranslatorEvent {
    return new PathTranslatorEvent(
      $this->container->get('http_kernel'),
      Request::create('/router/translate-path'),
      HttpKernelInterface::MAIN_REQUEST,
      $path
    );
  }

}
