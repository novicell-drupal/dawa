<?php

namespace Drupal\dawa_custom_forms_field\Plugin\CustomForms\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_forms\Annotation\CustomFormsFieldType;
use Drupal\custom_forms\CustomFormItem;
use Drupal\custom_forms\Plugin\CustomForms\FieldType\CustomFormsFieldTypeBase;
use Drupal\custom_forms_states\Element\StateElement;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DawaAddress
 *
 * @package Drupal\dawa_custom_forms_field\Plugin\CustomForms\FieldType
 *
 * @CustomFormsFieldType(
 *   id = "dawa_address",
 *   label = @Translation("DAWA Autocomplete address"),
 *   description = @Translation("An address field that autocompletes itself based on the danish address registry."),
 * )
 */
class DawaAddress extends CustomFormsFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldList() : array {
    return [
      'address' => [
        'type' => 'textfield',
        'title' => $this->configuration['label']
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(CustomFormItem $item, array &$form, FormStateInterface $form_state, Request $request) : array {
    $field = parent::buildForm($item, $form, $form_state, $request);
    $visibility = (!empty($this->configuration['visibility']) && $this->configuration['visibility'] === 'hidden')? 'hidden' : 'visible';

    if ($visibility === 'hidden') {
      $field = [
        '#type' => 'hidden',
        '#default_value' => !empty($this->configuration['default_value']) ? $this->configuration['default_value'] : NULL,
      ];

      // States handling.
      $formatted_states = StateElement::formatStates($item->getStates(), $field);
      if (!empty($formatted_states)) {
        $field['#states'] = $formatted_states;
      }
      return $field;
    }

    $field += [
      '#type' => 'dawa_autocomplete',
      '#title' => $this->configuration['label'],
      '#title_display' => (isset($this->configuration['show_label']) && (boolean)$this->configuration['show_label'] === FALSE)? 'invisible' : 'before',
      '#placeholder' => !empty($this->configuration['placeholder'])? $this->configuration['placeholder'] : '',
      '#default_value' => !empty($this->configuration['default_value'])? $this->configuration['default_value'] : ['id' => '', 'value' => '', 'data' => []],
      '#description' => !empty($this->configuration['description'])? $this->configuration['description'] : '',
    ];

    // States handling
    $formatted_states = StateElement::formatStates($item->getStates(), $field);
    if (!empty($formatted_states)) {
      $field['#states'] = $formatted_states;
    }

    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function modifyJquerySelector($machine_name) : string {
    // Because the dawa autocomplete field consists of a value and id subfield,
    // we need to modify the jquery selector to target the value field.
    return $machine_name.'[value]';
  }

  /**
   * {@inheritdoc}
   */
  public function process(array &$values, FormStateInterface $form_state, CustomFormItem $item) : void {

    // We should process the field values regardless of the field being empty
    // Making sure we always get the same result (but with empty/null values).
    $address_data = $values[$this->configuration['machine_name']];
    $value = [
      'id'            => !empty($address_data['id']) ? $address_data['id'] : '',
      'street'        => !empty($address_data['data']['vejnavn']) ? $address_data['data']['vejnavn'] : '',
      'house_no'      => !empty($address_data['data']['husnr']) ? $address_data['data']['husnr'] : '',
      'floor'         => !empty($address_data['data']['etage']) ? $address_data['data']['etage'] : '',
      'door'          => !empty($address_data['data']['dør']) ? $address_data['data']['dør'] : '',
      'postal_code'   => !empty($address_data['data']['postnr']) ? $address_data['data']['postnr'] : '',
      'city'          => !empty($address_data['data']['postnrnavn']) ? $address_data['data']['postnrnavn'] : '',
      'x'             => !empty($address_data['data']['x']) ? $address_data['data']['x'] : '',
      'y'             => !empty($address_data['data']['y']) ? $address_data['data']['y'] : '',
    ];

    if ((bool)$this->configuration['primary']) {
      $values['_address']['primary'] = $value;
    }

    $values['_address'][$this->configuration['machine_name']] = $value;
    $values['_address'][$this->configuration['machine_name']]['source_field'] = $this->configuration['machine_name'];
    $values['_address'][$this->configuration['machine_name']]['primary'] = (bool)$this->configuration['primary'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(CustomFormItem $item, array &$values, array &$errors) : bool {
    // We should only validate the field if it's not empty as required validation
    // has already been handled globally.
    if (!empty($values[$item->getMachineName()])) {
      $invalid = FALSE;
      if (
        empty($values[$item->getMachineName()]['data']) ||
        empty($values[$item->getMachineName()]['id']) ||
        empty($values[$item->getMachineName()]['value'])
      ) {
        $invalid = TRUE;
      }

      // If it's not invalid, try to check the address.
      if (!$invalid) {
        /** @var \Drupal\dawa\Controller\Addresses $address_service */
        $address_service = \Drupal::service('dawa.api.addresses');
        $address_check = $address_service->addressLookup($values[$item->getMachineName()]['id'], 'mini');
        if ($address_check === FALSE) {
          $invalid = TRUE;
        }

        if ($invalid) {
          $errors[$item->getMachineName()]['invalid'] = $this->t('Invalid address selected, please try again.');
          return FALSE;
        }
      } else {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function formatReceiptValue($value) : array {
    if (
      !empty($value[$this->configuration['machine_name']]['id']) &&
      !empty($value[$this->configuration['machine_name']]['data'])
    ) {
      $result = parent::formatReceiptValue($value);
      $result['value'] = [
        '#theme' => 'dawa_address',
        '#id' => $value['_address'][$this->configuration['machine_name']]['id'],
        '#data' => [
          'vejnavn' => $value['_address'][$this->configuration['machine_name']]['street'],
          'husnr' => $value['_address'][$this->configuration['machine_name']]['house_no'],
          'etage' => $value['_address'][$this->configuration['machine_name']]['floor'],
          'dør' => $value['_address'][$this->configuration['machine_name']]['door'],
          'postnr' => $value['_address'][$this->configuration['machine_name']]['postal_code'],
          'postnrnavn' => $value['_address'][$this->configuration['machine_name']]['city'],
        ],
        '#view' => 'full',
        '#show_country' => FALSE,
      ];
    } else {
      $result = [];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(CustomFormItem $item, array &$form, FormStateInterface $form_state) : void {
    parent::buildSettingsForm($item, $form, $form_state);

    $form['default_value'] = [
      '#type' => 'dawa_autocomplete',
      '#title' => $this->t('Default value'),
      '#placeholder' => $this->t('Default value'),
      '#description' => $this->t('Controls the default value of the field.'),
      '#default_value' => !empty($this->configuration['default_value'])? $this->configuration['default_value'] : ['id' => '', 'value' => '', 'data' => []],
    ];

    $form['primary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use as primary address.'),
      '#description' => $this->t('Use the value submitted to this field as the primary address for the submission.'),
      '#default_value' => !empty($this->configuration['primary'])? $this->configuration['primary'] : FALSE,
    ];

    if (!empty($form['visibility'])) {
      $form['default_value']['#states']['required'] = [
        'select[name="visibility"]' => ['value' => 'hidden']
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(array $values, CustomFormItem $item = NULL) : bool {
    if (
      empty($values[$this->configuration['machine_name']]['data']) ||
      empty($values[$this->configuration['machine_name']]['id']) ||
      empty($values[$this->configuration['machine_name']]['value'])
    ) {
      return TRUE;
    }
    return FALSE;
  }

}
