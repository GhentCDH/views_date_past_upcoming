<?php

namespace Drupal\views_date_past_upcoming;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Classifies stored date values as upcoming, past or without a date.
 *
 * The rule, shared by the Views field and sort plugins:
 * - When an end date is present, the item is past once the end has passed.
 * - When only a start date is present, the item stays upcoming for the whole
 *   of that day and becomes past at the next midnight.
 * - Date-only fields (no time) compare both dates with today's date.
 * - Items without a start and an end date are classified as "none".
 *
 * "Now" and "start of today" are computed in the current (site or user)
 * timezone and converted to the storage timezone (UTC), so they can be
 * compared as strings with the stored values, both in PHP and in SQL. "Now"
 * is rounded down to the minute, which keeps the Views results cache usable.
 */
class PastUpcomingClassifier {

  /**
   * The item is upcoming (or still running).
   */
  public const UPCOMING = 'upcoming';

  /**
   * The item is upcoming and has already started.
   */
  public const ONGOING = 'ongoing';

  /**
   * The item is in the past.
   */
  public const PAST = 'past';

  /**
   * The item has no date.
   */
  public const NONE = 'none';

  /**
   * Constructs a PastUpcomingClassifier.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * Returns the cut-off values, in storage format, for a datetime type.
   *
   * @param string $datetime_type
   *   The datetime type of the field storage ('date', 'datetime' or 'allday').
   *
   * @return array{end: string, start: string}
   *   - end: the value an end date is compared with (now, or today's date).
   *   - start: the value a start date is compared with (start of today, or
   *     today's date).
   */
  public function getCutoffs(string $datetime_type): array {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $now = (new \DateTimeImmutable('@' . $this->getNow()))->setTimezone($timezone);

    if ($this->isDateOnly($datetime_type)) {
      $today = $now->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
      return ['end' => $today, 'start' => $today];
    }

    $utc = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    return [
      'end' => $now->setTimezone($utc)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      'start' => $now->setTime(0, 0)->setTimezone($utc)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    ];
  }

  /**
   * Classifies a start and an optional end value.
   *
   * @param string|null $start
   *   The stored start value.
   * @param string|null $end
   *   The stored end value, or NULL for fields without an end date.
   * @param string $datetime_type
   *   The datetime type of the field storage.
   *
   * @return string
   *   One of the UPCOMING, ONGOING, PAST or NONE constants.
   */
  public function classify(?string $start, ?string $end, string $datetime_type): string {
    $start = $start === '' ? NULL : $start;
    $end = $end === '' ? NULL : $end;
    $cutoffs = $this->getCutoffs($datetime_type);

    if ($end !== NULL) {
      if ($end < $cutoffs['end']) {
        return self::PAST;
      }
      return $start !== NULL && $start <= $cutoffs['end'] ? self::ONGOING : self::UPCOMING;
    }
    if ($start !== NULL) {
      return $start >= $cutoffs['start'] ? self::UPCOMING : self::PAST;
    }
    return self::NONE;
  }

  /**
   * Returns the number of seconds until the classification of a value changes.
   *
   * @param string|null $start
   *   The stored start value.
   * @param string|null $end
   *   The stored end value, or NULL for fields without an end date.
   * @param string $datetime_type
   *   The datetime type of the field storage.
   *
   * @return int
   *   The number of seconds, or Cache::PERMANENT when it never changes.
   */
  public function getMaxAge(?string $start, ?string $end, string $datetime_type): int {
    $status = $this->classify($start, $end, $datetime_type);
    if ($status === self::PAST || $status === self::NONE) {
      return Cache::PERMANENT;
    }

    $changes = [];
    if ($end !== NULL && $end !== '') {
      // Past once the end has passed (at the start of the next minute, because
      // "now" is rounded down to the minute, or at midnight after a date).
      $changes[] = $this->isDateOnly($datetime_type)
        ? $this->getNextMidnight($end)
        : $this->toTimestamp($end) - $this->toTimestamp($end) % 60 + 60;
      // Upcoming items that have not started yet become ongoing.
      if ($status === self::UPCOMING && $start !== NULL && $start !== '') {
        $changes[] = $this->isDateOnly($datetime_type)
          ? $this->toTimestamp($start)
          : (int) ceil($this->toTimestamp($start) / 60) * 60;
      }
    }
    else {
      // Start-only items are upcoming for the whole day of their start date.
      $changes[] = $this->getNextMidnight($start);
    }

    return max(0, min($changes) - $this->getNow());
  }

  /**
   * Returns the current time, rounded down to the minute.
   */
  protected function getNow(): int {
    $request_time = $this->time->getRequestTime();
    return $request_time - $request_time % 60;
  }

  /**
   * Checks whether a datetime type stores dates without a time.
   */
  protected function isDateOnly(string $datetime_type): bool {
    return $datetime_type === DateTimeItem::DATETIME_TYPE_DATE;
  }

  /**
   * Converts a stored value to a timestamp.
   *
   * Date-only values are interpreted as midnight in the current timezone,
   * datetime values as UTC.
   */
  protected function toTimestamp(string $value): int {
    if (strlen($value) === 10) {
      return (new \DateTimeImmutable($value, new \DateTimeZone(date_default_timezone_get())))->getTimestamp();
    }
    return (new \DateTimeImmutable($value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE)))->getTimestamp();
  }

  /**
   * Returns the timestamp of the first midnight after a stored value.
   *
   * Midnight is computed in the current timezone.
   */
  protected function getNextMidnight(string $value): int {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    return (new \DateTimeImmutable('@' . $this->toTimestamp($value)))
      ->setTimezone($timezone)
      ->setTime(0, 0)
      ->modify('+1 day')
      ->getTimestamp();
  }

}
