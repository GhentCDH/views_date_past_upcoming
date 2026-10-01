<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\field;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views_date_past_upcoming\PastUpcomingClassifier;
use Drupal\views_date_past_upcoming\Plugin\views\PastUpcomingHandlerInterface;
use Drupal\views_date_past_upcoming\Plugin\views\PastUpcomingHandlerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Outputs a label telling whether a date is upcoming or in the past.
 *
 * Only the first value of a multi-value field is evaluated.
 *
 * @see \Drupal\views_date_past_upcoming\PastUpcomingClassifier
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("past_upcoming")
 */
class PastUpcoming extends FieldPluginBase implements PastUpcomingHandlerInterface, ContainerFactoryPluginInterface, CacheableDependencyInterface {

  use PastUpcomingHandlerTrait;

  /**
   * The status of the row being rendered, used for the status token.
   */
  protected ?string $lastStatus = NULL;

  /**
   * Constructs a PastUpcoming field handler.
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
  public function query() {
    $this->addDateFields();
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['label_upcoming'] = ['default' => 'Upcoming'];
    $options['label_past'] = ['default' => 'Past'];
    $options['label_ongoing'] = ['default' => ''];
    $options['label_none'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['past_upcoming_rule'] = [
      '#type' => 'item',
      '#markup' => $this->t('Items with an end date are past once the end date has passed. Items with only a start date stay upcoming for the whole day of their start date.'),
      '#weight' => -100,
    ];

    $form['label_upcoming'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for upcoming dates'),
      '#default_value' => $this->options['label_upcoming'],
      '#weight' => -99,
    ];

    $form['label_past'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for past dates'),
      '#default_value' => $this->options['label_past'],
      '#weight' => -98,
    ];

    $form['label_ongoing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for ongoing items'),
      '#description' => $this->t('Used for items with an end date that have started but not ended yet. Leave empty to use the label for upcoming dates.'),
      '#default_value' => $this->options['label_ongoing'],
      '#weight' => -97,
    ];

    $form['label_none'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label when there is no date'),
      '#description' => $this->t('Leave empty to output nothing, so the "No results behavior" settings of this field apply.'),
      '#default_value' => $this->options['label_none'],
      '#weight' => -96,
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * Returns the status of a row.
   *
   * @return string
   *   One of the PastUpcomingClassifier status constants. Ongoing items are
   *   reported as upcoming unless a label for ongoing items is configured.
   */
  public function getValue(ResultRow $values, $field = NULL) {
    [$start, $end] = $this->getDateValues($values);
    $status = $this->classifier->classify($start, $end, $this->getDatetimeType());
    if ($status === PastUpcomingClassifier::ONGOING && $this->options['label_ongoing'] === '') {
      $status = PastUpcomingClassifier::UPCOMING;
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $this->lastStatus = $this->getValue($values);
    [$start, $end] = $this->getDateValues($values);

    $build = [
      '#cache' => [
        'contexts' => ['timezone'],
        'max-age' => $this->classifier->getMaxAge($start, $end, $this->getDatetimeType()),
      ],
    ];
    $label = (string) ($this->options['label_' . $this->lastStatus] ?? '');
    if ($label !== '') {
      $build['#plain_text'] = $label;
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function addSelfTokens(&$tokens, $item) {
    $tokens['{{ ' . $this->options['id'] . '__status }}'] = $this->lastStatus ?? '';
  }

  /**
   * {@inheritdoc}
   */
  protected function documentSelfTokens(&$tokens) {
    $tokens['{{ ' . $this->options['id'] . '__status }}'] = $this->t('The status as a machine name: upcoming, ongoing, past or none. Useful as a CSS class.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['timezone'];
  }

  /**
   * {@inheritdoc}
   *
   * The time-dependent max-age is bubbled per row from render().
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
