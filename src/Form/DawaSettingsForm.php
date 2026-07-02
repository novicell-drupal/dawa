<?php

namespace Drupal\dawa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure DAWA address settings.
 */
class DawaSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dawa_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['dawa.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('dawa.settings');

    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Adressevaelger token'),
      '#default_value' => $settings->get('token') ?: 'adressevaelger123',
      '#required' => TRUE,
      '#description' => $this->t('Token used by the Adressevaelger API and browser component.'),
    ];

    $form['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Adressevaelger API URL'),
      '#default_value' => $settings->get('api_url') ?: 'https://adressevaelger.dk',
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('dawa.settings')
      ->set('token', trim((string) $form_state->getValue('token')))
      ->set('api_url', rtrim((string) $form_state->getValue('api_url'), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
