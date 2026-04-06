<?php

namespace Drupal\bee_hotel\Controller;

use Drupal\bee_hotel\BeeHotelGuestMessageTokens;
use Drupal\bee_hotel\Logger;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides route responses for BeeHotel guest messages.
 */
class GuestMessages extends ControllerBase {

  /**
   * The Guest Message tokens service.
   *
   * @var \Drupal\bee_hotel\BeeHotelGuestMessageTokens
   */
  protected $guestMessageTokens;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The BeeHotel commerce utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;


  /**
   * The BeeHotel Logger.
   *
   * @var \Drupal\bee_hotel\Logger
   */
  protected $logger;

  /**
   * Constructs a GuestMessages object.
   */
  public function __construct(
    BeeHotelGuestMessageTokens $guest_message_tokens,
    LanguageManagerInterface $language_manager,
    BeeHotelCommerce $beehotel_commerce,
    Logger $logger
  ) {
    $this->guestMessageTokens = $guest_message_tokens;
    $this->languageManager = $language_manager;
    $this->beehotelCommerce = $beehotel_commerce;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel.guest_message_tokens'),
      $container->get('language_manager'),
      $container->get('beehotel_utils.beehotelcommerce'),
      $container->get('bee_hotel.logger')
    );
  }

  /**
   * Displays the list of messages filtered by current language.
   */
  public function result($commerce_order, $type) {
    $current_lang_id = $this->languageManager->getCurrentLanguage()->getId();
    $guest_info = $this->beehotelCommerce->getGuestInfoFromReservatation($commerce_order);
    $items = [];

    // Query for guest_message nodes.
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', 'guest_message')
      ->sort('changed', 'DESC');

    $nids = $query->execute();
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      /** @var \Drupal\node\NodeInterface $node */

      // FILTER: Only show nodes that have a translation in the current language.
      if (!$node->hasTranslation($current_lang_id)) {
        continue;
      }

      $translated_node = $node->getTranslation($current_lang_id);

      $attachments = $this->getAttachments($translated_node);
      $links = $this->getLinks($translated_node);

      $items[] = $this->messageFormat(
        $translated_node->get('field_message')->value,
        [
          'commerce_order' => $commerce_order,
          'node_title' => $translated_node->label(),
          'node_nid' => $translated_node->id(),
          'attachments' => $attachments['string'] ?? '',
          'links' => $links,
          'guest' => $guest_info,
        ]
      );
    }

    $language_switcher = $this->customLanguageSwitcher();
    $prefix = \Drupal::service('renderer')->renderInIsolation($language_switcher);
    $prefix .= "<div id='tokens-list'></div>";

    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
      '#attributes' => ['id' => 'guest-messages'],
      '#wrapper_attributes' => ['class' => 'container'],
      '#prefix' => Markup::create($prefix),
      '#suffix' => Markup::create("<div class='actions-footer'><a href='" . Url::fromRoute('node.add', ['node_type' => 'guest_message'])->toString() . "' class='button'>" . $this->t("Add a message") . "</a></div>"),
    ];
  }

  /**
   * Apply tokens to the message string.
   */
  private function applyTokens($value, $commerce_order) {
    if (empty($value)) {
      return NULL;
    }

    $tokens = $this->guestMessageTokens->get($commerce_order);
    foreach ($tokens as $k => $v) {
      if (isset($v['value'])) {
        $value = str_replace("[" . $k . "]", "<span class='token'>" . $v['value'] . "</span>", $value);
      }
    }
    return $value;
  }

  /**
   * Formats the message output with action links.
   */

  private function messageFormat($value, array $options) {
    if (empty($value)) {
      return NULL;
    }

    $value = $this->applyTokens($value, $options['commerce_order']);
    $value = nl2br($value);

    // Edit Link.
    $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $options['node_nid']])
    ->setOptions(['attributes' => ['class' => ['message-edit']]]);
    $edit_link = Link::fromTextAndUrl($this->t('edit'), $edit_url)->toString();

    // Mail Preview Link.
    $mail_url = Url::fromRoute('beehotel.guest_messages.mail.preview', [
      'node' => $options['node_nid'],
      'commerce_order' => $options['commerce_order']->id(),
    ])->setOptions(['attributes' => ['class' => ['mail-preview']]]);
    $mail_link = Link::fromTextAndUrl($this->t('mail: @email', ['@email' => $options['commerce_order']->getEmail()]), $mail_url)->toString();

    // Copy Button.
    $copy_link = "<a href='#' data-id='{$options['node_nid']}' class='message-copier'>{$this->t('copy')}</a>";

    // WhatsApp Link.
    $whatsapp_html = '';
    if (!empty($options['guest']['field_telephone'])) {
      $whatsapp_render = $this->generateWhatsAppLink($options['guest']['field_telephone'], $value);
      $whatsapp_html = \Drupal::service('renderer')->renderInIsolation($whatsapp_render);
    }

    // Build the header with actions.
    $output = "<h4>" . $options['node_title'] . " <span class='actions-wrapper'>";
    $output .= "<ul class='actions'>";
    $output .= "<li>$mail_link</li>";
    $output .= "<li>$edit_link</li>";
    $output .= "<li>$copy_link</li>";
    if ($whatsapp_html) {
      $output .= "<li>$whatsapp_html</li>";
    }
    $output .= "</ul></span></h4>";

    // Retrieve all email sends for this message and order.
    $sends = $this->getEmailSendsForMessage($options['node_nid'], $options['commerce_order']->id());

    if (!empty($sends)) {
      $unique_id = 'email-sends-' . $options['node_nid'] . '-' . $options['commerce_order']->id();
      $output .= "<div class='email-sends-wrapper'>";
      $output .= "<div class='email-sends-header' data-toggle='#" . $unique_id . "'>";
      $output .= "<span class='toggle-icon'>▶</span> ";
      $output .= "<strong>" . $this->t('Sent emails') . "</strong> ";
      $output .= "<span class='email-count'>(" . count($sends) . ")</span>";
      $output .= "</div>";
      $output .= "<div id='" . $unique_id . "' class='email-sends-content collapsed'>";
      $output .= "<ul>";
      foreach ($sends as $send) {
        $formatted_date = \Drupal::service('date.formatter')->format($send->created, 'long');
        $recipient = $send->details['recipient'] ?? $this->t('unknown');
        $output .= "<li>" . $this->t('@date to @recipient', [
          '@date' => $formatted_date,
          '@recipient' => $recipient,
        ]) . "</li>";
      }
      $output .= "</ul>";
      $output .= "</div>";
      $output .= "</div>";
    }

    // Message content.
    $output .= "<div class='message' id='message-{$options['node_nid']}'>$value</div>";

    // Attachments and links footer.
    if (!empty($options['attachments']) || !empty($options['links'])) {
      $output .= "<div class='message-footer'>";
      if (!empty($options['attachments'])) {
        $output .= "<div class='attachments'><strong>" . $this->t('Attachments:') . "</strong> " . $options['attachments'] . "</div>";
      }
      if (!empty($options['links'])) {
        $output .= "<div class='links'><strong>" . $this->t('Links:') . "</strong> " . $options['links'] . "</div>";
      }
      $output .= "</div>";
    }

    return Markup::create($output);
  }




          // private function messageFormat($value, array $options) {
          //   if (empty($value)) {
          //     return NULL;
          //   }
          //
          //   $value = $this->applyTokens($value, $options['commerce_order']);
          //   $value = nl2br($value);
          //
          //   // Edit Link.
          //   $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $options['node_nid']])
          //   ->setOptions(['attributes' => ['class' => ['message-edit']]]);
          //   $edit_link = Link::fromTextAndUrl($this->t('edit'), $edit_url)->toString();
          //
          //   // Mail Preview Link.
          //   $mail_url = Url::fromRoute('beehotel.guest_messages.mail.preview', [
          //     'node' => $options['node_nid'],
          //     'commerce_order' => $options['commerce_order']->id(),
          //   ])->setOptions(['attributes' => ['class' => ['mail-preview']]]);
          //   $mail_link = Link::fromTextAndUrl($this->t('mail: @email', ['@email' => $options['commerce_order']->getEmail()]), $mail_url)->toString();
          //
          //   // Copy Button.
          //   $copy_link = "<a href='#' data-id='{$options['node_nid']}' class='message-copier'>{$this->t('copy')}</a>";
          //
          //   // WhatsApp Link.
          //   $whatsapp_html = '';
          //   if (!empty($options['guest']['field_telephone'])) {
          //     $whatsapp_render = $this->generateWhatsAppLink($options['guest']['field_telephone'], $value);
          //     $whatsapp_html = \Drupal::service('renderer')->renderInIsolation($whatsapp_render);
          //   }
          //
          //   // Build the header with actions.
          //   $output = "<h4>" . $options['node_title'] . " <span class='actions-wrapper'>";
          //   $output .= "<ul class='actions'>";
          //   $output .= "<li>$mail_link</li>";
          //   $output .= "<li>$edit_link</li>";
          //   $output .= "<li>$copy_link</li>";
          //   if ($whatsapp_html) {
          //     $output .= "<li>$whatsapp_html</li>";
          //   }
          //   $output .= "</ul></span></h4>";
          //
          //   // Retrieve all email sends for this message and order.
          //   $sends = $this->getEmailSendsForMessage($options['node_nid'], $options['commerce_order']->id());
          //
          //   if (!empty($sends)) {
          //     $output .= "<div class='email-sends'>";
          //     $output .= "<strong>" . $this->t('Sent emails:') . "</strong>";
          //     $output .= "<ul>";
          //     foreach ($sends as $send) {
          //       $formatted_date = \Drupal::service('date.formatter')->format($send->created, 'long');
          //       $recipient = $send->details['recipient'] ?? $this->t('unknown');
          //       $output .= "<li>" . $this->t('@date to @recipient', [
          //         '@date' => $formatted_date,
          //         '@recipient' => $recipient,
          //       ]) . "</li>";
          //     }
          //     $output .= "</ul>";
          //     $output .= "</div>";
          //   }
          //
          //   // Message content.
          //   $output .= "<div class='message' id='message-{$options['node_nid']}'>$value</div>";
          //
          //   // Attachments and links footer.
          //   if (!empty($options['attachments']) || !empty($options['links'])) {
          //     $output .= "<div class='message-footer'>";
          //     if (!empty($options['attachments'])) {
          //       $output .= "<div class='attachments'><strong>" . $this->t('Attachments:') . "</strong> " . $options['attachments'] . "</div>";
          //     }
          //     if (!empty($options['links'])) {
          //       $output .= "<div class='links'><strong>" . $this->t('Links:') . "</strong> " . $options['links'] . "</div>";
          //     }
          //     $output .= "</div>";
          //   }
          //
          //   return Markup::create($output);
          // }



                      // private function messageFormat($value, array $options) {
                      //   if (empty($value)) {
                      //     return NULL;
                      //   }
                      //
                      //   $value = $this->applyTokens($value, $options['commerce_order']);
                      //   $value = nl2br($value);
                      //
                      //   // Edit Link.
                      //   $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $options['node_nid']])
                      //     ->setOptions(['attributes' => ['class' => ['message-edit']]]);
                      //   $edit_link = Link::fromTextAndUrl($this->t('edit'), $edit_url)->toString();
                      //
                      //   // Mail Preview Link.
                      //   $mail_url = Url::fromRoute('beehotel.guest_messages.mail.preview', [
                      //     'node' => $options['node_nid'],
                      //     'commerce_order' => $options['commerce_order']->id(),
                      //   ])->setOptions(['attributes' => ['class' => ['mail-preview']]]);
                      //   $mail_link = Link::fromTextAndUrl($this->t('mail: @email', ['@email' => $options['commerce_order']->getEmail()]), $mail_url)->toString();
                      //
                      //   // Copy Button.
                      //   $copy_link = "<a href='#' data-id='{$options['node_nid']}' class='message-copier'>{$this->t('copy')}</a>";
                      //
                      //   // WhatsApp Link.
                      //   $whatsapp_html = '';
                      //   if (!empty($options['guest']['field_telephone'])) {
                      //     $whatsapp_render = $this->generateWhatsAppLink($options['guest']['field_telephone'], $value);
                      //     $whatsapp_html = \Drupal::service('renderer')->renderInIsolation($whatsapp_render);
                      //   }
                      //
                      //   $output = "<h4>" . $options['node_title'] . " <span class='actions-wrapper'>";
                      //   $output .= "<ul class='actions'><li>$mail_link</li><li>$edit_link</li><li>$copy_link</li>";
                      //   if ($whatsapp_html) {
                      //     $output .= "<li>$whatsapp_html</li>";
                      //   }
                      //   $output .= "</ul></span></h4>";
                      //
                      //
                      //
                      //
                      //
                      //                                   $output = "<h4>" . $options['node_title'] . " <span class='actions-wrapper'>";
                      //                               $output .= "<ul class='actions'>";
                      //                               $output .= "<li>$mail_link</li>";
                      //                               $output .= "<li>$edit_link</li>";
                      //                               $output .= "<li>$copy_link</li>";
                      //                               if ($whatsapp_html) {
                      //                                 $output .= "<li>$whatsapp_html</li>";
                      //                               }
                      //                               $output .= "</ul></span></h4>";
                      //
                      //                               // Retrieve all email sends for this message and order
                      //                               $sends = $this->getEmailSendsForMessage($options['node_nid'], $options['commerce_order']->id());
                      //
                      //                               if (!empty($sends)) {
                      //                                 $output .= "<div class='email-sends'>";
                      //                                 $output .= "<strong>" . $this->t('Sent emails:') . "</strong>";
                      //                                 $output .= "<ul>";
                      //                                 foreach ($sends as $send) {
                      //                                   $formatted_date = \Drupal::service('date.formatter')->format($send->created, 'long');
                      //                                   $recipient = $send->details['recipient'] ?? $this->t('unknown');
                      //                                   $output .= "<li>" . $this->t('@date to @recipient', [
                      //                                     '@date' => $formatted_date,
                      //                                     '@recipient' => $recipient,
                      //                                   ]) . "</li>";
                      //                                 }
                      //                                 $output .= "</ul>";
                      //                                 $output .= "</div>";
                      //                               }
                      //
                      //
                      //
                      //
                      //
                      //
                      //
                      //
                      //   $output .= "<div class='message' id='message-{$options['node_nid']}'>$value</div>";
                      //
                      //   if (!empty($options['attachments']) || !empty($options['links'])) {
                      //     $output .= "<div class='message-footer'>";
                      //     if (!empty($options['attachments'])) {
                      //       $output .= "<div class='attachments'><strong>" . $this->t('Attachments:') . "</strong> " . $options['attachments'] . "</div>";
                      //     }
                      //     if (!empty($options['links'])) {
                      //       $output .= "<div class='links'><strong>" . $this->t('Links:') . "</strong> " . $options['links'] . "</div>";
                      //     }
                      //     $output .= "</div>";
                      //   }
                      //
                      //   return Markup::create($output);
                      // }

  /**
   * Generates a WhatsApp link.
   */
  private function generateWhatsAppLink($phone, $text = '') {
    $cleaned_phone = preg_replace('/[^0-9]/', '', $phone);
    $text_with_breaks = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $plain_text = trim(html_entity_decode(strip_tags($text_with_breaks), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $encoded_text = UrlHelper::encodePath($plain_text);

    return [
      '#type' => 'link',
      '#title' => Markup::create('<i class="fab fa-whatsapp"></i> WhatsApp'),
      '#url' => Url::fromUri("https://wa.me/{$cleaned_phone}?text={$encoded_text}"),
      '#attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
        'class' => ['whatsapp-link'],
      ],
    ];
  }

  /**
   * Retrieves attachments for the node.
   */
  private function getAttachments(NodeInterface $node) {
    $attachments = ['string' => '', 'array' => []];
    if ($node->hasField('field_attachments')) {
      foreach ($node->get('field_attachments')->referencedEntities() as $entity) {
        $url = $entity->getFileUri(); // Adjust if using Media or File field specifically
        $filename = $entity->getFilename();
        $attachments['string'] .= "<a href='" . \Drupal::service('file_url_generator')->generateString($url) . "' target='_blank'>$filename</a> | ";
      }
    }
    return $attachments;
  }

  /**
   * Retrieves links for the node.
   */
  private function getLinks(NodeInterface $node) {
    $links = [];
    if ($node->hasField('field_links')) {
      foreach ($node->field_links as $item) {
        $links[] = "<a href='{$item->uri}' target='_blank'>{$item->uri}</a>";
      }
    }
    return implode(' | ', $links);
  }

  /**
   * Generates a custom language switcher.
   */
  public function customLanguageSwitcher() {
    $languages = $this->languageManager->getLanguages();
    $links = [];
    foreach ($languages as $language) {
      $links[] = [
        'title' => $language->getName(),
        'url' => Url::fromRoute('<current>', [], ['language' => $language]),
        'attributes' => ['class' => ['language-link', 'language-link-' . $language->getId()]],
      ];
    }
    return [
      '#theme' => 'links__language_switcher',
      '#links' => $links,
      '#attributes' => ['class' => ['language-switcher']],
      '#set_active_class' => TRUE,
    ];
  }


  /**
    * Retrieves all email sends for a node and order.
    *
    * @param int $node_id
    *   The guest_message node ID.
    * @param int $order_id
    *   The commerce order ID.
    *
    * @return array
    *   Array of log objects, each containing details, created timestamp, etc.
    */
    private function getEmailSendsForMessage($node_id, $order_id) {
      // Use the generic logger to fetch all email_sent logs for this node/order.
      // We could also add a limit if needed, but showing all is fine.
      return $this->logger->getLatest('node', $node_id, 'email_sent', 6); // 0 = no limit
    }


}
