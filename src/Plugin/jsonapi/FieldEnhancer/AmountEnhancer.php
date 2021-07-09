<?php

namespace Drupal\smc_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Exception;
use Shaper\Util\Context;
use Drupal\Component\Serialization\Json;

/**
 * Use Amount Enhancer to modify the jackpot widget amount field.
 *
 * @ResourceFieldEnhancer(
 *   id = "amount",
 *   label = @Translation("Amount (only for Jackpot widget paragraph)"),
 *   description = @Translation("Modifies the Jackpot Amount field if the Jackpot API is set.")
 * )
 */
class AmountEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    if ($context['resource_object']->hasField('field_jackpot_source')) {
      $field_jackpot_source = $context['resource_object']->getField('field_jackpot_source')->value;
      $field_jackpot_game_id = $context['resource_object']->getField('field_jackpot_game_id')->value;
      if ($field_jackpot_source == 'smc-api') {
        global $_smc_api_cache_tags;
        $games = $this->getGames();
        if ($games) {
          $data = (float) $this->getJackpotValue($games, (int) $field_jackpot_game_id);
          $_smc_api_cache_tags[] = ['tag' => 'paragraph:' . $context['resource_object']->getField('drupal_internal__id')->value, 'time' => 60];
        }
      }
    }
    return $data;
  }

  /**
   * Get games through SMC API.
   */
  private function getGames() {
    $games = &drupal_static(__FUNCTION__);
    if (!isset($games)) {
      $config = \Drupal::config('smc_api.jackpot_settings');
      $jackpot_api = $config->get('jackpot_api');
      $client_id = $config->get('jackpot_client_id');
      $secret = $config->get('jackpot_secret');

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
        $request = $client->request('GET', trim($jackpot_api), $options);
        $code = $request->getStatusCode();
        if ($code == 200) {
          $body = Json::decode($request->getBody()->getContents());
          if (empty($body)) {
            throw new Exception("SMC API returned empty response");
          }
          $games = $body;
        }
        else {
          throw new Exception($this->t("SMC API not working with response code: :code", [':code' => $code]));
        }
      }
      catch (Exception $e) {
        watchdog_exception('smc_api', $e, $e->getMessage());
      }
    }
    return $games;
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
      \Drupal::logger('smc_api')->error($this->t("Provided Game ID not found in the SMC API response."));
    }
    else {
      $amount = array_sum(array_column($body, 'meterValue'));
    }

    return $amount;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'oneOf' => [
        ['type' => 'number'],
        ['type' => 'null'],
      ],
    ];
  }

}
