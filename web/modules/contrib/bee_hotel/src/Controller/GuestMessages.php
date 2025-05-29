<?php

namespace Drupal\bee_hotel\Controller;

use Drupal\bee_hotel\BeeHotelGuestMessageTokens;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for BeeHotel module.
 */
class GuestMessages extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The Guest Message tokens object.
   *
   * @var \Drupal\bee_hotel\GuestMessageTokens
   */
  protected $guestMessageTokens;

  /**
   * Constructs a new GuestMessages object.
   *
   * @param \Drupal\bee_hotel\BeeHotelGuestMessageTokens $guest_message_tokens
   *   The tokens object.
   */
  public function __construct(BeeHotelGuestMessageTokens $guest_message_tokens) {
    $this->guestMessageTokens = $guest_message_tokens;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel.guest_message_tokens'),
    );
  }

  /**
   * Produces search result.
   */
  public function result($commerce_order, $type) {

    $data = [];
    $tmp = [];

    $data['commerce_order'] = $commerce_order;

    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'guest_message')
      ->pager(100);
    $nids = $query->execute();

    foreach ($nids as $nid) {
      $data['node'] = Node::load($nid);
      $data['attachments'] = $this->getAttachments($data['node']);
      $data['links'] = $this->getLinks($data['node']);

      $tmp['message'] = $this->messageFormat($data['node']->get('field_message')->value,
        $options = [
          'commerce_order' => $data['commerce_order'],
          'node_title' => $data['node']->get("title")->value,
          'node_nid' => $data['node']->Id(),
          'attachments' => $data['attachments']['string'],
          'links' => $data['links'],
        ],
      );
      $data['items'][] = $tmp['message'];
    }

    $data['prefix'] = "<div>";
    $data['prefix'] .= $this->t("Pre-filled message templates for guest communication (Email, WhatsApp, etc.)");
    $data['prefix'] .= "</div>";

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $data['items'],
      '#attributes' => ['id' => 'guest-messages'],
      '#wrapper_attributes' => ['class' => 'container'],
      '#prefix' => $data['prefix'],
      '#suffix' => "<a href='/node/add/guest_message'>" . $this->t("Add a message") . "</a>",
    ];

  }

  /**
   * Apply available tokens.
   */
  private function applyTokens($value, $commerce_order) {
    $data = [];
    $data['setting']['token_prefix'] = "[";
    $data['setting']['token_suffix'] = "]";

    $data['value'] = $value;
    $data['commerce_order'] = $commerce_order;

    $data['tokens'] = $this->guestMessageTokens->get($commerce_order);

    // Replace string with token.
    foreach ($data['tokens'] as $k => $v) {
      if (isset($k) && isset($v)) {
        if (isset($v['value'])) {
          $data['value'] = str_replace(
            $data['setting']['token_prefix'] . $k . $data['setting']['token_suffix'],
            "<span class='token'>" . $v['value'] . "</span>",
            $data['value']
          );
        }
      }
    }
    return $data['value'];
  }

  /**
   * Format message output.
   */
  private function messageFormat($value, $options) {

    $data = [];

    $data['value'] = $value;
    $data['options'] = $options;

    // A). replace tokens.
    $data['value'] = $this->applyTokens($data['value'], $data['options']['commerce_order']);

    // B). add line break BR.
    $data['value'] = str_replace(["\r\n'", "\n\r", "\n", "\r"], "<br/>", $data['value']);

    // C). Add title + edit link.
    $url = Url::fromRoute('entity.node.edit_form', ['node' => $data['options']['node_nid']]);

    $link_options = [
      'attributes' => [
        'class' => [
          'message-edit',
        ],
      ],
    ];
    $url->setOptions($link_options);

    $link = Link::fromTextAndUrl('edit', $url);

    $data['build'] = ['link' => $link->toRenderable()];
    $data['edit_link'] = \Drupal::service('renderer')->render($data['build']);

    $data['copy_to_clipboard'] = "<a href='#' data-id='" . $data['options']['node_nid'];
    $data['copy_to_clipboard'] .= "' class='message-copier' ";
    $data['copy_to_clipboard'] .= "id='message-copier-" . $data['options']['node_nid'] . "'>[copy]</a>";

    $data['tmp'] = "<h4>" . $data['options']['node_title'] . "<span style='font-size:0.6em'>";
    $data['tmp'] .= "[" . $data['edit_link'] . "]" . $data['copy_to_clipboard'] . "</span></h4>";
    $data['tmp'] .= "<div class='message' id='message-" . $data['options']['node_nid'] . "'>";
    $data['tmp'] .= $data['value'] . "</div>";
    $data['value'] = $data['tmp'];

    $data['footer'] = "<div>";
    if (isset($data['options']['attachments'])) {
      $data['footer'] .= "<div class='attachments'> Attachments: " .
        $data['options']['attachments'] .
      "</div>";
    }
    if (isset($data['options']['links'])) {
      $data['footer'] .= "<div class='links'> Links: " . $data['options']['links'] . "</div>";
    }
    $data['footer'] .= "</div>";

    $data['value'] = Markup::create($data['value'] . $data['footer']);

    return $data['value'];

  }

  /**
   * Get atatchments for a given guest_message.
   *
   * @todo themed outout.
   */
  private function getAttachments($node) {
    $data = [];
    $data['referenced_entities'] = $node->get('field_attachments')->referencedEntities();
    $data['attachments']['string'] = "";
    foreach ($data['referenced_entities'] as $entity) {
      $tmp = $entity->get("uri")->url;
      $tmp = explode("/", $tmp);
      $end = end($tmp);
      $data['attachments']['array'][] = [
        'url' => $entity->get("uri")->url,
        'label' => $end,
      ];
      $data['attachments']['string'] .= "<a href='" . $entity->get("uri")->url . "'>" . $end . "</a> | ";
    }
    return $data['attachments'];
  }

  /**
   * Get links for a given guest_message.
   */
  private function getLinks($node) {

    $data = [];
    $data['referenced_entities'] = $node->get('field_links');
    $data['links']['items'] = [];
    foreach ($node->field_links as $item) {
      $data['links']['items'][] = "<a href='" . $item->uri . "'>" . $item->uri . "</a>";
    }
    $data['links']['string'] = implode("|", $data['links']['items']);
    return $data['links']['string'];

  }

}
