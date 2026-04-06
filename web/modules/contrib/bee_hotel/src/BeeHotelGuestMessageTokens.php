<?php

namespace Drupal\bee_hotel;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provides route responses for BeeHotel module.
 */
class BeeHotelGuestMessageTokens {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The Hooks container.
   *
   * @var \Drupal\bee_hotel\BeeHotelGuestMessageHooks
   */
  protected $beeHotelGuestMessageHooks;

  /**
   * Constructs a new TokensReader object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\bee_hotel\BeeHotelGuestMessageHooks $bee_hotel_guest_message_hooks
   *   The guest message hook container.
   */
  public function __construct(ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, BeeHotelGuestMessageHooks $bee_hotel_guest_message_hooks) {
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->beeHotelGuestMessageHooks = $bee_hotel_guest_message_hooks;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('file_system'),
      $container->get('beehotel.guest_message_hooks'),
    );
  }

  /**
   * Tokens list with schema.
   *
   * Get Tokens.
   *
   * @param object $commerce_order
   *   (mandatory) The commer order we're comnucating for.
   *
   *   Every token is an item with defined key => value pairs.
   *
   *   An associative array containing:
   *   - description: string describing the token.
   *   - entiy: where into value are fund.
   *   - field: entity field
   *   - hook: after basic resolution of value, hook is needed.
   *   - property: field property
   *   - value: default value.
   *
   * @return array
   *   An array of tokens with values
   */
  public function get($commerce_order) {

    $d = [];
    $d['commerce_order'] = $commerce_order;

    // Load YML tokens.
    $d['tokens'] = $this->readTokens();

    foreach ($d['tokens'] as $id => $token) {

      if (isset($token['#value']['value']) && $token['#value']['value'] != NULL) {
        $d['tokens'][$token['#value']['id']]['value'] = $token['#value']['value'];
        continue;
      }

      // D. Assign value from standard field.
      $tmp = $token['#value']['settings']['property'];
      $d['tokens'][$token['#value']['id']]['value'] = $d['commerce_order']->get($token['#value']['settings']['field'])->$tmp;

      // Z. HOOKS.
      // Hook overriding.
      if (isset($token['#value']['settings']['hook']) && $token['#value']['settings']['hook'] == TRUE) {
        $d['tokens'][$token['#value']['id']]['value'] = $this
          ->beeHotelGuestMessageHooks
          ->main($token['#value']['id'], $token, $d);
      }

      // Tokens from top level fields are ready to be formatted and delivered.
      if (!is_object($d['tokens'][$token['#value']['id']]['value']) && !isset($token['#value']['settings']['hook'])) {
        if ($token['#value']['settings']['property'] == "number") {
          $d['tokens'][$token['#value']['id']]['value'] = bee_hotel_number_format((float) $d['tokens'][$token['#value']['id']]['value']);
        }
      }

      if ($token['#value']['id'] == "balance_cash_currencies") {
      }
    }

    return $d['tokens'];

  }

  /**
   * Read Tokens.
   *
   * Read token YML file from directory and
   * return them as array.
   *
   * return array
   */
  public function readTokens() {

    $module_path = $this->moduleHandler->getModule('bee_hotel')->getPath();
    $yaml_directory = $module_path . '/config/guest_messages';

    $files = $this->fileSystem->scanDirectory($yaml_directory, '/\.yml$/');

    if (empty($files)) {
      $build['#markup'] = $this->t('No YAML files found in @path.', ['@path' => $yaml_directory]);
      return $build;
    }

    $output = [];

    foreach ($files as $file) {

      $file_path = $file->uri;
      try {
        // Read the file content.
        $yaml_content = file_get_contents($file_path);
        // Parse the YAML content.
        $data = Yaml::parse($yaml_content);

        $output[] = [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => $data,
        ];

      }
      catch (\Exception $e) {
        $output[] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Error parsing file @filename: @message', [
            '@filename' => $file->name,
            '@message' => $e->getMessage(),
          ]),
        ];
        $this->getLogger('my_yaml_reader')->error('Error parsing YAML file @filename: @message', [
          '@filename' => $file->name,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $output;

  }

  /**
   * Consume token contributed module.
   */
  private function getTokenFromTokenModule($beehotel_token, $options) {
    $d['tokens'] = \Drupal::service('token.tree_builder')->buildRenderable([
      'node',
      'user',
      'commerce_order',
    ]);
    return $token;
  }

  /**
   * Tokens list with schema.
   *
   * Every token is an item with defined key => value pairs.
   *
   *  An associative array containing:
   *  - description: string describing the token.
   *  - entiy: where into value are fund.
   *  - field: entity field
   *  - hook: after basic resolution of value, hook is needed.
   *  - property: field property
   *  - value: default value.
   */
  public function guestMessageTokensSchema() {
    // Moved as YML.
  }


  /**
   * Apply available tokens.
   *
   * @param string $value
   *   The string value to process.
   * @param object $commerce_order
   *   The commerce order object.
   *
   * @return string|null
   *   The processed string with tokens replaced.
   *
   * @IMPORTANT: CLONED FROM CONTROLLER
   *
   */
  public function applyTokens($value, $commerce_order) {
    $data = [];
    $data['commerce_order'] = $commerce_order;
    $data['value'] = $value;
    $data['setting']['token_prefix'] = "[";
    $data['setting']['token_suffix'] = "]";
    $data['tokens'] = $this->get($commerce_order);

    if (!isset($data['value'])) {
      return NULL;
    }

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
}
