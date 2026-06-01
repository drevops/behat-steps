<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Drupal\Component\Utility\UrlHelper;
use Drupal\redirect\Entity\Redirect;

/**
 * Manage Drupal redirect entities provided by the contrib `redirect` module.
 *
 * - Create one or more redirects from a table of source/destination/status.
 * - Delete redirects by source path.
 * - Assert that redirects do or do not exist for given source paths.
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
      $source = $this->redirectNormalizeSource($path);

      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('redirect_source.path', $source)
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
   * Assert that one or more redirects exist.
   *
   * Provide redirect data in the following format:
   *
   * | from              | to                        | status_code |
   * | /old/about        | /about                    | 301         |
   * | /promo            | https://example.com/promo |             |
   * | /legacy/contact   |                           |             |
   *
   * The `from` column is required. The `to` and `status_code` columns are
   * optional: when blank or omitted, only the source path is matched. When
   * `to` is provided, internal paths (`/about`) are normalised to
   * `internal:/about` to match the storage format. When `status_code` is
   * provided, it is validated against the allowed set (301, 302, 303, 307,
   * 308).
   *
   * @code
   * Then the following redirects should exist:
   *   | from              | to                        | status_code |
   *   | /old/about        | /about                    | 301         |
   *   | /promo            | https://example.com/promo |             |
   *   | /legacy/contact   |                           |             |
   * @endcode
   */
  #[Then('the following redirects should exist:')]
  public function redirectAssertExist(TableNode $table): void {
    $this->redirectAssertModuleEnabled();

    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $missing = [];

    foreach ($table->getHash() as $row) {
      $from = isset($row['from']) ? trim($row['from']) : '';

      if ($from === '') {
        throw new \RuntimeException('Each redirect row must define a non-empty "from" path.');
      }

      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('redirect_source.path', $this->redirectNormalizeSource($from));

      $to = isset($row['to']) ? trim($row['to']) : '';
      if ($to !== '') {
        $query->condition('redirect_redirect.uri', $this->redirectNormalizeDestination($to));
      }

      $status_code_raw = isset($row['status_code']) ? trim($row['status_code']) : '';
      if ($status_code_raw !== '') {
        $query->condition('status_code', $this->redirectNormalizeStatusCode($status_code_raw));
      }

      $ids = $query->execute();

      if (empty($ids)) {
        $missing[] = $this->redirectFormatRow($from, $to, $status_code_raw);
      }
    }

    if ($missing !== []) {
      throw new \Exception(sprintf('The following redirects should exist but were not found: %s.', implode(', ', $missing)));
    }
  }

  /**
   * Assert that no redirect exists for one or more source paths.
   *
   * Provide one source path per row.
   *
   * @code
   * Then the following redirects should not exist:
   *   | /old/about      |
   *   | /legacy/contact |
   * @endcode
   */
  #[Then('the following redirects should not exist:')]
  public function redirectAssertNotExist(TableNode $table): void {
    $this->redirectAssertModuleEnabled();

    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $present = [];

    foreach ($table->getColumn(0) as $path) {
      $source = $this->redirectNormalizeSource($path);

      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('redirect_source.path', $source)
        ->execute();

      if (!empty($ids)) {
        $present[] = sprintf('"%s"', $path);
      }
    }

    if ($present !== []) {
      throw new \Exception(sprintf('The following redirects should not exist but were found: %s.', implode(', ', $present)));
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

  /**
   * Normalise a source path the same way the `redirect` module stores it.
   */
  protected function redirectNormalizeSource(string $path): string {
    return ltrim(trim($path), '/');
  }

  /**
   * Normalise a destination URI the same way `Redirect::setRedirect()` does.
   *
   * Internal paths gain an `internal:/` prefix; external `http(s)://` URLs
   * pass through unchanged. Values that already begin with `internal:` are
   * returned as-is to avoid double-prefixing.
   */
  protected function redirectNormalizeDestination(string $uri): string {
    if (str_starts_with($uri, 'internal:')) {
      return $uri;
    }

    return UrlHelper::isExternal($uri) ? $uri : 'internal:/' . ltrim($uri, '/');
  }

  /**
   * Format a redirect row for inclusion in an assertion failure message.
   */
  protected function redirectFormatRow(string $from, string $to, string $status_code): string {
    $parts = [sprintf('from="%s"', $from)];

    if ($to !== '') {
      $parts[] = sprintf('to="%s"', $to);
    }

    if ($status_code !== '') {
      $parts[] = sprintf('status_code=%s', $status_code);
    }

    return sprintf('{%s}', implode(', ', $parts));
  }

}
