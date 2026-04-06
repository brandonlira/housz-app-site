<?php

namespace Drupal\beehotel_utils;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Commerce related utils for BeeHotel.
 *
 * Implements ContainerInjectionInterface.
 */
class BeeHotelCommerce {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currency storage.
   *
   * @var \Drupal\commerce_currency\CurrencyStorageInterface
   */
  protected $currencyStorage;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new BeeHotelPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_manager,
      MessengerInterface $messenger
                              ) {
    $this->entityTypeManager = $entity_manager;
    $this->currencyStorage = $entity_manager->getStorage('commerce_currency');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Get currency from current store.
   */
  public function currentStoreCurrency() {

    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $this->entityTypeManager->getStorage('commerce_store');
    $store = $store_storage->loadDefault();

    if (!isset($store)) {
      $tmp = $this->t("No Store fund. BEE Hotel require a store at least. For a complete BEE Hotel setup, install the beehotel_samplehotel module.");
      $this->messenger()->addError($tmp);
      (new RedirectResponse('/admin/commerce/config/stores'))->send();
    }

    $currency_code = $store->get('default_currency')->getValue()[0]['target_id'];
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface $currency */
    $currency = $this->currencyStorage->load($currency_code);
    return $currency;
  }


  // public function getGuestInfoFromReservatation($order) {
  //   // Get billing profile
  //   $data = [];
  //   $data['order'] = $order;
  //   $data['family_name'] = $order->getBillingProfile()->get('address')->family_name;
  //   $data['given_name'] = $order->getBillingProfile()->get('address')->given_name;
  //   $data['field_telephone'] = $order->getBillingProfile()->field_telephone->value;
  //   return $data;
  // }

  /**
   * Get guest information from a Commerce order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return array
   *   An associative array with keys: order, family_name, given_name,
   *   field_telephone. Returns empty strings for missing data.
   */
  public function getGuestInfoFromReservatation($order) {
    $data = [
        'order' => $order,
        'family_name' => '',
        'given_name' => '',
        'field_telephone' => '',
    ];

    $billing_profile = $order->getBillingProfile();
    if ($billing_profile && $billing_profile->hasField('address')) {
        $address = $billing_profile->get('address')->first();
        if ($address) {
            $data['family_name'] = $address->family_name ?? '';
            $data['given_name'] = $address->given_name ?? '';
        }
    }

    if ($billing_profile && $billing_profile->hasField('field_telephone')) {
        $data['field_telephone'] = $billing_profile->field_telephone->value ?? '';
    }

    return $data;
  }


}
