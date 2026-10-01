<?php

namespace Drupal\views_date_past_upcoming\Plugin\views;

use Drupal\views\ResultRow;

/**
 * Interface for Views handlers that classify a date as past or upcoming.
 */
interface PastUpcomingHandlerInterface {

  /**
   * Returns the stored start and end values of a result row.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   *
   * @return array{0: string|null, 1: string|null}
   *   The start and end values in storage format. The end value is NULL for
   *   fields without an end date.
   */
  public function getDateValues(ResultRow $row): array;

  /**
   * Returns the datetime type of the field storage.
   *
   * @return string
   *   'date', 'datetime' or 'allday'.
   */
  public function getDatetimeType(): string;

}
