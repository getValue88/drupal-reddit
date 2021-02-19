<?php

namespace Drupal\eme\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\eme\Access\DownloadAccessCheck;

/**
 * The form which downloads the generated entity migration module.
 */
class DownloadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eme_export_download_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!(new DownloadAccessCheck())->access()->isAllowed()) {
      $form['info'] = [
        '#type' => 'item',
        '#markup' => $this->t("The export isn't accessible."),
      ];
      return $form;
    }
    $form['info'] = [
      '#type' => 'item',
      '#markup' => $this->t("The content entity migration set starts to download in  @countdown-seconds seconds. If it doesn't, use the button below.", [
        '@countdown-seconds' => Markup::create('<span class="js-eme-countdown">5</span>'),
      ]),
      '#attached' => ['library' => ['eme/countdown']],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download'),
    ];

    $file_download_url = Url::fromRoute('eme.eme_export_download_file')->setAbsolute()->toString();
    $refresh = [
      '#type' => 'html_tag',
      '#tag' => 'meta',
      '#value' => 'ignored',
      '#attributes' => [
        'http-equiv' => 'refresh',
        'content' => "5;url={$file_download_url}",
      ],
    ];
    $form['#attached']['html_head'][] = [$refresh, 'export-download'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('eme.eme_export_download_file');
  }

}
