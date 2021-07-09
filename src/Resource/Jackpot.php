<?php

namespace Drupal\smc_api\Resource;

use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Symfony\Component\HttpFoundation\Request;
use Exception;
use Drupal\Component\Serialization\Json;
use Symfony\Component\Routing\Route;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Processes a request for SMC API Jackpot.
 *
 * @internal
 */
final class Jackpot extends EntityQueryResourceBase {

  use StringTranslationTrait;

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request): ResourceResponse {

    $config = \Drupal::config('smc_api.jackpot_settings');
    $jackpot_api = $config->get('jackpot_api');
    $client_id = $config->get('jackpot_client_id');
    $secret = $config->get('jackpot_secret');
    $game_id = $request->attributes->get('gameid');
    $meta['amount'] = NULL;
    $this->t("SMC API returned empty response");

    try {
      $client = \Drupal::httpClient();
      $options = [
        'auth' => [
          $client_id,
          $secret,
        ],
        'connect_timeout' => 3,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ];
      $api_request = $client->request('GET', trim($jackpot_api), $options);
      $code = $api_request->getStatusCode();
      if ($code == 200) {
        $body = Json::decode($api_request->getBody()->getContents());
        if (empty($body)) {
          throw new Exception($this->t("SMC API returned empty response"));
        }
        $meta['amount'] = (float) $this->getJackpotValue($body, (int) $game_id);
      }
      else {
        throw new Exception($this->t("SMC API not working with response code: :code", [':code' => $code]));
      }
    }
    catch (Exception $e) {
      watchdog_exception('smc_api', $e, $e->getMessage());
    }

    $response = $this->createJsonapiResponse(new ResourceObjectData([]), $request, 200, [], NULL, $meta);
    $response->getCacheableMetadata()->setCacheMaxAge(60);
    $response->setMaxAge(60);
    $date = new \DateTime('now');
    $date->add(new \DateInterval('PT1M'));
    $response->setExpires($date);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('node');
  }

  /**
   * Determines the Jackpot amount based in the game id.
   *
   * @param array $body
   *   Jackpot body.
   * @param string|null $game_id
   *   Jackpot Game ID.
   */
  private function getJackpotValue(array $body, ?string $game_id) {
    $amount = NULL;
    if (!empty($game_id)) {
      foreach ($body as $value) {
        if ($game_id == $value['id']) {
          return $value['meterValue'];
        }
      }
      throw new Exception($this->t("Provided Game ID not found in the SMC API response."));
    }
    else {
      $amount = array_sum(array_column($body, 'meterValue'));
    }

    return $amount;
  }

}
