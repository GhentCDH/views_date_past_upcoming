<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\sort;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views_date_past_upcoming\Plugin\views\LegacyPastUpcomingHandlerTrait;

/**
 * Deprecated "Custom Global" past/upcoming sort.
 *
 * Takes the machine name of the date field as an option. Existing views are
 * converted to the field-based handler by
 * views_date_past_upcoming_post_update_convert_legacy_handlers().
 *
 * Deprecated: use the "(past/upcoming)" sort of the date field instead. This
 * handler will be removed in a future major version.
 *
 * @ViewsSort("date_past_upcoming_sort")
 */
class DatePastUpcomingSort extends PastUpcoming {

  use LegacyPastUpcomingHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->resolveLegacyDefinition('sort')) {
      parent::query();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['datetime_field_machinename'] = ['default' => ''];
    // No longer used: the end date is always used when it is present.
    $options['use_end_date'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['datetime_field_machinename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datetime field machine name'),
      '#description' => $this->t('Deprecated: add the "(past/upcoming)" sort of the date field instead.'),
      '#required' => TRUE,
      '#default_value' => $this->options['datetime_field_machinename'],
      '#weight' => -101,
    ];
  }

}
