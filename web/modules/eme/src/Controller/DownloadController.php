<?php

namespace Drupal\eme\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\eme\Eme;
use Drupal\system\FileDownloadController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for downloading the generated module.
 */
class DownloadController implements ContainerInjectionInterface {

  /**
   * The file download controller.
   *
   * @var \Drupal\system\FileDownloadController
   */
  protected $fileDownloadController;

  /**
   * Constructs a DownloadController object.
   *
   * @param \Drupal\system\FileDownloadController $file_download_controller
   *   The file download controller.
   */
  public function __construct(FileDownloadController $file_download_controller) {
    $this->fileDownloadController = $file_download_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      new FileDownloadController($container->get('stream_wrapper_manager'))
    );
  }

  /**
   * Downloads a tarball of generated content migration module.
   */
  public function download() {
    $request = new Request([
      'file' => Eme::ARCHIVE_NAME,
      'destination' => 'http://st.local',
    ]);
    return $this->fileDownloadController->download($request, 'temporary');
  }

}
