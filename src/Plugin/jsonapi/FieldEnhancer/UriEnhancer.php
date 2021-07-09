<?php

namespace Drupal\smc_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Url;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;

/**
 * Use URI Enhancer to modify the path.
 *
 * @ResourceFieldEnhancer(
 *   id = "uri",
 *   label = @Translation("URI path"),
 *   description = @Translation("Modify the URL")
 * )
 */
class UriEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'absolute_url' => 0,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    $settings = empty($resource_field_info['enhancer']['settings'])
      ? $this->getConfiguration()
      : $resource_field_info['enhancer']['settings'];
    $form = parent::getSettingsForm($resource_field_info);
    $form['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => 'Absolute Url',
      '#default_value' => $settings['absolute_url'],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $values = $data;

    try {
      if (isset($data['url'])) {
        $url = Url::fromUserInput($data['url']);
        $url = $this->setAbsoluteValue($url);
        $values['url'] = $url->toString();
      }
      elseif (isset($data['uri'])) {
        $url = Url::fromUri($data['uri']);
        $url = $this->setAbsoluteValue($url);
        $values['uri'] = $url->toString();
      }
    }
    catch (\Exception $e) {
      $url = $data['uri'] ? $data['uri'] : $data['url'];
      \Drupal::logger('jsonapi_extras')->error('Failed to create a URL from uri @uri. Error: @error', [
        '@uri' => $url,
        '@error' => $e->getMessage(),
      ]);
    }

    return $values;
  }

  /**
   * Set the URL to absolute if checkbox is enabled.
   *
   * @param \Drupal\Core\Url $url
   *   Internal URI.
   */
  private function setAbsoluteValue(Url $url) {
    // Use absolute urls if configured.
    $configuration = $this->getConfiguration();
    if ($configuration['absolute_url']) {
      $url->setAbsolute(TRUE);
    }

    return $url;
  }

  /**
   * {@inheritDoc}
   */
  protected function doTransform($value, Context $context) {
  }

  /**
   * {@inheritDoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'object',
    ];
  }

}
