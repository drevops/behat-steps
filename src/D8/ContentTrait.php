<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

/**
 * Trait ContentTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait ContentTrait {

  /**
   * @var \Drupal\DrupalExtension\Context\MinkContext
   */
  protected $minkContext;

  /**
   * @BeforeScenario
   */
  public function contentGetMinkContext(BeforeScenarioScope $scope) {
    /** @var \Behat\Behat\Context\Environment\InitializedContextEnvironment $environment */
    $environment = $scope->getEnvironment();
    $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

  /**
   * @When I visit :type :title
   */
  public function contentVisitPageWithTitle($type, $title) {
    $nids = $this->nodeLoadMultiple($type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new RuntimeException(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    ksort($nids);

    $nid = end($nids);
    $path = $this->locatePath('/node/' . $nid);
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * @When I edit :type :title
   */
  public function contentEditPageWithTitle($type, $title) {
    $nids = $this->nodeLoadMultiple($type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new RuntimeException(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    $nid = current($nids);
    $path = $this->locatePath('/node/' . $nid) . '/edit';
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * @Given /^no ([a-zA-z0-9_-]+) content:$/
   */
  public function contentDelete($type, TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $nids = $this->nodeLoadMultiple($type, $nodeHash);

      $controller = \Drupal::entityTypeManager()->getStorage('node');
      $entities = $controller->loadMultiple($nids);
      $controller->delete($entities);
    }
  }

  /**
   * @Given no managed files:
   */
  public function contentDeleteManagedFiles(TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $hash) {
      $files = file_load_multiple([], $hash);
      file_delete_multiple(array_keys($files));
    }
  }

  /**
   * Change moderation state of a content with specified title.
   *
   * @When the moderation state of :type :title changes from :old_state to :new_state
   */
  public function contentModeratePageWithTitle($type, $title, $old_state, $new_state) {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'title' => $title,
        'type' => $type,
      ]);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    /** @var \Drupal\node\Entity\Node $node */
    $node = current($nodes);
    $current_old_state = $node->get('moderation_state')->first()->getString();
    if ($current_old_state != $old_state) {
      throw new \Exception(sprintf('The current state "%s" is different from "%s"', $current_old_state, $old_state));
    }

    $node->set('moderation_state', $new_state);
    $node->save();
  }

  /**
   * Fills in form CKEditor field with specified id.
   *
   * Example: When I fill in CKEditor on field "edit-body-0-value" with "Test"
   * Example: And I fill in CKEditor on field "edit-body-0-value" with "Test"
   *
   * @When /^I fill in CKEditor on field "([^"]*)" with "([^"]*)"$/
   */
  public function contentFillCkeditorField($field, $value) {
    $this->minkContext->getSession()->executeScript("CKEDITOR.instances[\"$field\"].setData(\"$value\");");
  }

  /**
   * Helper to load multiple nodes with specified type and conditions.
   */
  protected function nodeLoadMultiple($type, $conditions = []) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $type);
    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
