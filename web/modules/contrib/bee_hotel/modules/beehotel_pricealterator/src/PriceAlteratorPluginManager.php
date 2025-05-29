<?php

namespace Drupal\beehotel_pricealterator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\beehotel_pricealterator\Annotation\PriceAlterator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A plugin manager for pricealterator plugins.
 *
 * The PriceAlteratorPluginManager class extends the DefaultPluginManager
 * to provide a way to manage pricealterator plugins. A plugin manager
 * defines a new plugin type and how instances of any plugin of that
 * type will be discovered, instantiated and more.
 *
 * Using the DefaultPluginManager as a starting point sets up our
 * pricealterator plugin type to use annotated discovery.
 *
 * The plugin manager is also declared as a service in
 * beehotel_pricealterator.services.yml. it can be easily  accessed and
 * used anytime we need to work with pricealterator plugins.
 */
class PriceAlteratorPluginManager extends DefaultPluginManager {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The bee hotel date utility.
   *
   * @var \Drupal\beehotel_utils\Dates
   */
  private $beehoteldates;

  /**
   * The container.
   */
  protected ContainerInterface $container;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Creates the discovery object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(
      \Traversable $namespaces,
      CacheBackendInterface $cache_backend,
      ModuleHandlerInterface $module_handler,
      EntityTypeManagerInterface $entity_type_manager,
      ConfigFactoryInterface $config_factory) {

    $subdir = 'Plugin/PriceAlterator';

    // The name of the interface that plugins should adhere to. Drupal will
    // enforce this as a requirement. If a plugin does not implement this
    // interface, Drupal will throw an error.
    $plugin_interface = PriceAlteratorInterface::class;

    // The name of the annotation class that contains the plugin definition.
    $plugin_definition_annotation_name = PriceAlterator::class;

    parent::__construct(
       $subdir,
       $namespaces,
       $module_handler,
       $plugin_interface,
       $plugin_definition_annotation_name
    );

    $this->alterInfo('pricealterator_info');
    $this->setCacheBackend($cache_backend, 'pricealterator_info', ['pricealterator_info']);
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Gets avaiable alterators.
   *
   * @param array $data
   *   An array of data.
   *
   * @return array
   *   Returns an array of all alterators.
   */
  public function alterators(array $data) {

    $data['caller'] = __METHOD__;
    $alterators = [];
    $alterators = $this->getDefinitions();

    $alterators = $this->activeAlterators($alterators);
    $alterators = $this->getCurrentValues($alterators, $data);
    $alterators = $this->getUserWeight($alterators);
    $alterators = $this->enabled($alterators);
    $alterators = $this->weightValidation($alterators);
    array_multisort(array_column($alterators, 'get_user_weight'), SORT_ASC, $alterators);
    return $alterators;
  }

  /**
   * Gets active alterators.
   *
   * @return array
   *   Returns an array of alterators active by annotation status
   */
  public function activeAlterators($alterators) {
    foreach ($alterators as $n => $a) {
      if ($a['status'] == 1) {
        $active[$n] = $a;
      }
    }
    return $active;
  }

  /**
   * Get current value for available alterators.
   */
  private function getCurrentValues($alterators, $data) {

    foreach ($alterators as $a) {
      $plugin = $this->createInstance($a['id']);
      if ($plugin !== NULL && method_exists($plugin, 'currentValue')) {
        $currentValue = $plugin->currentValue($data, []);
        $alterators[$a['id']]['current_value'] = $currentValue;
      }
      else {
        $alterators[$a['id']]['current_value'] = '??';
      }
    }
    return $alterators;
  }

  /**
   * Get the price alterator weight in the chain set by User.
   */
  private function getUserWeight($alterators) {

    foreach ($alterators as $a) {
      $plugin = $this->createInstance($a['id']);
      if ($plugin !== NULL && method_exists($plugin, 'getUserWeight')) {
        $get_user_weight = $plugin->getUserWeight();
        $alterators[$a['id']]['get_user_weight'] = $get_user_weight;
      }
      else {
        // Price alterators with no user weight floats on top.
        $alterators[$a['id']]['get_user_weight'] = -999;
      }

    }
    return $alterators;
  }

  /**
   * Custom Alterators weight 1xxx (from 1000 till 1999).
   *
   * March24. Deprecated. Weight is handled by user via draggable table.
   *
   * @return array
   *   Returns an array of weight-validated alterators
   */
  public function weightValidation(&$alterators) {
    // @todo implement validation
    return $alterators;
  }

  /**
   * A price alterator may be enabled via UI.
   *
   * @return array
   *   Return an array of UI enabled alterators.
   */
  public function enabled(&$alterators) {
    foreach ($alterators as $a) {
      $plugin = $this->createInstance($a['id']);
      if ($a['type'] == 'mandatory') {
        $alterators[$a['id']]['enabled'] = 1;
      }
      elseif ($plugin !== NULL && method_exists($plugin, 'enabled')) {
        $enabled = $plugin->enabled($a);
        $alterators[$a['id']]['enabled'] = $enabled;
      }

    }
    return $alterators;
  }

}
