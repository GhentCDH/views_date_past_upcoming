<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\sort;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\views_date_past_upcoming\PastUpcomingClassifier;
use Drupal\views_date_past_upcoming\Plugin\views\PastUpcomingHandlerInterface;
use Drupal\views_date_past_upcoming\Plugin\views\PastUpcomingHandlerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sorts upcoming dates first, then past dates, then rows without a date.
 *
 * - Upcoming (and ongoing) items: by start date, soonest first.
 * - Past items: by end date (or start date when there is no end date), most
 *   recent first.
 * - Items without a date: last, in the order of the next sort criterion.
 *
 * The sort order is fixed, so it cannot be changed or exposed. Only the first
 * value of a multi-value field is evaluated.
 *
 * @see \Drupal\views_date_past_upcoming\PastUpcomingClassifier
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort("past_upcoming")
 */
class PastUpcoming extends SortPluginBase implements PastUpcomingHandlerInterface, ContainerFactoryPluginInterface {

  use PastUpcomingHandlerTrait;

  /**
   * Sort bucket of upcoming and ongoing items.
   */
  protected const BUCKET_UPCOMING = 0;

  /**
   * Sort bucket of past items.
   */
  protected const BUCKET_PAST = 1;

  /**
   * Sort bucket of items without a date.
   */
  protected const BUCKET_NONE = 2;

  /**
   * Constructs a PastUpcoming sort handler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\views_date_past_upcoming\PastUpcomingClassifier $classifier
   *   The past/upcoming classifier.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected PastUpcomingClassifier $classifier,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('views_date_past_upcoming.classifier'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // The sort order is fixed.
    unset($form['order']);

    $form['past_upcoming_rule'] = [
      '#type' => 'item',
      '#markup' => $this->t('Upcoming items first (soonest first), then past items (most recent first), then items without a date. Items with an end date are past once the end date has passed; items with only a start date stay upcoming for the whole day of their start date.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('Upcoming first, then past, then no date');
  }

  /**
   * {@inheritdoc}
   *
   * "Start of today" depends on the timezone. The time-dependent max-age is
   * added to the rendered view in views_date_past_upcoming_views_post_render().
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['timezone']);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $alias = $this->addDateFields();
    if (!$alias) {
      return;
    }

    $start = $alias . '.' . $this->definition['start_column'];
    $end = !empty($this->definition['end_column']) ? $alias . '.' . $this->definition['end_column'] : NULL;
    $cutoffs = $this->classifier->getCutoffs($this->getDatetimeType());
    $id = $this->options['id'];

    // 1. Bucket: upcoming, past, no date.
    [$bucket, $placeholders] = $this->buildBucketExpression($start, $end, $cutoffs);
    $this->query->addOrderBy(NULL, $bucket, 'ASC', $id . '_bucket', ['placeholders' => $placeholders]);

    // 2. Upcoming items by start date, soonest first.
    [$bucket, $placeholders] = $this->buildBucketExpression($start, $end, $cutoffs);
    $start_or_end = $end ? "COALESCE($start, $end)" : $start;
    $formula = "CASE WHEN ($bucket) = " . self::BUCKET_UPCOMING . " THEN $start_or_end END";
    $this->query->addOrderBy(NULL, $formula, 'ASC', $id . '_upcoming', ['placeholders' => $placeholders]);

    // 3. Past items by end date (or start date), most recent first.
    [$bucket, $placeholders] = $this->buildBucketExpression($start, $end, $cutoffs);
    $end_or_start = $end ? "COALESCE($end, $start)" : $start;
    $formula = "CASE WHEN ($bucket) = " . self::BUCKET_PAST . " THEN $end_or_start END";
    $this->query->addOrderBy(NULL, $formula, 'DESC', $id . '_past', ['placeholders' => $placeholders]);
  }

  /**
   * Builds the SQL expression assigning each row to a sort bucket.
   *
   * The stored values are ISO 8601 strings in UTC, so they are compared as
   * strings with the cut-off values. This works the same on all databases.
   *
   * @param string $start
   *   The qualified start column.
   * @param string|null $end
   *   The qualified end column, or NULL for fields without an end date.
   * @param array $cutoffs
   *   The cut-off values from PastUpcomingClassifier::getCutoffs().
   *
   * @return array
   *   The SQL expression and its placeholder values.
   */
  protected function buildBucketExpression(string $start, ?string $end, array $cutoffs): array {
    $start_placeholder = $this->placeholder();
    $placeholders = [$start_placeholder => $cutoffs['start']];
    $start_case = "WHEN $start IS NOT NULL THEN CASE WHEN $start >= $start_placeholder THEN " . self::BUCKET_UPCOMING . ' ELSE ' . self::BUCKET_PAST . ' END';

    $end_case = '';
    if ($end) {
      $end_placeholder = $this->placeholder();
      $placeholders[$end_placeholder] = $cutoffs['end'];
      $end_case = "WHEN $end IS NOT NULL THEN CASE WHEN $end >= $end_placeholder THEN " . self::BUCKET_UPCOMING . ' ELSE ' . self::BUCKET_PAST . ' END ';
    }

    return ["CASE {$end_case}{$start_case} ELSE " . self::BUCKET_NONE . ' END', $placeholders];
  }

}
