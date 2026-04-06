<?php

namespace Drupal\hous_z_api\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Email Builder for Hous-Z API booking notifications.
 *
 * @EmailBuilder(
 *   id = "hous_z_api",
 *   label = @Translation("Hous-Z API"),
 *   sub_types = {
 *     "booking_created_admin"  = @Translation("New booking — admin"),
 *     "booking_created_guest"  = @Translation("New booking — guest"),
 *     "booking_confirmed"      = @Translation("Booking confirmed"),
 *     "booking_cancelled"      = @Translation("Booking cancelled"),
 *     "booking_status_admin"   = @Translation("Status changed — admin"),
 *     "booking_status_changed" = @Translation("Status changed — guest"),
 *   },
 *   common_adjusters = {},
 *   override = FALSE,
 * )
 */
class HousZEmailBuilder extends EmailBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, string $to = '', string $subject = '', string $html = ''): void {
    // setTo must be called in the initialisation phase (createParams),
    // not in build() — see https://www.drupal.org/node/3501754
    if ($to !== '') {
      $email->setTo($to);
    }
    $email
      ->setParam('subject', $subject)
      ->setParam('html', $html);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $email->setSubject($email->getParam('subject'));

    $email->setBody([
      '#type'     => 'inline_template',
      '#template' => '{{ html|raw }}',
      '#context'  => ['html' => $email->getParam('html')],
    ]);
  }

}
