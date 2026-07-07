<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Step\Given;

/**
 * Manage Drupal webforms.
 *
 * - Delete webforms matching a given title for test isolation.
 * - Clone webform templates into new webforms for scenario setup.
 * - Cloned webforms are automatically removed at the end of the scenario.
 *
 * Requires `drupal/webform` module.
 */
trait WebformTrait {

  use HelperTrait;

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
   * Clone a webform template into a new webform with the given title.
   *
   * Finds a webform template whose title contains the given string,
   * duplicates it as a non-template webform, and tracks it for cleanup.
   *
   * @param string $title
   *   The title for the new webform.
   * @param string $template
   *   The title (or partial title) of the template to clone.
   *
   * @code
   *   Given a webform "My contact form" from template "Contact"
   * @endcode
   */
  #[Given('a webform :title from template :template')]
  public function webformCloneTemplate(string $title, string $template): void {
    $templates = $this->webformTemplates($template);

    if (empty($templates)) {
      throw new \RuntimeException(sprintf('No webform template matching "%s" was found.', $template));
    }

    $source = reset($templates);

    /** @var \Drupal\webform\WebformInterface $clone */
    $clone = $source->createDuplicate();
    $clone->set('title', $title);
    $clone->set('id', $this->webformMachineName($title));
    $clone->set('template', FALSE);
    $clone->save();

    $this->entityRegister($clone);
  }

  /**
   * Load all webform templates whose title contains the given string.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return \Drupal\webform\WebformInterface[]
   *   An array of matching webform template entities.
   */
  protected function webformTemplates(string $title): array {
    $webforms = $this->webformLoadAll($title);

    return array_filter($webforms, static fn($webform): bool => $webform->isTemplate());
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
    // Clear config factory cache to pick up webform changes made via the
    // admin UI in a separate process.
    \Drupal::configFactory()->reset();

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');
    $storage = $entity_type_manager->getStorage('webform');
    $storage->resetCache();

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $title, 'CONTAINS')
      ->sort('title')
      ->sort('id')
      ->execute();

    if (empty($ids)) {
      return [];
    }

    /** @var \Drupal\webform\WebformInterface[] $webforms */
    $webforms = $storage->loadMultiple($ids);

    return $webforms;
  }

  /**
   * Generate a sanitized machine name from a title.
   *
   * @param string $title
   *   The human-readable title.
   *
   * @return string
   *   A machine name suitable for a webform ID.
   */
  protected function webformMachineName(string $title): string {
    $machine_name = strtolower($title);
    $machine_name = (string) preg_replace('/[^a-z0-9_]+/', '_', $machine_name);
    $machine_name = trim($machine_name, '_');
    $machine_name = substr($machine_name, 0, 26);

    $storage = \Drupal::entityTypeManager()->getStorage('webform');
    $attempts = 0;
    do {
      $candidate = $machine_name . '_' . random_int(1000, 9999);
      $attempts++;
      if ($attempts > 50) {
        throw new \RuntimeException(sprintf('Unable to generate a unique webform machine name for "%s" after 50 attempts.', $title));
      }
    } while ($storage->load($candidate) !== NULL);

    return $candidate;
  }

}
