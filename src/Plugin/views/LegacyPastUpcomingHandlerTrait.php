<?php

namespace Drupal\views_date_past_upcoming\Plugin\views;

/**
 * Resolves the field table for the deprecated "Custom Global" handlers.
 *
 * The deprecated handlers live on the global "views" table and store the
 * machine name of the date field as an option. This trait looks up the
 * past/upcoming handler definition of that field, so the deprecated handlers
 * can reuse the logic of the field-based handlers.
 *
 * Deprecated: will be removed together with the "Custom Global" handlers.
 */
trait LegacyPastUpcomingHandlerTrait {

  /**
   * Points this handler to the table of the configured date field.
   *
   * @param string $handler_type
   *   The handler type: 'field' or 'sort'.
   *
   * @return bool
   *   TRUE when the field could be resolved.
   */
  protected function resolveLegacyDefinition(string $handler_type): bool {
    $field_name = $this->options['datetime_field_machinename'] ?? '';
    $entity_type = $this->view->getBaseEntityType();
    if ($field_name === '' || !$entity_type) {
      return FALSE;
    }

    $table = $entity_type->id() . '__' . $field_name;
    $definition = \Drupal::service('views.views_data')->get($table)[$field_name . '_past_upcoming'][$handler_type] ?? NULL;
    if (!$definition) {
      return FALSE;
    }

    $this->table = $table;
    foreach (['start_column', 'end_column', 'datetime_type'] as $key) {
      $this->definition[$key] = $definition[$key] ?? NULL;
    }
    return TRUE;
  }

}
