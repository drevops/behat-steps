<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Step\Given;

/**
 * Manage Drupal webforms.
 *
 * - Delete webforms matching a given title for test isolation.
 */
trait WebformTrait {

  /**
   * Remove all webforms with a title containing the given string.
   *
   * Silently succeeds if no matching webforms are found.
   *
   * @param string $title
   *   The title (or partial title) of the webform(s) to delete.
   *
   * @code
   *   Given the webform "Test form" does not exist
   * @endcode
   */
  #[Given('the webform :title does not exist')]
  public function webformDelete(string $title): void {
    $webforms = $this->webformLoadAll($title);

    foreach ($webforms as $webform) {
      $webform->delete();
    }
  }

  /**
   * Load all webforms whose title contains the given string.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return \Drupal\webform\WebformInterface[]
   *   An array of matching webform entities.
   */
  protected function webformLoadAll(string $title): array {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');

    $ids = $entity_type_manager->getStorage('webform')->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $title, 'CONTAINS')
      ->execute();

    if (empty($ids)) {
      return [];
    }

    /** @var \Drupal\webform\WebformInterface[] $webforms */
    $webforms = $entity_type_manager->getStorage('webform')->loadMultiple($ids);

    return $webforms;
  }

}
