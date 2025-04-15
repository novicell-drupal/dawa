<?php

namespace Drupal\transform_api_dawa\Plugin\Transform\Field;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\transform_api\FieldTransformBase;


/**
 * Transform field plugin for dawa address field types.
 *
 * @FieldTransform(
 *  id = "dawa_address_transform",
 *  label = @Translation("DAWA Address transform"),
 *  field_types = {
 *    "dawa_address_autocomplete",
 *  }
 * )
 */
class DawaAddressTransform extends FieldTransformBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'transform_mode' => 'default',
    ] + parent::defaultSettings();
  }

  /**
   * @return array
   *   Transform mode options.
   */
  protected function getTransformModeOptions(): array {
    return [
      'default' => $this->t('Default (only address)'),
      'all' => $this->t('All data'),
      'latlng' => $this->t('Latitude and Longitude'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['transform_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Transform mode'),
      '#options' => $this->getTransformModeOptions(),
      '#default_value' => $this->getSetting('transform_mode') ?? 'default',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $transform_mode = $this->getSetting('transform_mode');

    $summary[] = $this->t('Transform mode: @mode', ['@mode' => $this->getTransformModeOptions()[$transform_mode]]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function transformElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $values = [];

    foreach ($items as $delta => $item) {
      $values[$delta] = $this->transformElement($item);
    }

    return $values;
  }

  /**
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *
   * @return array
   */
  protected function transformElement(FieldItemInterface $item): array {
    $values = $item->getValue();

    switch ($this->getSetting('transform_mode')) {
      case 'default':
        // Only return the address.
        return [
          'value' => $values['value'],
        ];

      case 'all':
        // Return all data.
        return $values;

      case 'latlng':
        // Return only latitude and longitude.
        if (isset($values['data']['x']) && isset($values['data']['y'])) {
          return [
            'lat' => $values['data']['y'],
            'lng' => $values['data']['x'],
          ];
        }
        break;
    }

    return [];
  }

}
