<?php

namespace Drupal\bee_hotel\EventSubscriber;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\Event\Form\FormBaseAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Change labels event subscriber.
 */
class ChangeUnitNameSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'hook_event_dispatcher.form_base_entity_form_display_form.alter' => ['addSettingsForm'],
      FormHookEvents::FORM_ALTER => ['alterForm'],
    ];
  }

  /**
   * Event handler for the third party settings of a widget.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormBaseAlterEvent $event
   *   Triggering event.
   */
  public function addSettingsForm(FormBaseAlterEvent $event) {
    $entityFormDisplay = $event->getFormState()->getFormObject()->getEntity();
    $form = &$event->getForm();

    // @todo improve this without bundle name
    if (isset($form) && $form['#bundle'] == "unit") {
      $form['change_labels'] = [
        '#type' => 'details',
        '#title' => $this->t('Change labels'),
      ];
      $form['change_labels']['unit_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label for Units (Example: rooms, apartment, boat)'),
        '#default_value' => $entityFormDisplay->getThirdPartySetting('bee_hotel', 'unit_name'),
      ];
      $form['#entity_builders'][] = [$this, 'saveThirdPartySettings'];
    }

  }

  /**
   * Entity Builder Callback.
   */
  public function saveThirdPartySettings($entityType, $entityFormDisplay, array &$form, FormStateInterface $formState) {

    if ($formState->getValue('unit_name')) {
      $entityFormDisplay->setThirdPartySetting('bee_hotel', 'unit_name', $formState->getValue('unit_name'));
      return;
    }

    $entityFormDisplay->unsetThirdPartySetting('bee_hotel', 'unit_name');
  }

  /**
   * Event handler for altering the submit label of a form.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormAlterEvent $event
   *   Triggering event.
   */
  public function alterForm(FormAlterEvent $event) {
    $formObject = $event->getFormState()->getFormObject();
    $form = &$event->getForm();
    if ($formObject instanceof ContentEntityFormInterface &&
      ($display = $formObject->getFormDisplay($event->getFormState())) &&
      isset($form['actions']['submit']['#value'])
    ) {
      $submitReplacement = $display->getThirdPartySetting('bee_hotel', 'unit_name');
      if ($submitReplacement) {
        $form['actions']['submit']['#value'] = $submitReplacement;
      }
    }
  }

}
