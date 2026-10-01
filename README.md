# views_date_past_upcoming

A lightweight Drupal module — no configuration forms, no custom entities — that adds a **past/upcoming** field and sort to every datetime and date range field in Views.

## How items are classified

| Data on the row | Past when… |
|---|---|
| Start and end date | the end date/time has passed |
| Start date only | the start day is over (it stays upcoming for the whole day) |
| Date-only field (no time) | the end date (or start date) is before today |
| No date at all | neither: these rows are "no date" |

"Now" and "start of today" are computed in the site or user timezone. "Now" is rounded down to the minute.

Items with an end date that have started but not ended yet are **ongoing**. They count as upcoming, and the field can give them their own label.

## Plugins

Both plugins are available for every configurable `datetime` and `daterange` field. In the Views UI they appear in the field's group as **"&lt;Field label&gt; (past/upcoming)"**. They work through relationships too.

### Field: (past/upcoming)

Outputs a label for each row.

| Option | Default | Description |
|---|---|---|
| Label for upcoming dates | *Upcoming* | |
| Label for past dates | *Past* | |
| Label for ongoing items | *(empty)* | Leave empty to use the upcoming label. |
| Label when there is no date | *Date unknown* | Leave empty to output nothing, so the field's *No results behavior* applies (e.g. "Hide if empty"). |

The token `{{ <field id>__status }}` holds the status as a machine name (`upcoming`, `ongoing`, `past` or `none`). This is useful for CSS classes in *Rewrite results*.

### Sort: (past/upcoming)

The sort only changes the order of the rows; it does not show any labels or headings. The order is fixed and cannot be exposed:

1. **Upcoming and ongoing** items, by start date, soonest first.
2. **Past** items, by end date (or start date when there is no end date), most recent first.
3. Items **without a date**, in the order of the next sort criterion (e.g. add a title sort after it).

### Showing headings (Upcoming / Past / Date unknown)

Combine the sort with the field:

1. Add the **"&lt;Field label&gt; (past/upcoming)"** sort.
2. Add the **"&lt;Field label&gt; (past/upcoming)"** field and tick *Exclude from display*.
3. Under **Format › Settings**, choose that field as the *Grouping field*.

The groups appear in the order of the sort: Upcoming, Past, Date unknown. If you set a label for ongoing items, an "Ongoing" group appears above "Upcoming". Ongoing items started earliest, so they are sorted first.

### Notes

- Only the first value of a multi-value date field is evaluated, so multi-value fields don't produce duplicate rows.
- Base fields (date fields defined in code rather than in the UI) are not supported, because Views has no field table for them.
- If the view returns no rows at all, Views' own **No results behavior** area applies.

## Caching

The output depends on the current time. The module limits the cache lifetime of the rendered view to the moment the first row changes from upcoming to past (or from upcoming to ongoing), and adds the `timezone` cache context. This works with Views caching, the render cache and the Dynamic Page Cache.

**Internal Page Cache** (anonymous users) ignores the cache lifetime. On sites where anonymous visitors see these views, either disable the `page_cache` module, or clear the cache regularly (e.g. nightly from cron).

## Upgrading from 1.x

Version 1.x provided "Date Past/Upcoming" handlers in the *Custom Global* group, where you typed the field's machine name. These handlers have been removed. Run the database updates right after deploying the code, before importing configuration (`drush deploy` does this in the right order):

```bash
drush updatedb
```

This converts existing views to the new field-based handlers.

Behaviour changes:
- The *Use end date if available* option is gone: the end date is always used when it is present.
- Rows without a date are now always sorted last. On MySQL they used to appear first.
- Dates are now evaluated in the correct timezone.

## Requirements

- Drupal 10 or 11
- `drupal:views` and `drupal:datetime` (and `drupal:datetime_range` for date range fields)

Tested on MySQL, PostgreSQL and SQLite.

## Installation

### Via Composer from GitHub (recommended)

Add the repository to your project's `composer.json`:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/GhentCDH/views_date_past_upcoming"
  }
]
```

Then require the module:

```bash
composer require drupal/views_date_past_upcoming
```

Enable the module:

```bash
drush en views_date_past_upcoming
```

### Manual installation

Download or clone this repository into `web/modules/custom/views_date_past_upcoming/` and enable it via Drush or the Drupal admin UI.

## Configuration

1. Open a View and click **Add** next to **Fields** (or **Sort criteria**).
2. Search for your date field and select **"&lt;Field label&gt; (past/upcoming)"**.
3. For the field, optionally adjust the labels.

## Running the tests

```bash
cd web
SIMPLETEST_DB=sqlite://localhost//tmp/test.sqlite ../vendor/bin/phpunit -c core/phpunit.xml.dist modules/custom/views_date_past_upcoming/tests
```

## License

GPL-2.0-or-later.
