<?php

namespace Drupal\beehotel_vertical\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure beehotel_vertical settings.
 */
class BeeeHotelVerticalSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'beehotel_vertical.settings';

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BeeeHotelVerticalSettingsForm.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_vertical_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('beehotel_vertical.settings');

    $url = Url::fromRoute('beehotel_vertical.vertical', []);
    $linkText = 'Go to ' . $this->t('Vertical');
    $linkHTMLMarkup = Markup::create($linkText);
    $link = Link::fromTextAndUrl($linkHTMLMarkup, $url);
    $link = $link->toRenderable();

    $form['beehotel_vertical_default_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default VertiCal options'),
      '#collapsible' => FALSE,
      '#description' => $link,
    ];

    $options = [
      0 => $this->t("Start Vertical from today"),
      86400 => $this->t("One day back"),
      (86400 * 2) => $this->t("2 days back"),
      (86400 * 3) => $this->t("3 days back"),
      (86400 * 4) => $this->t("4 days back"),
      (86400 * 7) => $this->t("7 days back"),
      (86400 * 30) => $this->t("30 days back"),
    ];

    $form['beehotel_vertical_default_options']['beehotel_vertical_timejump'] = [
      '#type' => 'select',
      '#title' => $this->t('Time jump'),
      '#options' => $options,
      '#description' => $this->t('The first day for VertiCal. Useful to expose past reservations.'),
      '#default_value' => $config->get('vertical.timejump'),
    ];

    $form['beehotel_vertical_default_options']['beehotel_vertical_warning_money'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Warning: Money to collect'),
      '#description' => $this->t('Expose a warning when money is to be collected.'),
      '#default_value' => $config->get('vertical.warning.money'),
    ];

    // Ottieni tutti i content type disponibili e filtra quelli con campo BAT
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $content_type_options = [];

    // Cerca possibili nomi di campo BAT
    $bat_field_patterns = [
      'field_bat_type', 'bat_type', 'field_bat_type_reference',
      'bat_type_reference', 'field_bat_unit', 'bat_unit'
    ];

    foreach ($content_types as $content_type) {
      $content_type_id = $content_type->id();

      try {
        $fields = $this->entityFieldManager->getFieldDefinitions('node', $content_type_id);

        // Controlla se questo content type ha un campo BAT
        $has_bat_field = FALSE;
        foreach ($bat_field_patterns as $field_pattern) {
          if (isset($fields[$field_pattern])) {
            $has_bat_field = TRUE;
            break;
          }
        }

        if ($has_bat_field) {
          $content_type_options[$content_type_id] = $content_type->label() . ' (' . $content_type_id . ')';
        }
      } catch (\Exception $e) {
        // Ignora content type senza campi
        continue;
      }
    }

    // Se nessun content type ha campo BAT, mostra tutti
    if (empty($content_type_options)) {
      foreach ($content_types as $content_type) {
        $content_type_options[$content_type->id()] = $content_type->label() . ' (' . $content_type->id() . ')';
      }
      \Drupal::messenger()->addWarning($this->t('No content types with BAT fields found. Showing all content types.'));
    }

    // Determina il content type corrente
    $selected_content_type = $form_state->getValue('beehotel_vertical_content_type',
                         $config->get('vertical.content_type') ?: (key($content_type_options) ?: 'unit'));

    $form['beehotel_vertical_default_options']['beehotel_vertical_content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Property Unit Content Type'),
      '#description' => $this->t('Select the content type that represents property units (must have BAT field).'),
      '#options' => $content_type_options,
      '#default_value' => $selected_content_type,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'callback' => '::updateFieldsAjax',
        'wrapper' => 'header-fields-wrapper',
        'event' => 'change',
      ],
    ];

    $form['beehotel_vertical_default_options']['header_fields_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'header-fields-wrapper'],
    ];

    // Mostra i campi solo se è selezionato un content type valido
    if ($selected_content_type) {
      // Carica i campi del content type selezionato
      $field_options = $this->getFieldOptions($selected_content_type);

      $current_header_fields = $config->get('vertical.header_fields') ?: ['title'];

      $form['beehotel_vertical_default_options']['header_fields_wrapper']['beehotel_vertical_header_fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Header fields to display'),
        '#description' => $this->t('Select which fields to display in the column headers for each property unit.'),
        '#options' => $field_options,
        '#default_value' => $current_header_fields,
      ];

      // Genera l'elenco dei token disponibili
      $token_list = [];
      foreach (array_keys($field_options) as $field_key) {
        $token_list[] = '[node:' . $field_key . ']';
      }

      $form['beehotel_vertical_default_options']['header_fields_wrapper']['beehotel_vertical_header_format'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Header display format'),
        // '#description' => $this->t('Define how to display the header fields. Use tokens like [node:title]. Available tokens: ') . implode(', ', $token_list),
        '#description' => $this->t('Define how to display the header fields. Use tokens like [node:title] or [node:field_name].
          <br><strong>Examples:</strong>
          <ul>
          <li><code>[node:title]</code> - Show only title</li>
          <li><code>[node:title] - [node:field_slogan]</code> - Title with slogan</li>
          <li><code>[node:field_reference]: [node:title]</code> - Reference code with title</li>
          <li><code>[node:title] ([node:field_floor]° floor)</code> - Title with floor number</li>
          </ul>
          Available tokens: ') . implode(', ', $token_list),
        '#default_value' => $config->get('vertical.header_format') ?: '[node:title]',
        '#rows' => 3,
      ];
















    }
    else {
      $form['beehotel_vertical_default_options']['header_fields_wrapper']['message'] = [
        '#markup' => '<p>' . $this->t('Please select a content type first.') . '</p>',
      ];
    }

    $form['beehotel_vertical_google_calendar'] = [
      '#type' => 'details',
      '#title' => $this->t('Google Calendar'),
      '#description' => $this->t('Import a public Google Calendar'),
      '#open' => TRUE,
      '#suffix' => $this->t("Example: https://www.googleapis.com/calendar/v3/calendars/0golue773vail1a18dt3gl6ooo@group.calendar.google.com/events?key=AIzaSyDRdKewQrdsKyzBCNF3HMqw9L6fRz4aYik"),
    ];

    $form['beehotel_vertical_google_calendar']['beehotel_google_calendar_id'] = [
      '#default_value' => $config->get('vertical.google_calendar_id'),
      '#type' => 'textfield',
      '#title' => $this->t('The calendar ID to exposed inside VertiCal'),
      '#required' => FALSE,
    ];

    $form['beehotel_vertical_google_calendar']['beehotel_google_calendar_api_key'] = [
      '#default_value' => $config->get('vertical.google_calendar_api_key'),
      '#type' => 'textfield',
      '#title' => $this->t('Google Calendar key'),
      '#description' => $this->t('From google api console'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback per aggiornare i campi quando cambia il content type.
   */
  public function updateFieldsAjax(array &$form, FormStateInterface $form_state) {
    return $form['beehotel_vertical_default_options']['header_fields_wrapper'];
  }

  /**
   * Get field options for a specific content type.
   */
  protected function getFieldOptions($content_type) {
    if (!$content_type) {
      return ['title' => $this->t('Title')];
    }

    $field_options = ['title' => $this->t('Title')];

    try {
      $fields = $this->entityFieldManager->getFieldDefinitions('node', $content_type);

      foreach ($fields as $field_name => $field_definition) {
        // Escludi campi di sistema e campi complessi non adatti per intestazioni
        $excluded_fields = [
          'nid', 'uuid', 'vid', 'type', 'langcode', 'revision_timestamp',
          'revision_uid', 'revision_log', 'status', 'uid', 'created', 'changed',
          'promote', 'sticky', 'default_langcode', 'revision_default',
          'revision_translation_affected', 'path'
        ];

        $field_type = $field_definition->getType();
        $is_simple_field = in_array($field_type, [
          'string', 'text', 'text_long', 'text_with_summary',
          'integer', 'decimal', 'float', 'boolean', 'list_string',
          'entity_reference', 'datetime', 'date', 'timestamp'
        ]);

        if (!in_array($field_name, $excluded_fields) &&
            $is_simple_field &&
            !str_starts_with($field_name, 'field_metatag') &&
            !str_contains($field_name, 'computed') &&
            !preg_match('/^field_bat_/', $field_name)) { // Escludi campi BAT interni

          $label = $field_definition->getLabel();
          $field_options[$field_name] = $label . ' (' . $field_name . ')';
        }
      }
    } catch (\Exception $e) {
      // Il content type potrebbe non esistere o non avere campi
      \Drupal::messenger()->addError($this->t('Unable to load fields for content type @type', ['@type' => $content_type]));
    }

    // Ordina alfabeticamente per label
    asort($field_options);

    return $field_options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('beehotel_vertical.settings')
      ->set('vertical.timejump', $form_state->getValue('beehotel_vertical_timejump'))
      ->set('vertical.warning.money', $form_state->getValue('beehotel_vertical_warning_money'))
      ->set('vertical.content_type', $form_state->getValue('beehotel_vertical_content_type'))
      ->set('vertical.header_fields', array_filter($form_state->getValue('beehotel_vertical_header_fields', [])))
      ->set('vertical.header_format', $form_state->getValue('beehotel_vertical_header_format', '[node:title]'))
      ->set('vertical.google_calendar_id', $form_state->getValue('beehotel_google_calendar_id'))
      ->set('vertical.google_calendar_api_key', $form_state->getValue('beehotel_google_calendar_api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
