<?php

namespace Drupal\hous_z_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides the Hous-Z management dashboard and home pages.
 */
class HouzDashboardController extends ControllerBase {

  protected Connection $database;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * Home page — landing for anonymous, redirect for authenticated.
   */
  public function home(): array|RedirectResponse {
    if ($this->currentUser()->isAuthenticated()) {
      return new RedirectResponse(Url::fromRoute('hous_z_management.dashboard')->toString());
    }

    return [
      '#theme'  => 'hous_z_landing',
      '#cache'  => ['contexts' => ['user.roles:anonymous']],
    ];
  }

  /**
   * Rich management dashboard for authenticated managers.
   */
  public function dashboard(): array {
    $booking_storage = $this->entityTypeManager->getStorage('bat_booking');
    $unit_storage    = $this->entityTypeManager->getStorage('bat_unit');

    // ── Stat counts ────────────────────────────────────────────────────────
    $total_rooms = (int) $unit_storage->getQuery()
      ->accessCheck(FALSE)->count()->execute();

    $total_bookings = (int) $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')->count()->execute();

    $pending_count = (int) $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')
      ->condition('field_event_state.entity.machine_name', 'pending')
      ->count()->execute();

    $confirmed_count = (int) $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')
      ->condition('field_event_state.entity.machine_name', 'confirmed')
      ->count()->execute();

    $cancelled_count = (int) $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')
      ->condition('field_event_state.entity.machine_name', 'cancelled')
      ->count()->execute();

    // Today's check-ins.
    $today = date('Y-m-d');
    $today_count = (int) $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')
      ->condition('booking_start_date', $today . '%', 'LIKE')
      ->count()->execute();

    // ── Upcoming check-ins (next 7 days) ────────────────────────────────────
    $next_week = date('Y-m-d', strtotime('+7 days'));
    $upcoming_ids = $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')
      ->condition('booking_start_date', $today, '>=')
      ->condition('booking_start_date', $next_week . ' 23:59:59', '<=')
      ->sort('booking_start_date', 'ASC')
      ->range(0, 10)
      ->execute();

    $upcoming = $this->formatBookings(array_values($upcoming_ids));

    // ── Recent bookings ───────────────────────────────────────────────────
    $recent_ids = $booking_storage->getQuery()
      ->accessCheck(FALSE)->condition('type', 'standard')
      ->sort('id', 'DESC')
      ->range(0, 8)
      ->execute();

    $recent = $this->formatBookings(array_values($recent_ids));

    return [
      '#theme'   => 'hous_z_dashboard',
      '#stats'   => [
        'rooms'     => $total_rooms,
        'total'     => $total_bookings,
        'pending'   => $pending_count,
        'confirmed' => $confirmed_count,
        'cancelled' => $cancelled_count,
        'today'     => $today_count,
      ],
      '#upcoming' => $upcoming,
      '#recent'   => $recent,
      '#username'    => $this->currentUser()->getDisplayName(),
      '#greeting'    => $this->timeGreeting(),
      '#today_label' => (new \DateTime())->format('l, d F Y'),
      '#cache'    => ['max-age' => 0],
      '#attached' => ['library' => ['hous_z_management/dashboard']],
    ];
  }

  /**
   * Bookings management page — full styled listing with filter + actions.
   */
  public function bookings(): array {
    $request      = \Drupal::request();
    $state_filter = $request->query->get('state', '');
    $page         = max(0, (int) $request->query->get('page', 0));
    $per_page     = 20;

    $storage = $this->entityTypeManager->getStorage('bat_booking');

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'standard')
      ->sort('id', 'DESC');

    if ($state_filter && in_array($state_filter, ['pending', 'confirmed', 'cancelled'], TRUE)) {
      $query->condition('field_event_state.entity.machine_name', $state_filter);
    }

    $total      = (int) (clone $query)->count()->execute();
    $ids        = $query->range($page * $per_page, $per_page)->execute();
    $rows       = $this->formatBookingsFull(array_values($ids));
    $page_count = (int) ceil($total / $per_page);

    return [
      '#theme'        => 'hous_z_bookings',
      '#rows'         => $rows,
      '#total'        => $total,
      '#state_filter' => $state_filter,
      '#pager'        => ['page' => $page, 'pages' => $page_count, 'per_page' => $per_page],
      '#cache'        => ['max-age' => 0],
      '#attached'     => ['library' => ['hous_z_management/dashboard']],
    ];
  }

  /**
   * Quick confirm/cancel action for a booking.
   */
  public function bookingAction(int $booking_id, string $action): RedirectResponse {
    $booking = $this->entityTypeManager->getStorage('bat_booking')->load($booking_id);
    $redirect = Url::fromRoute('hous_z_management.bookings', [], ['query' => \Drupal::request()->query->all()])->toString();

    if (!$booking) {
      \Drupal::messenger()->addError($this->t('Booking not found.'));
      return new RedirectResponse($redirect);
    }

    // Map short action name to actual machine name.
    $machine_map = ['confirm' => 'confirmed', 'cancel' => 'cancelled'];
    $machine_name = $machine_map[$action] ?? $action;

    // Load the target state entity by machine name.
    $all_states = $this->entityTypeManager->getStorage('state')->loadMultiple();
    $state = NULL;
    foreach ($all_states as $s) {
      if ($s->getMachineName() === $machine_name) {
        $state = $s;
        break;
      }
    }

    if (!$state) {
      \Drupal::messenger()->addError($this->t('State "@s" not found.', ['@s' => $action]));
      return new RedirectResponse($redirect);
    }

    $booking->set('field_event_state', $state->id());
    $booking->save();

    \Drupal::messenger()->addStatus($this->t('Booking #@id marked as @state.', [
      '@id'    => $booking_id,
      '@state' => $action,
    ]));

    return new RedirectResponse($redirect);
  }

  /**
   * Formats bookings with full detail for the bookings list page.
   */
  protected function formatBookingsFull(array $ids): array {
    if (empty($ids)) {
      return [];
    }

    $bookings = $this->entityTypeManager->getStorage('bat_booking')->loadMultiple($ids);
    $rows     = [];

    foreach ($bookings as $booking) {
      $event = $booking->get('booking_event_reference')->entity ?? NULL;
      $unit  = $event?->get('event_bat_unit_reference')->entity ?? NULL;
      $state = $booking->get('field_event_state')->entity ?? NULL;

      $status_machine = $state?->get('machine_name')->value ?? 'pending';

      $rows[] = [
        'id'           => (int) $booking->id(),
        'room'         => $unit?->label() ?? '—',
        'guest'        => $booking->get('field_requester_email')->value ?? '—',
        'check_in'     => $this->formatDate($booking->get('booking_start_date')->value ?? ''),
        'check_out'    => $this->formatDate($booking->get('booking_end_date')->value ?? ''),
        'status'       => $status_machine,
        'status_label' => $state?->label() ?? ucfirst($status_machine),
        'edit_url'     => Url::fromRoute('entity.bat_booking.edit_form', ['bat_booking' => $booking->id()])->toString(),
        'confirm_url'  => Url::fromRoute('hous_z_management.booking_action', ['booking_id' => $booking->id(), 'action' => 'confirm'])->toString(),
        'cancel_url'   => Url::fromRoute('hous_z_management.booking_action', ['booking_id' => $booking->id(), 'action' => 'cancel'])->toString(),
      ];
    }

    return $rows;
  }

  /**
   * Units management page — styled listing with actions.
   */
  public function units(): array {
    $storage = $this->entityTypeManager->getStorage('bat_unit');
    $ids     = $storage->getQuery()->accessCheck(FALSE)->sort('id', 'ASC')->execute();
    $units   = $storage->loadMultiple($ids);

    $rows = [];
    foreach ($units as $unit) {
      $booking_count = (int) $this->entityTypeManager->getStorage('bat_booking')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'standard')
        ->condition('booking_event_reference.entity.event_bat_unit_reference', $unit->id())
        ->count()->execute();

      $rows[] = [
        'id'      => (int) $unit->id(),
        'name'    => $unit->label(),
        'beds'    => $unit->get('field_beds')->value ?: '—',
        'address' => $unit->get('field_address')->value ?: '—',
        'email'   => $unit->get('field_manager_email')->value ?: '—',
        'status'  => $unit->get('status')->value ? 'active' : 'inactive',
        'bookings'   => $booking_count,
        'edit_url'   => Url::fromRoute('entity.bat_unit.edit_form', ['bat_unit' => $unit->id()])->toString(),
        'delete_url' => Url::fromRoute('entity.bat_unit.delete_form', ['bat_unit' => $unit->id()])->toString(),
      ];
    }

    return [
      '#theme'    => 'hous_z_units',
      '#rows'     => $rows,
      '#add_url'  => Url::fromRoute('entity.bat_unit.add_page')->toString(),
      '#cache'    => ['max-age' => 0],
      '#attached' => ['library' => ['hous_z_management/dashboard']],
    ];
  }

  /**
   * Returns "morning", "afternoon", or "evening" based on current hour.
   */
  protected function timeGreeting(): string {
    $hour = (int) (new \DateTime())->format('G');
    if ($hour < 12) {
      return 'morning';
    }
    if ($hour < 18) {
      return 'afternoon';
    }
    return 'evening';
  }

  /**
   * Formats a list of booking IDs into display arrays.
   */
  protected function formatBookings(array $ids): array {
    if (empty($ids)) {
      return [];
    }

    $bookings = $this->entityTypeManager->getStorage('bat_booking')->loadMultiple($ids);
    $rows     = [];

    foreach ($bookings as $booking) {
      $event = $booking->get('booking_event_reference')->entity ?? NULL;
      $unit  = $event?->get('event_bat_unit_reference')->entity ?? NULL;
      $state = $booking->get('field_event_state')->entity ?? NULL;

      $check_in  = $this->formatDate($booking->get('booking_start_date')->value ?? '');
      $check_out = $this->formatDate($booking->get('booking_end_date')->value ?? '');
      $time_in   = $booking->hasField('field_check_in_time') ? (string) $booking->get('field_check_in_time')->value : '';
      if ($time_in !== '') {
        $check_in .= ' ' . $time_in;
      }

      $status_machine = $state?->get('machine_name')->value ?? 'pending';

      $rows[] = [
        'id'        => (int) $booking->id(),
        'room'      => $unit?->label() ?? '—',
        'guest'     => $booking->get('field_requester_email')->value ?? '—',
        'check_in'  => $check_in,
        'check_out' => $check_out,
        'status'    => $status_machine,
        'status_label' => $state?->label() ?? ucfirst($status_machine),
      ];
    }

    return $rows;
  }

  /**
   * Formats a BAT storage date value to British dd/mm/yyyy.
   */
  protected function formatDate(string $value): string {
    if ($value === '') {
      return '—';
    }
    try {
      return (new \DateTime($value))->format('d/m/Y');
    }
    catch (\Exception) {
      return $value;
    }
  }

}
