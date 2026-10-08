<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Exception\AssertionException;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use Drupal\Component\Utility\UrlHelper;
use Drupal\redirect\Entity\Redirect;

/**
 * Manage Drupal redirect entities provided by the contrib `redirect` module.
 *
 * - Create 1 or more redirects from a table of source/destination/status.
 * - Delete redirects by source path.
 * - Assert that redirects do or do not exist for given source paths.
 * - Created redirects are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait RedirectTrait {

  use EntityLifecycleTrait;

  /**
   * Allowed HTTP status codes for redirects.
   *
   * @var int[]
   */
  protected static array $redirectAllowedStatusCodes = [301, 302, 303, 307, 308];

  /**
   * Create 1 or more redirects.
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
  public function redirectCreateMultiple(TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getHash() as $row) {
      if (trim($row['from'] ?? '') === '') {
        throw new \RuntimeException('Each redirect row must define a non-empty "from" path.');
      }

      if (trim($row['to'] ?? '') === '') {
        throw new \RuntimeException(sprintf('Redirect from "%s" is missing a non-empty "to" value.', trim($row['from'])));
      }

      $this->redirectCreate(trim($row['from']), trim($row['to']), $this->redirectNormalizeStatusCode($row['status_code'] ?? NULL));
    }
  }

  /**
   * Delete redirects by source path.
   *
   * Each row is 1 source path. Rows that match no existing redirect are
   * silently skipped.
   *
   * @code
   * Given the following redirects do not exist:
   *   | /old/about      |
   *   | /legacy/contact |
   * @endcode
   */
  #[Given('the following redirects do not exist:')]
  public function redirectDeleteMultiple(TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getColumn(0) as $path) {
      $this->redirectDelete($path);
    }
  }

  /**
   * Assert that 1 or more redirects exist.
   *
   * The `from` column is required. The `to` and `status_code` columns are
   * optional: when blank or omitted, only the source path is matched.
   *
   * When `to` is provided, internal paths (`/about`) are normalized to
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
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $missing = $this->redirectGetMissing($table->getHash());

    if ($missing !== []) {
      throw new AssertionException(sprintf('The following redirects should exist but were not found: %s.', implode(', ', $missing)));
    }
  }

  /**
   * Assert that no redirect exists for 1 or more source paths.
   *
   * Each row is 1 source path.
   *
   * @code
   * Then the following redirects should not exist:
   *   | /old/about      |
   *   | /legacy/contact |
   * @endcode
   */
  #[Then('the following redirects should not exist:')]
  public function redirectAssertNotExist(TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $present = $this->redirectGetPresent($table->getColumn(0));

    if ($present !== []) {
      throw new AssertionException(sprintf('The following redirects should not exist but were found: %s.', implode(', ', $present)));
    }
  }

  /**
   * Create a redirect.
   *
   * The redirect is removed after the scenario.
   *
   * @param string $from
   *   The source path.
   * @param string $to
   *   The destination, as an internal path or an external URL.
   * @param int $status_code
   *   The HTTP status code.
   *
   * @return \Drupal\redirect\Entity\Redirect
   *   The redirect.
   */
  public function redirectCreate(string $from, string $to, int $status_code = 301): Redirect {
    $redirect = Redirect::create(['status_code' => $status_code]);
    $redirect->setSource($from);
    $redirect->setRedirect($to);
    $redirect->save();

    $this->entityLifecycleRegister($redirect);

    return $redirect;
  }

  /**
   * Delete the redirects from a source path.
   *
   * @param string $from
   *   The source path.
   */
  public function redirectDelete(string $from): void {
    $ids = $this->redirectQueryIds($from);

    if ($ids === []) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('redirect');
    $storage->delete($storage->loadMultiple($ids));
  }

  /**
   * Check whether a redirect exists.
   *
   * @param string $from
   *   The source path.
   * @param string|null $to
   *   The destination, or NULL to match any destination.
   * @param int|null $status_code
   *   The HTTP status code, or NULL to match any status code.
   *
   * @return bool
   *   TRUE when a redirect matches, FALSE otherwise.
   */
  public function redirectExists(string $from, ?string $to = NULL, ?int $status_code = NULL): bool {
    return $this->redirectQueryIds($from, $to, $status_code) !== [];
  }

  /**
   * Query the IDs of the redirects matching a source path.
   *
   * @param string $from
   *   The source path.
   * @param string|null $to
   *   The destination, or NULL to match any destination.
   * @param int|null $status_code
   *   The HTTP status code, or NULL to match any status code.
   *
   * @return array<int|string, int|string>
   *   The redirect IDs.
   */
  protected function redirectQueryIds(string $from, ?string $to = NULL, ?int $status_code = NULL): array {
    $query = \Drupal::entityTypeManager()->getStorage('redirect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', $this->redirectNormalizeSource($from));

    if ($to !== NULL) {
      $query->condition('redirect_redirect.uri', $this->redirectNormalizeDestination($to));
    }

    if ($status_code !== NULL) {
      $query->condition('status_code', $status_code);
    }

    return $query->execute();
  }

  /**
   * Get the rows of a redirect table that match no redirect.
   *
   * @param array<int, array<string, string>> $rows
   *   The rows, each with a "from" path and optional "to" and "status_code"
   *   values. A blank optional value matches any.
   *
   * @return array<int, string>
   *   The unmatched rows, formatted for a failure message.
   *
   * @throws \RuntimeException
   *   When a row has no "from" path, or an invalid status code.
   */
  protected function redirectGetMissing(array $rows): array {
    $missing = [];

    foreach ($rows as $row) {
      $from = isset($row['from']) ? trim($row['from']) : '';

      if ($from === '') {
        throw new \RuntimeException('Each redirect row must define a non-empty "from" path.');
      }

      $to = isset($row['to']) ? trim($row['to']) : '';
      $status_code = isset($row['status_code']) ? trim($row['status_code']) : '';

      if (!$this->redirectExists($from, $to === '' ? NULL : $to, $status_code === '' ? NULL : $this->redirectNormalizeStatusCode($status_code))) {
        $missing[] = $this->redirectFormatRow($from, $to, $status_code);
      }
    }

    return $missing;
  }

  /**
   * Get the source paths that have a redirect.
   *
   * @param array<int, string> $paths
   *   The source paths.
   *
   * @return array<int, string>
   *   The quoted source paths with a redirect.
   */
  protected function redirectGetPresent(array $paths): array {
    $present = [];

    foreach ($paths as $path) {
      if ($this->redirectExists($path)) {
        $present[] = sprintf('"%s"', $path);
      }
    }

    return $present;
  }

  /**
   * Normalize the status code value from a table cell.
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
   * Normalize a source path the same way the `redirect` module stores it.
   */
  protected function redirectNormalizeSource(string $path): string {
    return ltrim(trim($path), '/');
  }

  /**
   * Normalize a destination URI the same way `Redirect::setRedirect()` does.
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

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function redirectPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('redirect'), 'the "redirect" module from the "drupal/redirect" package is enabled'),
    ];
  }

}
