<?php

namespace Drupal\bat_event\Entity\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Event entities.
 *
 * @ingroup bat
 */
class EventDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this Event?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {

    // With BEE module enabled, get back to the availability tab.
    if (\Drupal::moduleHandler()->moduleExists('bee')) {
      $request = \Drupal::request();
      $tmp['session'] = $request->getSession();
      $tmp['nid'] = $tmp['session']->get('bee_nid', 0);
      $tmp['destination'] = new Url('bee.node.availability', ['node' => $tmp['nid']]);
      return $tmp['destination'];
    }
    else {
      return new Url('entity.bat_event.collection');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('The event has been deleted'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
