<?php

namespace Drupal\smc_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Jackpot settings form.
 */
class JackpotSettingsForm extends ConfigFormBase {
  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'smc_api.jackpot_settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'smc_api_admin_jackpot_settings';
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

    $form['jackpot_api'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Jackpot API'),
      '#default_value' => $config->get('jackpot_api'),
    ];

    $form['jackpot_client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('jackpot_client_id'),
    ];

    $form['jackpot_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#default_value' => $config->get('jackpot_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('jackpot_api', $form_state->getValue('jackpot_api'))
      ->set('jackpot_client_id', $form_state->getValue('jackpot_client_id'))
      ->set('jackpot_secret', $form_state->getValue('jackpot_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
