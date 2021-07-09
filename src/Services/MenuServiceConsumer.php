<?php

namespace Drupal\smc_api\Services;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use Exception;

/**
 * Menu API service consumer.
 */
class MenuServiceConsumer {

  use StringTranslationTrait;

  /**
   * GuzzleHttp\Client definition.
   *
   * @var GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configuration;

  /**
   * Constructor.
   */
  public function __construct(Client $httpClient, ConfigFactory $configFactory) {
    $this->httpClient = $httpClient;
    $this->configuration = $configFactory;
  }

  /**
   * Retrieves the Menu API response.
   *
   * @param string $location_id
   *   Menu location id.
   */
  public function getMenus(string $location_id) {
    $menu_api_key = $this->configuration->get('smc_api.menu_settings')->get('menu_api_key');
    $menu_api = $this->configuration->get('smc_api.menu_settings')->get('menu_api');
    $menu_api = trim($menu_api);
    $menu_api = $menu_api . "/{$location_id}";

    try {
      $options = [
        'connect_timeout' => 3,
        'headers' => [
          'Accept' => 'application/json',
          'Authorization' => $menu_api_key,
        ],
      ];
      $api_request = $this->httpClient->request('GET', $menu_api, $options);
      $code = $api_request->getStatusCode();
      if ($code == 200) {
        $body = Json::decode($api_request->getBody()->getContents());
        if (empty($body)) {
          throw new Exception($this->t("SMC Menu API returned empty response."));
        }
        return $body;
      }
      else {
        throw new Exception($this->t("SMC Menu API returned error response with :code.", [':code' => $code]));
      }
    }
    catch (Exception $e) {
      watchdog_exception('smc_api_menu_api', $e, $e->getMessage());
    }
  }

}
