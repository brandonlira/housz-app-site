<?php

namespace Drupal\bee_hotel\Form;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\bee_hotel\BeeHotelGuestMessageTokens;
use Drupal\bee_hotel\Logger;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for sending guest messages with BCC support.
 */
class BeeHotelGuestMessagesMail extends FormBase {

  /**
   * Config settings name.
   */
  const SETTINGS = 'beehotel.settings';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The BeeHotel commerce utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The Guest Message tokens service.
   *
   * @var \Drupal\bee_hotel\BeeHotelGuestMessageTokens
   */
  protected $guestMessageTokens;

  /**
   * The current node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The current commerce order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $commerceOrder;


  /**
   * The BeeHotel Logger.
   *
   * @var \Drupal\bee_hotel\Logger
   */
  protected $logger;

  /**
   * Constructs a new BeeHotelGuestMessagesMail object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    BeeHotelCommerce $beehotel_commerce,
    MailManagerInterface $mail_manager,
    BeeHotelGuestMessageTokens $guest_message_tokens,
    Logger $logger
  ) {
    $this->configFactory = $config_factory;
    $this->beehotelCommerce = $beehotel_commerce;
    $this->mailManager = $mail_manager;
    $this->guestMessageTokens = $guest_message_tokens;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('beehotel_utils.beehotelcommerce'),
      $container->get('plugin.manager.mail'),
      $container->get('beehotel.guest_message_tokens'),
      $container->get('bee_hotel.logger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_guest_messages';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL, OrderInterface $commerce_order = NULL) {
    // Persist entities to the class properties and form state.
    $this->node = $node;
    $this->commerceOrder = $commerce_order;
    $form_state->set('node_id', $node->id());
    $form_state->set('order_id', $commerce_order->id());

    // Prepare the tokenized message.
    $default_message = $node->get('field_message')->value ?? '';
    $tokenized_message = $this->guestMessageTokens->applyTokens($default_message, $this->commerceOrder) ?? $default_message;

    $form['to'] = [
      '#type' => 'email',
      '#title' => $this->t('Recipient email'),
      '#default_value' => $commerce_order->getEmail(),
      '#required' => TRUE,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $this->t('@hotel_name', ['@hotel_name' => $node->label()]),
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Message'),
      '#default_value' => $tokenized_message,
      '#required' => TRUE,
      '#format' => 'full_html',
      '#rows' => 12,
    ];

    $form['cc_recipient'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CC Recipients'),
      '#description' => $this->t('Comma-separated email addresses for visible copies.'),
    ];

    $form['confirmation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I confirm I want to send this message'),
      '#required' => TRUE,
      '#weight' => 30,
    ];

    $form['actions'] = ['#type' => 'actions', '#weight' => 40];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send message'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $commerce_order->toUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $message_value = $form_state->getValue('message');
    $text = is_array($message_value) ? $message_value['value'] : $message_value;

    if (empty(trim(strip_tags($text)))) {
      $form_state->setErrorByName('message', $this->t('The message cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    // Initialize data container for debugging.
    $data = [];

    // Store form values in data container.
    $data['order_id'] = $form_state->get('order_id');
    $data['node_id'] = $form_state->get('node_id');
    $data['to'] = $values['to'];
    $data['subject'] = $values['subject'];
    $data['cc_recipient'] = $values['cc_recipient'] ?? '';
    $data['message'] = is_array($values['message']) ? $values['message']['value'] : $values['message'];

    // Reload entities to ensure freshness and avoid null pointer exceptions.
    $storage = \Drupal::entityTypeManager();
    $data['order'] = $storage->getStorage('commerce_order')->load($data['order_id']);
    $data['node'] = $storage->getStorage('node')->load($data['node_id']);

    // Validate required entities.
    if (!$data['order'] || !$data['node']) {
      $this->messenger()->addError($this->t('Order or message not found.'));
      $form_state->setRedirect('<front>');
      return;
    }

    // Prepare BCC: Hidden copy to the site administrator email.
    $data['bcc_recipients'] = [];
    $site_mail = $this->configFactory->get('system.site')->get('mail');
    if (!empty($site_mail)) {
      $data['bcc_recipients'][] = $site_mail;
    }

    // Prepare CC: Visible copies from the form field.
    $data['cc_recipients'] = [];
    if (!empty($data['cc_recipient'])) {
      $emails = explode(',', $data['cc_recipient']);
      $validator = \Drupal::service('email.validator');
      foreach ($emails as $email) {
        $email = trim($email);
        if ($validator->isValid($email)) {
          $data['cc_recipients'][] = $email;
        }
      }
    }

    try {
      $params = [
        'subject' => $data['subject'],
        'message' => $data['message'],
        'order' => $data['order'],
        'node' => $data['node'],
      ];

      if (!empty($data['bcc_recipients'])) {
        $params['bcc_recipient'] = implode(',', array_unique($data['bcc_recipients']));
      }

      if (!empty($data['cc_recipients'])) {
        $params['cc_recipient'] = implode(',', array_unique($data['cc_recipients']));
      }

      // Trigger the email sending.
      $result = $this->mailManager->mail(
        'bee_hotel',
        'guest_message',
        $data['to'],
        $this->currentUser()->getPreferredLangcode(),
                                         $params,
                                         NULL,
                                         TRUE
      );

      if ($result['result']) {
        $this->messenger()->addStatus($this->t('Email successfully sent to %to.', ['%to' => $data['to']]));

        // Log the activity to the Commerce Order Log.
        if (\Drupal::hasService('commerce_log.log_storage')) {
          $log_storage = \Drupal::service('commerce_log.log_storage');
          $log_text = $data['subject'] . "\n\n" . strip_tags($data['message']);
          $log_storage->generate($data['order'], 'order_other', ['message' => $log_text])
          ->setUid($this->currentUser()->id())
          ->save();
        }

        // Add logger data.
        $data['logger'] = $this->logger;
        $data['order_email'] = $data['order']->getEmail();
        $data['node_title'] = $data['node']->label();
        $data['status'] = 'sent';

        // Debug: dump the entire data container.
        // dump($data);
        // exit;
        // Log to our custom logger.
        if ($this->logger) {
          $this->logger->log(
            'node',
            $data['node']->id(),
                             'email_sent',
                             [
                             'order_id' => $data['order']->id(),
                             'recipient' => $data['order']->getEmail(),
                             'subject' => $data['node']->label(),
                             'status' => 'sent',
                             ]
          );
          $this->messenger()->addStatus($this->t('Email log saved.'));
        } else {
          $this->messenger()->addError($this->t('Logger service not available.'));
        }

        $form_state->setRedirect('entity.commerce_order.guest_messages', ['commerce_order' => $data['order']->id()]);
      } else {
        $this->messenger()->addError($this->t('Failed to send the email.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while sending the email.'));
      \Drupal::logger('bee_hotel')->error($e->getMessage());
      dump($e->getMessage()); // Debug exception.
    }
  }






}
