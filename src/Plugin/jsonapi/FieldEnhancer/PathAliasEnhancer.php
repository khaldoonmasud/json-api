<?php

namespace Drupal\smc_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;

/**
 * Use Path Alias enhancer to extra information for Drupal internal path.
 *
 * @ResourceFieldEnhancer(
 *   id = "path_alias",
 *   label = @Translation("Path Alias for path (internal path field only and nid field is required)"),
 *   description = @Translation("Adds source path if alias is not available. nid field should be enabled.")
 * )
 */
class PathAliasEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    if (empty($data['alias'])) {
      if ($context['resource_object']->hasField('drupal_internal__nid')) {
        $data['alias'] = '/node/' . $context['resource_object']->getField('drupal_internal__nid')->value;
      }
    }
    return $data;
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
        ['type' => 'object'],
        ['type' => 'array'],
        ['type' => 'null'],
      ],
    ];
  }

}
