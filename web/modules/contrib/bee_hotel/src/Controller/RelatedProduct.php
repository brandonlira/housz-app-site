<?php

namespace Drupal\bee_hotel\Controller;

use Drupal\bee_hotel\BeeHotel;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\commerce_product\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for BeeHotel module.
 */
class RelatedProduct extends ControllerBase {

  /**
   * The Bee Hotel utility.
   *
   * @var \Drupal\bee_hotel\BeeHotel
   */
  protected $beehotel;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

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
   * Constructs a new RelatedProduct object.
   *
   * @param \Drupal\bee_hotel\BeeHotel $bee_hotel
   *   The BeeHotel Utility.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(BeeHotel $bee_hotel, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, ConfigFactoryInterface $config_factory) {
    $this->beehotel = $bee_hotel;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bee_hotel.beehotel'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('config.factory')
    );
  }

  /**
   * Produces a related product page for a given $node.
   */
  public function product(Node $node) {
    $data = [];

    $beehotelConfig = $this->configFactory->getEditable('beehotel.settings');

    $beehotelConfig->set('unit_reservation_form_disabled', FALSE)->save();

    if ($beehotelConfig->get('beehotel.setup_mode') == 1) {
      $this->beehotel->checkBeeHotelSetupNode($node);
    }

    $data['tmp'] = $node->get("field_product")->target_id;
    $data['product'] = Product::load((int) $data['tmp']);

    /*Load Product Variations*/
    foreach ($data['product']->getVariationIds() as $variation) {
      $data['tmp'] = $this->entityTypeManager->getStorage('commerce_product_variation')->load((int) $variation);
      $data['variations']['items'][] = $data['tmp'];
      $data['variations']['html'][] = $data['tmp']->toLink()->toRenderable();
    }

    $data['tmp'] = Url::fromUri("base://product/" . $data['product']->Id() . "/variations", ['absolute' => FALSE])->toString();
    $data['variations']['link'] = $this->t('See <a href="@uri">variation</a> page.', ['@uri' => $data['tmp']]);

    $data['title'] = $data['product']->toLink()->toRenderable();

    $output = "<h2>" . $this->t("Product and variations related to this Bee Hotel unit") . "</h2>";
    $output .= "<p>" . $this->t("Product:") . " <b>" . $this->renderer->render($data['title']) . "</b></p>";
    $output .= "<p>" . $this->t("Variations:") . "</p>";

    if (isset($data['variations']['html'])) {
      $output .= "<ol>";
      foreach ($data['variations']['html'] as $html) {
        $output .= "<li>" . $this->renderer->render($html);
      }
      $output .= "</ol>";
    }

    $output .= $data['variations']['link'];
    $build = ['#markup' => $output];

    return $build;
  }

}
