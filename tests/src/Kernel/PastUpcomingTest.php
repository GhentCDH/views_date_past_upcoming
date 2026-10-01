<?php

namespace Drupal\Tests\views_date_past_upcoming\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\RenderContext;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_date_past_upcoming\PastUpcomingClassifier;

/**
 * Tests the past/upcoming field and sort handlers.
 *
 * All tests run on Thursday 1 October 2026, 14:00:30 in Europe/Brussels
 * (12:00:30 UTC).
 *
 * @group views_date_past_upcoming
 */
class PastUpcomingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'datetime_range',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
    'views',
    'views_date_past_upcoming',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'node', 'views']);

    $this->config('system.date')->set('timezone.default', 'Europe/Brussels')->save();
    date_default_timezone_set('Europe/Brussels');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(strtotime('2026-10-01T12:00:30Z'));
    $time->method('getCurrentTime')->willReturn(strtotime('2026-10-01T12:00:30Z'));
    $this->container->set('datetime.time', $time);
    $this->container->set('views_date_past_upcoming.classifier', new PastUpcomingClassifier($time));

    NodeType::create(['type' => 'event', 'name' => 'Event'])->save();
    $this->createDateField('field_when', 'daterange', 'datetime');
    $this->createDateField('field_period', 'daterange', 'date');
    $this->createDateField('field_start', 'datetime', 'datetime', -1);
    $this->createDateField('field_day', 'datetime', 'date');
  }

  /**
   * Tests the labels of a date range field.
   */
  public function testDateRangeLabels(): void {
    $this->createEvents('field_when', [
      'ended_this_morning' => ['2026-10-01T07:00:00', '2026-10-01T10:00:00'],
      'ends_this_minute' => ['2026-10-01T07:00:00', '2026-10-01T12:00:00'],
      'running' => ['2026-09-30T08:00:00', '2026-10-02T16:00:00'],
      'tomorrow' => ['2026-10-02T08:00:00', '2026-10-02T10:00:00'],
      'no_date' => NULL,
    ]);

    $labels = $this->renderLabels('field_when', ['label_ongoing' => 'Ongoing', 'label_none' => 'TBA']);
    $this->assertSame([
      'ended_this_morning' => 'Past',
      'ends_this_minute' => 'Ongoing',
      'no_date' => 'TBA',
      'running' => 'Ongoing',
      'tomorrow' => 'Upcoming',
    ], $labels);

    // By default, ongoing items are upcoming and items without a date are
    // "Date unknown".
    $labels = $this->renderLabels('field_when');
    $this->assertSame('Upcoming', $labels['running']);
    $this->assertSame('Date unknown', $labels['no_date']);

    // An empty "no date" label outputs nothing.
    $labels = $this->renderLabels('field_when', ['label_none' => '']);
    $this->assertSame('', $labels['no_date']);
  }

  /**
   * Tests the labels of a datetime field with only a start date.
   */
  public function testStartOnlyLabels(): void {
    $this->createEvents('field_start', [
      // 30 September, 23:30 in Brussels.
      'yesterday_late' => '2026-09-30T21:30:00',
      // 1 October, 00:30 in Brussels.
      'today_just_after_midnight' => '2026-09-30T22:30:00',
      'today_earlier' => '2026-10-01T06:00:00',
      'next_week' => '2026-10-08T06:00:00',
    ]);

    $this->assertSame([
      'next_week' => 'Upcoming',
      'today_earlier' => 'Upcoming',
      'today_just_after_midnight' => 'Upcoming',
      'yesterday_late' => 'Past',
    ], $this->renderLabels('field_start'));
  }

  /**
   * Tests the labels of date-only fields.
   */
  public function testDateOnlyLabels(): void {
    $this->createEvents('field_day', [
      'yesterday' => '2026-09-30',
      'today' => '2026-10-01',
    ]);
    $this->assertSame(['today' => 'Upcoming', 'yesterday' => 'Past'], $this->renderLabels('field_day'));

    $this->createEvents('field_period', [
      'ended_yesterday' => ['2026-09-28', '2026-09-30'],
      'ends_today' => ['2026-09-28', '2026-10-01'],
    ]);
    $labels = $this->renderLabels('field_period');
    $this->assertSame('Past', $labels['ended_yesterday']);
    $this->assertSame('Upcoming', $labels['ends_today']);
  }

  /**
   * Tests the sort order of a date range field.
   */
  public function testDateRangeSort(): void {
    $this->createEvents('field_when', [
      'b_no_date' => NULL,
      'last_week' => ['2026-09-24T08:00:00', '2026-09-24T09:00:00'],
      'tomorrow' => ['2026-10-02T08:00:00', '2026-10-02T10:00:00'],
      'a_no_date' => NULL,
      'ended_this_morning' => ['2026-10-01T07:00:00', '2026-10-01T10:00:00'],
      'running' => ['2026-09-30T08:00:00', '2026-10-02T16:00:00'],
    ]);

    $this->assertSame([
      'running',
      'tomorrow',
      'ended_this_morning',
      'last_week',
      'a_no_date',
      'b_no_date',
    ], $this->getSortedTitles('field_when'));
  }

  /**
   * Tests the sort order of a multi-value datetime field.
   */
  public function testStartOnlySort(): void {
    $this->createEvents('field_start', [
      'no_date' => NULL,
      'yesterday_late' => '2026-09-30T21:30:00',
      'next_week' => '2026-10-08T06:00:00',
      'today_earlier' => '2026-10-01T06:00:00',
      'today_just_after_midnight' => '2026-09-30T22:30:00',
      'last_month' => '2026-09-01T06:00:00',
      // Only the first value counts; the second must not duplicate the row.
      'multiple_values' => ['2026-10-03T06:00:00', '2026-09-01T06:00:00'],
    ]);

    $this->assertSame([
      'today_just_after_midnight',
      'today_earlier',
      'multiple_values',
      'next_week',
      'yesterday_late',
      'last_month',
      'no_date',
    ], $this->getSortedTitles('field_start'));
  }

  /**
   * Tests the max-age of the rendered view.
   */
  public function testMaxAge(): void {
    $classifier = $this->container->get('views_date_past_upcoming.classifier');

    // Start only, today: until midnight in Brussels (22:00 UTC).
    $this->assertSame(10 * 3600, $classifier->getMaxAge('2026-10-01T06:00:00', NULL, 'datetime'));
    // Not started yet: until it starts (becomes ongoing).
    $this->assertSame(20 * 3600, $classifier->getMaxAge('2026-10-02T08:00:00', '2026-10-02T10:00:00', 'datetime'));
    // Running: until the minute after the end.
    $this->assertSame(28 * 3600 + 60, $classifier->getMaxAge('2026-09-30T08:00:00', '2026-10-02T16:00:00', 'datetime'));
    // Date only, ends today: until midnight.
    $this->assertSame(10 * 3600, $classifier->getMaxAge('2026-09-28', '2026-10-01', 'date'));
    // Past or no date: never changes.
    $this->assertSame(Cache::PERMANENT, $classifier->getMaxAge('2026-09-28', '2026-09-30', 'date'));
    $this->assertSame(Cache::PERMANENT, $classifier->getMaxAge(NULL, NULL, 'datetime'));

    // The rendered view expires when the first row changes.
    $this->createEvents('field_when', [
      'tomorrow' => ['2026-10-02T08:00:00', '2026-10-02T10:00:00'],
      'running' => ['2026-09-30T08:00:00', '2026-10-01T13:30:00'],
    ]);
    $view = $this->createView('field_when', TRUE);
    $build = $view->buildRenderable('default');
    $this->container->get('renderer')->renderRoot($build);
    $this->assertSame(5460, $build['#cache']['max-age']);
    $this->assertContains('timezone', $build['#cache']['contexts']);
  }

  /**
   * Tests the conversion of 1.x "Custom Global" handlers.
   *
   * @see views_date_past_upcoming_post_update_convert_legacy_handlers()
   */
  public function testLegacyConversion(): void {
    $legacy = [
      'datetime_field_machinename' => 'field_when',
      'use_end_date' => FALSE,
    ];
    $view = View::create([
      'id' => 'legacy',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'fields' => [
              'date_past_upcoming' => [
                'id' => 'date_past_upcoming',
                'table' => 'views',
                'field' => 'date_past_upcoming',
                'plugin_id' => 'date_past_upcoming',
                'label_past' => 'Over',
              ] + $legacy,
            ],
            'sorts' => [
              'date_past_upcoming_sort' => [
                'id' => 'date_past_upcoming_sort',
                'table' => 'views',
                'field' => 'date_past_upcoming_sort',
                'plugin_id' => 'date_past_upcoming_sort',
              ] + $legacy,
            ],
          ],
        ],
      ],
    ]);
    // The 1.x options no longer have a schema, so write the view directly to
    // the config storage, like existing 1.x configuration.
    $this->container->get('config.storage')->write('views.view.legacy', $view->toArray());

    \Drupal::moduleHandler()->loadInclude('views_date_past_upcoming', 'php', 'views_date_past_upcoming.post_update');
    views_date_past_upcoming_post_update_convert_legacy_handlers();

    $options = View::load('legacy')->getDisplay('default')['display_options'];
    $this->assertSame([
      'id' => 'date_past_upcoming',
      'table' => 'node__field_when',
      'field' => 'field_when_past_upcoming',
      'plugin_id' => 'past_upcoming',
      'label_past' => 'Over',
    ], $options['fields']['date_past_upcoming']);
    $this->assertSame([
      'id' => 'date_past_upcoming_sort',
      'table' => 'node__field_when',
      'field' => 'field_when_past_upcoming',
      'plugin_id' => 'past_upcoming',
    ], $options['sorts']['date_past_upcoming_sort']);
  }

  /**
   * Creates a date field on the event content type.
   */
  protected function createDateField(string $field_name, string $type, string $datetime_type, int $cardinality = 1): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => ['datetime_type' => $datetime_type],
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'event',
    ])->save();
  }

  /**
   * Creates events, keyed by title.
   *
   * @param string $field_name
   *   The date field to fill.
   * @param array $events
   *   Per title: NULL for no date, a string for a datetime field, a list of
   *   strings for a multi-value datetime field, or a start and end value for a
   *   date range field.
   */
  protected function createEvents(string $field_name, array $events): void {
    $is_range = FieldStorageConfig::loadByName('node', $field_name)->getType() === 'daterange';
    foreach ($events as $title => $value) {
      if ($value === NULL) {
        $items = [];
      }
      elseif ($is_range) {
        $items = [['value' => $value[0], 'end_value' => $value[1]]];
      }
      else {
        $items = array_map(fn ($v) => ['value' => $v], (array) $value);
      }
      Node::create(['type' => 'event', 'title' => $title, $field_name => $items])->save();
    }
  }

  /**
   * Creates and returns a view of events.
   */
  protected function createView(string $field_name, bool $sort, array $field_options = []): ViewExecutable {
    $handler = [
      'id' => $field_name . '_past_upcoming',
      'table' => 'node__' . $field_name,
      'field' => $field_name . '_past_upcoming',
      'plugin_id' => 'past_upcoming',
    ];
    $title_sort = [
      'id' => 'title',
      'table' => 'node_field_data',
      'field' => 'title',
      'plugin_id' => 'standard',
      'order' => 'ASC',
    ];

    if ($existing = View::load('events')) {
      $existing->delete();
    }
    View::create([
      'id' => 'events',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'fields' => [$handler['id'] => $handler + $field_options],
            'sorts' => $sort ? [$handler['id'] => $handler, 'title' => $title_sort] : ['title' => $title_sort],
            'pager' => ['type' => 'none', 'options' => ['offset' => 0]],
          ],
        ],
      ],
    ])->save();

    $view = Views::getView('events');
    $view->setDisplay('default');
    return $view;
  }

  /**
   * Returns the rendered labels, keyed by title.
   */
  protected function renderLabels(string $field_name, array $field_options = []): array {
    $view = $this->createView($field_name, FALSE, $field_options);
    $view->execute();
    $handler = $view->field[$field_name . '_past_upcoming'];

    $labels = [];
    foreach ($view->result as $row) {
      $labels[$row->_entity->label()] = (string) $this->container->get('renderer')->executeInRenderContext(
        new RenderContext(),
        fn () => $handler->advancedRender($row),
      );
    }
    ksort($labels);
    return $labels;
  }

  /**
   * Returns the titles of the events, in the order of the past/upcoming sort.
   */
  protected function getSortedTitles(string $field_name): array {
    $view = $this->createView($field_name, TRUE);
    $view->execute();
    return array_map(fn ($row) => $row->_entity->label(), $view->result);
  }

}
