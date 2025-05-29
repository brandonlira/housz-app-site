<?php

namespace Drupal\beehotel_pricealterator\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Provides a 'Debug block for price alteration' block.
 *
 * @Block(
 * id = "price_alterator_debug_block",
 * admin_label = @Translation("price alterator debug block"),
 * category = @Translation("price_alterator_debug_block"),
 * )
 */
class PriceAlteratorDebugBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The account object.
   *
   * @var Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * A create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   * @param array $configuration
   *   A configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('session'),
    );
  }

  /**
   * The __construct method.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Session $session,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   *
   * The return value of the build() method is a renderable array. Returning an
   * empty array will result in empty block contents. The front end will not
   * display empty blocks.
   */
  public function build() {

    $data = [];
    $data['alterators_current_stack'] = $this->session->get('alterators_current_stack');
    $path = \Drupal::service('path.current')->getPath();
    $data['path']['pieces'] = explode("/", $path);

    if ($data['path']['pieces'][1] != 'cart') {
      return;
    }

    if (!isset($data['alterators_current_stack'])) {
      return;
    }

    $chain = "<ul>";
    $prev = NULL;

    $as = $data['alterators_current_stack'];

    for ($i = 0; $i < count($as); $i++) {

      if ($as[$i]['id'] == "GetSeason") {
        $chain .= "<li> <b>Season:</b> : " . $as[$i]['season'];

      }
      else {
        $final = [];
        if ($as[$i + 1] == NULL) {
          $final['description'] = "<span class='description'> <<< " . $this->t("final avg price") . "</span>";
          $final['class'] = "is-final";
        }

        $chain .= "<li class='" . $final['class'] . "'><b>" . $as[$i]['id'] . "</b>: <span>" . number_format($as[$i]['price'], 2) . "</span>";

        if (isset($prev)) {

          $class = $sign = "";
          $diff = $as[$i]['price'] - $prev;

          if ($diff < 0) {
            $sign = "-";
            $class = "subtract bold smaller";
            $value = $sign . number_format($diff, 2);
          }
          elseif ($diff > 0) {
            $class = "add bold smaller";
            $sign = "+";
            $value = $sign . number_format($diff, 2);
          }
          else {
            $diff = "0";
            $class = "grey bold smaller";
            $value = $this->t("=");
          }
          $chain .= "<span class='" . $class . "'> (" . $value . ") ";
          $chain .= $final['description'];
          $chain .= "</span>";
        }
      }
      $prev = $as[$i]['price'];
    }

    $chain .= "</ul>";

    // Edit link.
    $chain .= "<p>" . Link::createFromRoute($this->t('Edit'), 'beehotel_pricealterator.info.chain')->toString() . " settings and order of the price alteration chain.</p>";

    /*
     * Show visibity.
     * @todo: expose here block permiossions (roles, users, etc).
     */
    $chain .= "<br/><div class='warning'>" . $this->t("This block is NOT to be exposed to public. Check permission settings") . "</div>";

    $data['info'] = [
      '#theme' => 'beehotel_pricealterator_pricechain_block',
      '#title' => "",
      '#as' => $as,
      '#description' => $chain,
      '#attached' => [
        'library' => [
          'beehotel_pricealterator/pricealterators',
          'beehotel_pricealterator/main',
          'beehotel_pricealterator/chart-chain',
        ],
      ],
    ];
    return $data['info'];

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
