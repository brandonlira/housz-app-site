<?php

namespace Drupal\beehotel_ical\Controller;

use Drupal\bee_hotel\Event;
use Drupal\Core\Controller\ControllerBase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines BeeHotelICal class.
 */
class BeeHotelICal extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;


  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The bee hotel event.
   *
   * @var \Drupal\bee_hotel\Event
   */
  private $beehotelEvent;

  /**
   * Constructs a new TaxNumberController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param Drupal\bee_hotel\Event $beehotel_event
   *   The Bee Hotel Event util.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, RequestStack $request_stack, Event $beehotel_event) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->requestStack = $request_stack;
    $this->beehotelEvent = $beehotel_event;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('request_stack'),
      $container->get('bee_hotel.util.event'),
    );
  }

  /**
   * Begin of text.
   */
  private function head($data) {

    $output  = "BEGIN:VCALENDAR\n";
    $output .= "PRODID:-//Drupal Bee Hotel//0.2 //EN\n";
    $output .= "VERSION:2.0\n";
    $output .= "CALSCALE:GREGORIAN\n";
    $output .= "METHOD:PUBLISH\n";
    $output .= "X-WR-CALNAME:BEEHotel-calendar\n";
    $output .= "X-WR-TIMEZONE:Europe/Rome\n";
    $output .= "X-WR-CALDESC:Druapl Bee Hotel Ical\n";
    return $output;

  }

  /**
   * Events inside the text.
   */
  private function events($data) {

    $ical_config = $this->config('beehotel_ical.settings')->get("ical");
    $host = $this->requestStack->getCurrentRequest()->getHost();

    for ($i = 1; $i < $data['howmanydays']; $i++) {
      $data['day']['d'] = date("d", strtotime('+' . $i . ' days', strtotime($data['today'])));
      $data['day']['month'] = date("m", strtotime('+' . $i . ' days', strtotime($data['today'])));
      $data['day']['year'] = date("Y", strtotime('+' . $i . ' days', strtotime($data['today'])));

      $data['status'] = $this->beehotelEvent->getNightState($data);
      $data['id'] = $this->beehotelEvent->getNightEvent($data);

      if (strpos("0" . $ical_config['blocking_status'], $data['status'])) {
        $event = $this->entityTypeManager->getStorage('bat_event')->load($data['id']);
        $current_day = [];
        $current_day['DTSTAMP'] = $data['today']->format("Ymd\THms\Z");
        $current_day['UUID'] = $event->uuid() . "@" . $host;
        $current_day['DTSTART'] = $this->dateFormatter->format(strtotime($event->get("event_dates")->value), 'custom', 'Ymd');
        $current_day['DTEND'] = $this->dateFormatter->format(strtotime($event->get("event_dates")->end_value), 'custom', 'Ymd');
        $current_day['LAST-MODIFIED'] = $this->dateFormatter->format($event->get("changed")->value, 'custom', 'Ymd\THms\Z');
        $current_day['CREATED'] = $this->dateFormatter->format($event->get("created")->value, 'custom', 'Ymd\THms\Z');
        $current_day['Y-m-d'] = $data['day']['year'] . "-" . $data['day']['month'] . "-" . $data['day']['d'];
        $current_day['one_day_after'] = date("Ymd", strtotime('+1 days', strtotime($current_day['Y-m-d'])));

        if ($current_day['one_day_after'] == substr($current_day['DTEND'], 0, 8)) {
          $current_day['ical']  = "BEGIN:VEVENT\r\n";
          $current_day['ical'] .= "DTSTART;VALUE=DATE:" . $current_day['DTSTART'] . "\r\n";
          $current_day['ical'] .= "DTEND;VALUE=DATE:" . $current_day['DTEND'] . "\r\n";
          $current_day['ical'] .= "DTSTAMP:" . $data['today']->format("Ymd\THms\Z") . "\r\n";
          $current_day['ical'] .= "UID:" . $current_day['UUID'] . "\r\n";
          $current_day['ical'] .= "CREATED:" . $current_day['CREATED'] . "\r\n";
          $current_day['ical'] .= "DESCRIPTION:\r\n";
          $current_day['ical'] .= "LAST-MODIFIED:" . $current_day['LAST-MODIFIED'] . "\r\n";
          $current_day['ical'] .= "LOCATION:\r\n";
          $current_day['ical'] .= "SEQUENCE:2\r\n";
          $current_day['ical'] .= "STATUS:CONFIRMED\r\n";
          $current_day['ical'] .= "SUMMARY:bbs bee_hotel\r\n";
          $current_day['ical'] .= "TRANSP:OPAQUE\r\n";
          $current_day['ical'] .= "END:VEVENT\r\n";
          $events .= $current_day['ical'];
        }
      }
    }
    return $events;
  }

  /**
   * End of text.
   */
  private function tail($data) {
    $output = "END:VCALENDAR\r\n";
    return $output;
  }

  /**
   * Get unit avaialbility.
   *
   * @todo this features may be duplicated inside beehotel.
   */
  public function availability(Node $node = NULL) {

    $data = [];
    $data['unit']['bid'] = $node->get("field_product")->target_id;
    $data['filename'] = "beehotel_" . $this->cleanFileName($node->gettitle()) . ".ics";
    $data['type'] = "bat_event";
    $data['howmanydays'] = 10;

    $date = new DrupalDateTime();
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $data['today'] = $date;
    $data['http_headers'] = $date->format('D, d M Y G:i:s \G\M\T');

    $data['head'] = $this->head($data);
    $data['body'] = $this->events($data);
    $data['tail'] = $this->tail($data);

    $output = $data['head'] . $data['body'] . $data['tail'];

    $response = new Response();
    $response->headers->set('content-type', 'text/calendar');
    $response->headers->set('vary', 'Sec-Fetch-Dest, Sec-Fetch-Mode, Sec-Fetch-Site');
    $response->headers->set('cache-control', 'no-cache, no-store, max-age=0, must-revalidate');
    $response->headers->set('pragma', 'no-cache');
    $response->headers->set('expires', 'Mon, 01 Jan 1970 00:00:00 GMT');
    $response->headers->set('date', $data['http_headers']);

    // Buggy.
    // $response->headers->set('content-length',
    // mb_strlen($data['body'], '8bit') );
    // .
    $response->headers->set('strict-transport-security', 'max-age=31536000; includeSubDomains; preload');
    $response->headers->set('cross-origin-opener-policy-report-only', 'same-origin-allow-popups; report-to="calendar_coop_coep"');
    $response->headers->set('cross-origin-embedder-policy-report-only', 'require-corp; report-to="calendar_coop_coep"');
    $response->headers->set('content-disposition', 'attachment; filename="' . $data['filename'] . '"');
    $response->setContent($output);
    return $response;
  }

  /**
   * Utility to produce a normalized file name.
   */
  private function cleanFileName($file_name) {

    $file_name_str = pathinfo($file_name, PATHINFO_FILENAME);

    // Replaces all spaces with hyphens.
    $file_name_str = str_replace(' ', '-', $file_name_str);
    // Removes special chars.
    $file_name_str = preg_replace('/[^A-Za-z0-9\-\_]/', '', $file_name_str);

    // Replaces multiple hyphens with single one.
    $file_name_str = preg_replace('/-+/', '-', $file_name_str);

    return $file_name_str;
  }

}
