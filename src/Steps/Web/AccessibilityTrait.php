<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\AfterSuite;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Tag;
use DrevOps\BehatSteps\Steps\Helper\Web\LastStepTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Assess accessibility of rendered pages.
 *
 * Supported tags:
 * - `@accessibility`                          Auto-mode, default threshold.
 * - `@accessibility:critical`                 Auto-mode, fail only on critical impact.
 * - `@accessibility:serious`                  Auto-mode, fail on critical or serious.
 * - `@accessibility:moderate`                 Auto-mode, fail on critical / serious / moderate.
 * - `@accessibility:minor`                    Auto-mode, fail on any impact.
 * - `@accessibility:any`                      Auto-mode, fail on any impact (alias).
 * - `@accessibility:warning`                  Auto-mode, never fail (advisory).
 * - `@accessibility:strict`                   Also fail on "incomplete" findings.
 * - `@behat-steps-skip:AccessibilityTrait`    Opt the scenario or feature out entirely.
 *
 * Tool-agnostic. Any engine that runs inside the existing Mink session can
 * be plugged in by overriding `accessibilityRunEngine()` and
 * `accessibilityNormalizeResults()`. The first performs the assessment and
 * returns raw results; the second remaps raw output into the canonical shape
 * the rest of the trait reads.
 *
 * Reporting. Each scenario writes its own HTML and JUnit report. After the
 * whole suite, a single cross-page `accessibility_report_<timestamp>.html`
 * (timestamp `YYYYMMDD_HHMMSS`) is written to the same directory,
 * de-duplicating every assessed page and rolling violations up by rule.
 *
 * 1 file is written per run, so a run never overwrites a previous one. The
 * aggregate accumulates in process-global state, so under parallel Behat each
 * process writes its own report.
 *
 * Console output. A 1-line per-page summary can be printed to the console
 * as pages are assessed. Printing is off by default; set the
 * `BEHAT_ACCESSIBILITY_PRINT` environment variable to a non-empty value other
 * than `0`, or override `accessibilityGetPrintCli()`, to enable it.
 *
 * @title Accessibility
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait AccessibilityTrait {

  use LastStepTrait;
  use StringTrait;

  /**
   * Canonical impact identifiers, ordered from most severe to least.
   *
   * Use these constants when an `accessibilityNormalizeResults()` override
   * maps an engine's native severity vocabulary to the trait's canonical
   * shape. Threshold tags (`@accessibility:critical`, `@accessibility:serious`
   * etc.) and the gate-filter logic both compare against these values.
   */
  public const string ACCESSIBILITY_IMPACT_CRITICAL = 'critical';

  public const string ACCESSIBILITY_IMPACT_SERIOUS = 'serious';

  public const string ACCESSIBILITY_IMPACT_MODERATE = 'moderate';

  public const string ACCESSIBILITY_IMPACT_MINOR = 'minor';

  /**
   * The default tag that assesses every page, with an optional gate variant.
   */
  protected const string ACCESSIBILITY_TAG = 'accessibility';

  /**
   * In-memory cache for the engine JavaScript source, fetched once per process.
   */
  protected static ?string $accessibilityCachedJs = NULL;

  /**
   * Working directory captured before a backend bootstrap can change it.
   *
   * The default report directory is resolved against this value rather than
   * a live `getcwd()` call.
   *
   * A backend that boots a site in this process can move the working
   * directory to the site's root. A live `getcwd()` would then resolve the
   * report directory away from the rest of the run's paths.
   */
  protected static ?string $accessibilityBaseDir = NULL;

  /**
   * Normalized per-scenario results accumulated across the whole suite.
   *
   * Populated as each scenario finalizes and consumed once by the static
   * `@AfterSuite` renderer. URLs are stored already formatted for display.
   *
   * @var array<int, array{feature: string, scenario: string, threshold: string, fail_on_incomplete: bool, impacts: array<int, string>, results: array<int, array{url: string, rules: string, result: array<string, mixed>}>, skipped: array<int, array{url: string, reason: string}>}>
   */
  protected static array $accessibilityAggregate = [];

  /**
   * Report directory captured in the instance phase for the static renderer.
   *
   * The `@AfterSuite` hook is static and cannot call
   * `accessibilityGetReportDir()` (an instance method, overridable per
   * consumer). The resolved directory is captured while a scenario
   * finalizes and reused when the aggregate is written.
   */
  protected static ?string $accessibilityAggregateReportDir = NULL;

  /**
   * Normalized results collected during the current scenario.
   *
   * @var array<int, array{url: string, rules: string, result: array<string, mixed>}>
   */
  protected array $accessibilityResults = [];

  /**
   * Feature title captured at scenario start for report metadata.
   */
  protected string $accessibilityFeatureName = '';

  /**
   * Scenario title captured at scenario start for report metadata.
   */
  protected string $accessibilityScenarioName = '';

  /**
   * Whether automatic mode (per-step assessment) is enabled.
   */
  protected bool $accessibilityAutoMode = FALSE;

  /**
   * URL of the last page checked in automatic mode to avoid duplicate runs.
   */
  protected string $accessibilityLastCheckedUrl = '';

  /**
   * Per-scenario threshold override resolved from tags.
   */
  protected ?string $accessibilityScenarioThreshold = NULL;

  /**
   * Per-scenario incomplete-fail override resolved from tags.
   */
  protected ?bool $accessibilityScenarioFailOnIncomplete = NULL;

  /**
   * Whether the scenario is opted out of accessibility processing.
   */
  protected bool $accessibilitySkip = FALSE;

  /**
   * Whether the automatic gate has already been enforced.
   */
  protected bool $accessibilityGated = FALSE;

  /**
   * Pages a sweep could not visit or assess during the current scenario.
   *
   * @var array<int, array{url: string, reason: string}>
   */
  protected array $accessibilitySkippedPages = [];

  /**
   * Capture the working directory, then clear the suite-level aggregate.
   */
  #[BeforeSuite]
  public static function accessibilityBeforeSuite(BeforeSuiteScope $scope): void {
    static::accessibilityCaptureBaseDir();
    static::accessibilityAggregateReset();
  }

  /**
   * Initialize accessibility state for the scenario.
   */
  #[BeforeScenario]
  public function accessibilityBeforeScenario(BeforeScenarioScope $scope): void {
    $this->accessibilityResults = [];
    $this->accessibilitySkippedPages = [];
    $this->accessibilityAutoMode = FALSE;
    $this->accessibilityLastCheckedUrl = '';
    $this->accessibilityScenarioThreshold = NULL;
    $this->accessibilityScenarioFailOnIncomplete = NULL;
    $this->accessibilityGated = FALSE;

    $this->accessibilitySkip = $this->skipTag(__TRAIT__, $scope);

    if ($this->accessibilitySkip) {
      return;
    }

    $this->lastStepSetLine($scope);

    $this->accessibilityFeatureName = $scope->getFeature()->getTitle() ?? 'feature';
    $this->accessibilityScenarioName = $scope->getScenario()->getTitle() ?? 'scenario';

    $this->accessibilityResolveTags($scope);
  }

  /**
   * Run the engine after each step when in automatic mode.
   *
   * Behat composes a step teardown into that step's result, so a gate failure
   * raised here marks the scenario as failed for the rerun cache. Gating on
   * the last step rather than on every step puts every page the scenario
   * visited in the report before the gate is applied.
   */
  #[AfterStep]
  public function accessibilityAfterStep(AfterStepScope $scope): void {
    if ($this->accessibilitySkip) {
      return;
    }

    if (!$this->accessibilityAutoMode) {
      return;
    }

    try {
      $session = $this->getSession();

      if (!$session->isStarted()) {
        return;
      }

      $url = $session->getCurrentUrl();
    }
    catch (\Throwable) {
      return;
    }

    if (!in_array($url, [...static::accessibilityGetBlankUrls(), $this->accessibilityLastCheckedUrl], TRUE)) {
      $this->accessibilityAssess($this->accessibilityGetDefaultRules());
    }

    // A failed step has already failed the scenario, so the gate is left to
    // accessibilityAfterScenario(), which still reports this page's
    // violations. The gate runs whether or not this step assessed a new page,
    // because a last step that does not navigate still ends the scenario.
    if (!$scope->getTestResult()->isPassed() || !$this->lastStepReached($scope)) {
      return;
    }

    $this->accessibilityGated = TRUE;
    $this->accessibilityEnforceGate();
  }

  /**
   * Write the scenario reports, feed the suite aggregate, then gate if needed.
   *
   * A failing step skips every step after it, including the one that applies
   * the gate, so the gate is applied here instead. The scenario has already
   * failed by then, so it cannot mask a passing scenario from the rerun
   * cache.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If a violation at or above the threshold was collected.
   */
  #[AfterScenario]
  public function accessibilityAfterScenario(AfterScenarioScope $scope): void {
    if ($this->accessibilitySkip) {
      return;
    }

    if ($this->accessibilityResults === [] && $this->accessibilitySkippedPages === []) {
      return;
    }

    $directory = $this->accessibilityGetReportDir();

    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    $slug = $this->stringSlug($this->accessibilityFeatureName) . '__' . $this->stringSlug($this->accessibilityScenarioName);
    file_put_contents($directory . '/' . $slug . '.html', $this->accessibilityRenderHtml());
    file_put_contents($directory . '/junit-' . $slug . '.xml', $this->accessibilityRenderJunit());

    $this->accessibilityAggregateCapture($directory);

    if (!$this->accessibilityAutoMode || $this->accessibilityGated || $scope->getTestResult()->isPassed()) {
      return;
    }

    $this->accessibilityEnforceGate();
  }

  /**
   * Render the single cross-page report after the whole suite has run.
   */
  #[AfterSuite]
  public static function accessibilityAfterSuite(AfterSuiteScope $scope): void {
    static::accessibilityWriteAggregateReport();
  }

  /**
   * Assess the accessibility of each page in a table.
   *
   * Every page is assessed before any check runs, and a page that can't be
   * visited or assessed is reported as skipped. In automatic mode the
   * scenario's gate decides the outcome; otherwise the failure threshold is
   * applied to every assessed page at the end of the step.
   *
   * @code
   * When I assess the accessibility of the following pages:
   *   | path     |
   *   | /        |
   *   | /about   |
   *   | /contact |
   * @endcode
   *
   * @actor:person
   */
  #[When('I assess the accessibility of the following pages:')]
  public function accessibilityAssessPagesInTable(TableNode $table): void {
    $this->accessibilityAssessPages($this->accessibilityParsePaths($table), $this->accessibilityGetDefaultRules());
  }

  /**
   * Assess the accessibility of each page in a table for given tags.
   *
   * @code
   * When I assess the accessibility of the following pages for the tags "wcag2a":
   *   | path   |
   *   | /      |
   *   | /about |
   * @endcode
   *
   * @actor:person
   */
  #[When('I assess the accessibility of the following pages for the tags :tags:')]
  public function accessibilityAssessPagesInTableForTags(string $tags, TableNode $table): void {
    $this->accessibilityAssessPages($this->accessibilityParsePaths($table), $tags);
  }

  /**
   * Assess the accessibility of the pages in the configured sitemap.
   *
   * The sitemap path, the path depth and the page limit come from the
   * `sitemap_path`, `sitemap_max_depth` and `sitemap_limit` options.
   *
   * @code
   * When I assess the accessibility of the pages in the sitemap
   * @endcode
   *
   * @actor:person
   */
  #[When('I assess the accessibility of the pages in the sitemap')]
  public function accessibilityAssessPagesInDefaultSitemap(): void {
    $paths = $this->accessibilityGetSitemapPaths($this->accessibilityGetSitemapPath(), $this->accessibilityGetSitemapMaxDepth(), $this->accessibilityGetSitemapLimit());

    $this->accessibilityAssessPages($paths, $this->accessibilityGetDefaultRules());
  }

  /**
   * Assess the accessibility of the pages in a sitemap.
   *
   * A sitemap index is followed to every sitemap it lists. Each page's path
   * is resolved against the base URL, whatever host the sitemap names.
   *
   * @code
   * When I assess the accessibility of the pages in the sitemap "/sitemap.xml"
   * @endcode
   *
   * @actor:person
   */
  #[When('I assess the accessibility of the pages in the sitemap :path')]
  public function accessibilityAssessPagesInSitemap(string $path): void {
    $paths = $this->accessibilityGetSitemapPaths($path, $this->accessibilityGetSitemapMaxDepth(), $this->accessibilityGetSitemapLimit());

    $this->accessibilityAssessPages($paths, $this->accessibilityGetDefaultRules());
  }

  /**
   * Assess the accessibility of the pages in a sitemap up to a path depth.
   *
   * The path `/` has a depth of 0 and `/about` a depth of 1.
   *
   * @code
   * When I assess the accessibility of the pages in the sitemap "/sitemap.xml" with a path depth of at most 1
   * @endcode
   *
   * @actor:person
   */
  #[When('I assess the accessibility of the pages in the sitemap :path with a path depth of at most :depth')]
  public function accessibilityAssessPagesInSitemapWithDepth(string $path, string $depth): void {
    $max_depth = $this->stringParseInteger($depth, 'depth', 0);
    $paths = $this->accessibilityGetSitemapPaths($path, $max_depth, $this->accessibilityGetSitemapLimit());

    $this->accessibilityAssessPages($paths, $this->accessibilityGetDefaultRules());
  }

  /**
   * Assess the accessibility of a number of pages in a sitemap.
   *
   * The limit applies after the depth filter, in sitemap order.
   *
   * @code
   * When I assess the accessibility of the pages in the sitemap "/sitemap.xml" with a path depth of at most 2 and a limit of 50 pages
   * @endcode
   *
   * @actor:person
   */
  #[When('I assess the accessibility of the pages in the sitemap :path with a path depth of at most :depth and a limit of :count page(s)')]
  public function accessibilityAssessPagesInSitemapWithDepthWithLimit(string $path, string $depth, string $count): void {
    $max_depth = $this->stringParseInteger($depth, 'depth', 0);
    $limit = $this->stringParseInteger($count, 'count', 1);
    $paths = $this->accessibilityGetSitemapPaths($path, $max_depth, $limit);

    $this->accessibilityAssessPages($paths, $this->accessibilityGetDefaultRules());
  }

  /**
   * Assert that the current page passes accessibility checks.
   *
   * @code
   * Then the page should pass accessibility checks
   * @endcode
   *
   * @actor:system
   */
  #[Then('the page should pass accessibility checks')]
  public function accessibilityAssertCurrentPagePasses(): void {
    $rules = $this->accessibilityGetDefaultRules();

    $this->accessibilityAssertResultPasses($this->accessibilityAssess($rules), $rules);
  }

  /**
   * Assert that the current page passes accessibility checks for given tags.
   *
   * @code
   * Then the page should pass accessibility checks for the tags "wcag2a"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the page should pass accessibility checks for the tags :tags')]
  public function accessibilityAssertCurrentPagePassesForTags(string $tags): void {
    $this->accessibilityAssertResultPasses($this->accessibilityAssess($tags), $tags);
  }

  /**
   * Assert that an assessment result passes the effective failure threshold.
   *
   * Incomplete findings fail the assertion only when the scenario fails on
   * incomplete findings, through the "strict" tag or the configuration.
   *
   * @param array<string, mixed> $result
   *   The normalized result, as returned by accessibilityAssess().
   * @param string $rules
   *   The rules the result was assessed against.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the result holds a violation at or above the threshold, or an
   *   incomplete finding the scenario fails on.
   */
  public function accessibilityAssertResultPasses(array $result, string $rules): void {
    $findings = $this->accessibilityFilterFindings($result);

    if ($findings['violations'] === [] && $findings['incomplete'] === []) {
      return;
    }

    throw new ExpectationException(
      $this->accessibilityFormatGateMessage(
        $this->getSession()->getCurrentUrl(),
        $rules,
        $this->accessibilityEffectiveThreshold(),
        $this->accessibilityEffectiveFailOnIncomplete(),
        $findings['violations'],
        $findings['incomplete']
      ),
      $this->getSession()->getDriver()
    );
  }

  /**
   * Assert that every result of a sweep passes the effective threshold.
   *
   * @param array<int, array{url: string, rules: string, result: array<string, mixed>}> $results
   *   The results the sweep assessed.
   * @param array<int, array{url: string, reason: string}> $skipped
   *   The pages the sweep skipped, listed in the failure message.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When a result holds a violation at or above the threshold, or an
   *   incomplete finding the scenario fails on.
   */
  protected function accessibilityAssertResultsPass(array $results, array $skipped): void {
    $messages = [];

    foreach ($results as $result) {
      $findings = $this->accessibilityFilterFindings($result['result']);

      if ($findings['violations'] === [] && $findings['incomplete'] === []) {
        continue;
      }

      $messages[] = $this->accessibilityFormatGateMessage($result['url'], $result['rules'], $this->accessibilityEffectiveThreshold(), $this->accessibilityEffectiveFailOnIncomplete(), $findings['violations'], $findings['incomplete']);
    }

    if ($messages === []) {
      return;
    }

    if ($skipped !== []) {
      $messages[] = $this->accessibilityFormatSkippedPages($skipped);
    }

    throw new ExpectationException(implode(PHP_EOL, $messages), $this->getSession()->getDriver());
  }

  /**
   * Fail the scenario when collected results breach the automatic gate.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If a violation at or above the threshold was collected.
   */
  protected function accessibilityEnforceGate(): void {
    $messages = [];

    foreach ($this->accessibilityResults as $result) {
      $display_url = $this->accessibilityFormatUrl((string) $result['url']);
      $findings = $this->accessibilityFilterFindings($result['result']);

      foreach ($findings['violations'] as $violation) {
        $messages[] = sprintf('  violation [%s] %s on %s', $violation['impact'] ?? 'unknown', $violation['id'] ?? '', $display_url);
      }

      foreach ($findings['incomplete'] as $issue) {
        $messages[] = sprintf('  incomplete [%s] %s on %s', $issue['impact'] ?? 'unknown', $issue['id'] ?? '', $display_url);
      }
    }

    if ($messages === []) {
      return;
    }

    if ($this->accessibilitySkippedPages !== []) {
      $messages[] = $this->accessibilityFormatSkippedPages($this->accessibilitySkippedPages);
    }

    $message = sprintf('Auto accessibility gate failed (threshold: %s, fail_on_incomplete: %s):' . PHP_EOL . '%s', $this->accessibilityEffectiveThreshold(), $this->accessibilityEffectiveFailOnIncomplete() ? 'yes' : 'no', implode(PHP_EOL, $messages));

    throw new ExpectationException($message, $this->getSession()->getDriver());
  }

  /**
   * Keep the findings of a result that fail the current scenario.
   *
   * @param array<string, mixed> $result
   *   A normalized result, as returned by accessibilityAssess().
   *
   * @return array{violations: array<int, array<string, mixed>>, incomplete: array<int, array<string, mixed>>}
   *   The violations at or above the effective threshold, and the incomplete
   *   findings when the scenario fails on them.
   */
  protected function accessibilityFilterFindings(array $result): array {
    return [
      'violations' => $this->accessibilityFilterViolations($result['violations'] ?? [], $this->accessibilityEffectiveThreshold()),
      'incomplete' => $this->accessibilityEffectiveFailOnIncomplete() ? array_values($result['incomplete'] ?? []) : [],
    ];
  }

  /**
   * Format the pages a sweep skipped, 1 line per page.
   *
   * @param array<int, array{url: string, reason: string}> $skipped
   *   The skipped pages, with the reason each could not be assessed.
   */
  protected function accessibilityFormatSkippedPages(array $skipped): string {
    $lines = [];

    foreach ($skipped as $page) {
      $lines[] = sprintf('  skipped "%s": %s', $this->accessibilityFormatUrl($page['url']), $page['reason']);
    }

    return implode(PHP_EOL, $lines);
  }

  /**
   * Return the JavaScript source to inject into the page.
   *
   * Default: fetched once per process from accessibilityGetCdnUrl(). Override
   * to return the engine script from a vendored package or asset path.
   *
   * A read is bounded by accessibilityGetFetchTimeout() and retried up to
   * accessibilityGetFetchAttempts() times. A stalled or throttled source
   * blocks for at most the timeout per attempt, not until PHP's own
   * default_socket_timeout expires.
   */
  public function accessibilityGetJs(): string {
    if (self::$accessibilityCachedJs !== NULL) {
      return self::$accessibilityCachedJs;
    }

    $url = $this->accessibilityGetCdnUrl();
    $timeout = $this->accessibilityGetFetchTimeout();
    $attempts = max(1, $this->accessibilityGetFetchAttempts());
    $content = FALSE;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
      $content = $this->accessibilityFetchJs($url, $timeout);

      if ($content !== FALSE && $content !== '') {
        break;
      }

      if ($attempt < $attempts) {
        usleep($attempt * 500000);
      }
    }

    if ($content === FALSE || $content === '') {
      throw new \RuntimeException(sprintf('Failed to fetch accessibility engine from %s after %d attempt(s) with a %d second timeout.', $url, $attempts, $timeout));
    }

    self::$accessibilityCachedJs = $content;

    return $content;
  }

  /**
   * Read the engine source once from the given location.
   *
   * Default: a single read bounded by the given timeout, returning FALSE
   * when the read fails. An HTTP or HTTPS location is fetched through the
   * bare client, which carries no scenario state; any other location is read
   * as a local file.
   *
   * Override to fetch through a different HTTP client; accessibilityGetJs()
   * supplies the retries around it.
   *
   * @param string $url
   *   Location the engine source is read from.
   * @param int $timeout
   *   Timeout, in seconds, for this read.
   *
   * @return string|false
   *   The engine source, or FALSE when the read fails.
   */
  public function accessibilityFetchJs(string $url, int $timeout): string|false {
    if (preg_match('#^https?://#i', $url) !== 1) {
      return @file_get_contents($url);
    }

    $browser = $this->httpBareClient(['timeout' => $timeout]);

    try {
      $browser->request('GET', $url);
    }
    catch (TransportExceptionInterface) {
      return FALSE;
    }

    $response = $browser->getInternalResponse();

    return $response->getStatusCode() < 400 ? $response->getContent() : FALSE;
  }

  /**
   * Return the per-attempt timeout, in seconds, for the engine fetch.
   *
   * Default: 10 seconds. Override to suit a slower source.
   */
  public function accessibilityGetFetchTimeout(): int {
    return $this->getOptionInt('accessibility', 'fetch_timeout');
  }

  /**
   * Return how many times the engine fetch is attempted before failing.
   *
   * Default: 3.
   */
  public function accessibilityGetFetchAttempts(): int {
    return $this->getOptionInt('accessibility', 'fetch_attempts');
  }

  /**
   * Return the URL used by the default accessibilityGetJs() implementation.
   *
   * Default: a pinned engine script from a public CDN. Override to point at
   * a different version, a private mirror, or a local asset.
   */
  public function accessibilityGetCdnUrl(): string {
    return $this->getOptionString('accessibility', 'cdn_url');
  }

  /**
   * Return the absolute directory used to write per-scenario reports.
   *
   * A relative `report_dir` is resolved against the directory the run was
   * launched from. That base is captured at `@BeforeSuite`, so it is stable
   * even after a backend bootstrap moves the working directory.
   *
   * When the suite hook has not run, the live working directory is used.
   */
  public function accessibilityGetReportDir(): string {
    $directory = $this->getOptionString('accessibility', 'report_dir');

    if (str_starts_with($directory, '/')) {
      return $directory;
    }

    $base = self::$accessibilityBaseDir ?? (getcwd() ?: '.');

    return $base . '/' . $directory;
  }

  /**
   * Capture the working directory once, before any scenario can chdir().
   */
  protected static function accessibilityCaptureBaseDir(): void {
    if (self::$accessibilityBaseDir === NULL) {
      $cwd = getcwd();

      if ($cwd !== FALSE) {
        self::$accessibilityBaseDir = $cwd;
      }
    }
  }

  /**
   * Return the base tag name that enables automatic mode (no `@` prefix).
   *
   * The trait recognizes this exact tag plus value variants
   * (`<tag>:critical`, `<tag>:serious`, `<tag>:moderate`, `<tag>:minor`,
   * `<tag>:warning`, `<tag>:strict`, `<tag>:any`) for per-scenario gate
   * configuration.
   *
   * Default: `accessibility`. Override to shorten.
   */
  public function accessibilityGetAutoTag(): string {
    return $this->getOptionString('accessibility', 'auto_tag');
  }

  /**
   * Return the default rule identifier passed to the engine.
   *
   * Default: the WCAG 2.0 A and AA tag set. Override to use a different
   * rule identifier expected by the engine in use.
   */
  public function accessibilityGetDefaultRules(): string {
    return $this->getOptionString('accessibility', 'default_rules');
  }

  /**
   * Return the sitemap the sitemap step without a path reads.
   *
   * Default: `/sitemap.xml`.
   */
  public function accessibilityGetSitemapPath(): string {
    return $this->getOptionString('accessibility', 'sitemap_path');
  }

  /**
   * Return the deepest path depth the sitemap steps assess by default.
   *
   * Default: NULL, so pages at every depth are assessed. A negative option
   * value maps to NULL.
   */
  public function accessibilityGetSitemapMaxDepth(): ?int {
    $depth = $this->getOptionInt('accessibility', 'sitemap_max_depth');

    return $depth < 0 ? NULL : $depth;
  }

  /**
   * Return the most pages the sitemap steps assess by default.
   *
   * Default: NULL, so every page is assessed. An option value below 1 maps
   * to NULL.
   */
  public function accessibilityGetSitemapLimit(): ?int {
    $limit = $this->getOptionInt('accessibility', 'sitemap_limit');

    return $limit < 1 ? NULL : $limit;
  }

  /**
   * Return the default failure threshold.
   *
   * One of `any` (fail on any violation), `never` (advisory only), or an
   * impact level from accessibilityGetImpacts(). Default: `any`.
   */
  public function accessibilityGetFailureThreshold(): string {
    return $this->getOptionString('accessibility', 'failure_threshold');
  }

  /**
   * Return TRUE if "incomplete" findings should fail the gate by default.
   *
   * Default: FALSE (incomplete findings are reported but do not fail).
   */
  public function accessibilityGetFailOnIncomplete(): bool {
    return $this->getOptionBool('accessibility', 'fail_on_incomplete');
  }

  /**
   * Return TRUE to print a 1-line per-page summary to the console.
   *
   * Default: enabled only when the `BEHAT_ACCESSIBILITY_PRINT` environment
   * variable is set to a non-empty value other than `0`. Override to
   * hardcode either behavior.
   */
  public function accessibilityGetPrintCli(): bool {
    $value = getenv('BEHAT_ACCESSIBILITY_PRINT');

    return !in_array($value, [FALSE, '', '0'], TRUE);
  }

  /**
   * Return the canonical impact levels in descending severity order.
   *
   * Default: the 4 `ACCESSIBILITY_IMPACT_*` constants on this trait.
   * Engines with a different severity vocabulary map to these constants
   * inside `accessibilityNormalizeResults()`.
   *
   * @return array<int, string>
   *   Impact identifiers ordered from most severe to least.
   */
  public function accessibilityGetImpacts(): array {
    return static::accessibilityGetDefaultImpacts();
  }

  /**
   * Return the impact levels in descending severity order, statically.
   *
   * @return array<int, string>
   *   Impact identifiers ordered from most severe to least.
   */
  protected static function accessibilityGetDefaultImpacts(): array {
    return [
      self::ACCESSIBILITY_IMPACT_CRITICAL,
      self::ACCESSIBILITY_IMPACT_SERIOUS,
      self::ACCESSIBILITY_IMPACT_MODERATE,
      self::ACCESSIBILITY_IMPACT_MINOR,
    ];
  }

  /**
   * Execute the engine against the current page and return raw results.
   *
   * Default: injects `accessibilityGetJs()`, runs the engine with the
   * given rule identifier, returns the engine's native output. Override
   * to call a different engine.
   *
   * The return value is passed to `accessibilityNormalizeResults()` before
   * any other trait method reads it, so the raw shape does not have to match
   * the canonical shape.
   *
   * @param string $rules
   *   Engine-specific rule identifier.
   *
   * @return array<string, mixed>
   *   Raw, engine-specific result array.
   */
  public function accessibilityRunEngine(string $rules): array {
    $session = $this->getSession();
    $driver = $session->getDriver();
    $driver->executeScript($this->accessibilityGetJs());

    $tag_list = json_encode($this->stringSplitCommaSeparated($rules));
    $driver->executeScript(sprintf(
      'window.__accessibilityResults = null; axe.run(document, { runOnly: { type: "tag", values: %s } }).then(function (r) { window.__accessibilityResults = r; }).catch(function (e) { window.__accessibilityResults = { error: String(e) }; });',
      $tag_list
    ));

    $session->wait(30000, 'window.__accessibilityResults !== null');
    // Some browser drivers cannot marshal every property of a large, nested
    // live object, so the result is serialized to JSON in the browser.
    $results = json_decode((string) $session->evaluateScript('return JSON.stringify(window.__accessibilityResults);'), TRUE);

    if (!is_array($results)) {
      throw new \RuntimeException('Accessibility engine did not return results.');
    }

    if (isset($results['error'])) {
      throw new \RuntimeException(sprintf('Accessibility engine failed: %s.', $results['error']));
    }

    return $results;
  }

  /**
   * Normalize raw engine output into the canonical shape used by the trait.
   *
   * Canonical shape:
   * `['violations' => [...], 'incomplete' => [...], 'passes' => [...]]`
   *
   * Default: maps each finding into the canonical fields explicitly. The
   * default engine's native shape shares field names with the canonical
   * shape, so this default mostly copies values straight across.
   *
   * Each field is still named individually, so the method also serves as a
   * template for overrides. Override when wiring a different engine to map
   * its native output (e.g. pa11y's `issues[]`, Lighthouse's `audits`) into
   * the canonical structure.
   *
   * @param array<string, mixed> $raw
   *   Raw result from `accessibilityRunEngine()`.
   *
   * @return array<string, mixed>
   *   Normalized result.
   */
  public function accessibilityNormalizeResults(array $raw): array {
    $normalized = ['violations' => [], 'incomplete' => [], 'passes' => []];

    foreach (['violations', 'incomplete'] as $bucket) {
      foreach ($raw[$bucket] ?? [] as $issue) {
        $impact = strtolower((string) ($issue['impact'] ?? ''));

        switch ($impact) {
          case 'critical':
            $impact = self::ACCESSIBILITY_IMPACT_CRITICAL;
            break;

          case 'serious':
            $impact = self::ACCESSIBILITY_IMPACT_SERIOUS;
            break;

          case 'moderate':
            $impact = self::ACCESSIBILITY_IMPACT_MODERATE;
            break;

          default:
            $impact = self::ACCESSIBILITY_IMPACT_MINOR;
        }

        $nodes = [];

        foreach ($issue['nodes'] ?? [] as $node) {
          $nodes[] = [
            'target' => (array) ($node['target'] ?? []),
            'html' => (string) ($node['html'] ?? ''),
          ];
        }

        $normalized[$bucket][] = [
          'id' => (string) ($issue['id'] ?? 'unknown'),
          'impact' => $impact,
          'help' => (string) ($issue['help'] ?? ''),
          'helpUrl' => (string) ($issue['helpUrl'] ?? ''),
          'nodes' => $nodes,
        ];
      }
    }

    foreach ($raw['passes'] ?? [] as $pass) {
      $normalized['passes'][] = ['id' => (string) ($pass['id'] ?? 'unknown')];
    }

    return $normalized;
  }

  /**
   * Resolve scenario / feature tags into mode and threshold state.
   *
   * A later variant replaces an earlier one, so a scenario tag overrides a
   * feature tag.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The scenario scope.
   */
  protected function accessibilityResolveTags(BeforeScenarioScope $scope): void {
    $auto_tag = $this->accessibilityGetAutoTag();
    $variants = array_map(strtolower(...), Tag::values($scope, $auto_tag));
    $impacts = $this->accessibilityGetImpacts();

    $this->accessibilityAutoMode = Tag::has($scope, $auto_tag) || $variants !== [];

    foreach ($variants as $variant) {
      if ($variant === 'warning' || $variant === 'warn') {
        $this->accessibilityScenarioThreshold = 'never';
      }
      elseif ($variant === 'strict') {
        $this->accessibilityScenarioFailOnIncomplete = TRUE;
      }
      elseif ($variant === 'any') {
        $this->accessibilityScenarioThreshold = 'any';
      }
      elseif (in_array($variant, $impacts, TRUE)) {
        $this->accessibilityScenarioThreshold = $variant;
      }
    }
  }

  /**
   * Return the active gate threshold for the current scenario.
   */
  protected function accessibilityEffectiveThreshold(): string {
    return $this->accessibilityScenarioThreshold ?? $this->accessibilityGetFailureThreshold();
  }

  /**
   * Return whether incomplete findings should fail the current scenario.
   */
  protected function accessibilityEffectiveFailOnIncomplete(): bool {
    return $this->accessibilityScenarioFailOnIncomplete ?? $this->accessibilityGetFailOnIncomplete();
  }

  /**
   * Filter violations by impact threshold.
   *
   * @param array<int, array<string, mixed>> $violations
   *   Normalized violations.
   * @param string $threshold
   *   `any`, `never`, or an impact level from accessibilityGetImpacts().
   *
   * @return array<int, array<string, mixed>>
   *   Violations meeting or exceeding the threshold.
   */
  protected function accessibilityFilterViolations(array $violations, string $threshold): array {
    if ($threshold === 'never') {
      return [];
    }

    if ($threshold === 'any') {
      return $violations;
    }

    $impacts = $this->accessibilityGetImpacts();
    $threshold_pos = array_search($threshold, $impacts, TRUE);

    if ($threshold_pos === FALSE) {
      return $violations;
    }

    $filtered = [];

    foreach ($violations as $violation) {
      $impact = strtolower((string) ($violation['impact'] ?? ''));
      $pos = array_search($impact, $impacts, TRUE);

      if ($pos !== FALSE && $pos <= $threshold_pos) {
        $filtered[] = $violation;
      }
    }

    return $filtered;
  }

  /**
   * Run the engine, normalize the result, record it for the scenario.
   *
   * @param string $rules
   *   Engine-specific rule identifier.
   *
   * @return array<string, mixed>
   *   Normalized result.
   */
  public function accessibilityAssess(string $rules): array {
    $raw = $this->accessibilityRunEngine($rules);
    $normalized = $this->accessibilityNormalizeResults($raw);

    $url = $this->getSession()->getCurrentUrl();
    $this->accessibilityResults[] = ['url' => $url, 'rules' => $rules, 'result' => $normalized];
    $this->accessibilityLastCheckedUrl = $url;

    if ($this->accessibilityGetPrintCli()) {
      fwrite(STDOUT, sprintf(PHP_EOL . '[accessibility] %s: %d violations, %d passes, %d incomplete (rules: %s)' . PHP_EOL,
        $this->accessibilityFormatUrl($url),
        count($normalized['violations'] ?? []),
        count($normalized['passes'] ?? []),
        count($normalized['incomplete'] ?? []),
        $rules
      ));
    }

    return $normalized;
  }

  /**
   * Visit and assess each page, then check what was found.
   *
   * Duplicate paths are assessed once. A page that throws while it is
   * visited or assessed is recorded as skipped, with the exception message
   * as the reason, and the next page is assessed.
   *
   * In automatic mode the scenario's gate checks the results. Otherwise the
   * failure threshold is applied to every page this call assessed, after
   * the last one.
   *
   * @param array<int, string> $paths
   *   Paths or absolute URLs, resolved against the base URL.
   * @param string $rules
   *   Engine-specific rule identifier.
   *
   * @throws \RuntimeException
   *   When no path is given, or no page could be assessed.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Outside automatic mode, when an assessed page holds a violation at or
   *   above the threshold, or an incomplete finding the scenario fails on.
   */
  public function accessibilityAssessPages(array $paths, string $rules): void {
    $paths = array_values(array_unique($paths));

    if ($paths === []) {
      throw new \RuntimeException('The list of pages to assess is empty.');
    }

    $results = [];
    $skipped = [];

    foreach ($paths as $path) {
      $url = $this->locatePath($path);

      try {
        $this->getSession()->visit($url);
        $result = $this->accessibilityAssess($rules);
        $results[] = ['url' => $this->accessibilityLastCheckedUrl, 'rules' => $rules, 'result' => $result];
      }
      catch (\Exception $exception) {
        $skipped[] = ['url' => $url, 'reason' => $exception->getMessage()];
      }
    }

    $this->accessibilitySkippedPages = [...$this->accessibilitySkippedPages, ...$skipped];

    try {
      // A skipped last page stays open, and automatic mode would otherwise
      // assess it again after this step.
      $this->accessibilityLastCheckedUrl = $this->getSession()->getCurrentUrl();
    }
    catch (\Exception) {
      // Automatic mode skips a session that cannot report its URL.
    }

    if ($results === []) {
      throw new \RuntimeException('No page could be assessed:' . PHP_EOL . $this->accessibilityFormatSkippedPages($skipped));
    }

    if ($this->accessibilityAutoMode) {
      return;
    }

    $this->accessibilityAssertResultsPass($results, $skipped);
  }

  /**
   * Parse the paths from the "path" column of a table of pages.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table, with a "path" header.
   *
   * @return array<int, string>
   *   The paths, in table order.
   */
  protected function accessibilityParsePaths(TableNode $table): array {
    if (!in_array('path', $table->getRow(0), TRUE)) {
      throw new \RuntimeException('The table of pages has no "path" column.');
    }

    $paths = array_map(trim(...), array_column($table->getHash(), 'path'));

    if (in_array('', $paths, TRUE)) {
      throw new \RuntimeException('The table of pages has an empty "path" cell.');
    }

    return $paths;
  }

  /**
   * Return the paths of the pages a sitemap lists.
   *
   * The sitemap is fetched as the scenario's visitor, with the session's
   * cookies and the site's basic-auth credentials. A sitemap index is
   * followed to every sitemap it lists, and a sitemap is never returned as a
   * page.
   *
   * Each location keeps its path and query string and drops its scheme, host
   * and fragment, so it resolves against the base URL. Paths are
   * de-duplicated in sitemap order, filtered by depth, then cut to the limit.
   *
   * @param string $path
   *   Path or absolute URL of the sitemap.
   * @param int|null $max_depth
   *   Deepest path depth to keep, where `/` is 0 and `/about` is 1, or NULL
   *   for every depth.
   * @param int|null $limit
   *   Most paths to return, or NULL for every path.
   *
   * @return array<int, string>
   *   Page paths, in sitemap order.
   *
   * @throws \RuntimeException
   *   When a sitemap can't be fetched or read, or it lists no page to keep.
   */
  public function accessibilityGetSitemapPaths(string $path, ?int $max_depth = NULL, ?int $limit = NULL): array {
    $paths = [];
    $pending = [$path];
    $visited = [];

    while ($pending !== []) {
      $sitemap = array_shift($pending);

      // An index that lists itself, directly or through another index, is
      // read once.
      if (isset($visited[$sitemap])) {
        continue;
      }

      $visited[$sitemap] = TRUE;
      $parsed = $this->accessibilityParseSitemap($this->accessibilityFetchSitemap($sitemap), $sitemap);

      if ($parsed['is_index']) {
        $pending = [...$pending, ...$parsed['locations']];
        continue;
      }

      $paths = [...$paths, ...$parsed['locations']];
    }

    $paths = $this->accessibilityFilterSitemapPaths($paths, $max_depth, $limit);

    if ($paths === []) {
      throw new \RuntimeException($max_depth === NULL ? sprintf('The sitemap "%s" lists no pages.', $path) : sprintf('The sitemap "%s" lists no pages with a path depth of at most %d.', $path, $max_depth));
    }

    return $paths;
  }

  /**
   * Fetch a sitemap through the detached HTTP client.
   *
   * @param string $path
   *   Path or absolute URL of the sitemap.
   *
   * @return string
   *   The response body.
   *
   * @throws \RuntimeException
   *   When the request fails or the response status is 400 or above.
   */
  protected function accessibilityFetchSitemap(string $path): string {
    $browser = $this->httpDetachedClient();
    $browser->setMaxRedirects(10);

    try {
      $browser->request('GET', $this->locatePath($path));
    }
    catch (TransportExceptionInterface $exception) {
      throw new \RuntimeException(sprintf('Unable to fetch the sitemap "%s": %s.', $path, $exception->getMessage()), 0, $exception);
    }

    $response = $browser->getInternalResponse();

    if ($response->getStatusCode() >= 400) {
      throw new \RuntimeException(sprintf('The sitemap "%s" returned HTTP status %d.', $path, $response->getStatusCode()));
    }

    return $response->getContent();
  }

  /**
   * Parse a sitemap or a sitemap index into the paths it lists.
   *
   * Only `loc` elements in the root element's namespace are read, so the
   * `image:loc` and `video:loc` of sitemap extensions are ignored.
   *
   * @param string $xml
   *   The sitemap document.
   * @param string $path
   *   Path of the sitemap, for failure messages.
   *
   * @return array{is_index: bool, locations: array<int, string>}
   *   Whether the document is a sitemap index, and the path of each page or
   *   sitemap it lists.
   *
   * @throws \RuntimeException
   *   When the document isn't XML, or its root is neither `urlset` nor
   *   `sitemapindex`.
   */
  protected function accessibilityParseSitemap(string $xml, string $path): array {
    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $is_loaded = trim($xml) !== '' && $document->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->documentElement;

    if (!$is_loaded || !$root instanceof \DOMElement) {
      throw new \RuntimeException(sprintf('The sitemap "%s" is not valid XML.', $path));
    }

    $entries = ['urlset' => 'url', 'sitemapindex' => 'sitemap'];

    if (!isset($entries[$root->localName])) {
      throw new \RuntimeException(sprintf('The sitemap "%s" has the root element "%s", but it should be "urlset" or "sitemapindex".', $path, $root->localName));
    }

    $xpath = new \DOMXPath($document);
    $prefix = '';

    if ($root->namespaceURI !== NULL) {
      $xpath->registerNamespace('sitemap', $root->namespaceURI);
      $prefix = 'sitemap:';
    }

    $locations = [];

    foreach ($xpath->query(sprintf('/%1$s%2$s/%1$s%3$s/%1$sloc', $prefix, $root->localName, $entries[$root->localName])) ?: [] as $node) {
      $location = $this->accessibilityExtractSitemapPath(trim((string) $node->nodeValue));

      if ($location !== NULL) {
        $locations[] = $location;
      }
    }

    return ['is_index' => $root->localName === 'sitemapindex', 'locations' => $locations];
  }

  /**
   * Extract the path and query string from a sitemap location.
   *
   * @param string $location
   *   The text of a `loc` element.
   *
   * @return string|null
   *   The path with its query string, or NULL for an empty or malformed
   *   location.
   */
  protected function accessibilityExtractSitemapPath(string $location): ?string {
    $parts = $location === '' ? FALSE : parse_url($location);

    if ($parts === FALSE) {
      return NULL;
    }

    $path = '/' . ltrim($parts['path'] ?? '', '/');

    return isset($parts['query']) ? $path . '?' . $parts['query'] : $path;
  }

  /**
   * De-duplicate sitemap paths, filter them by depth and cut them to a limit.
   *
   * @param array<int, string> $paths
   *   Paths in sitemap order.
   * @param int|null $max_depth
   *   Deepest path depth to keep, or NULL for every depth.
   * @param int|null $limit
   *   Most paths to return, or NULL for every path.
   *
   * @return array<int, string>
   *   The kept paths, in sitemap order.
   */
  protected function accessibilityFilterSitemapPaths(array $paths, ?int $max_depth, ?int $limit): array {
    $filtered = [];

    foreach (array_unique($paths) as $path) {
      if ($max_depth !== NULL && $this->accessibilityGetPathDepth($path) > $max_depth) {
        continue;
      }

      $filtered[] = $path;
    }

    return $limit === NULL ? $filtered : array_slice($filtered, 0, max(0, $limit));
  }

  /**
   * Return the number of non-empty segments in the path of a URL.
   *
   * @param string $path
   *   A path, with or without a query string.
   *
   * @return int
   *   0 for `/`, 1 for `/about` and `/about/`, 2 for `/about/team`.
   */
  protected function accessibilityGetPathDepth(string $path): int {
    $segments = explode('/', (string) parse_url($path, PHP_URL_PATH));

    return count(array_filter($segments, static fn(string $segment): bool => $segment !== ''));
  }

  /**
   * Build the human-readable error message for 1 assessed page.
   *
   * @param string $url
   *   URL of the page that was assessed.
   * @param string $rules
   *   Rule identifier used.
   * @param string $threshold
   *   Effective gate threshold.
   * @param bool $check_incomplete
   *   Whether incomplete findings fail the gate.
   * @param array<int, array<string, mixed>> $violations
   *   Filtered violations.
   * @param array<int, array<string, mixed>> $incomplete
   *   Incomplete findings to include.
   */
  protected function accessibilityFormatGateMessage(string $url, string $rules, string $threshold, bool $check_incomplete, array $violations, array $incomplete): string {
    $lines = [
      sprintf('Accessibility gate failed on %s (rules: %s, threshold: %s, fail_on_incomplete: %s):', $this->accessibilityFormatUrl($url), $rules, $threshold, $check_incomplete ? 'yes' : 'no'),
    ];

    foreach ($violations as $violation) {
      $lines[] = sprintf('  violation [%s] %s - %s', $violation['impact'] ?? 'unknown', $violation['id'], $violation['help']);
      $lines[] = sprintf('    %s', $violation['helpUrl']);

      foreach ($violation['nodes'] ?? [] as $node) {
        $lines[] = sprintf('    -> %s', static::accessibilityStringifyTarget($node['target'] ?? []));
        $html = trim((string) ($node['html'] ?? ''));

        if ($html !== '') {
          $lines[] = sprintf('       %s', mb_strimwidth($html, 0, 160, '...'));
        }
      }
    }

    foreach ($incomplete as $issue) {
      $lines[] = sprintf('  incomplete [%s] %s - %s', $issue['impact'] ?? 'unknown', $issue['id'], $issue['help']);
      $lines[] = sprintf('    %s', $issue['helpUrl']);

      foreach ($issue['nodes'] ?? [] as $node) {
        $lines[] = sprintf('    -> %s', static::accessibilityStringifyTarget($node['target'] ?? []));
      }
    }

    return implode(PHP_EOL, $lines);
  }

  /**
   * Flatten a node target array into a human-readable string.
   *
   * @param array<int, mixed> $target
   *   Canonical `target` value of a node entry.
   */
  protected static function accessibilityStringifyTarget(array $target): string {
    return implode(' > ', array_map(static fn($t): string => is_array($t) ? implode(' ', $t) : (string) $t, $target));
  }

  /**
   * Format a page URL for display in reports and gate messages.
   *
   * Default: strip the configured Mink `base_url` prefix so reports show the
   * page path (`/contact`) rather than the internal host and port
   * (`http://nginx:8080/contact`). The absolute form adds no information and
   * makes reports non-portable.
   *
   * The base URL itself maps to `/` and the query string is kept. Only the
   * known `base_url` is stripped: a cross-origin URL captured during
   * assessment stays absolute, so it remains distinguishable.
   *
   * Override to keep the absolute URL or to format it differently.
   */
  public function accessibilityFormatUrl(string $url): string {
    $base = rtrim((string) $this->getMinkParameter('base_url'), '/');

    if ($base === '') {
      return $url;
    }

    if ($url === $base) {
      return '/';
    }

    if (str_starts_with($url, $base . '/')) {
      return substr($url, strlen($base));
    }

    return $url;
  }

  /**
   * Return URL values that represent a blank tab rather than a real page.
   *
   * Override to extend.
   *
   * @return array<int, string>
   *   URL values to ignore.
   */
  public static function accessibilityGetBlankUrls(): array {
    return ['', 'about:blank', 'data:,'];
  }

  /**
   * Render the scenario-level HTML report from collected results.
   *
   * Composes the page wrapper around the per-URL section markup. The 2
   * pieces are split so consumers can rebrand the page without changing
   * the section logic.
   */
  protected function accessibilityRenderHtml(): string {
    return $this->accessibilityRenderHtmlPage($this->accessibilityRenderHtmlSections());
  }

  /**
   * Wrap the per-URL sections in a standalone HTML page.
   *
   * Default: a self-contained HTML document with the trait's built-in
   * styles. Override to brand the report (custom doctype, header/footer,
   * external stylesheet, project logo, etc.) without rebuilding the section
   * markup, which `$sections` already holds.
   *
   * @param string $sections
   *   Pre-rendered per-URL section markup from
   *   accessibilityRenderHtmlSections().
   */
  public function accessibilityRenderHtmlPage(string $sections): string {
    $title = htmlspecialchars($this->accessibilityFeatureName . ' > ' . $this->accessibilityScenarioName, ENT_QUOTES);
    $threshold = htmlspecialchars($this->accessibilityEffectiveThreshold(), ENT_QUOTES);
    $fail_on_incomplete = $this->accessibilityEffectiveFailOnIncomplete() ? 'yes' : 'no';

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Accessibility report - {$title}</title>
<style>
:root { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
body { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; color: #1f2328; }
h1 { font-size: 1.5rem; border-bottom: 1px solid #d0d7de; padding-bottom: .5rem; }
h2 { font-size: 1.1rem; margin-top: 2rem; word-break: break-all; }
h3 { font-size: 1rem; margin-top: 1.5rem; }
.meta { color: #57606a; font-size: .9rem; }
.issue { border: 1px solid #d0d7de; border-radius: 6px; padding: .75rem 1rem; margin: .5rem 0; }
.issue.violation { border-left: 4px solid #cf222e; }
.issue.incomplete { border-left: 4px solid #9a6700; }
.impact { display: inline-block; padding: .1rem .5rem; border-radius: 3px; font-size: .75rem; font-weight: 600; text-transform: uppercase; margin-right: .5rem; }
.impact.critical { background: #cf222e; color: white; }
.impact.serious { background: #d1242f; color: white; }
.impact.moderate { background: #bf8700; color: white; }
.impact.minor { background: #57606a; color: white; }
.impact.unknown { background: #d0d7de; color: #1f2328; }
.rule-id { font-family: ui-monospace, SFMono-Regular, monospace; }
.node { background: #f6f8fa; padding: .5rem; border-radius: 3px; margin: .5rem 0; font-size: .85rem; }
.node code { font-family: ui-monospace, SFMono-Regular, monospace; white-space: pre-wrap; word-break: break-all; }
a { color: #0969da; }
</style>
</head>
<body>
<h1>Accessibility report</h1>
<p class="meta">{$title} &middot; threshold: <code>{$threshold}</code> &middot; fail on incomplete: <code>{$fail_on_incomplete}</code></p>
{$sections}
</body>
</html>
HTML;
  }

  /**
   * Render the per-URL section markup (1 `<section>` per visited URL).
   *
   * Returns only the inner content that the page wrapper embeds. Override
   * to change how each section renders (rare); for branding the
   * surrounding page, override accessibilityRenderHtmlPage() instead.
   */
  public function accessibilityRenderHtmlSections(): string {
    $body_sections = [];

    foreach ($this->accessibilityResults as $result) {
      $url = htmlspecialchars($this->accessibilityFormatUrl((string) $result['url']), ENT_QUOTES);
      $rules = htmlspecialchars((string) $result['rules'], ENT_QUOTES);
      $violations = $result['result']['violations'] ?? [];
      $incomplete = $result['result']['incomplete'] ?? [];
      $passes_count = count($result['result']['passes'] ?? []);

      $section = sprintf('<section class="page"><h2>%s</h2><p class="meta">Rules: <code>%s</code> &middot; %d violations &middot; %d incomplete &middot; %d passes</p>', $url, $rules, count($violations), count($incomplete), $passes_count);

      $section .= $this->accessibilityRenderIssueList('Violations', 'violation', $violations);
      $section .= $this->accessibilityRenderIssueList('Incomplete (needs human review)', 'incomplete', $incomplete);
      $section .= '</section>';
      $body_sections[] = $section;
    }

    foreach ($this->accessibilitySkippedPages as $skipped) {
      $body_sections[] = sprintf('<section class="page skipped"><h2>%s</h2><p class="meta">Skipped: %s</p></section>', htmlspecialchars($this->accessibilityFormatUrl($skipped['url']), ENT_QUOTES), htmlspecialchars($skipped['reason'], ENT_QUOTES));
    }

    return implode("\n", $body_sections);
  }

  /**
   * Render a single issue list (violations or incomplete) as HTML.
   *
   * @param string $heading
   *   Section heading.
   * @param string $css_class
   *   CSS class applied to each issue ('violation' or 'incomplete').
   * @param array<int, array<string, mixed>> $issues
   *   Issues to render.
   */
  protected function accessibilityRenderIssueList(string $heading, string $css_class, array $issues): string {
    if ($issues === []) {
      return sprintf('<h3>%s</h3><p class="meta">None.</p>', htmlspecialchars($heading, ENT_QUOTES));
    }

    $out = sprintf('<h3>%s</h3>', htmlspecialchars($heading, ENT_QUOTES));

    foreach ($issues as $issue) {
      $impact = strtolower((string) ($issue['impact'] ?? 'unknown'));
      $impact_safe = htmlspecialchars($impact, ENT_QUOTES);
      $id = htmlspecialchars((string) ($issue['id'] ?? ''), ENT_QUOTES);
      $help = htmlspecialchars((string) ($issue['help'] ?? ''), ENT_QUOTES);
      $help_url = htmlspecialchars((string) ($issue['helpUrl'] ?? ''), ENT_QUOTES);

      $out .= sprintf('<div class="issue %s"><span class="impact %s">%s</span><span class="rule-id">%s</span> &mdash; %s', htmlspecialchars($css_class, ENT_QUOTES), $impact_safe, $impact_safe, $id, $help);

      if ($help_url !== '') {
        $out .= sprintf(' (<a href="%s" target="_blank" rel="noopener">docs</a>)', $help_url);
      }

      foreach ($issue['nodes'] ?? [] as $node) {
        $target = htmlspecialchars(static::accessibilityStringifyTarget($node['target'] ?? []), ENT_QUOTES);
        $html = htmlspecialchars(trim((string) ($node['html'] ?? '')), ENT_QUOTES);
        $out .= sprintf('<div class="node"><strong>%s</strong><br><code>%s</code></div>', $target, $html);
      }

      $out .= '</div>';
    }

    return $out;
  }

  /**
   * Render the scenario-level JUnit XML report from collected results.
   *
   * Violations are gated by the scenario's effective threshold, exactly as
   * the pass/fail gate is: only violations meeting the threshold are
   * serialized as `<failure>` cases. An advisory run (threshold `never`)
   * therefore writes a report with 0 failures instead of one that fails
   * a JUnit-consuming CI check.
   *
   * Violations below the threshold are recorded as passing cases carrying
   * the finding in `<system-out>`, so they stay visible without failing the
   * report.
   *
   * A violation emits 1 `<testcase>` per affected node and a passed rule
   * emits 1 `<testcase>` with no node. A page a sweep skipped emits 1
   * `<testcase>` holding a `<skipped>` element with the reason. `tests`
   * counts every case and `failures` counts the cases carrying a `<failure>`.
   */
  protected function accessibilityRenderJunit(): string {
    $threshold = $this->accessibilityEffectiveThreshold();
    $suites_xml = '';
    $total_tests = 0;
    $total_failures = 0;

    foreach ($this->accessibilityResults as $result) {
      $url = $this->accessibilityFormatUrl((string) $result['url']);
      $violations = $result['result']['violations'] ?? [];
      $passes = $result['result']['passes'] ?? [];

      $cases_xml = '';
      $tests = 0;
      $failures = 0;

      foreach ($violations as $violation) {
        $is_failing = $this->accessibilityFilterViolations([$violation], $threshold) !== [];
        $rule_id = (string) ($violation['id'] ?? 'unknown');
        $impact = (string) ($violation['impact'] ?? 'unknown');
        $help = (string) ($violation['help'] ?? '');
        $help_url = (string) ($violation['helpUrl'] ?? '');

        foreach ($violation['nodes'] ?? [] as $node) {
          $target = static::accessibilityStringifyTarget($node['target'] ?? []);
          $html = trim((string) ($node['html'] ?? ''));
          $details = sprintf('URL: %s' . PHP_EOL . 'Rule: %s' . PHP_EOL . 'Target: %s' . PHP_EOL . 'HTML: %s' . PHP_EOL . 'Docs: %s', $url, $rule_id, $target, $html, $help_url);
          $classname = htmlspecialchars('accessibility.' . $rule_id, ENT_XML1 | ENT_QUOTES);
          $name = htmlspecialchars($target ?: $rule_id, ENT_XML1 | ENT_QUOTES);
          $tests++;

          if (!$is_failing) {
            $cases_xml .= sprintf('<testcase classname="%s" name="%s"><system-out>%s</system-out></testcase>', $classname, $name, htmlspecialchars('[advisory] ' . $details, ENT_XML1 | ENT_QUOTES));
            continue;
          }

          $failures++;
          $cases_xml .= sprintf('<testcase classname="%s" name="%s"><failure type="%s" message="%s">%s</failure></testcase>', $classname, $name, htmlspecialchars($impact, ENT_XML1 | ENT_QUOTES), htmlspecialchars(sprintf('[%s] %s', $impact, $help), ENT_XML1 | ENT_QUOTES), htmlspecialchars($details, ENT_XML1 | ENT_QUOTES));
        }
      }

      foreach ($passes as $pass) {
        $rule_id = (string) ($pass['id'] ?? 'unknown');
        $tests++;
        $cases_xml .= sprintf('<testcase classname="accessibility.%s" name="%s passed"/>', htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES), htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES));
      }

      $total_tests += $tests;
      $total_failures += $failures;

      $suites_xml .= sprintf('<testsuite name="%s" tests="%d" failures="%d" errors="0">%s</testsuite>', htmlspecialchars((string) $url, ENT_XML1 | ENT_QUOTES), $tests, $failures, $cases_xml);
    }

    foreach ($this->accessibilitySkippedPages as $skipped) {
      $url = htmlspecialchars($this->accessibilityFormatUrl($skipped['url']), ENT_XML1 | ENT_QUOTES);
      $total_tests++;
      $suites_xml .= sprintf('<testsuite name="%1$s" tests="1" failures="0" errors="0" skipped="1"><testcase classname="accessibility.skipped" name="%1$s"><skipped message="%2$s"/></testcase></testsuite>', $url, htmlspecialchars($skipped['reason'], ENT_XML1 | ENT_QUOTES));
    }

    return sprintf(
      '<?xml version="1.0" encoding="UTF-8"?><testsuites name="%s" tests="%d" failures="%d">%s</testsuites>',
      htmlspecialchars($this->accessibilityFeatureName . ' > ' . $this->accessibilityScenarioName, ENT_XML1 | ENT_QUOTES),
      $total_tests,
      $total_failures,
      $suites_xml
    );
  }

  /**
   * Clear the suite-level aggregate state before the suite runs.
   *
   * The accumulator is process-global, so resetting at suite start stops a
   * second suite in the same process from inheriting the first one's results.
   */
  protected static function accessibilityAggregateReset(): void {
    self::$accessibilityAggregate = [];
    self::$accessibilityAggregateReportDir = NULL;
  }

  /**
   * Record the scenario's formatted results for the suite-level aggregate.
   *
   * The static renderer has no instance to call `accessibilityFormatUrl()`
   * or a consumer override of it on, so URLs are formatted in the instance
   * phase.
   *
   * @param string $directory
   *   The resolved per-scenario report directory, captured for the static
   *   `@AfterSuite` renderer.
   */
  protected function accessibilityAggregateCapture(string $directory): void {
    self::$accessibilityAggregateReportDir = $directory;

    $results = [];

    foreach ($this->accessibilityResults as $result) {
      $results[] = [
        'url' => $this->accessibilityFormatUrl((string) $result['url']),
        'rules' => (string) $result['rules'],
        'result' => $result['result'],
      ];
    }

    $skipped = [];

    foreach ($this->accessibilitySkippedPages as $page) {
      $skipped[] = ['url' => $this->accessibilityFormatUrl($page['url']), 'reason' => $page['reason']];
    }

    self::$accessibilityAggregate[] = [
      'feature' => $this->accessibilityFeatureName,
      'scenario' => $this->accessibilityScenarioName,
      'threshold' => $this->accessibilityEffectiveThreshold(),
      'fail_on_incomplete' => $this->accessibilityEffectiveFailOnIncomplete(),
      // The suite renderer is static and cannot call an override, so the
      // impact list the scenario was gated under is stored with its results.
      'impacts' => $this->accessibilityGetImpacts(),
      'results' => $results,
      'skipped' => $skipped,
    ];
  }

  /**
   * Write the aggregate report when at least 1 scenario produced results.
   *
   * 1 timestamped file is written per suite run, so a run never overwrites a
   * previous one.
   *
   * The same timestamp is used for the filename and the in-page "generated"
   * line. It is resolved here so the render methods stay deterministic.
   */
  protected static function accessibilityWriteAggregateReport(): void {
    if (self::$accessibilityAggregate === []) {
      return;
    }

    $directory = self::$accessibilityAggregateReportDir ?? (getcwd() ?: '.') . '/.logs/test_results/accessibility';

    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    $time = time();
    $data = static::accessibilityAggregateData(self::$accessibilityAggregate, date('Y-m-d H:i', $time));
    file_put_contents($directory . '/' . static::accessibilityAggregateFilename($time), static::accessibilityRenderAggregate($data));
  }

  /**
   * Build the timestamped aggregate report filename.
   *
   * @param int $time
   *   Unix timestamp the report was generated at.
   *
   * @return string
   *   Filename of the form `accessibility_report_YYYYMMDD_HHMMSS.html`.
   */
  protected static function accessibilityAggregateFilename(int $time): string {
    return 'accessibility_report_' . date('Ymd_His', $time) . '.html';
  }

  /**
   * De-duplicate assessed pages by URL across every scenario.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   *
   * @return array<string, array<string, mixed>>
   *   1 entry per unique URL, in first-seen order, each holding its
   *   violations, incomplete and passes counts, and visiting scenarios.
   */
  protected static function accessibilityAggregatePages(array $aggregate): array {
    $blank = static::accessibilityGetBlankUrls();
    $pages = [];

    foreach ($aggregate as $entry) {
      foreach ($entry['results'] ?? [] as $result) {
        $url = (string) ($result['url'] ?? '');

        if (in_array($url, $blank, TRUE)) {
          continue;
        }

        $pages[$url] ??= [
          'violations' => $result['result']['violations'] ?? [],
          'incomplete' => count($result['result']['incomplete'] ?? []),
          'passes' => count($result['result']['passes'] ?? []),
          'scenarios' => [],
        ];

        $feature = (string) ($entry['feature'] ?? '');
        $scenario = (string) ($entry['scenario'] ?? '');
        $label = $feature !== '' ? $feature . ' > ' . $scenario : $scenario;
        $pages[$url]['scenarios'][$label] = TRUE;
      }
    }

    return $pages;
  }

  /**
   * Read the impact list the scenarios were gated under.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   *
   * @return array<int, string>
   *   Impact identifiers ordered from most severe to least, empty when no
   *   entry carries a list.
   */
  protected static function accessibilityAggregateImpacts(array $aggregate): array {
    foreach ($aggregate as $entry) {
      if (is_array($entry['impacts'] ?? NULL) && $entry['impacts'] !== []) {
        return array_values(array_map(strval(...), $entry['impacts']));
      }
    }

    return [];
  }

  /**
   * Roll violations up by rule and tally totals by impact.
   *
   * Rules are sorted highest-impact first, then by affected-element count.
   *
   * @param array<string, array<string, mixed>> $pages
   *   Per-URL rollup from accessibilityAggregatePages().
   * @param array<int, string> $impacts
   *   Impact identifiers ordered from most severe to least. Defaults to the
   *   static list when the aggregate carries none.
   *
   * @return array{rules: array<string, array<string, mixed>>, totals: array<string, int>}
   *   Severity-sorted rules and per-impact totals.
   */
  protected static function accessibilityAggregateRollup(array $pages, array $impacts = []): array {
    $impacts = $impacts === [] ? static::accessibilityGetDefaultImpacts() : $impacts;
    $rank = array_flip($impacts);
    $rules = [];
    $totals = array_fill_keys($impacts, 0);

    foreach ($pages as $url => $page) {
      foreach ($page['violations'] ?? [] as $violation) {
        $impact = (string) ($violation['impact'] ?? self::ACCESSIBILITY_IMPACT_MINOR);
        $totals[$impact] = ($totals[$impact] ?? 0) + 1;

        $rule_id = (string) ($violation['id'] ?? 'unknown');

        $rules[$rule_id] ??= [
          'impact' => $impact,
          'help' => (string) ($violation['help'] ?? ''),
          'helpUrl' => (string) ($violation['helpUrl'] ?? ''),
          'pages' => [],
          'nodes' => [],
        ];

        $rules[$rule_id]['pages'][$url] = TRUE;

        foreach ($violation['nodes'] ?? [] as $node) {
          $rules[$rule_id]['nodes'][] = [
            'url' => (string) $url,
            'target' => static::accessibilityStringifyTarget($node['target'] ?? []),
            'html' => trim((string) ($node['html'] ?? '')),
          ];
        }
      }
    }

    uasort($rules, static function (array $a, array $b) use ($rank): int {
      $by_impact = ($rank[$a['impact']] ?? 9) <=> ($rank[$b['impact']] ?? 9);

      return $by_impact !== 0 ? $by_impact : count($b['nodes']) <=> count($a['nodes']);
    });

    return ['rules' => $rules, 'totals' => $totals];
  }

  /**
   * Assemble every value the renderer reads into 1 data array.
   *
   * De-duplication, severity tallies, sorting, counting, and target
   * flattening all happen here and in the methods it calls. The single
   * renderer only turns ready values into markup.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   * @param string $generated
   *   Human-readable generation timestamp.
   *
   * @return array<string, mixed>
   *   Render-ready data: `generated`, `page_count`, `scenario_count`,
   *   `total_violations`, `totals`, `pages`, `rules`, `skipped`, and
   *   `scenarios`.
   */
  protected static function accessibilityAggregateData(array $aggregate, string $generated): array {
    $deduped = static::accessibilityAggregatePages($aggregate);
    $rollup = static::accessibilityAggregateRollup($deduped, static::accessibilityAggregateImpacts($aggregate));
    $totals = $rollup['totals'];
    $blank = static::accessibilityGetBlankUrls();

    $pages = [];

    foreach ($deduped as $url => $page) {
      $chips = [];

      foreach ($page['violations'] ?? [] as $violation) {
        $chips[] = [
          'id' => (string) ($violation['id'] ?? 'unknown'),
          'impact' => strtolower((string) ($violation['impact'] ?? self::ACCESSIBILITY_IMPACT_MINOR)),
          'count' => count($violation['nodes'] ?? []),
        ];
      }

      $pages[] = [
        'url' => (string) $url,
        'violations' => $chips,
        'incomplete' => (int) ($page['incomplete'] ?? 0),
        'passes' => (int) ($page['passes'] ?? 0),
        'scenarios' => implode(', ', array_keys($page['scenarios'] ?? [])),
      ];
    }

    $rules = [];

    foreach ($rollup['rules'] as $rule_id => $rule) {
      $rules[] = [
        'id' => (string) $rule_id,
        'impact' => (string) ($rule['impact'] ?? self::ACCESSIBILITY_IMPACT_MINOR),
        'help' => (string) ($rule['help'] ?? ''),
        'helpUrl' => (string) ($rule['helpUrl'] ?? ''),
        'page_count' => count($rule['pages'] ?? []),
        'nodes' => $rule['nodes'] ?? [],
      ];
    }

    $skipped = [];

    foreach ($aggregate as $entry) {
      $feature = (string) ($entry['feature'] ?? '');
      $scenario = (string) ($entry['scenario'] ?? '');

      foreach ($entry['skipped'] ?? [] as $page) {
        $skipped[] = [
          'url' => (string) ($page['url'] ?? ''),
          'reason' => (string) ($page['reason'] ?? ''),
          'scenario' => $feature !== '' ? $feature . ' > ' . $scenario : $scenario,
        ];
      }
    }

    $scenarios = [];

    foreach ($aggregate as $entry) {
      $detail = [];

      foreach ($entry['results'] ?? [] as $result) {
        $url = (string) ($result['url'] ?? '');

        if (in_array($url, $blank, TRUE)) {
          continue;
        }

        $detail[] = [
          'url' => $url,
          'rules' => (string) ($result['rules'] ?? ''),
          'violation_count' => count($result['result']['violations'] ?? []),
          'incomplete_count' => count($result['result']['incomplete'] ?? []),
          'passes_count' => count($result['result']['passes'] ?? []),
          'violations' => static::accessibilityAggregateFindings($result['result']['violations'] ?? []),
          'incomplete' => static::accessibilityAggregateFindings($result['result']['incomplete'] ?? []),
        ];
      }

      $scenarios[] = [
        'feature' => (string) ($entry['feature'] ?? ''),
        'scenario' => (string) ($entry['scenario'] ?? ''),
        'threshold' => (string) ($entry['threshold'] ?? ''),
        'fail_on_incomplete' => (bool) ($entry['fail_on_incomplete'] ?? FALSE),
        'pages' => $detail,
      ];
    }

    return [
      'generated' => $generated,
      'page_count' => count($deduped),
      'scenario_count' => count($aggregate),
      'total_violations' => array_sum($totals),
      'totals' => $totals,
      'pages' => $pages,
      'rules' => $rules,
      'skipped' => $skipped,
      'scenarios' => $scenarios,
    ];
  }

  /**
   * Flatten normalized findings into render-ready rows.
   *
   * @param array<int, array<string, mixed>> $issues
   *   Normalized violations or incomplete findings.
   *
   * @return array<int, array<string, mixed>>
   *   Each finding with its impact, id, help, helpUrl, and flattened nodes.
   */
  protected static function accessibilityAggregateFindings(array $issues): array {
    $findings = [];

    foreach ($issues as $issue) {
      $nodes = [];

      foreach ($issue['nodes'] ?? [] as $node) {
        $nodes[] = [
          'target' => static::accessibilityStringifyTarget($node['target'] ?? []),
          'html' => trim((string) ($node['html'] ?? '')),
        ];
      }

      $findings[] = [
        'impact' => strtolower((string) ($issue['impact'] ?? 'unknown')),
        'id' => (string) ($issue['id'] ?? ''),
        'help' => (string) ($issue['help'] ?? ''),
        'helpUrl' => (string) ($issue['helpUrl'] ?? ''),
        'nodes' => $nodes,
      ];
    }

    return $findings;
  }

  /**
   * Render the entire aggregate report from prepared data.
   *
   * This is the single rendering entry point. Every value it reads is already
   * computed in `$data` by accessibilityAggregateData().
   *
   * A consumer can therefore override this method alone to completely
   * restyle the report - markup, CSS, and layout - without changing any of
   * the aggregation logic.
   *
   * @param array<string, mixed> $data
   *   Render-ready data from accessibilityAggregateData().
   */
  public static function accessibilityRenderAggregate(array $data): string {
    $issue_list = static function (string $heading, string $css_class, array $issues): string {
      if ($issues === []) {
        return sprintf('<h5>%s</h5><p class="meta">None.</p>', htmlspecialchars($heading, ENT_QUOTES));
      }

      $parts = [sprintf('<h5>%s</h5>', htmlspecialchars($heading, ENT_QUOTES))];

      foreach ($issues as $issue) {
        $impact = htmlspecialchars((string) ($issue['impact'] ?? 'unknown'), ENT_QUOTES);
        $issue_html = sprintf('<div class="issue %s"><span class="impact %s">%s</span><span class="rule-id">%s</span> &mdash; %s', htmlspecialchars($css_class, ENT_QUOTES), $impact, $impact, htmlspecialchars((string) ($issue['id'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($issue['help'] ?? ''), ENT_QUOTES));

        if (((string) ($issue['helpUrl'] ?? '')) !== '') {
          $issue_html .= sprintf(' (<a href="%s" target="_blank" rel="noopener">docs</a>)', htmlspecialchars((string) $issue['helpUrl'], ENT_QUOTES));
        }

        foreach ($issue['nodes'] ?? [] as $node) {
          $issue_html .= sprintf('<div class="node"><strong>%s</strong><br><code>%s</code></div>', htmlspecialchars((string) ($node['target'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($node['html'] ?? ''), ENT_QUOTES));
        }

        $parts[] = $issue_html . '</div>';
      }

      return implode('', $parts);
    };

    $totals = $data['totals'] ?? [];
    $state = ((int) ($data['total_violations'] ?? 0)) > 0 ? 'fail' : 'ok';
    $cards = '<section class="cards">'
      . sprintf('<div class="card "><span class="num">%d</span><span class="lbl">pages assessed</span></div>', (int) ($data['page_count'] ?? 0))
      . sprintf('<div class="card "><span class="num">%d</span><span class="lbl">scenarios</span></div>', (int) ($data['scenario_count'] ?? 0))
      . sprintf('<div class="card %s"><span class="num">%d</span><span class="lbl">violations</span></div>', $state, (int) ($data['total_violations'] ?? 0))
      . sprintf('<div class="card crit"><span class="num">%d</span><span class="lbl">critical</span></div>', (int) ($totals[self::ACCESSIBILITY_IMPACT_CRITICAL] ?? 0))
      . sprintf('<div class="card ser"><span class="num">%d</span><span class="lbl">serious</span></div>', (int) ($totals[self::ACCESSIBILITY_IMPACT_SERIOUS] ?? 0))
      . sprintf('<div class="card mod"><span class="num">%d</span><span class="lbl">moderate</span></div>', (int) ($totals[self::ACCESSIBILITY_IMPACT_MODERATE] ?? 0))
      . sprintf('<div class="card min"><span class="num">%d</span><span class="lbl">minor</span></div>', (int) ($totals[self::ACCESSIBILITY_IMPACT_MINOR] ?? 0))
      . '</section>';

    $rows = [];

    foreach ($data['pages'] ?? [] as $page) {
      $chips = [];

      foreach ($page['violations'] ?? [] as $chip) {
        $chips[] = sprintf('<span class="vtype %s">%s <b>%d</b></span>', htmlspecialchars((string) ($chip['impact'] ?? self::ACCESSIBILITY_IMPACT_MINOR), ENT_QUOTES), htmlspecialchars((string) ($chip['id'] ?? 'unknown'), ENT_QUOTES), (int) ($chip['count'] ?? 0));
      }

      $vtypes = $chips === [] ? '<span class="muted">&mdash;</span>' : implode('', $chips);
      $rows[] = sprintf('<tr class="%s"><td class="url">%s</td><td class="vtypes">%s</td><td class="n">%d</td><td class="n">%d</td><td class="muted">%s</td></tr>', $chips === [] ? 'good' : 'bad', htmlspecialchars((string) ($page['url'] ?? ''), ENT_QUOTES), $vtypes, (int) ($page['incomplete'] ?? 0), (int) ($page['passes'] ?? 0), htmlspecialchars((string) ($page['scenarios'] ?? ''), ENT_QUOTES));
    }

    $pages_section = '<section><h2>Pages assessed</h2><p class="meta">Each URL is listed once, even when several scenarios visit it. Violations are broken down by rule, with the number of affected elements.</p>'
      . '<table><thead><tr><th>URL</th><th>Violations by type</th><th>Incomplete</th><th>Passes</th><th>Seen in scenarios</th></tr></thead><tbody>'
      . implode('', $rows) . '</tbody></table></section>';

    $skipped_rows = [];

    foreach ($data['skipped'] ?? [] as $page) {
      $skipped_rows[] = sprintf('<tr class="bad"><td class="url">%s</td><td>%s</td><td class="muted">%s</td></tr>', htmlspecialchars((string) ($page['url'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($page['reason'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($page['scenario'] ?? ''), ENT_QUOTES));
    }

    $skipped_section = $skipped_rows === [] ? '' : '<section><h2>Pages not assessed</h2><p class="meta">Pages a sweep could not visit or assess, with the reason.</p>'
      . '<table><thead><tr><th>URL</th><th>Reason</th><th>Scenario</th></tr></thead><tbody>'
      . implode('', $skipped_rows) . '</tbody></table></section>';

    if (($data['rules'] ?? []) === []) {
      $rules_section = '<section><h2>Violations by rule</h2><p class="meta">No violations found.</p></section>';
    }
    else {
      $blocks = [];

      foreach ($data['rules'] ?? [] as $rule) {
        $nodes = [];

        foreach ($rule['nodes'] ?? [] as $node) {
          $nodes[] = sprintf('<div class="node"><div class="where"><code>%s</code> &middot; <span class="muted">%s</span></div><pre>%s</pre></div>', htmlspecialchars((string) ($node['target'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($node['url'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($node['html'] ?? ''), ENT_QUOTES));
        }

        $impact = htmlspecialchars((string) ($rule['impact'] ?? self::ACCESSIBILITY_IMPACT_MINOR), ENT_QUOTES);
        $docs = ((string) ($rule['helpUrl'] ?? '')) !== '' ? sprintf(' &middot; <a href="%s" target="_blank" rel="noopener">docs</a>', htmlspecialchars((string) $rule['helpUrl'], ENT_QUOTES)) : '';
        $blocks[] = sprintf('<div class="rule"><h3><span class="impact %s">%s</span> <span class="rule-id">%s</span></h3><p class="meta">%s &middot; affects %d page(s) &middot; %d element(s)%s</p>%s</div>', $impact, $impact, htmlspecialchars((string) ($rule['id'] ?? 'unknown'), ENT_QUOTES), htmlspecialchars((string) ($rule['help'] ?? ''), ENT_QUOTES), (int) ($rule['page_count'] ?? 0), count($rule['nodes'] ?? []), $docs, implode('', $nodes));
      }

      $rules_section = '<section><h2>Violations by rule</h2><p class="meta">Highest impact first.</p>' . implode('', $blocks) . '</section>';
    }

    $detail = [];

    foreach ($data['scenarios'] ?? [] as $scenario) {
      $sections = [];

      foreach ($scenario['pages'] ?? [] as $page) {
        $sections[] = sprintf('<div class="page-detail"><h4>%s</h4><p class="meta">Rules: <code>%s</code> &middot; %d violations &middot; %d incomplete &middot; %d passes</p>%s%s</div>', htmlspecialchars((string) ($page['url'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($page['rules'] ?? ''), ENT_QUOTES), (int) ($page['violation_count'] ?? 0), (int) ($page['incomplete_count'] ?? 0), (int) ($page['passes_count'] ?? 0), $issue_list('Violations', 'violation', $page['violations'] ?? []), $issue_list('Incomplete (needs human review)', 'incomplete', $page['incomplete'] ?? []));
      }

      $detail[] = sprintf('<div class="scenario"><h3>%s <span class="muted">%s</span></h3><p class="meta">threshold: <code>%s</code> &middot; fail on incomplete: <code>%s</code></p>%s</div>', htmlspecialchars((string) ($scenario['scenario'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($scenario['feature'] ?? ''), ENT_QUOTES), htmlspecialchars((string) ($scenario['threshold'] ?? ''), ENT_QUOTES), ($scenario['fail_on_incomplete'] ?? FALSE) ? 'yes' : 'no', implode('', $sections));
    }

    $scenarios_section = '<section><h2>Per-scenario detail</h2><p class="meta">Every page each scenario assessed, in order, with its full findings embedded.</p>' . implode('', $detail) . '</section>';

    $generated = htmlspecialchars((string) ($data['generated'] ?? ''), ENT_QUOTES);
    $body = $cards . $pages_section . $skipped_section . $rules_section . $scenarios_section;

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Accessibility report - aggregate</title>
<style>
:root { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
body { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; color: #1f2328; }
h1 { font-size: 1.6rem; border-bottom: 1px solid #d0d7de; padding-bottom: .5rem; }
h2 { font-size: 1.2rem; margin-top: 2.5rem; }
h3 { font-size: 1rem; margin: 1.25rem 0 .25rem; word-break: break-word; }
.meta { color: #57606a; font-size: .9rem; margin: .25rem 0 1rem; }
.muted { color: #57606a; }
.cards { display: flex; flex-wrap: nowrap; gap: .5rem; margin: 1.5rem 0; }
.card { flex: 1 1 0; min-width: 0; border: 1px solid #d0d7de; border-radius: 8px; padding: .6rem .4rem; text-align: center; }
.card .num { display: block; font-size: 1.5rem; font-weight: 700; }
.card .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .02em; color: #57606a; }
.card.fail { border-color: #cf222e; background: #fff5f5; }
.card.fail .num { color: #cf222e; }
.card.ok { border-color: #1a7f37; background: #f3fbf5; }
.card.ok .num { color: #1a7f37; }
.card.crit .num { color: #cf222e; }
.card.ser .num { color: #e8590c; }
.card.mod .num { color: #bf8700; }
.card.min .num { color: #57606a; }
table { width: 100%; border-collapse: collapse; font-size: .9rem; }
th, td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid #eaeef2; vertical-align: top; }
th { background: #f6f8fa; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
td.url { font-family: ui-monospace, SFMono-Regular, monospace; word-break: break-all; width: 25%; }
td.n { text-align: right; font-variant-numeric: tabular-nums; }
td.vtypes { line-height: 1.5; }
.vtype { display: block; width: fit-content; white-space: nowrap; font-family: ui-monospace, SFMono-Regular, monospace; font-size: .72rem; padding: .05rem .4rem; margin: 0 0 .2rem; border-radius: 3px; border: 1px solid #d0d7de; }
.vtype b { font-weight: 700; }
.vtype.critical { border-color: #cf222e; color: #cf222e; background: #fff5f5; }
.vtype.serious { border-color: #e8590c; color: #bc4c00; background: #fff8f3; }
.vtype.moderate { border-color: #bf8700; color: #9a6700; background: #fffbf0; }
.vtype.minor { border-color: #57606a; color: #57606a; background: #f6f8fa; }
.rule { border: 1px solid #d0d7de; border-radius: 8px; padding: .5rem 1rem 1rem; margin: .75rem 0; }
.impact { display: inline-block; padding: .1rem .5rem; border-radius: 3px; font-size: .7rem; font-weight: 700; text-transform: uppercase; color: #fff; }
.impact.critical { background: #cf222e; }
.impact.serious { background: #e8590c; }
.impact.moderate { background: #bf8700; }
.impact.minor { background: #57606a; }
.impact.unknown { background: #d0d7de; color: #1f2328; }
.rule-id { font-family: ui-monospace, SFMono-Regular, monospace; }
.node { background: #f6f8fa; border-radius: 6px; padding: .5rem .75rem; margin: .5rem 0; }
.node .where { font-size: .85rem; margin-bottom: .35rem; }
.node strong { font-family: ui-monospace, SFMono-Regular, monospace; font-weight: 600; }
.node code { font-family: ui-monospace, SFMono-Regular, monospace; white-space: pre-wrap; word-break: break-all; }
.node pre { margin: 0; white-space: pre-wrap; word-break: break-all; font-size: .8rem; color: #1f2328; }
.scenario { border: 1px solid #d0d7de; border-radius: 8px; padding: .25rem 1rem 1rem; margin: 1rem 0; }
.page-detail { margin: .75rem 0; padding-left: .75rem; border-left: 3px solid #eaeef2; }
.page-detail h4 { font-family: ui-monospace, SFMono-Regular, monospace; font-size: .9rem; margin: .5rem 0 .15rem; word-break: break-all; }
.page-detail .meta { margin: 0 0 .4rem; }
.page-detail h5 { font-size: .85rem; margin: .85rem 0 .35rem; }
.issue { border: 1px solid #d0d7de; border-radius: 6px; padding: .6rem .85rem; margin: .4rem 0; font-size: .9rem; }
.issue.violation { border-left: 4px solid #cf222e; }
.issue.incomplete { border-left: 4px solid #9a6700; }
a { color: #0969da; }
</style>
</head>
<body>
<h1>Accessibility report - aggregate</h1>
<p class="meta">One page summarizing every accessibility assessment in the run &middot; generated {$generated}</p>
{$body}
</body>
</html>
HTML;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function accessibilityConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'If `TRUE`, assesses every page an `@accessibility` scenario visits and writes its reports. If `FALSE`, no page is assessed automatically and no report is written, while the accessibility steps still assess the page they run on.'),
      new Option('auto_tag', default: self::ACCESSIBILITY_TAG, description: 'The name, without the `@`, of the tag that switches on automatic assessment for a scenario and carries its threshold, as in `@accessibility:serious`.'),
      new Option('default_rules', default: 'wcag2a,wcag2aa', description: 'The comma-separated axe-core tags that select the rules to check, in automatic mode and in the step that names no tags.'),
      new Option('failure_threshold', default: 'any', description: 'The impact at which a violation fails the scenario: `critical`, `serious`, `moderate` or `minor` fails on that impact or a worse one, `any` on every violation and `never` on none. Any other value fails on every violation, like `any`. An `@accessibility:VALUE` tag overrides it for 1 scenario.'),
      new Option('fail_on_incomplete', default: FALSE, description: "If `TRUE`, a finding axe-core can't decide fails the scenario, whatever the threshold. If `FALSE`, it doesn't. `@accessibility:strict` sets it to `TRUE` for 1 scenario."),
      new Option('cdn_url', default: 'https://cdn.jsdelivr.net/npm/axe-core@4.11.4/axe.min.js', description: 'The URL of the axe-core script the trait loads into each page it assesses, or the absolute path of a local copy. The script is read once per run.'),
      new Option('fetch_timeout', default: 10, description: 'The time, in seconds, an attempt to fetch the axe-core script over HTTP can go without receiving data before it fails.'),
      new Option('fetch_attempts', default: 3, description: 'The number of attempts to fetch the axe-core script before the step fails, with a pause after each failed attempt that grows by half a second. A value below 1 counts as 1.'),
      new Option('report_dir', default: '.logs/test_results/accessibility', description: "The directory each scenario's HTML and JUnit reports and the run's summary report are written to. A relative path resolves against the directory the run was launched from."),
      new Option('sitemap_path', default: '/sitemap.xml', description: 'The path or URL of the XML sitemap `I assess the accessibility of the pages in the sitemap` reads. A path resolves against the base URL.'),
      new Option('sitemap_max_depth', default: -1, description: 'The deepest path depth the sitemap steps assess when the step names none, where `/` has a depth of 0 and `/about` a depth of 1. A negative value assesses pages at every depth.'),
      new Option('sitemap_limit', default: 0, description: 'The most pages the sitemap steps assess when the step names no limit, counted in sitemap order after the depth filter. A value of 0 or less assesses every page.'),
    ];
  }

}
