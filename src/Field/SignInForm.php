<?php

namespace Drupal\smc_api\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Represents the computed sign in form.
 */
class SignInForm extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if ($entity->getEntityTypeId() == 'paragraph' && $entity->bundle() == 'header') {
      $is_anonymous = \Drupal::currentUser()->isAnonymous();
      if ($is_anonymous) {
        $form = \Drupal::formBuilder()->getForm('Drupal\openid_connect\Form\OpenIDConnectLoginForm');
        $host = \Drupal::request()->getSchemeAndHttpHost();

        // Form Action to Absolute URL.
        $form['#action'] = $host;

        // Service Renderer.
        $service_renderer = \Drupal::service('renderer');
        $login_form = $service_renderer->renderPlain($form);
        $this->setValue($login_form);
      }
    }
  }

}
