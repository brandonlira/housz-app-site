<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Context\CustomSnippetAcceptingContext;

/**
 * Features context.
 */
class BatFeatureContext extends RawDrupalContext implements CustomSnippetAcceptingContext {

  /**
   * The Mink context.
   *
   * @var Drupal\DrupalExtension\Context\MinkContext
   */
  private $minkContext;

  /**
   * Keep track of units so they can be cleaned up.
   *
   * @var array
   */
  public $units = [];

  /**
   * Keep track of Types so they can be cleaned up.
   *
   * @var array
   */
  public $types = [];

  /**
   * Keep track of Types bundles so they can be cleaned up.
   *
   * @var array
   */
  public $typeBundles = [];

  /**
   * Keep track of events so they can be cleaned up.
   *
   * @var array
   */
  public $events = [];

  /**
   * Keep track of event types so they can be cleaned up.
   *
   * @var array
   */
  public $eventTypes = [];

  /**
   * Track.
   *
   * Keep track of created content types so they can be cleaned up.
   *
   * @var array
   */
  public $contentTypes = [];

  /**
   * Track.
   *
   * Keep track of created fields so they can be cleaned up.
   *
   * @var array
   */
  public $fields = [];

  /**
   * Get.
   */
  public static function getAcceptedSnippetType() {
    return 'regex';
  }

  /**
   * Before.
   *
   * @BeforeScenario
   */
  public function before(BeforeScenarioScope $scope) {
    $environment = $scope->getEnvironment();
    $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

  /**
   * After.
   *
   * @AfterScenario
   */
  public function after(AfterScenarioScope $scope) {
    foreach ($this->getUserManager()->getUsers() as $user) {
      $query = \Drupal::entityQuery('bat_event');
      $query->accessCheck(TRUE);
      $query->condition('uid', $user->uid);
      $event_ids = $query->execute();
      if ($event_ids) {
        bat_event_delete_multiple($event_ids);
      }
    }

    if (!empty($this->units)) {
      foreach ($this->units as $unit) {
        $unit->delete();
      }
    }

    if (!empty($this->Types)) {
      foreach ($this->Types as $type) {
        $type->delete();
      }
    }

    if (!empty($this->typeBundles)) {
      foreach ($this->typeBundles as $type_bundle) {
        $type_bundle->delete();
      }
    }

    if (!empty($this->eventTypes)) {
      foreach ($this->eventTypes as $event_type) {
        $event_type->delete();
      }
    }

    if (!empty($this->events)) {
      bat_event_delete_multiple($this->events);
    }

    foreach ($this->content_types as $content_type) {
      node_type_delete($content_type);
    }

    foreach ($this->fields as $field) {
      field_delete_field($field);
    }

  }

  /**
   * Descr.
   *
   * @When /^I am on the "([^"]*)" type$/
   */
  public function iAmOnTheType($type_name) {
    $this->iAmDoingOnTheType('view', $type_name);
  }

  /**
   * Descr.
   *
   * @When /^I am editing the "([^"]*)" type$/
   */
  public function iAmEditingTheType($type_name) {
    $this->iAmDoingOnTheType('edit', $type_name);
  }

  /**
   * Asserts that a given node type is editable.
   */
  public function assertEditNodeOfType($type) {
    $node = (object) ['type' => $type];
    $saved = $this->getDriver()->createNode($node);
    $this->nodes[] = $saved;

    // Set internal browser on the node edit page.
    $this->getSession()->visit($this->locatePath('/node/' . $saved->nid . '/edit'));
  }

  /**
   * Fills a field using JS to avoid event firing.
   *
   * @param string $field
   *   Comment.
   * @param string $value
   *   Comment.
   */
  protected function fillFieldByJs($field, $value) {
    $field = str_replace('\\"', '"', $field);
    $value = str_replace('\\"', '"', $value);
    $xpath = $this->getSession()->getPage()->findField($field)->getXpath();

    $element = $this->getSession()->getDriver()->getWebDriverSession()->element('xpath', $xpath);
    $elementID = $element->getID();
    $subscript = "arguments[0]";
    $script = str_replace('{{ELEMENT}}', $subscript, '{{ELEMENT}}.value = "' . $value . '"');

    $tmp = [
      'script' => $script,
      'args' => [
        'ELEMENT' => $elementID,
      ],
    ];
    return $this->getSession()->getDriver()->getWebDriverSession()->execute($tmp);
  }

  /**
   * Redirects user to the action page for the given unit.
   *
   * @param string $action
   *   Comment.
   * @param string $type_name
   *   Comment.
   */
  protected function iAmDoingOnTheType(string $action, string $type_name) {
    $url = "admin/bat/config/types/manage/$type_id/$action";
    $this->getSession()->visit($this->locatePath($url));
  }

  /**
   * Returns a type_id from its name.
   *
   * @param string $type_name
   *   Comment.
   *
   * @return int
   *   Comment.
   *
   * @throws RuntimeException
   */
  protected function findTypeByName(string $type_name) {
    $query = \Drupal::entityQuery('bat_unit_type');
    $query->accessCheck(TRUE);
    $query->condition('name', $type_name);
    $results = $query->execute();
    if ($results) {
      return key($results);
    }
    else {
      throw new RuntimeException('Unable to find that type');
    }
  }

}
