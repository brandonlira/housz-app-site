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

}
