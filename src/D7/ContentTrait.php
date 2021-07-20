<?php

namespace DrevOps\BehatSteps\D7;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait ContentTrait.
 *
 * @package DrevOps\BehatSteps\D7
 */
trait ContentTrait {

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
    $nodes = node_load_multiple(NULL, [
      'title' => $title,
      'type' => $type,
    ]);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    ksort($nodes);

    $node = end($nodes);
    $path = $this->locatePath('/node/' . $node->nid);
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
    $nodes = node_load_multiple(NULL, [
      'title' => $title,
      'type' => $type,
    ]);

    if (empty($nodes)) {
      throw new \Exception(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    $node = current($nodes);
    $path = $this->locatePath('/node/' . $node->nid) . '/edit';
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
      $nodes = node_load_multiple([], $nodeHash + ['type' => $type]);
      node_delete_multiple(array_keys($nodes));
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

}
