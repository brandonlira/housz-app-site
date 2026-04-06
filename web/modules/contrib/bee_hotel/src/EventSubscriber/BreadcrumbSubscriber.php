<?php

namespace Drupal\custom_breadcrumb\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber to alter breadcrumbs on specific pages.
 */
class BreadcrumbSubscriber implements EventSubscriberInterface {

  /**
   * The breadcrumb builder.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbBuilder;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb_builder
   *   The breadcrumb builder.
   */
  public function __construct(BreadcrumbBuilderInterface $breadcrumb_builder) {
    $this->breadcrumbBuilder = $breadcrumb_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\Request
