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
   *
   * Initialisation phase — setTo() must be called here, not in build().
   *
   * @param string $to      Recipient email address.
   * @param string $subject Email subject line.
   * @param array  $vars    Template variables for hous_z_api_email theme hook.
   */
  public function createParams(EmailInterface $email, string $to = '', string $subject = '', array $vars = []): void {
    if ($to !== '') {
      $email->setTo($to);
    }
    $email
      ->setParam('subject', $subject)
      ->setParam('vars', $vars);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $vars = $email->getParam('vars') ?? [];

    $email->setSubject($email->getParam('subject'));

    $email->setBody([
      '#theme'        => 'hous_z_api_email',
      '#heading'      => $vars['heading']      ?? '',
      '#intro'        => $vars['intro']        ?? '',
      '#rows'         => $vars['rows']         ?? [],
      '#details'      => $vars['details']      ?? '',
      '#status'       => $vars['status']       ?? 'pending',
      '#status_label' => $vars['status_label'] ?? '',
      '#cta_email'    => $vars['cta_email']    ?? '',
      '#logo_url'     => $vars['logo_url']     ?? '',
    ]);
  }

}
