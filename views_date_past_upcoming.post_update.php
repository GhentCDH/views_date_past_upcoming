<?php

/**
 * @file
 * Post update functions for the views_date_past_upcoming module.
 */

use Drupal\views\Entity\View;

/**
 * Converts the deprecated "Custom Global" handlers to field-based handlers.
 */
function views_date_past_upcoming_post_update_convert_legacy_handlers() {
  $views_data = \Drupal::service('views.views_data');
  $logger = \Drupal::logger('views_date_past_upcoming');
  $legacy_plugins = [
    'fields' => ['type' => 'field', 'plugin_id' => 'date_past_upcoming'],
    'sorts' => ['type' => 'sort', 'plugin_id' => 'date_past_upcoming_sort'],
  ];
  $converted = [];

  foreach (View::loadMultiple() as $view) {
    $entity_type = $views_data->get($view->get('base_table'))['table']['entity type'] ?? NULL;
    $displays = $view->get('display');
    $changed = FALSE;

    foreach ($displays as $display_id => $display) {
      foreach ($legacy_plugins as $section => $legacy) {
        foreach ($display['display_options'][$section] ?? [] as $handler_id => $handler) {
          if (($handler['table'] ?? '') !== 'views' || ($handler['plugin_id'] ?? '') !== $legacy['plugin_id']) {
            continue;
          }

          $field_name = $handler['datetime_field_machinename'] ?? '';
          $table = $entity_type . '__' . $field_name;
          $field = $field_name . '_past_upcoming';
          if (!$entity_type || $field_name === '' || empty($views_data->get($table)[$field][$legacy['type']])) {
            $logger->warning('Could not convert the past/upcoming @type %id in display %display of view %view: field %field not found.', [
              '@type' => $legacy['type'],
              '%id' => $handler_id,
              '%display' => $display_id,
              '%view' => $view->id(),
              '%field' => $field_name,
            ]);
            continue;
          }

          $handler['table'] = $table;
          $handler['field'] = $field;
          $handler['plugin_id'] = 'past_upcoming';
          unset($handler['datetime_field_machinename'], $handler['use_end_date']);
          $displays[$display_id]['display_options'][$section][$handler_id] = $handler;
          $changed = TRUE;
        }
      }
    }

    if ($changed) {
      $view->set('display', $displays);
      $view->save();
      $converted[] = $view->id();
    }
  }

  return $converted
    ? t('Converted the past/upcoming handlers of these views: @views.', ['@views' => implode(', ', $converted)])
    : t('No past/upcoming handlers needed to be converted.');
}
