<?php

namespace Drupal\bee_hotel;

use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Utilities for BeeHotel.
 */
class BeeHotel implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  private $beehotelunit;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new ProductAttributeForm object.
   *
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $attribute_field_manager
   *   The attribute field manager.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotel Unit Utility.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    ProductAttributeFieldManagerInterface $attribute_field_manager,
    BeeHotelUnit $bee_hotel_unit,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger) {
    $this->attributeFieldManager = $attribute_field_manager;
    $this->beehotelunit = $bee_hotel_unit;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_product.attribute_field_manager'),
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Node SETUP mode.
   */
  public function checkBeeHotelSetupNode(EntityInterface $node) {

    $this->checkGuestAttribute();

    // Accept reservation.
    $this->beehotelunit->checkUnitAcceptsReservations($node);

    // Correct variations.
    $this->beehotelunit->checkProductVariations($node);

    // Weekly price table.
    $this->beehotelunit->checkBasePriceTable($node);
  }

  /**
   * Guest attribute check.
   */
  public function checkGuestAttribute() {
    // A. Guests attributes required.
    if (!ProductAttribute::load('guests')) {
      $tmp = t("BEE Hotel requires a commerce  attribute named 'guests', not found in current configuration.") . " ";
      $tmp .= t("<a href='@path'>Fix this</a> now", ['@path' => '/admin/commerce/product-attributes']);
      $this->messenger->addError(Markup::create($tmp));
    }

    // B. BEE flag as variation type.
    $attribute_field_manager = \Drupal::service('commerce_product.attribute_field_manager');
    $map = $attribute_field_manager->getFieldMap();
    if (!isset($map['bee'])) {
      $link = Link::fromTextAndUrl('Set BEE as "Product variation types"', Url::fromUserInput('/admin/commerce/product-attributes/manage/guests'));
      $this->messenger->addWarning($link);

    }
  }

  /**
   * Libraries check.
   */
  public function checkLibraries() {
    $data = [];
    // A. Litepicker library.
    // @todo replace litepicker with https://easepick.com.
    $data['litepicker'] = '/libraries/litepicker/litepicker.js';
    if (!file_exists(DRUPAL_ROOT . $data['litepicker'])) {
      $link = Link::fromTextAndUrl('Litepicker library is missing"', Url::fromUri('https://www.drupal.org/node/3126948'));
      $this->messenger->addWarning($link);
    }
  }

  /**
   * Check current page is admin.
   */
  public static function isAdmin() {
    if (\Drupal::service('router.admin_context')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get currency code.
   *
   * In the Bee Hotel enviroment currency code
   * is related to the current unit.
   * More scenario may arise.
   *
   * @param array $data
   *   The data array.
   * @param array $options
   *   The options array to query the currency code.
   *
   * @return string
   *   Current code as string
   */
  public function getCurrencyCode(array $data, array $options) {
    $data['stores'] = $data['product']->getStores();
    $data['store'] = reset($data['stores']);
    $data['currency_code'] = $data['fallback_currency_code'] = "USD";
    return $data['store']->get('default_currency')->getValue()[0]['target_id'];

    // See https://www.drupal.org/project/bee_hotel/issues/3446298
    // Check if $data['store'] is an object before calling methods on it.
    // Code below requires a better test.
    // if (is_object($data['store']) &&
    // $data['store']->hasField('default_currency')) {
    // Get the default_currency field value if available,
    // otherwise set a fallback value.
    // $data['currency_code'] = $data['store']
    // ->get('default_currency')
    // ->isEmpty() ?
    // $data['fallback_currency_code'] :
    // $data['store']->get('default_currency')->getValue()[0]['target_id'];
    // }
    // return $data['currency_code'];.
  }

}
