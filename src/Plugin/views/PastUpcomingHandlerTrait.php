<?php

namespace Drupal\views_date_past_upcoming\Plugin\views;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\views\ResultRow;

/**
 * Shared query logic for the past/upcoming field and sort handlers.
 *
 * The handlers are attached to the dedicated table of a datetime or daterange
 * field (see views_date_past_upcoming_field_views_data_alter()). Their
 * definition holds the start column, the optional end column and the datetime
 * type of the field storage.
 */
trait PastUpcomingHandlerTrait {

  /**
   * The query aliases of the start and end columns.
   *
   * @var array<string, string>
   */
  protected array $dateAliases = [];

  /**
   * {@inheritdoc}
   */
  public function getDatetimeType(): string {
    return $this->definition['datetime_type'] ?? DateTimeItem::DATETIME_TYPE_DATETIME;
  }

  /**
   * {@inheritdoc}
   */
  public function getDateValues(ResultRow $row): array {
    $start = isset($this->dateAliases['start']) ? ($row->{$this->dateAliases['start']} ?? NULL) : NULL;
    $end = isset($this->dateAliases['end']) ? ($row->{$this->dateAliases['end']} ?? NULL) : NULL;
    return [$start, $end];
  }

  /**
   * Joins the field table, limited to the first value of the field.
   *
   * Restricting the join to delta 0 prevents duplicate rows for multi-value
   * fields. The join provided by Views already takes care of the language and
   * deleted conditions.
   *
   * @return string|null
   *   The table alias, or NULL when the table cannot be joined.
   */
  protected function ensureDateTable(): ?string {
    if (!isset($this->tableAlias)) {
      $join = $this->getJoin();
      if ($join && is_array($join->extra ?? [])) {
        $join->extra = $join->extra ?? [];
        $join->extra[] = ['field' => 'delta', 'value' => 0, 'numeric' => TRUE];
      }
      $this->tableAlias = $this->query->ensureTable($this->table, $this->relationship, $join) ?: NULL;
    }
    return $this->tableAlias;
  }

  /**
   * Adds the start and end columns to the query.
   *
   * @return string|null
   *   The table alias, or NULL when the table cannot be joined.
   */
  protected function addDateFields(): ?string {
    if (empty($this->definition['start_column']) || !($alias = $this->ensureDateTable())) {
      return NULL;
    }
    $this->dateAliases['start'] = $this->query->addField($alias, $this->definition['start_column']);
    if (!empty($this->definition['end_column'])) {
      $this->dateAliases['end'] = $this->query->addField($alias, $this->definition['end_column']);
    }
    return $alias;
  }

}
