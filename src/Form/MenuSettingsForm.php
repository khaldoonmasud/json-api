<?php

namespace Drupal\smc_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Menu settings form.
 */
class MenuSettingsForm extends ConfigFormBase {
  /**
    * Config settings.
    *
    * @var string
    */
  const SETTINGS = 'smc_api.menu_settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smc_api_admin_menu_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['menu_api'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Menu API'),
      '#default_value' => $config->get('menu_api'),
    ];

    $form['menu_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Menu API key'),
      '#default_value' => $config->get('menu_api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('menu_api', $form_state->getValue('menu_api'))
      ->set('menu_api_key', $form_state->getValue('menu_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
