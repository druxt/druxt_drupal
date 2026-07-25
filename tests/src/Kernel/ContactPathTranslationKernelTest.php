<?php

declare(strict_types=1);

namespace Drupal\Tests\druxt\Kernel;

use Drupal\contact\Entity\ContactForm;
use Drupal\decoupled_router\PathTranslatorEvent;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the ContactPathTranslatorSubscriber in-process.
 *
 * @group druxt
 */
#[Group('druxt')]
#[RunTestsInSeparateProcesses]
class ContactPathTranslationKernelTest extends KernelTestBase {

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
    'jsonapi',
    'jsonapi_resources',
    'decoupled_router',
    'contact',
    'druxt',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'contact']);

    ContactForm::create(['id' => 'feedback', 'label' => 'Feedback'])->save();
    \Drupal::configFactory()->getEditable('contact.settings')->set('default_form', 'feedback')->save();

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Translates a path through the ContactPathTranslatorSubscriber.
   */
  private function translatePath(string $path): array {
    $request = Request::create('/router/translate-path', 'GET');
    $event = new PathTranslatorEvent(
      $this->container->get('http_kernel'),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      $path
    );
    $this->container->get('druxt.contact_path_translator.subscriber')->onPathTranslation($event);

    $response = $event->getResponse();
    return [
      'status' => $response->getStatusCode(),
      'data' => json_decode((string) $response->getContent(), TRUE),
    ];
  }

  /**
   * Tests that the site contact page translates with entity metadata.
   */
  public function testContactPathTranslation(): void {
    $result = $this->translatePath('/contact');

    $this->assertSame(200, $result['status']);
    $this->assertIsArray($result['data']);
    $this->assertSame('contact_form', $result['data']['entity']['type']);
    $this->assertSame('feedback', $result['data']['entity']['id']);
    $this->assertArrayHasKey('jsonapi', $result['data']);
  }

  /**
   * Tests that a non-contact route short-circuits and leaves the response.
   */
  public function testNonContactPath(): void {
    $result = $this->translatePath('/user/login');

    // Not a contact.site_page route, so the subscriber returns early.
    $this->assertSame(404, $result['status']);
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
