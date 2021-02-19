<?php

namespace Drupal\eme\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\eme\InterfaceAwareExportBatchRunner;
use Drupal\eme\Utility\EmeUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Base for content export forms.
 */
abstract class ExportFormBase extends FormBase {

  /**
   * List of the discovered modules.
   *
   * @var string[]
   */
  protected $discoveredModules;

  /**
   * Info about discovered previous exports.
   *
   * @var array[]
   */
  protected $discoveredExports;

  /**
   * The export batch runner.
   *
   * @var \Drupal\eme\InterfaceAwareExportBatchRunner
   */
  protected $batchRunner;

  /**
   * Construct an export form instance.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\InterfaceAwareExportBatchRunner $batch_runner
   *   The export batch runner.
   */
  public function __construct(ModuleExtensionList $module_list, InterfaceAwareExportBatchRunner $batch_runner) {
    $this->discoveredModules = array_keys($module_list->reset()->getList());
    $this->discoveredExports = EmeUtils::getExports($module_list);
    $this->batchRunner = $batch_runner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module'),
      $container->get('eme.batch_runner')
    );
  }

  /**
   * Batch finish callback.
   */
  public static function finishBatch($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Content export finished.'), 'status');
      $route_name = $results['redirect']
        ? 'eme.eme_export_download'
        : 'eme.collection';
      return new RedirectResponse(Url::fromRoute($route_name)->toString(), 307);
    }
    else {
      // An error occurred. "$operations" contains the operations which remain
      // "unprocessed".
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]), 'error');
    }
  }

}
