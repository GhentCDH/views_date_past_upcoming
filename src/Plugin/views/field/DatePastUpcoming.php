<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;


/**
 * Computed field that labels a date as 'Past' or 'Upcoming'.
 *
 * Reads a configured datetime field from the row's entity and returns a
 * configurable label based on whether the date falls before or after the
 * current day. Supports date-range fields via an optional 'use end date'
 * setting. No query alteration is needed because the value is computed in PHP.
 *
 * @ViewsField("date_past_upcoming")
 */
class DatePastUpcoming extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
  }


  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['datetime_field_machinename'] = ['default' => ''];
    $options['use_end_date'] = ['default' => 0];
    $options['label_past'] = ['default' => $this->t('Past')];
    $options['label_upcoming'] = ['default' => $this->t('Upcoming')];

    return $options;
  }


  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['datetime_field_machinename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datetime machine name'),
      '#description' => $this->t('The machine name of the datetime field to evaluate (e.g. field_event_date).'),
      '#required' => TRUE,
      '#default_value' => $this->options['datetime_field_machinename'] ?? '',
    ];

    $form['use_end_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use end date if available'),
      '#description' => $this->t('For date range fields, evaluate the end date instead of the start date.'),
      '#default_value' => $this->options['use_end_date'] ?? FALSE,
    ];

    $form['label_past'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for past dates'),
      '#default_value' => $this->options['label_past'],
    ];

    $form['label_upcoming'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for upcoming dates'),
      '#default_value' => $this->options['label_upcoming'] ?? 'Upcoming',
    ];
  }


  /**
   * Returns the past or upcoming label for the row entity's date field.
   *
   * Returns NULL when the entity is missing or the configured field is empty.
   * Uses the end date of a date-range field when 'use_end_date' is enabled.
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $entity = $values->_entity;

    if (!$entity) {
      return NULL;
    }

    $field_name = $this->options['datetime_field_machinename'];

    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return NULL;
    }

    $field = $entity->get($field_name);
    $date_value = $field->value;

    if (
      $this->options['use_end_date'] &&
      isset($field->end_value) &&
      !empty($field->end_value)
    ) {
      $date_value = $field->end_value;
    }

    $timestamp = strtotime($date_value);
    $today = strtotime('today');

    return $timestamp < $today ? $this->options['label_past'] : $this->options['label_upcoming'];
  }


  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return $value ?? '';
  }

}
