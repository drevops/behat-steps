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
   * The mink context.
   *
   * @var \Drupal\DrupalExtension\Context\MinkContext
   */
  protected $contentMinkContext;

  /**
   * @BeforeScenario
   */
  public function contentGetMinkContext(BeforeScenarioScope $scope) {
    $this->contentMinkContext = $scope->getEnvironment()->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

  /**
   * Navigate to page with specified type and title.
   *
   * @code
   * When I visit "article" "Test article"
   * @endcode
   *
   * @When I visit :type :title
   */
  public function contentVisitPageWithTitle($type, $title) {
    $nids = $this->contentNodeLoadMultiple($type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    ksort($nids);

    $nid = end($nids);
    $path = $this->locatePath('/node/' . $nid);
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * Navigate to edit page with specified type and title.
   *
   * @code
   * When I edit "article" "Test article"
   * @endcode
   *
   * @When I edit :type :title
   */
  public function contentEditPageWithTitle($type, $title) {
    $nids = $this->contentNodeLoadMultiple($type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    $nid = current($nids);
    $path = $this->locatePath('/node/' . $nid) . '/edit';
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * Remove content defined by provided properties.
   *
   * @code
   * Given no "article" content:
   * | title                |
   * | Test article         |
   * | Another test article |
   * @endcode
   *
   * @Given /^no ([a-zA-z0-9_-]+) content:$/
   */
  public function contentDelete($type, TableNode $nodesTable) {
    foreach ($nodesTable->getHash() as $nodeHash) {
      $nids = $this->contentNodeLoadMultiple($type, $nodeHash);

      $controller = \Drupal::entityTypeManager()->getStorage('node');
      $entities = $controller->loadMultiple($nids);
      $controller->delete($entities);
    }
  }

  /**
   * Delete managed files defined by provided properties.
   *
   * @code
   * Given no managed files:
   * | filename      |
   * | myfile.jpg    |
   * | otherfile.jpg |
   * @endcode
   *
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
   * @code
   * When the moderation state of "article" "Test article" changes from "draft" to "published"
   * @endcode
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
   * @code
   * When I fill in CKEditor on field "edit-body-0-value" with "Test"
   * And I fill in CKEditor on field "edit-body-0-value" with "Test"
   * @endcode
   *
   * @When /^I fill in CKEditor on field "([^"]*)" with "([^"]*)"$/
   */
  public function contentFillCkeditorField($field, $value) {
    $this->contentMinkContext->getSession()->executeScript("CKEDITOR.instances[\"$field\"].setData(\"$value\");");
  }

  /**
   * Helper to load multiple nodes with specified type and conditions.
   *
   * @param string $type
   *   The node type.
   * @param array $conditions
   *   Conditions keyed by field names.
   *
   * @return array
   *   Array of node ids.
   */
  protected function contentNodeLoadMultiple($type, array $conditions = []) {
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
