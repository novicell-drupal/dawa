<?php

namespace Drupal\dawa\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'dawa_autocomplete' element.
 *
 * @WebformElement(
 *   id = "dawa_autocomplete",
 *   label = @Translation("DAWA Address autocomplete"),
 *   description = @Translation("Provides advanced element for storing, validating and displaying danish postal addresses."),
 *   category = @Translation("Advanced elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   dependencies = {
 *     "dawa",
 *   }
 * )
 *
 * @see \Drupal\dawa\Element\AutocompleteAddressField
 */
class DawaAddressAutocomplete extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    return parent::getItemFormats() + [
        'address' => $this->t('Address'),
        'id' => $this->t('ID'),
        'coordinates' => $this->t('Coordinates'),
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function getItemDefaultFormat() {
    return 'address';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return \Drupal::moduleHandler()->moduleExists('dawa') ? $this->t('DAWA Address autocomplete') : parent::getPluginLabel();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (!$this->hasValue($element, $webform_submission, $options)) {
      return '';
    }

    $format = $this->getItemFormat($element);

    // Handle custom composite text items.
    if ($format === 'custom' && !empty($element['#format_text'])) {
      return $this->formatCustomItem('text', $element, $webform_submission, $options);
    }

    switch ($format) {
      case 'address':
        return $this->formatRawLines($element, $webform_submission, $options)['betegnelse'];

      case 'id':
        return $this->formatRawLines($element, $webform_submission, $options)['id'];

      case 'coordinates':
        return $this->formatRawLines($element, $webform_submission, $options)['x'] . ', ' . $this->formatRawLines($element, $webform_submission, $options)['y'];

      case 'raw':
        $lines = $this->addTextFormating($this->formatRawLines($element, $webform_submission, $options));
        return implode(PHP_EOL, $lines);

      default:
        $lines = $this->addTextFormating($this->formatNormalLines($element, $webform_submission, $options));
        return implode(PHP_EOL, $lines);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (!$this->hasValue($element, $webform_submission, $options)) {
      return '';
    }

    $format = $this->getItemFormat($element);

    // Handle custom composite html items.
    if ($format === 'custom' && !empty($element['#format_html'])) {
      return $this->formatCustomItem('html', $element, $webform_submission, $options);
    }

    switch ($format) {
      case 'address':
        return $this->formatRawLines($element, $webform_submission, $options)['betegnelse'];

      case 'id':
        return $this->formatRawLines($element, $webform_submission, $options)['id'];

      case 'coordinates':
        return $this->formatRawLines($element, $webform_submission, $options)['x'] . ', ' . $this->formatRawLines($element, $webform_submission, $options)['y'];

      case 'raw':
        $items = $this->addHtmlFormating($this->formatRawLines($element, $webform_submission, $options));
        return [
          '#theme' => 'item_list',
          '#items' => $items,
        ];

      default:
        $lines = $this->addHtmlFormating($this->formatNormalLines($element, $webform_submission, $options));
        if (empty($lines)) {
          return '';
        }
        foreach ($lines as $key => $line) {
          if (is_string($line)) {
            $lines[$key] = ['#markup' => $line];
          }
          $lines[$key]['#suffix'] = '<br />';
        }
        // Remove the <br/> suffix from the last line.
        unset($lines[$key]['#suffix']);
        return $lines;
    }
  }

  protected function addHtmlFormating($lines) {
    foreach ($lines as $title => $value) {
      $lines[$title] = [
        '#type' => 'inline_template',
        '#template' => '<b>{{ title }}:</b> {{ value }}',
        '#context' => [
          'title' => $title,
          'value' => $value,
        ],
      ];
    }
    return $lines;
  }

  protected function addTextFormating($lines) {
    foreach ($lines as $title => $value) {
      $lines[$title] = $title . ': ' . $value;
    }
    return $lines;
  }

  protected function formatNormalLines(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];
    if (!empty($value['betegnelse'])) {
      $lines[$this->t('Address')->__toString()] = $value['betegnelse'];
    }
    if (!empty($value['id'])) {
      $lines[$this->t('DAWA ID')->__toString()] = $value['id'];
    }
    if (!empty($value['x']) && !empty($value['y'])) {
      $lines[$this->t('Coordinates')->__toString()] = $value['x'] . ', ' . $value['y'];
    }
    return $lines;
  }

  protected function formatRawLines(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];
    foreach ($value as $key => $item) {
      $lines[$key] = $item;
    }
    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(array &$element, WebformSubmissionInterface $webform_submission) {
    $webform_key = (isset($options['webform_key'])) ? $options['webform_key'] : $element['#webform_key'];
    $values = $webform_submission->getElementData($webform_key);
    if (isset($values['data'])) {
      $values = $values['data'];
    }
    $webform_submission->setElementData($webform_key, $values);
  }

}
