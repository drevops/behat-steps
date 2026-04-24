<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Drupal\Field\FileHandler;
use DrevOps\BehatSteps\Drupal\Field\TextLongHandler;
use Drupal\Driver\DrupalDriver;

/**
 * Override Drupal Extension behaviors.
 *
 * - Automated entity deletion before creation to avoid duplicates.
 * - Improved user authentication handling for anonymous users.
 * - Custom field handlers registered with the active 'DrupalDriver' core to
 *   cover field types and stub-resolution patterns the upstream driver does
 *   not ship out of the box (see 'src/Drupal/Field').
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
   * Registers custom field handlers on the active driver core.
   *
   * The driver's 'Core::registerFieldHandler()' is the documented extension
   * point for project-specific handlers (see
   * 'vendor/drupal/drupal-driver/src/Drupal/Driver/Core/Field/README.md').
   * The 'BeforeScenario' hook fires once per scenario, before any entity
   * step runs, so registering on every scenario is safe and idempotent: the
   * registry is a flat field-type → class map and re-registering the same
   * class is a no-op.
   *
   * Skip with tag: '@behat-steps-skip:overrideRegisterFieldHandlers'.
   */
  #[BeforeScenario]
  public function overrideRegisterFieldHandlers(BeforeScenarioScope $scope): void {
    // @codeCoverageIgnoreStart
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    $driver = $this->getDrupal()?->getDriver();

    if (!$driver instanceof DrupalDriver) {
      return;
    }

    $core = $driver->getCore();
    $core->registerFieldHandler('text_long', TextLongHandler::class);
    $core->registerFieldHandler('file', FileHandler::class);
    $core->registerFieldHandler('image', FileHandler::class);
  }

  /**
   * {@inheritdoc}
   */
  public function createNodes(mixed $type, TableNode $table): void {
    $type = (string) $type;
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->contentDelete($type, $filtered_table);
    parent::createNodes($type, $table);
  }

  /**
   * {@inheritdoc}
   */
  public function createUsers(TableNode $table): void {
    // Delete entities before creating them.
    $this->userDelete($table);
    parent::createUsers($table);
  }

  /**
   * {@inheritdoc}
   */
  public function assertAuthenticatedByRole(mixed $role): void {
    $role = (string) $role;
    // Override parent assertion to allow using 'anonymous user' role without
    // actually creating a user with role. By default,
    // assertAuthenticatedByRole() will create a user with 'authenticated role'
    // even if 'anonymous user' role is provided.
    if ($role === 'anonymous user' || $role === 'anonymous') {
      // @codeCoverageIgnoreStart
      if (!empty($this->userManager->getCurrentUser())) {
        $this->logout();
      }
      // @codeCoverageIgnoreEnd
    }
    else {
      parent::assertAuthenticatedByRole($role);
    }
  }

}
