# views_date_past_upcoming

A lightweight Drupal module — no configuration forms, no schema, no custom entities — that provides two Views plugins for classifying a date field as **past** or **upcoming** relative to the current day.

> **Database support**: the sort plugin has only been tested on MySQL. Code paths exist for PostgreSQL and SQLite, but they are untested and may not work correctly without further adjustments.

## Plugins

### Field: Date Past/Upcoming

A computed Views field that reads a datetime (or date range) field from each row's entity and outputs a configurable label — by default **Past** or **Upcoming**.

**Options**

| Option | Description |
|---|---|
| Datetime machine name | Machine name of the date field to evaluate (e.g. `field_event_date`). |
| Use end date if available | For date range fields: evaluate the end date instead of the start date. |
| Label for past dates | Output label when the date is before today (default: *Past*). |
| Label for upcoming dates | Output label when the date is today or later (default: *Upcoming*). |

### Sort: Date Past/Upcoming Sort

A custom sort that orders rows so that upcoming dates appear first (ascending, soonest first), followed by past dates (descending, most recent first). The sort direction is fixed and cannot be exposed to end users.

**Options**

| Option | Description |
|---|---|
| Datetime field machine name | Machine name of the date field to sort by. |
| Use end date if available | For date range fields: sort by end date instead of start date. |

## Requirements

- Drupal 10 or higher
- `drupal:views` module

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
2. Under the group **Custom Global**, select **Date Past/Upcoming** (field) or **Date Past/Upcoming Sort** (sort).
3. Configure the machine name of the datetime field you want to evaluate.
4. Optionally adjust the labels or enable end-date evaluation for date range fields.

## License

GPL-2.0-or-later.
