<?php

namespace Drupal\dawa\Plugin\Field\FieldType;


use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Class DawaAddressAutocomplete
 *
 * @package Drupal\dawa\Plugin\Field\FieldType
 *
 * @FieldType(
 *   id = "dawa_address_autocomplete",
 *   label = @Translation("DAWA Address autocomplete"),
 *   default_formatter = "dawa_address_autocomplete_formatter",
 *   default_widget = "dawa_address_autocomplete_widget",
 *   category = @Translation("Text")
 * )
 */
class DawaAddressAutocomplete extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['id'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel(t('DAWA ID'));
    $properties['value'] = DataDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel(t('The entered address value'));
    $properties['data'] = MapDataDefinition::create('map')
      ->setRequired(FALSE)
      ->setLabel(t('Address data'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'value' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'data' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
          'not null' => FALSE,
        ]
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $id = $this->get('id')->getValue();
    $value = $this->get('value')->getValue();

    if (empty($id) || empty($value)) {
      return TRUE;
    }
    return FALSE;
  }
}