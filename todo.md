# views_date_past_upcoming — TODO

## Known limitations

- **Database portability**: `DatePastUpcomingSort::query()` switches on
  `$connection->driver()` to select date functions per backend. Only tested
  on MySQL. PostgreSQL is the most uncertain: Drupal stores datetime values as
  plain strings (varchar), and `EXTRACT(EPOCH FROM ...)` expects a typed
  timestamp — the current code may throw a type error and likely needs an
  explicit cast (`::timestamp`). SQLite is probably closer to working since
  `strftime('%s', ...)` accepts ISO 8601 strings, but is also untested.

- **Date parsing in `DatePastUpcoming::getValue()`**: the field reads the raw
  string value from the entity field item and passes it to `strtotime()`. This
  assumes the stored format is parseable by PHP (Drupal's datetime fields store
  ISO 8601 strings, so this works in practice, but it would be more robust to use
  `\Drupal\datetime\Plugin\Field\FieldType\DateTimeItem` utilities).

## Missing features

- **Tests**: no automated tests exist. PHPUnit kernel tests covering the field
  getValue logic and a functional test for the sort would be good additions.
