<?php

namespace Drupal\beehotel_vertical\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\bee_hotel\Event;

/**
 * Service for handling card operations.
 */
class CardService {

  use StringTranslationTrait;

  /**
   * Card state constants.
   */
  public const CARD_STATE_AVAILABLE = 1;
  public const CARD_STATE_NOT_AVAILABLE = 2;
  public const CARD_STATE_BOOKED = 3;

  /**
   * Card color constants.
   */
  public const CARD_COLOR_GREEN = 'green';
  public const CARD_COLOR_RED = 'red';
  public const CARD_COLOR_GREY = 'grey';

  /**
   * Card label constants.
   */
  public const CARD_LABEL_AVAILABLE = 'AV';
  public const CARD_LABEL_NOT_AVAILABLE = 'NO';
  public const CARD_LABEL_NOT_DEFINED = 'ND';

  /**
   * The bee hotel event service.
   *
   * @var \Drupal\bee_hotel\Event
   */
  protected $event;

  /**
   * Constructs a new CardService.
   *
   * @param \Drupal\bee_hotel\Event $event
   *   The Bee Hotel Event service.
   */
  public function __construct(Event $event) {
    $this->event = $event;
  }

  /**
   * Generate card markup for a cell.
   *
   * @param array $data
   *   The data array containing unit and day information.
   *
   * @return array
   *   Card data with ID and markup.
   */
  public function generateCard(array $data): array {
    $state = $this->getCardState($data);
    $card_id = $this->generateCardId($data);

    return [
      '#id' => $card_id,
      '#markup' => $this->buildCardMarkup($card_id, $state),
    ];
  }

  /**
   * Get the current card state from data.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Card state with front/back labels and colors.
   */
  public function getCardState(array $data): array {
    $state_id = $this->event->getNightState($data);
    $data['event']['id'] = $this->event->getNightEvent($data);
    $data['event']['length'] = '';

    if (isset($data['event']['id'])) {
      $data['event']['object'] = bat_event_load($data['event']['id'], FALSE);
      $data['event']['length'] = $this->event->getEventLength($data['event']['object'], ['output' => "timestamp"]);
    }

    if (isset($state_id)) {
      // Events with no reservation longer than one day not supported
      if ($data['event']['length'] == 86400 || $data['event']['length'] == '') {
        if ($state_id == self::CARD_STATE_AVAILABLE) {
          return [
            'front' => [
              'label' => $this->t('AV'),
              'css' => self::CARD_COLOR_GREEN,
            ],
            'back' => [
              'label' => $this->t('NO'),
              'css' => self::CARD_COLOR_RED,
            ],
          ];
        }
        else {
          return [
            'front' => [
              'label' => $this->t('NO'),
              'css' => self::CARD_COLOR_RED,
            ],
            'back' => [
              'label' => $this->t('AV'),
              'css' => self::CARD_COLOR_GREEN,
            ],
          ];
        }
      }
    }

    // Default/undefined state
    return [
      'front' => [
        'label' => $this->t('ND'),
        'css' => self::CARD_COLOR_GREY,
      ],
      'back' => [
        'label' => $this->t('AV'),
        'css' => self::CARD_COLOR_GREEN,
      ],
    ];
  }

  /**
   * Generate a unique card ID.
   *
   * @param array $data
   *   The data array.
   *
   * @return string
   *   The card ID.
   */
  public function generateCardId(array $data): string {
    return implode('-', [
      'card',
      $data['unit']['bid'],
      $data['day']['year'],
      $data['day']['month'],
      $data['day']['day'],
    ]);
  }

  /**
   * Build card HTML markup.
   *
   * @param string $card_id
   *   The card HTML ID.
   * @param array $state
   *   The card state.
   *
   * @return string
   *   The card HTML markup.
   */
  public function buildCardMarkup(string $card_id, array $state): string {
    return sprintf(
      '<div class="state state-card">
        <div class="card" id="%s">
          <div class="card__face card__face--front %s">%s</div>
          <div class="card__face card__face--back %s">%s</div>
        </div>
      </div>',
      $card_id,
      $state['front']['css'],
      $state['front']['label'],
      $state['back']['css'],
      $state['back']['label']
    );
  }

  /**
   * Handle AJAX card flip.
   *
   * @param string $card_id
   *   The card ID.
   * @param int $new_state
   *   The new state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response with commands.
   */
  public function handleAjaxFlip(string $card_id, int $new_state): AjaxResponse {
    $response = new AjaxResponse();
    $selector = '#' . $card_id;

    // Determine new colors and text based on state
    if ($new_state == self::CARD_STATE_AVAILABLE) {
      $front_color = self::CARD_COLOR_GREEN;
      $front_text = $this->t('AV');
      $back_color = self::CARD_COLOR_RED;
      $back_text = $this->t('NO');
    }
    else {
      $front_color = self::CARD_COLOR_RED;
      $front_text = $this->t('NO');
      $back_color = self::CARD_COLOR_GREEN;
      $back_text = $this->t('AV');
    }

    // Update faces with new content
    $back_html = sprintf(
      '<div class="card__face card__face--back %s">%s</div>',
      $back_color,
      $back_text
    );
    $response->addCommand(new HtmlCommand($selector . ' .card__face--back', $back_html));

    $front_html = sprintf(
      '<div class="card__face card__face--front %s">%s</div>',
      $front_color,
      $front_text
    );
    $response->addCommand(new HtmlCommand($selector . ' .card__face--front', $front_html));

    // Toggle the flip animation
    $response->addCommand(new InvokeCommand($selector, 'toggleClass', ['is-flipped']));

    return $response;
  }

  /**
   * Get state labels for front and back.
   *
   * @param int $state_id
   *   The state ID.
   * @param bool $is_back
   *   Whether to get back label.
   *
   * @return string
   *   The label.
   */
  public function getStateLabel(int $state_id, bool $is_back = FALSE): string {
    $labels = [
      self::CARD_STATE_AVAILABLE => $is_back ? $this->t('NO') : $this->t('AV'),
      self::CARD_STATE_NOT_AVAILABLE => $is_back ? $this->t('AV') : $this->t('NO'),
    ];

    return $labels[$state_id] ?? $this->t('ND');
  }

  /**
   * Get state color for front and back.
   *
   * @param int $state_id
   *   The state ID.
   * @param bool $is_back
   *   Whether to get back color.
   *
   * @return string
   *   The color class.
   */
  public function getStateColor(int $state_id, bool $is_back = FALSE): string {
    $colors = [
      self::CARD_STATE_AVAILABLE => $is_back ? self::CARD_COLOR_RED : self::CARD_COLOR_GREEN,
      self::CARD_STATE_NOT_AVAILABLE => $is_back ? self::CARD_COLOR_GREEN : self::CARD_COLOR_RED,
    ];

    return $colors[$state_id] ?? self::CARD_COLOR_GREY;
  }

  /**
   * Create card link for AJAX.
   *
   * @param array $card
   *   The card data.
   *
   * @return array
   *   Render array for the link.
   */
  public function createCardLink(array $card): array {
    if (!isset($card['#id'])) {
      return [];
    }

    return [
      '#type' => 'link',
      '#title' => ['#markup' => $card['#markup']],
      '#attached' => [
        'library' => [
          'core/drupal.ajax',
          'core/jquery',
        ],
      ],
      '#attributes' => [
        'class' => ['use-ajax', 'state-link'],
        'data-dialog-type' => 'ajax',
        'data-ajax-progress' => 'none',
      ],
      '#url' => \Drupal\Core\Url::fromRoute('beehotel_vertical.ajax_link_callback', [
        'nojs' => 'ajax',
        'card_id' => $card['#id'],
      ]),
    ];
  }
}
