<?php

namespace Drupal\bee_hotel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\File\FileSystemInterface;

/**
 * Controller for reading YAML files from a module directory.
 */
class TokensReader extends ControllerBase {

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
   * Constructs a new TokensReader object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, FileSystemInterface $file_system) {
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('file_system')
    );
  }

  /**
   * Displays the content of YAML files.
   *
   * @return array
   *   A render array.
   */
  public function content() {
    $build = [
      '#type' => 'container',
      '#markup' => '',
    ];

    // // Get the path to the 'config_yamls' directory within the module.
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

        // Format the output.
        $output[] = [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => "<code>" . $this->t('@filename', ['@filename' => $file->name]) . "</code>",
        ];

        $output[] = [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => var_export($data, TRUE),
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
    $build['#markup'] = $this->t('<h1>Parsed YAML Files</h1>');

    $build['#markup'] .= $this->t('<div>customise your tokens overriding files in <pre>%d</pre></div>', ['%d' => $yaml_directory]);
    $build['#markup'] .= $this->t('<div>In future releases you will have a separate space where to place your custom tokens.</div><hr>');
    $build['content'] = $output;

    return $build;
  }

}
