<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\sort;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sorts results so that upcoming dates appear first, past dates last.
 *
 * Upcoming dates are ordered ascending (soonest first); past dates follow
 * in descending order (most recent first). The sort direction and expose
 * controls from the parent are removed because the ordering logic is fixed.
 *
 * Uses a CASE expression in SQL to assign a numeric sort key:
 * - upcoming: epoch timestamp of the date (ascending = soonest first)
 * - past: 10000000000 - epoch timestamp (so most recent past sorts before older)
 *
 * @ViewsSort("date_past_upcoming_sort")
 */
class DatePastUpcomingSort extends SortPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected PluginManagerInterface $joinManager,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    foreach (['order', 'expose', 'exposed'] as $key) {
      unset($options[$key]);
    }

    $options['datetime_field_machinename'] = ['default' => ''];
    $options['use_end_date'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // The sort order is fixed; the expose control is irrelevant.
    unset($form['order'], $form['expose_button']);

    $form['datetime_field_machinename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datetime field machine name'),
      '#required' => TRUE,
      '#default_value' => $this->options['datetime_field_machinename'] ?? '',
      '#description' => $this->t('Example: field_time_period (without node__ prefix)'),
    ];

    $form['use_end_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use end date if available'),
      '#default_value' => $this->options['use_end_date'] ?? FALSE,
      '#description' => $this->t('For date range fields, sort by end date instead of start date.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $field = $this->options['datetime_field_machinename'];

    if (empty($field)) {
      return;
    }

    $entity_type = $this->view->getBaseEntityType()->id();
    $field_table = "{$entity_type}__{$field}";

    $configuration = [
      'table' => $field_table,
      'field' => 'entity_id',
      'left_table' => $entity_type . '_field_data',
      'left_field' => $entity_type === 'node' ? 'nid' : 'id',
    ];

    $join = $this->joinManager->createInstance('standard', $configuration);
    $alias = $this->query->addRelationship($field_table, $join, $entity_type . '_field_data');

    $column_suffix = $this->options['use_end_date'] ? 'end_value' : 'value';
    $column = "{$alias}.{$field}_{$column_suffix}";

    switch ($this->database->driver()) {
      case 'pgsql':
        $now = 'CURRENT_TIMESTAMP';
        $epoch = "EXTRACT(EPOCH FROM {$column})";
        break;

      case 'sqlite':
        $now = "datetime('now')";
        $epoch = "strftime('%s', {$column})";
        break;

      default:
        $now = 'NOW()';
        $epoch = "UNIX_TIMESTAMP({$column})";
    }

    // Upcoming dates sort by their epoch value (ascending = soonest first).
    // Past dates sort by their inverted epoch so the most recent past date
    // appears before older past dates.
    $formula = "
      CASE
        WHEN {$column} >= {$now}
          THEN {$epoch}
        ELSE
          10000000000 - {$epoch}
      END
    ";

    $this->query->addOrderBy(NULL, $formula, 'ASC', 'date_past_upcoming_order');
  }

}
