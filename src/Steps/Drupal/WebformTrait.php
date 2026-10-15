<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Manage Drupal webforms.
 *
 * - Delete webforms matching a given title for test isolation.
 * - Clone webform templates into new webforms for scenario setup.
 * - Cloned webforms are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait WebformTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

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
   *
   * @actor:system
   */
  #[Given('the webform :title does not exist')]
  public function webformDelete(string $title): void {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($this->webformLoadMultiple($title) as $webform) {
      $backend->deleteEntity($webform);
    }
  }

  /**
   * Clone a webform template into a new webform with the given title.
   *
   * When several templates match, the newest is cloned: the template the
   * scenario created last, and otherwise the one with the last machine name
   * in natural order.
   *
   * @param string $title
   *   The title for the new webform.
   * @param string $template
   *   The title (or partial title) of the template to clone.
   *
   * @code
   *   Given the webform "My contact form" has been created from the template "Contact"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the webform :title has been created from the template :template')]
  public function webformCloneTemplate(string $title, string $template): void {
    $this->backendFor(ConfigEntityCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $source = $this->webformFindTemplateByTitle($template);

    if (!$source instanceof EntityStubInterface) {
      throw new \RuntimeException(sprintf('No webform template matching "%s" was found.', $template));
    }

    $this->webformCreateFromTemplate($title, $source);
  }

  /**
   * Create a webform as a copy of a template.
   *
   * The webform is removed after the scenario.
   *
   * @param string $title
   *   The title of the new webform.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $template
   *   The stub of the template to copy, carrying its 'id'.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the new webform, carrying its 'id'.
   */
  public function webformCreateFromTemplate(string $title, EntityStubInterface $template): EntityStubInterface {
    $created = $this->backendFor(ConfigEntityCapabilityInterface::class)->duplicateConfigEntity($template, [
      'id' => $this->webformMachineName($title),
      'title' => $title,
      'template' => FALSE,
    ]);

    $this->entityLifecycleRegister($created, 'id');

    return $created;
  }

  /**
   * Find the newest webform template whose title contains the given string.
   *
   * The template the scenario created last wins, and otherwise the one with
   * the last machine name in natural order.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the newest matching template, carrying its 'id', or NULL
   *   when none matches.
   */
  public function webformFindTemplateByTitle(string $title): ?EntityStubInterface {
    return $this->entityLifecycleFindNewest($this->webformLoadTemplateMultiple($title), 'id');
  }

  /**
   * Load all webform templates whose title contains the given string.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching template carrying its 'id', keyed by ID, or an
   *   empty array when none match.
   */
  public function webformLoadTemplateMultiple(string $title): array {
    return $this->webformQueryStubs($title, ['template' => TRUE]);
  }

  /**
   * Load all webforms whose title contains the given string.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching webform carrying its 'id', keyed by ID, or an empty
   *   array when none match.
   */
  public function webformLoadMultiple(string $title): array {
    return $this->webformQueryStubs($title);
  }

  /**
   * Load a stub per webform whose title contains a string and matching conditions.
   *
   * @param string $title
   *   The title string to search for (CONTAINS match).
   * @param array<string, mixed> $conditions
   *   Further conditions keyed by property name, as 'queryEntityIds()' takes
   *   them.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching webform carrying its 'id', keyed by ID, or an empty
   *   array when none match.
   */
  protected function webformQueryStubs(string $title, array $conditions = []): array {
    $this->backendFor(EntityLookupCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    // A webform changed through the admin UI in another process stays as this
    // process loaded it until this process resets its caches.
    $this->getBackendRegistry()->getResolvedBackendFor(CacheCapabilityInterface::class)?->cacheClearStatic();

    return $this->queryEntityStubs('webform', 'id', ['title' => ['CONTAINS', $title]] + $conditions);
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

    $attempts = 0;

    do {
      $candidate = $machine_name . '_' . random_int(1000, 9999);
      $attempts++;

      if ($attempts > 50) {
        throw new \RuntimeException(sprintf('Unable to generate a unique webform machine name for "%s" after 50 attempts.', $title));
      }
    } while ($this->queryEntityIds('webform', ['id' => $candidate]) !== []);

    return $candidate;
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function webformPrerequisites(): array {
    return [
      Prerequisite::capability(ConfigEntityCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('webform'), 'the "webform" module from the "drupal/webform" package is enabled'),
    ];
  }

}
