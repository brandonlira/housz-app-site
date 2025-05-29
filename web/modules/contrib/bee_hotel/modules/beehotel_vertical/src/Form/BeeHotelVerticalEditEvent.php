<?php

namespace Drupal\beehotel_vertical\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the content_entity_example entity edit forms.
 *
 * @ingroup content_entity_example
 */
class BeeHotelVerticalEditEvent extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state
      ->setRedirect('entity.content_entity_example_contact.collection');
    $entity = $this->getEntity();
    $entity->save();
  }

}
