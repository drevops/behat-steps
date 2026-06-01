<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Drupal\redirect\Entity\Redirect;

/**
 * Manage Drupal redirect entities provided by the contrib `redirect` module.
 *
 * - Create one or more redirects from a table of source/destination/status.
 * - Delete redirects by source path.
 * - Automatically clean up created redirects after scenario completion.
 *
 * Requires the `redirect` contrib module to be installed and enabled in the
 * consumer project: add `drupal/redirect` to `composer.json` and enable the
 * module as part of the site's standard setup (e.g. in `core.extension.yml`).
 *
 * Skip processing with tag: `@behat-steps-skip:redirectAfterScenario`
 */
trait RedirectTrait {

  /**
   * Allowed HTTP status codes for redirects.
   *
   * @var int[]
   */
  protected static $redirectAllowedStatusCodes = [301, 302, 303, 307, 308];

  /**
   * Redirects created during a scenario.
   *
   * @var \Drupal\redirect\Entity\Redirect[]
   */
  protected $redirects = [];

  /**
   * Create one or more redirects.
   *
   * Provide redirect data in the following format:
   *
   * | from              | to                        | status_code |
   * | /old/about        | /about                    | 301         |
   * | /promo            | https://example.com/promo | 302         |
   * | /legacy/contact   | /contact                  |             |
   *
   * The `status_code` column is optional and defaults to `301` when omitted
   * or left blank. Allowed values: 301, 302, 303, 307, 308.
   *
   * Destinations may be internal paths (`/about`) or external URLs
   * (`https://example.com/promo`). Internal paths are stored as
   * `internal:/about` so the `redirect` module routes them correctly.
   *
   * @code
   * Given the following redirects exist:
   *   | from              | to                        | status_code |
   *   | /old/about        | /about                    | 301         |
   *   | /promo            | https://example.com/promo | 302         |
   *   | /legacy/contact   | /contact                  |             |
   * @endcode
   */
  #[Given('the following redirects exist:')]
  public function redirectCreate(TableNode $table): void {
    $this->redirectAssertModuleEnabled();

    foreach ($table->getHash() as $row) {
      $from = isset($row['from']) ? trim($row['from']) : '';
      $to = isset($row['to']) ? trim($row['to']) : '';

      if ($from === '') {
        throw new \RuntimeException('Each redirect row must define a non-empty "from" path.');
      }

      if ($to === '') {
        throw new \RuntimeException(sprintf('Redirect from "%s" is missing a non-empty "to" value.', $from));
      }

      $status_code = $this->redirectNormalizeStatusCode($row['status_code'] ?? NULL);

      $redirect = Redirect::create(['status_code' => $status_code]);
      $redirect->setSource($from);
      $redirect->setRedirect($to);
      $redirect->save();

      $this->redirects[] = $redirect;
    }
  }

  /**
   * Delete redirects by source path.
   *
   * Provide one source path per row. Rows that match no existing redirect are
   * silently skipped.
   *
   * @code
   * Given the following redirects do not exist:
   *   | /old/about      |
   *   | /legacy/contact |
   * @endcode
   */
  #[Given('the following redirects do not exist:')]
  public function redirectDelete(TableNode $table): void {
    $this->redirectAssertModuleEnabled();

    $storage = \Drupal::entityTypeManager()->getStorage('redirect');

    foreach ($table->getColumn(0) as $path) {
      $path = ltrim(trim((string) $path), '/');

      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('redirect_source.path', $path)
        ->execute();

      if (empty($ids)) {
        continue;
      }

      $entities = $storage->loadMultiple($ids);
      $storage->delete($entities);

      // Drop any cleanup references for the just-deleted redirects so the
      // after-scenario hook does not attempt to delete them again.
      $deleted_ids = array_map(intval(...), array_keys($entities));
      $this->redirects = array_values(array_filter(
        $this->redirects,
        fn(Redirect $redirect): bool => !in_array((int) $redirect->id(), $deleted_ids, TRUE),
      ));
    }
  }

  /**
   * Remove all created redirects after scenario run.
   */
  #[AfterScenario('@api')]
  public function redirectAfterScenario(AfterScenarioScope $scope): void {
    // @codeCoverageIgnoreStart
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    if (empty($this->redirects)) {
      return;
    }

    // @codeCoverageIgnoreStart
    if (!\Drupal::moduleHandler()->moduleExists('redirect')) {
      $this->redirects = [];

      return;
    }
    // @codeCoverageIgnoreEnd
    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $ids = array_filter(array_map(fn(Redirect $redirect): int => (int) $redirect->id(), $this->redirects));
    $entities = $ids !== [] ? $storage->loadMultiple($ids) : [];

    if (!empty($entities)) {
      $storage->delete($entities);
    }

    $this->redirects = [];
  }

  /**
   * Throw when the `redirect` module is not enabled.
   */
  protected function redirectAssertModuleEnabled(): void {
    // @codeCoverageIgnoreStart
    if (!\Drupal::moduleHandler()->moduleExists('redirect')) {
      throw new \RuntimeException('The "redirect" module is not enabled. Add "drupal/redirect" to the consumer project\'s composer.json and enable the module as part of the site setup.');
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Normalise the status code value from a table cell.
   *
   * @param string|null $value
   *   The raw value from the table cell, or NULL when the column is missing.
   *
   * @return int
   *   A valid HTTP redirect status code.
   */
  protected function redirectNormalizeStatusCode(?string $value): int {
    $value = $value === NULL ? '' : trim($value);

    if ($value === '') {
      return 301;
    }

    if (!ctype_digit($value)) {
      throw new \RuntimeException(sprintf('Invalid redirect status code "%s". Allowed values are: %s.', $value, implode(', ', static::$redirectAllowedStatusCodes)));
    }

    $status_code = (int) $value;

    if (!in_array($status_code, static::$redirectAllowedStatusCodes, TRUE)) {
      throw new \RuntimeException(sprintf('Invalid redirect status code "%d". Allowed values are: %s.', $status_code, implode(', ', static::$redirectAllowedStatusCodes)));
    }

    return $status_code;
  }

}
