<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;

/**
 * Override Drupal Extension behaviors.
 *
 * - Automated entity deletion before creation to avoid duplicates.
 * - Improved user authentication handling for anonymous users.
 *
 * Use with caution: depending on your version of Drupal Extension, PHP and
 * Composer, the step definition string (/^Given etc.../) may need to be defined
 * for these overrides. If you encounter errors about missing or duplicated
 * step definitions, do not include this trait and rather copy the contents of
 * this file into your feature context file and copy the step definition strings
 * from the Drupal Extension.
 */
trait OverrideTrait {

  /**
   * Force Drupal to bootstrap before any `@api` scenario runs.
   *
   * The 6.x driver bootstraps Drupal lazily inside `getDriver()`, so any
   * step method that calls `\Drupal::` directly (without going through
   * `getDriver()` first) hits a `ContainerNotInitializedException`. Many
   * trait step methods do exactly that. Calling `getDriver()` once here
   * primes the container for the rest of the scenario.
   *
   * Skip with: `@behat-steps-skip:overrideBootstrapDrupal`.
   */
  #[BeforeScenario('@api')]
  public function overrideBootstrapDrupal(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $this->getDriver();
  }

  /**
   * {@inheritdoc}
   */
  public function createNodes(mixed $type, TableNode $table): void {
    $type = (string) $type;
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // 6.x driver bootstraps Drupal lazily inside getDriver(); call it
    // before our pre-delete touches \Drupal::.
    $this->getDriver();
    // Delete entities before creating them.
    $this->contentDelete($type, $filtered_table);
    parent::createNodes($type, $table);
  }

  /**
   * {@inheritdoc}
   */
  public function createUsers(TableNode $table): void {
    // 6.x driver bootstraps Drupal lazily inside getDriver(); call it
    // before our pre-delete touches \Drupal::.
    $this->getDriver();
    // Delete entities before creating them.
    $this->userDelete($table);
    parent::createUsers($table);
  }

  /**
   * {@inheritdoc}
   */
  public function iAmLoggedInAsUserWithRole(string $role): void {
    // Override parent step to allow using 'anonymous user' role without
    // actually creating a user with role. By default,
    // iAmLoggedInAsUserWithRole() creates a user with 'authenticated role'
    // even if 'anonymous user' role is provided.
    if ($role === 'anonymous user' || $role === 'anonymous') {
      // @codeCoverageIgnoreStart
      if (!empty($this->userManager->getCurrentUser())) {
        $this->logout();
      }
      // @codeCoverageIgnoreEnd
    }
    else {
      parent::iAmLoggedInAsUserWithRole($role);
    }
  }

}
