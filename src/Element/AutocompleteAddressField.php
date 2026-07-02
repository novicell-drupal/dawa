<?php

namespace Drupal\dawa\Element;


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a one-line text field form element for dawa address autocomplete.
 *
 * Usage example:
 * @code
 * $form['title'] = [
 *   '#type' => 'dawa_autocomplete',
 *   '#title' => $this->t('Address'),
 *   '#default_value' => [
 *     'id' => 'specific-dawa-id',
 *     'value' => 'Text shown in text field',
 *     'data' => []
 *   ],
 *   #required' => TRUE,
 * ];
 * @endcode
 *
 * @FormElement("dawa_autocomplete")
 */
class AutocompleteAddressField extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#markup' => '',
      '#default_value' => [],
      '#process' => [
        [$class, 'processAutocompleteAddressField']
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $value = ['id' => '', 'value' => '', 'data' => []];

    if ($input === FALSE) {
      if (!empty($element['#default_value'])) {
        return $element['#default_value'];
      } else {
        return $value;
      }
    }

    // Throw out all invalid array keys; we only allow the field containing the dawa id and entered value.
    foreach ($value as $allowed_key => $default) {
      if (isset($input[$allowed_key])) {
        $value[$allowed_key] = $input[$allowed_key] ?? $default;
      }
    }

    return $value;
  }

  /**
   * Prepares a #type 'textfield' render element for input.html.twig.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for input.html.twig.
   */
  public static function processAutocompleteAddressField($element) {
    $settings = \Drupal::config('dawa.settings');

    $element['#wrapper_attributes']['class'][] = 'dawa-autocomplete';
    $element['#wrapper_attributes']['class'][] = 'js-dawa-autocomplete';
    $element['#element_validate'] = [[get_called_class(), 'validateAutocompleteAddressField']];
    $element['#tree'] = TRUE;

    $attributes = [
      'class' => [
        'autocomplete-field',
        'js-autocomplete-field'
      ],
      'data-dawa-id' => $element['#name'] . '[id]',
      'autocomplete' => 'off',
    ];
    if (!empty($element['#attributes'])) {
      $attributes = self::array_merge_recursive_ex($attributes, $element['#attributes']);
    }

    $element['value'] = [
      '#type' => 'textfield',
      '#required' => $element['#required'],
      '#description' => empty($element['#description']) ? NULL : $element['#description'],
      '#value' => empty($element['#value']['value']) ? NULL : $element['#value']['value'],
      '#default_value' => empty($element['#default_value']['value']) ? NULL : $element['#default_value']['value'],
      '#placeholder' => empty($element['#placeholder']) ? NULL : $element['#placeholder'],
      '#attributes' => $attributes,
      '#wrapper_attributes' => [
        'data-type' => 'dawa-autocomplete',
      ],
      '#error_no_message' => TRUE,
      '#attached' => [
        'library' => [
          'dawa/autocomplete'
        ],
        'drupalSettings' => [
          'dawa' => [
            'adressevaelger' => [
              'token' => $settings->get('token') ?: 'adressevaelger123',
              'apiUrl' => $settings->get('api_url') ?: 'https://adressevaelger.dk',
            ],
          ],
        ],
      ]
    ];
    // Title handling
    if (!empty($element['#title'])) {
      $element['value']['#title'] = $element['#title'];
      unset($element['#title']);
    }
    if (!empty($element['#title_display'])) {
      $element['value']['#title_display'] = $element['#title_display'];
      unset($element['#title_display']);
    }

    $element['id'] = [
      '#type' => 'hidden',
      '#value' => empty($element['#value']['id']) ? NULL : $element['#value']['id'],
      '#default_value' => empty($element['#default_value']['id']) ? NULL : $element['#default_value']['id'],
    ];

    // Ajax handling
    if (!empty($element['#ajax'])) {
      $element['value']['#ajax'] = $element['#ajax'];
      // Override ajax event as we currently only support triggering ajax when the autocomplete is finished.
      $element['value']['#ajax']['event'] = 'dawa_autocomplete_finished';
      unset($element['#ajax']);
    }
    // States handling
    if (!empty($element['#states'])) {
      $element['value']['#states'] = $element['#states'];
      unset($element['#states']);
    }

    return $element;
  }

  /**
   * Validates a autocomplete address field element.
   */
  public static function validateAutocompleteAddressField(&$element, FormStateInterface $form_state, &$complete_form) {
    $id = $element['id']['#value'];
    $entered_address = $element['value']['#value'];
    /** @var \Drupal\dawa\Controller\Addresses $dawa_service */
    $dawa_service = \Drupal::service('dawa.api.addresses');

    // Default values for the field.
    $values = ['id' => '', 'value' => '', 'data' => []];

    // If the field is required and the id is missing, throw an invalid address
    // error.
    if ($element['#required'] && (empty($id) || empty($entered_address))) {
      $form_state->setError($element, t('Invalid address, please select one from the suggestions.'));
    }

    if (!empty($id)) {

      $values['id'] = $id;
      $address_object = $dawa_service->addressLookup($id, 'nestet');
      // If the returned address object is empty, assume an invalid address and
      // display the error message.
      if (empty($address_object)) {
        $form_state->setError($element, t('Invalid address, please select one from the suggestions.'));
      } else {

        if (!empty($entered_address)) {
          // If the address entered does not match the address gotten from the Id
          // display the invalid address error.
          if ($entered_address !== $address_object['adressebetegnelse']) {
            $form_state->setError($element, t('Invalid address, please select one from the suggestions.'));
          }

          $values['value'] = $address_object['adressebetegnelse'];

          unset($address_object);
        } else {
          $form_state->setError($element, t('Invalid address, please select one from the suggestions.'));
        }

        // Get the mini object
        $address_object = $dawa_service->addressLookup($id, 'mini');

        $values['data'] = $address_object;

        $form_state->setValueForElement($element, $values);
      }
    }

    // If both ID and and the entered address is empty, set the default value.
    if (empty($id) && empty($entered_address)) {
      $form_state->setValueForElement($element, $values);
    }

    return $element;
  }

  /**
   * Recursively merge an array, overwriting any matching set keys.
   *
   * @param array $array1
   * @param array $array2
   *
   * @return array
   */
  private static function array_merge_recursive_ex(array $array1, array $array2) {
    $merged = $array1;

    foreach ($array2 as $key => & $value) {
      if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
        $merged[$key] = self::array_merge_recursive_ex($merged[$key], $value);
      } else if (is_numeric($key)) {
        if (!in_array($value, $merged)) {
          $merged[] = $value;
        }
      } else {
        $merged[$key] = $value;
      }
    }

    return $merged;
  }
}
