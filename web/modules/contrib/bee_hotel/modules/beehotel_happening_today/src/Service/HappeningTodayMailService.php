<?php

namespace Drupal\beehotel_happening_today\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Service per l'invio del riepilogo "Happening Today".
 */
class HappeningTodayMailService {

  use StringTranslationTrait;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new HappeningTodayMailService.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $language_manager
  ) {
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
  }

 /**
  * Sends the "Happening Today" summary.
  *
  * @param array $data
  *   The data processed by the service.
  * @param string|null $to_email
  *   Recipient email. If null, uses site email.
  * @param string $date
  *   The reference date for "today".
  *
  * @return bool
  *   TRUE if email was sent successfully, FALSE otherwise.
  */
  public function sendHappeningTodaySummary(array $data = [], string $date = NULL): bool {

      $data['config'] = \Drupal::config('beehotel_happening_today.settings');

      // Get and validate recipients.
      $data['recipients'] = array_filter(
          array_map('trim',
            $data['config']->get('recipients'),
          ),
          function($email) {
              return \Drupal::service('email.validator')->isValid($email);
          }
      );

      $data['mail'] = [
          'success' => false,
          'error' => null,
          'recipient' => $data['recipients'],
          'subject' => $this->buildEmailSubject($data['day']['formatted_date']),
      ];

      try {
          // Genera e renderizza il report
          $data['body'] = $this->renderer->renderPlain(
              \Drupal::service('beehotel_happening_today.report_generator')->generateDailyReport()
          );

          foreach ($data['recipients'] as $recipient) {
              $result = $this->mailManager->mail(
                  'beehotel_happening_today',
                  'happening_today_summary',
                  $recipient,
                  $this->languageManager->getCurrentLanguage()->getId(),
                  [
                      'subject' => $data['mail']['subject'],
                      'body' => $data['body'],
                      'headers' => ['Content-Type' => 'text/html; charset=UTF-8;'],
                      'data' => $data,
                  ],
                  NULL,
                  TRUE
              );

              if ($result['result'] === TRUE) {
                  $data['mail']['success_count']++;
              }
          }

          $data['mail']['success'] = $data['mail']['success_count'] > 0;

          // Log results.
          if ($data['mail']['success_count'] > 0) {
              \Drupal::logger('beehotel_happening_today')->info('Email sent to @count/@total recipients', [
                  '@count' => $data['mail']['success_count'],
                  '@total' => count($data['recipients']),
              ]);
          }
          else {
              \Drupal::logger('beehotel_happening_today')->error('Failed to send email to any of @total recipients', [
                  '@total' => count($data['recipients']),
              ]);
          }

          return $data['mail']['success'];

      } catch (\Exception $e) {
          $data['mail']['error'] = $e->getMessage();
          $this->loggerFactory->get('beehotel_happening_today')->error('Eccezione durante l\'invio: @error', [
              '@error' => $data['mail']['error']
          ]);
          return false;
      }
  }

  /**
  * Builds the email subject.
  */
  protected function buildEmailSubject(string $date = NULL): string {
    $data['date'] = $date ?: date('Y-m-d');
    return $this->t('Daily Report - @date', [
        '@date' => \Drupal::service('date.formatter')->format(
            strtotime($data['date']), 'custom', 'd M y'
        )
    ]);
  }

  /**
   * Builds the email body.
   */
  protected function buildEmailBody(array $data, string $date): string {
    $build = [
      '#theme' => 'happening_today_email',
      '#data' => $data,
      '#date' => $date,
      '#timestamp' => strtotime($date),
    ];

    return $this->renderer->renderPlain($build);
  }

}
