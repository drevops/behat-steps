<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;

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
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'title' => $title,
        'type' => $type,
      ]);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    ksort($nodes);

    $node = end($nodes);
    $path = $this->locatePath($node->toUrl()->getInternalPath());
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * @When I edit :type :title
   */
  public function contentEditPageWithTitle($type, $title) {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'title' => $title,
        'type' => $type,
      ]);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    $node = current($nodes);
    $path = $this->locatePath('/node/' . $node->id()) . '/edit';
    print $path;
    $this->getSession()->visit($path);
  }

  /**
   * @Given no :type content type
   */
  public function contentDelete($type) {
    $content_type_entity = \Drupal::entityManager()->getStorage('node_type')->load($type);
    if ($content_type_entity) {
      $content_type_entity->delete();
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
      throw new ExpectationException(sprintf('The current state "%s" is different from "%s"', $current_old_state, $old_state));
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

}
