<?php

namespace Drupal\Tests\druxt\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;
use GuzzleHttp\RequestOptions;
use Drupal\Component\Utility\NestedArray;

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
6ytghubv
     // Example english JSON data to test for with:
     // {
     //   resolved: 'https://domain.tld/en/articles',
     //   view: {
     //     uuid: 'UUID',
     //     view_id: 'featured_articles',
     //     display_id: 'page_1',
     //     langcode: 'en,'
     //   },
     //   jsonapi: {
     //     individual: 'https://domain.tld/en/jsonapi/view/view/UUID',
     //     resourceName: 'view--view',
     //     pathPrefix: 'en/jsonapi',
     //     basePath: '/en/jsonapi',
     //     entryPoint: 'https://domain.tld/en/jsonapi'
     //   },
     //   jsonapi_views: 'https://domain.tld/en/jsonapi/views/featured_articles/page_1'
     // }

     // Example spanish JSON data to test for with:
     // {
     //   resolved: 'https://domain.tld/es/articles',
     //   view: {
     //     uuid: 'UUID',
     //     view_id: 'featured_articles',
     //     display_id: 'page_1',
     //     langcode: 'es,'
     //   },
     //   jsonapi: {
     //     individual: 'https://domain.tld/es/jsonapi/view/view/UUID',
     //     resourceName: 'view--view',
     //     pathPrefix: 'es/jsonapi',
     //     basePath: '/es/jsonapi',
     //     entryPoint: 'https://domain.tld/es/jsonapi'
     //   },
     //   jsonapi_views: 'https://domain.tld/es/jsonapi/views/featured_articles/page_1'
     // }

    return true;


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

  /**
   * Get a JSON:API Views resource response document.
   *
   * @param \Drupal\core\Url $url
   *   The url for a JSON:API View.
   *
   * @return array
   *   The response document.
   */
  protected function getJsonApiViewResponse(Url $url) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $response = $this->request('GET', $url, $request_options);

    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));

    $response_document = Json::decode((string) $response->getBody());

    $this->assertIsArray($response_document['data']);
    $this->assertArrayNotHasKey('errors', $response_document);

    return [$response_document, $response->getHeaders()];
  }

  /**
   * Get a JSON:API Views Url for a given view display and optionally language.
   *
   * @param string $view_name
   *   The View name.
   * @param string $display_id
   *   The View display id.
   * @param string $query
   *   A query object to add to the request.
   * @param string $langcode
   *   A langcode to add to the request.
   *
   * @return \Drupal\core\Url
   *   The url for a JSON:API View.
   */
  protected function getJsonApiViewUrl($view_name, $display_id, $query = [], $langcode = "en", ) {
    $url = Url::fromUri("internal:/jsonapi/views/{$view_name}/{$display_id}");
    $url->setOption('query', $query);

    return $url;
  }

  /**
   * Returns Guzzle request options for authentication.
   *
   * @return array
   *   Guzzle request options to use for authentication.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function getAuthenticationRequestOptions() {
    return [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->account->name->value . ':' . $this->account->passRaw),
      ],
    ];
  }

}
