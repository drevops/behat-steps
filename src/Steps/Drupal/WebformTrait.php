<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;

/**
 * Manage Drupal webforms.
 *
 * - Delete webforms matching a given title for test isolation.
 * - Clone webform templates into new webforms for scenario setup.
 * - Cloned webforms are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait WebformTrait {

  use EntityLifecycleTrait;

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
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $webforms = $this->webformLoadMultiple($title);

    foreach ($webforms as $webform) {
      $webform->delete();
    }
  }

  /**
   * Clone a webform template into a new webform with the given title.
   *
   * @param string $title
   *   The title for the new webform.
   * @param string $template
   *   The title (or partial title) of the template to clone.
   *
   * @code
   *   Given the webform "My contact form" exists from the template "Contact"
   * @endcode
   */
  #[Given('the webform :title exists from the template :template')]
  public function webformCloneTemplate(string $title, string $template): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $templates = $this->webformLoadTemplateMultiple($template);

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

    $this->entityLifecycleRegister($clone);
  }

  /**
   * Load all webform templates whose title contains the given string.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return array<string, \Drupal\webform\WebformInterface>
   *   The matching webform templates keyed by ID, or an empty array when none
   *   match.
   */
  public function webformLoadTemplateMultiple(string $title): array {
    $webforms = $this->webformLoadMultiple($title);

    return array_filter($webforms, static fn($webform): bool => $webform->isTemplate());
  }

  /**
   * Load all webforms whose title contains the given string.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return array<string, \Drupal\webform\WebformInterface>
   *   The matching webforms keyed by ID, or an empty array when none match.
   */
  public function webformLoadMultiple(string $title): array {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    // A webform change made via the admin UI in a separate process leaves
    // the config factory cache stale, so it is reset first.
    \Drupal::configFactory()->reset();

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::entityTypeManager();
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

    /** @var array<string, \Drupal\webform\WebformInterface> $webforms */
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
    $this->backendFor(CoreCapabilityInterface::class);

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

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function webformPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('webform'), 'the "webform" module from the "drupal/webform" package is enabled'),
    ];
  }

}
