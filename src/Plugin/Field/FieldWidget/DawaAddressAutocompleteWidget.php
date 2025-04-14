<?php

namespace Drupal\dawa\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DawaAddressAutocompleteWidget
 *
 * @package Drupal\dawa\Plugin\Field\FieldWidget
 *
 * @FieldWidget(
 *   id = "dawa_address_autocomplete_widget",
 *   label = @Translation("DAWA Address autocomplete widget"),
 *   field_types = {
 *     "dawa_address_autocomplete"
 *   }
 * )
 */
class DawaAddressAutocompleteWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // We just need to tell it what field type to use, validation and saving
    // of data is all handled for us by the field.
    $element['#type'] = 'dawa_autocomplete';
    if (!empty($items[$delta]) && !empty($items[$delta]->id) && !empty($items[$delta]->value)) {
      $data = !empty($items[$delta]->data)? $items[$delta]->data : [];
      $element['#default_value'] = ['id' => $items[$delta]->id, 'value' => $items[$delta]->value, 'data' => $data];
    }

    return $element;
  }
}