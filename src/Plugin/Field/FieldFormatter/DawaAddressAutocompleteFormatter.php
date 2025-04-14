<?php

namespace Drupal\dawa\Plugin\Field\FieldFormatter;


use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DawaAddressAutocompleteFormatter
 *
 * @package Drupal\dawa\Plugin\Field\FieldFormatter
 *
 * @FieldFormatter(
 *   id = "dawa_address_autocomplete_formatter",
 *   label = @Translation("DAWA Address autocomplete formatter"),
 *   field_types = {
 *     "dawa_address_autocomplete"
 *   }
 * )
 */
class DawaAddressAutocompleteFormatter  extends FormatterBase {

  /**
   * @var \Drupal\Core\Extension\ModuleHandler $moduleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->moduleHandler = \Drupal::service('module_handler');
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'display_as' => 'full',
        'include_country' => TRUE
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    switch ($this->getSetting('display_as')) {
      case 'single-line':
        $display_as = $this->t('Single line');
        break;
      case 'full':
      default:
        $display_as = $this->t('Full address');
        break;
    }

    if ($this->moduleHandler->moduleExists('leaflet') && $this->getSetting('display_as') === 'leaflet') {
      $display_as = $this->t('Leaflet map');
    }

    $summary['display_as'] = $this->t('<b>Display as:</b> @display_as',
      [
        '@display_as' => $display_as
      ]
    );
    $summary['include_country'] = $this->t('<b>Include country:</b> @include_country.',
      [
        '@include_country' => $this->getSetting('include_country')? $this->t('Yes') : $this->t('No')
      ]
    );
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['display_as'] = [
      '#title' => $this->t('Display as'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('display_as'),
      '#options' => [
        'full' => $this->t('Full address'),
        'single-line' => $this->t('Single line'),
      ]
    ];

    $element['include_country'] = [
      '#title' => $this->t('Include country'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('include_country'),
    ];
    if ($this->moduleHandler->moduleExists('leaflet')) {
      $element['display_as']['#options']['leaflet'] = $this->t('Leaflet map');
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {

      switch ($this->getSetting('display_as')) {
        case 'single-line':
          $theme = 'dawa_address__inline';
          break;
        case 'full':
        default:
          $theme = 'dawa_address';
          break;
      }

      $element[$delta] = [
        '#theme' => $theme,
        '#id' => $item->id,
        '#data' => $item->data,
        '#view' => $this->getSetting('display_as'),
        '#show_country' => $this->getSetting('include_country')
      ];

      if ($this->moduleHandler->moduleExists('leaflet') && $this->getSetting('display_as') === 'leaflet') {
        $element[$delta] = $this->getLeafletMap($item, $element[$delta]);
      }
    }

    return $element;
  }

  /**
   * Get the leaflet map render array.
   *
   * @param object $item
   *   The address item used to get map data from.
   * @param array $popup_data
   *   The render array to display in the info popup when clicking the marker.
   *
   * @return array
   *   Returns the render array for a leaflet map.
   */
  protected function getLeafletMap($item, array $popup_data) {
    /** @var \Drupal\leaflet\LeafletService $leaflet_service */
    $leaflet_service = \Drupal::service('leaflet.service');

    if (!empty($leaflet_service)) {

      $map = leaflet_leaflet_map_info();
      $features = [
        [
          'type' => 'point',
          'lat' => $item->data['y'],
          'lon' => $item->data['x'],
          'popup' => \Drupal::service('renderer')->render($popup_data),
        ]
      ];
      $leaflet = $leaflet_service->leafletRenderMap(reset($map), $features);

      $result = [
        '#theme' => 'dawa_address__leaflet',
        '#id' => $item->id,
        '#data' => $item->data,
        '#view' => $this->getSetting('display_as'),
        '#show_country' => FALSE,
        '#leaflet' => $leaflet
      ];

      return $result;
    }

    return $popup_data;
  }
}