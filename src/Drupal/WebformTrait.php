<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;

/**
 * Manage Drupal webforms.
 *
 * - Delete webforms matching a given title for test isolation.
 * - Clone webform templates into new webforms for scenario setup.
 * - Automatically clean up cloned webforms after scenario completion.
 *
 * Requires `drupal/webform` module.
 *
 * Skip processing with tag: `@behat-steps-skip:webformAfterScenario`
 */
trait WebformTrait {

  /**
   * Array of created webform entities for cleanup.
   *
   * @var array<int,\Drupal\webform\WebformInterface>
   */
  protected static $webformEntities = [];

  /**
   * Clean all created webform instances after scenario run.
   */
  #[AfterScenario]
  public function webformAfterScenario(AfterScenarioScope $scope): void {
    // @codeCoverageIgnoreStart
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    foreach (static::$webformEntities as $webform) {
      $webform->delete();
    }

    static::$webformEntities = [];
  }

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
   * @param string $template
   *   The title (or partial title) of the template to clone.
   * @param string $title
   *   The title for the new webform.
   *
   * @code
   *   Given I clone webform template "Contact" into "My contact form"
   *   Given a webform "My form" from template "Contact"
   * @endcode
   */
  #[Given('I clone webform template :template into :title')]
  public function webformCloneTemplate(string $template, string $title): void {
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

    static::$webformEntities[] = $clone;
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

    return $machine_name . '_' . random_int(1000, 9999);
  }

}
