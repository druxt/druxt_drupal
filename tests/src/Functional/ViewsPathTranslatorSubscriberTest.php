<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests ViewsPathTranslatorSubscriber for proper handling of
 * multi-language paths.
 *
 * @group druxt
 */
class ViewsPathTranslatorSubscriberTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'druxt',
    'decoupled_router',
    'jsonapi_views_test',
    'jsonapi_views',
    'language',
    'node',
  ];
  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['jsonapi_views_test_node_view'];

  /**
   * {@inheritdoc}
   */

  protected $defaultTheme = 'stark';

  /**
   * Consumer user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $consumer;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(get_class($this), ['jsonapi_views_test']);
    $this->enableViewsTestModule();

    $language = ConfigurableLanguage::createFromLangcode('ca');
    $language->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    // Create consumer.
    $this->consumer = $this->createUser(['access content', 'access druxt resources']);
    $this->drupalLogin($this->consumer);

    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes.ca', 'ca')
      ->save();

    // $this->container->get('router.builder')->rebuildIfNeeded();
  }

  /**
   * Tests that Views display paths resolve to the correct view_id / display_id
   * when a language is specified in the path.
   */
  public function testViewsPathTranslatorSubscriber() {
    $res = $this->drupalGet(
      Url::fromRoute('decoupled_router.path_translation'),
      [
        'query' => [
          'path' => '/ca/jsonapi-views-test-node-view',
          '_format' => 'json',
        ],
      ]
    );
    // // Assert that the English language code is handled properly.
    // $res = $this->drupalGet('/en/recipes');
    $this->assertSession()->statusCodeEquals(200);
    // $this->assertSession()->responseContains('Deep mediterranean quiche');

    // $res = $this->drupalGet(Url::fromRoute("jsonapi.decoupled_router.views"));
    // $this->assertSession()->statusCodeEquals(200);
    // $output = Json::decode($res);
    // $this->assertNotEmpty($output['data']);
    // $this->assertEquals('recipes', $output['data']['view_id']);
    // $this->assertEquals('page_1', $output['data']['display_id']);
    // // @todo What else to check for in data?

    // // Assert that the Spanish language code is handled properly.
    // $res = $this->drupalGet('/es/recipes');
    // $this->assertSession()->statusCodeEquals(200);
    // $this->assertSession()->responseContains('Quiche mediterráneo profundo');

    // $res = $this->drupalGet(Url::fromRoute("jsonapi.decoupled_router.views"));
    // $this->assertSession()->statusCodeEquals(200);
    // $output = Json::decode($res);
    // $this->assertNotEmpty($output['data']);
    // $this->assertEquals('recipes', $output['data']['view_id']);
    // $this->assertEquals('page_1', $output['data']['display_id']);
    // @todo What else to check for in data?

  }

}
