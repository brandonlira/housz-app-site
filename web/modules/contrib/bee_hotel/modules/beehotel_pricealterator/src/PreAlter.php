<?php

namespace Drupal\beehotel_pricealterator;

use Drupal\beehotel_utils\Dates;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Class preparing Alteration.
 *
 * @package Drupal\beehotel_pricealterator\Services
 *   this is part of the beehotel package.
 */
class PreAlter {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The dates Utilities.
   *
   * @var \Drupal\beehotel_utils\Dates
   */
  protected $datesUtil;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * A construct method for the PreAlter class.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The user storage.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\beehotel_utils\Dates $bee_hotel_dates
   *   The BeeHotel Dates Utility.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
     AccountInterface $currentUser,
     ModuleHandlerInterface $module_handler,
     ConfigFactory $config_factory,
     Dates $bee_hotel_dates,
     MessengerInterface $messenger) {
    $this->currentUser = $currentUser;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->datesUtil = $bee_hotel_dates;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('beehotel_utils.dates'),
      $container->get('messenger'),
    );
  }

  /**
   * Price base table.
   *
   * @return array
   *   get the price base table.
   */
  public function baseTable($data) {

    // $days = $this->beehotelPricealteratorUtil->days();
    $days = $this->datesUtil->days();

    $basetable = [];

    $config = $this->configFactory->getEditable('beehotel_pricealterator.settings');

    foreach ($days as $d => $label) {

      // High.
      $tmp = $config->get($data['nid'] . "_" . $d . "_high");

      if (isset($tmp)) {
        $basetable[$data['nid']]['high'][$d] = $tmp;
      }
      else {
        // Data is missing.  Redirect to base table.
        $tmp = $this->t("Incorrect values in price table.");
        $tmp .= $this->t("Set correct base prices per day/season");
        $this->messenger->addWarning($tmp);
        (new RedirectResponse('/node/' . ($data['nid']) . '/basepricetable'))->send();
        exit();
      }

      // Low.
      $tmp = $config->get($data['nid'] . "_" . $d . "_low");

      if (isset($tmp)) {
        $basetable[$data['nid']]['low'][$d] = $tmp;
      }

      // Peack.
      $tmp = $config->get($data['nid'] . "_" . $d . "_peak");

      if (isset($tmp)) {
        $basetable[$data['nid']]['peak'][$d] = $tmp;
      }
    }
    return $basetable;
  }

  /**
   * Get the season of the requested night.
   *
   * @return array
   *   get the season of the requested night.
   */
  public function season($data) {

    // We should get season from the GetSeason class.
    $season = $this->moduleHandler->invokeAll('beehotel_pricealterator_season', [$data]);
    if (!isset($season)) {
      return "\$season is required. Is at least beehotel_pricealterators_beehotel_pricealterator_season running?";
    }

    return reset($season);
  }

}
