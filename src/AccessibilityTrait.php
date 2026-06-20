<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\AfterSuite;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

/**
 * Assess accessibility of rendered pages.
 *
 * Supported tags:
 * - `@accessibility`                          Auto-mode, default threshold.
 * - `@accessibility-critical`                 Auto-mode, fail only on critical impact.
 * - `@accessibility-serious`                  Auto-mode, fail on critical or serious.
 * - `@accessibility-moderate`                 Auto-mode, fail on critical / serious / moderate.
 * - `@accessibility-minor`                    Auto-mode, fail on any impact.
 * - `@accessibility-any`                      Auto-mode, fail on any impact (alias).
 * - `@accessibility-warning`                  Auto-mode, never fail (advisory).
 * - `@accessibility-strict`                   Also fail on "incomplete" findings.
 * - `@behat-steps-skip:AccessibilityTrait`    Opt the scenario or feature out entirely.
 *
 * Tool-agnostic. Any engine that runs inside the existing Mink session can
 * be plugged in by overriding `accessibilityRunEngine()` (perform the
 * assessment, return raw results) and `accessibilityNormalizeResults()`
 * (remap raw output into the canonical shape the rest of the trait expects).
 *
 * Reporting. Each scenario writes its own HTML and JUnit report. After the
 * whole suite, a single cross-page `index.html` is written to the same
 * directory, de-duplicating every assessed page and rolling violations up by
 * rule. The aggregate accumulates in process-global state, so under parallel
 * Behat each process writes its own `index.html`.
 */
trait AccessibilityTrait {

  use HelperTrait;

  /**
   * Canonical impact identifiers, ordered from most severe to least.
   *
   * Use these constants when an `accessibilityNormalizeResults()` override
   * maps an engine's native severity vocabulary to the trait's canonical
   * shape. Threshold tags (`@accessibility-critical`, `@accessibility-serious`
   * etc.) and the gate-filter logic both compare against these values.
   */
  public const IMPACT_CRITICAL = 'critical';

  public const IMPACT_SERIOUS = 'serious';

  public const IMPACT_MODERATE = 'moderate';

  public const IMPACT_MINOR = 'minor';

  /**
   * In-memory cache for the engine JavaScript source - fetched once per run.
   */
  protected static ?string $accessibilityCachedJs = NULL;

  /**
   * Working directory captured before any test bootstrap can chdir().
   *
   * The default report directory anchors to this rather than a live
   * `getcwd()` call, which an `@api` bootstrap mutates by chdir()-ing to the
   * docroot - moving reports out of the path-anchored location used by the
   * rest of the run. Captured once at `@BeforeSuite`, before the first
   * scenario, so it records the directory the run was launched from.
   */
  protected static ?string $accessibilityBaseDir = NULL;

  /**
   * Normalized per-scenario results accumulated across the whole suite.
   *
   * Populated as each scenario finalizes and consumed once by the static
   * `@AfterSuite` renderer. URLs are stored already formatted for display.
   *
   * @var array<int, array{feature: string, scenario: string, threshold: string, failOnIncomplete: bool, results: array<int, array{url: string, rules: string, result: array<string, mixed>}>}>
   */
  protected static array $accessibilityAggregate = [];

  /**
   * Report directory captured in the instance phase for the static renderer.
   *
   * The `@AfterSuite` hook is static and cannot call
   * `accessibilityGetReportDir()` (an instance method, overridable per
   * consumer), so the resolved directory is captured while a scenario
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
   * Capture the working directory once, before any scenario can chdir().
   */
  #[BeforeSuite]
  public static function accessibilityCaptureBaseDir(): void {
    if (self::$accessibilityBaseDir === NULL) {
      $cwd = getcwd();
      // Leave the base unset when getcwd() fails so the report directory
      // retries it later rather than locking in an empty, root-relative base.
      if ($cwd !== FALSE) {
        self::$accessibilityBaseDir = $cwd;
      }
    }
  }

  /**
   * Initialize accessibility state for the scenario.
   */
  #[BeforeScenario]
  public function accessibilitySetupScenario(BeforeScenarioScope $scope): void {
    $this->accessibilityResults = [];
    $this->accessibilityAutoMode = FALSE;
    $this->accessibilityLastCheckedUrl = '';
    $this->accessibilityScenarioThreshold = NULL;
    $this->accessibilityScenarioFailOnIncomplete = NULL;

    $this->accessibilitySkip = $scope->getFeature()->hasTag('behat-steps-skip:AccessibilityTrait')
      || $scope->getScenario()->hasTag('behat-steps-skip:AccessibilityTrait');

    if ($this->accessibilitySkip) {
      return;
    }

    $this->accessibilityFeatureName = $scope->getFeature()->getTitle() ?? 'feature';
    $this->accessibilityScenarioName = $scope->getScenario()->getTitle() ?? 'scenario';

    $tags = array_merge(
      $scope->getFeature()->getTags() ?? [],
      $scope->getScenario()->getTags() ?? []
    );
    $this->accessibilityResolveTags($tags);
  }

  /**
   * Run the engine after each step when in automatic mode.
   */
  #[AfterStep]
  public function accessibilityAutoAssess(AfterStepScope $scope): void {
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

    if (in_array($url, [...static::accessibilityBlankUrls(), $this->accessibilityLastCheckedUrl], TRUE)) {
      return;
    }

    $this->accessibilityAssess($this->accessibilityGetDefaultRules());
  }

  /**
   * Write reports and enforce the gate at the end of the scenario.
   */
  #[AfterScenario]
  public function accessibilityFinalizeScenario(AfterScenarioScope $scope): void {
    if ($this->accessibilitySkip) {
      return;
    }

    if ($this->accessibilityResults === []) {
      return;
    }

    $dir = $this->accessibilityGetReportDir();
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }
    $slug = $this->helperSlug($this->accessibilityFeatureName) . '__' . $this->helperSlug($this->accessibilityScenarioName);
    file_put_contents($dir . '/' . $slug . '.html', $this->accessibilityRenderHtml());
    file_put_contents($dir . '/junit-' . $slug . '.xml', $this->accessibilityRenderJunit());

    // Accumulate before the gate so a scenario that fails the gate is still
    // represented in the suite-level aggregate.
    $this->accessibilityAggregateCapture($dir);

    if (!$this->accessibilityAutoMode) {
      return;
    }

    $threshold = $this->accessibilityEffectiveThreshold();
    $check_incomplete = $this->accessibilityEffectiveFailOnIncomplete();
    $messages = [];

    foreach ($this->accessibilityResults as $r) {
      $display_url = $this->accessibilityFormatUrl((string) $r['url']);

      foreach ($this->accessibilityFilterViolations($r['result']['violations'] ?? [], $threshold) as $v) {
        $messages[] = sprintf('  violation [%s] %s on %s', $v['impact'] ?? 'unknown', $v['id'] ?? '', $display_url);
      }
      if ($check_incomplete) {
        foreach ($r['result']['incomplete'] ?? [] as $i) {
          $messages[] = sprintf('  incomplete [%s] %s on %s', $i['impact'] ?? 'unknown', $i['id'] ?? '', $display_url);
        }
      }
    }

    if ($messages !== []) {
      $message = sprintf("Auto accessibility gate failed (threshold=%s, fail_on_incomplete=%s):\n%s", $threshold, $check_incomplete ? 'yes' : 'no', implode("\n", $messages));
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the current page passes accessibility checks.
   *
   * @code
   * Then the current page should pass accessibility checks
   * @endcode
   */
  #[Then('the current page should pass accessibility checks')]
  public function accessibilityAssertCurrentPage(): void {
    $this->accessibilityAssertCurrentPageForTags($this->accessibilityGetDefaultRules());
  }

  /**
   * Assert that the current page passes accessibility checks for given rules.
   *
   * @code
   * Then the current page should pass accessibility checks for tags "wcag2a"
   * @endcode
   */
  #[Then('the current page should pass accessibility checks for tags :rules')]
  public function accessibilityAssertCurrentPageForTags(string $rules): void {
    $result = $this->accessibilityAssess($rules);

    $threshold = $this->accessibilityEffectiveThreshold();
    $check_incomplete = $this->accessibilityEffectiveFailOnIncomplete();

    $violations = $this->accessibilityFilterViolations($result['violations'] ?? [], $threshold);
    $incomplete = $check_incomplete ? ($result['incomplete'] ?? []) : [];

    if ($violations === [] && $incomplete === []) {
      return;
    }

    throw new ExpectationException(
      $this->accessibilityFormatGateMessage(
        $this->getSession()->getCurrentUrl(),
        $rules,
        $threshold,
        $check_incomplete,
        $violations,
        $incomplete
      ),
      $this->getSession()->getDriver()
    );
  }

  /**
   * Return the JavaScript source to inject into the page.
   *
   * Default: fetched once per process from accessibilityGetCdnUrl(). Override
   * to ship the engine script from a vendored package or asset path.
   */
  protected function accessibilityGetJs(): string {
    if (self::$accessibilityCachedJs !== NULL) {
      return self::$accessibilityCachedJs;
    }

    $content = @file_get_contents($this->accessibilityGetCdnUrl());
    if ($content === FALSE || $content === '') {
      throw new \RuntimeException(sprintf('Failed to fetch accessibility engine from %s', $this->accessibilityGetCdnUrl()));
    }

    self::$accessibilityCachedJs = $content;

    return $content;
  }

  /**
   * Return the URL used by the default accessibilityGetJs() implementation.
   *
   * Default: a pinned engine script from a public CDN. Override to point at
   * a different version, a private mirror, or a local asset.
   */
  protected function accessibilityGetCdnUrl(): string {
    return 'https://cdn.jsdelivr.net/npm/axe-core@4.11.4/axe.min.js';
  }

  /**
   * Return the absolute directory used to write per-scenario reports.
   *
   * Default: `.logs/test_results/accessibility/` under the directory the run
   * was launched from (captured at `@BeforeSuite`, so it is stable even after
   * an `@api` bootstrap chdir()s to the docroot), falling back to the live
   * working directory when the suite hook has not run. Override to return an
   * already-absolute path.
   */
  protected function accessibilityGetReportDir(): string {
    $base = self::$accessibilityBaseDir ?? (getcwd() ?: '.');

    return $base . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility';
  }

  /**
   * Return the base tag name that enables automatic mode (no `@` prefix).
   *
   * The trait recognises this exact tag plus suffix variants
   * (`<tag>-critical`, `<tag>-serious`, `<tag>-moderate`, `<tag>-minor`,
   * `<tag>-warning`, `<tag>-strict`, `<tag>-any`) for per-scenario gate
   * configuration. Default: `accessibility`. Override to shorten.
   */
  protected function accessibilityGetAutoTag(): string {
    return 'accessibility';
  }

  /**
   * Return the default rule identifier passed to the engine.
   *
   * Default: WCAG 2.0/2.1 A and AA tag set. Override to use a different
   * rule identifier expected by your engine.
   */
  protected function accessibilityGetDefaultRules(): string {
    return 'wcag2a,wcag2aa';
  }

  /**
   * Return the default failure threshold.
   *
   * One of `any` (fail on any violation), `never` (advisory only), or an
   * impact level from accessibilityGetImpacts(). Default: `any`.
   */
  protected function accessibilityGetFailureThreshold(): string {
    return 'any';
  }

  /**
   * Return TRUE if "incomplete" findings should fail the gate by default.
   *
   * Default: FALSE (incomplete findings are reported but do not fail).
   */
  protected function accessibilityGetFailOnIncomplete(): bool {
    return FALSE;
  }

  /**
   * Return the canonical impact levels in descending severity order.
   *
   * Default: the four `IMPACT_*` constants on this trait. Engines with a
   * different severity vocabulary map to these constants inside
   * `accessibilityNormalizeResults()`.
   *
   * @return array<int, string>
   *   Impact identifiers ordered from most severe to least.
   */
  protected function accessibilityGetImpacts(): array {
    return [
      self::IMPACT_CRITICAL,
      self::IMPACT_SERIOUS,
      self::IMPACT_MODERATE,
      self::IMPACT_MINOR,
    ];
  }

  /**
   * Execute the engine against the current page and return RAW results.
   *
   * Default: injects `accessibilityGetJs()`, runs the engine with the
   * given rule identifier, returns the engine's native output. Override
   * to call a different engine. The return value is fed to
   * `accessibilityNormalizeResults()` before any other trait logic touches
   * it, so the raw shape does not have to match the canonical shape.
   *
   * @param string $rules
   *   Engine-specific rule identifier.
   *
   * @return array<string, mixed>
   *   Raw, engine-specific result array.
   */
  protected function accessibilityRunEngine(string $rules): array {
    $session = $this->getSession();
    $driver = $session->getDriver();
    $driver->executeScript($this->accessibilityGetJs());

    $tag_list = json_encode(array_map(trim(...), explode(',', $rules)));
    $driver->executeScript(sprintf(
      'window.__accessibilityResults = null; axe.run(document, { runOnly: { type: "tag", values: %s } }).then(function (r) { window.__accessibilityResults = r; }).catch(function (e) { window.__accessibilityResults = { error: String(e) }; });',
      $tag_list
    ));

    $session->wait(30000, 'window.__accessibilityResults !== null');
    $results = $session->evaluateScript('return window.__accessibilityResults;');

    if (!is_array($results)) {
      throw new \RuntimeException('Accessibility engine did not return results.');
    }

    if (isset($results['error'])) {
      throw new \RuntimeException('Accessibility engine failed: ' . $results['error']);
    }

    return $results;
  }

  /**
   * Normalize raw engine output into the canonical shape used by the trait.
   *
   * Canonical shape (see class docblock for the full structure):
   * `['violations' => [...], 'incomplete' => [...], 'passes' => [...]]`
   *
   * Default: maps each finding into the canonical fields explicitly. The
   * default engine's native shape happens to share field names with the
   * canonical shape, so this default mostly copies values straight across
   * - but each field is named at the call site so the method also serves
   * as a template for overrides. Override when wiring a different engine
   * to map its native output (e.g. pa11y's `issues[]`, Lighthouse's
   * `audits`) into the canonical structure.
   *
   * @param array<string, mixed> $raw
   *   Raw result from `accessibilityRunEngine()`.
   *
   * @return array<string, mixed>
   *   Normalized result.
   */
  protected function accessibilityNormalizeResults(array $raw): array {
    $normalized = ['violations' => [], 'incomplete' => [], 'passes' => []];

    foreach (['violations', 'incomplete'] as $bucket) {
      foreach ($raw[$bucket] ?? [] as $issue) {
        $impact = strtolower((string) ($issue['impact'] ?? ''));
        switch ($impact) {
          case 'critical':
            $impact = self::IMPACT_CRITICAL;
            break;

          case 'serious':
            $impact = self::IMPACT_SERIOUS;
            break;

          case 'moderate':
            $impact = self::IMPACT_MODERATE;
            break;

          default:
            $impact = self::IMPACT_MINOR;
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
   * @param array<int, string> $tags
   *   Combined feature and scenario tags.
   */
  protected function accessibilityResolveTags(array $tags): void {
    $auto_tag = $this->accessibilityGetAutoTag();
    $impacts = $this->accessibilityGetImpacts();

    foreach ($tags as $tag) {
      if ($tag === $auto_tag) {
        $this->accessibilityAutoMode = TRUE;
        continue;
      }

      if (!str_starts_with($tag, $auto_tag . '-')) {
        continue;
      }

      $variant = strtolower(substr($tag, strlen((string) $auto_tag) + 1));
      $this->accessibilityAutoMode = TRUE;

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
    foreach ($violations as $v) {
      $impact = strtolower((string) ($v['impact'] ?? ''));
      $pos = array_search($impact, $impacts, TRUE);
      if ($pos !== FALSE && $pos <= $threshold_pos) {
        $filtered[] = $v;
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
  protected function accessibilityAssess(string $rules): array {
    $raw = $this->accessibilityRunEngine($rules);
    $normalized = $this->accessibilityNormalizeResults($raw);

    $url = $this->getSession()->getCurrentUrl();
    $this->accessibilityResults[] = ['url' => $url, 'rules' => $rules, 'result' => $normalized];
    $this->accessibilityLastCheckedUrl = $url;

    fwrite(STDOUT, sprintf("\n[accessibility] %s: %d violations, %d passes, %d incomplete (rules: %s)\n",
      $this->accessibilityFormatUrl($url),
      count($normalized['violations'] ?? []),
      count($normalized['passes'] ?? []),
      count($normalized['incomplete'] ?? []),
      $rules
    ));

    return $normalized;
  }

  /**
   * Build the human-readable error message for the explicit assertion.
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

    foreach ($violations as $v) {
      $lines[] = sprintf('  violation [%s] %s - %s', $v['impact'] ?? 'unknown', $v['id'], $v['help']);
      $lines[] = sprintf('    %s', $v['helpUrl']);
      foreach ($v['nodes'] ?? [] as $node) {
        $lines[] = sprintf('    -> %s', static::accessibilityStringifyTarget($node['target'] ?? []));
        $html = trim((string) ($node['html'] ?? ''));
        if ($html !== '') {
          $lines[] = sprintf('       %s', mb_strimwidth($html, 0, 160, '...'));
        }
      }
    }

    foreach ($incomplete as $i) {
      $lines[] = sprintf('  incomplete [%s] %s - %s', $i['impact'] ?? 'unknown', $i['id'], $i['help']);
      $lines[] = sprintf('    %s', $i['helpUrl']);
      foreach ($i['nodes'] ?? [] as $node) {
        $lines[] = sprintf('    -> %s', static::accessibilityStringifyTarget($node['target'] ?? []));
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Flatten a node target array into a human-readable string.
   *
   * @param array<int, mixed> $target
   *   Canonical `target` value of a node entry.
   */
  protected static function accessibilityStringifyTarget(array $target): string {
    return implode(' > ', array_map(fn($t): string => is_array($t) ? implode(' ', $t) : (string) $t, $target));
  }

  /**
   * Format a page URL for display in reports and gate messages.
   *
   * Default: strip the configured Mink `base_url` prefix so reports show the
   * page path (`/contact`) rather than the internal host and port
   * (`http://nginx:8080/contact`), which is noise and makes reports
   * non-portable. The base URL itself maps to `/` and the query string is
   * kept. Only the known `base_url` is stripped: a genuinely cross-origin URL
   * captured during assessment stays absolute, so it remains distinguishable.
   * Override to keep the absolute URL or to format it differently.
   */
  protected function accessibilityFormatUrl(string $url): string {
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
   * Render the scenario-level HTML report from collected results.
   *
   * Composes the page wrapper around the per-URL section markup. The two
   * pieces are split so consumers can rebrand the page without touching
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
   * external stylesheet, project logo, etc.) without having to rebuild
   * the section markup - the caller already supplies it as `$sections`.
   *
   * @param string $sections
   *   Pre-rendered per-URL section markup from
   *   accessibilityRenderHtmlSections().
   */
  protected function accessibilityRenderHtmlPage(string $sections): string {
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
   * Render the per-URL section markup (one `<section>` per visited URL).
   *
   * Returns only the inner content that the page wrapper embeds. Override
   * to change how each section renders (rare); for branding the
   * surrounding page, override accessibilityRenderHtmlPage() instead.
   */
  protected function accessibilityRenderHtmlSections(): string {
    $body_sections = [];

    foreach ($this->accessibilityResults as $r) {
      $url = htmlspecialchars($this->accessibilityFormatUrl((string) $r['url']), ENT_QUOTES);
      $rules = htmlspecialchars((string) $r['rules'], ENT_QUOTES);
      $violations = $r['result']['violations'] ?? [];
      $incomplete = $r['result']['incomplete'] ?? [];
      $passes_count = count($r['result']['passes'] ?? []);

      $section = sprintf('<section class="page"><h2>%s</h2><p class="meta">Rules: <code>%s</code> &middot; %d violations &middot; %d incomplete &middot; %d passes</p>', $url, $rules, count($violations), count($incomplete), $passes_count);

      $section .= $this->accessibilityRenderIssueList('Violations', 'violation', $violations);
      $section .= $this->accessibilityRenderIssueList('Incomplete (needs human review)', 'incomplete', $incomplete);
      $section .= '</section>';
      $body_sections[] = $section;
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
   */
  protected function accessibilityRenderJunit(): string {
    $suites_xml = '';
    $total_tests = 0;
    $total_failures = 0;

    foreach ($this->accessibilityResults as $r) {
      $url = $this->accessibilityFormatUrl((string) $r['url']);
      $violations = $r['result']['violations'] ?? [];
      $passes = $r['result']['passes'] ?? [];

      $tests = count($violations) + count($passes);
      $failures = count($violations);
      $total_tests += $tests;
      $total_failures += $failures;

      $cases_xml = '';
      foreach ($violations as $v) {
        $rule_id = (string) ($v['id'] ?? 'unknown');
        $impact = (string) ($v['impact'] ?? 'unknown');
        $help = (string) ($v['help'] ?? '');
        $help_url = (string) ($v['helpUrl'] ?? '');

        foreach ($v['nodes'] ?? [] as $node) {
          $target = static::accessibilityStringifyTarget($node['target'] ?? []);
          $html = trim((string) ($node['html'] ?? ''));

          $message = sprintf('[%s] %s', $impact, $help);
          $details = sprintf("URL: %s\nRule: %s\nTarget: %s\nHTML: %s\nDocs: %s", $url, $rule_id, $target, $html, $help_url);

          $cases_xml .= sprintf(
            '<testcase classname="accessibility.%s" name="%s"><failure type="%s" message="%s">%s</failure></testcase>',
            htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES),
            htmlspecialchars((string) $target ?: $rule_id, ENT_XML1 | ENT_QUOTES),
            htmlspecialchars($impact, ENT_XML1 | ENT_QUOTES),
            htmlspecialchars($message, ENT_XML1 | ENT_QUOTES),
            htmlspecialchars($details, ENT_XML1 | ENT_QUOTES)
          );
        }
      }

      foreach ($passes as $p) {
        $rule_id = (string) ($p['id'] ?? 'unknown');
        $cases_xml .= sprintf('<testcase classname="accessibility.%s" name="%s passed"/>', htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES), htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES));
      }

      $suites_xml .= sprintf('<testsuite name="%s" tests="%d" failures="%d" errors="0">%s</testsuite>', htmlspecialchars((string) $url, ENT_XML1 | ENT_QUOTES), $tests, $failures, $cases_xml);
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
   * Return URL values that represent a blank tab rather than a real page.
   *
   * Shared by the per-step auto assessment (so a blank tab never enters the
   * results) and the aggregate renderer (defence in depth). Override to extend.
   *
   * @return array<int, string>
   *   URL values to ignore.
   */
  protected static function accessibilityBlankUrls(): array {
    return ['', 'about:blank', 'data:,'];
  }

  /**
   * Clear the suite-level aggregate state before the suite runs.
   *
   * The accumulator is process-global, so resetting at suite start stops a
   * second suite in the same process from inheriting the first one's results.
   */
  #[BeforeSuite]
  public static function accessibilityAggregateReset(): void {
    self::$accessibilityAggregate = [];
    self::$accessibilityAggregateReportDir = NULL;
  }

  /**
   * Record the scenario's formatted results for the suite-level aggregate.
   *
   * URLs are formatted here, in the instance phase, so the static renderer can
   * reuse the one `accessibilityFormatUrl()` helper (and any consumer override
   * of it) without an instance to call it on.
   *
   * @param string $dir
   *   The resolved per-scenario report directory, captured for the static
   *   `@AfterSuite` renderer.
   */
  protected function accessibilityAggregateCapture(string $dir): void {
    self::$accessibilityAggregateReportDir = $dir;

    $results = [];
    foreach ($this->accessibilityResults as $result) {
      $results[] = [
        'url' => $this->accessibilityFormatUrl((string) $result['url']),
        'rules' => (string) $result['rules'],
        'result' => $result['result'],
      ];
    }

    self::$accessibilityAggregate[] = [
      'feature' => $this->accessibilityFeatureName,
      'scenario' => $this->accessibilityScenarioName,
      'threshold' => $this->accessibilityEffectiveThreshold(),
      'failOnIncomplete' => $this->accessibilityEffectiveFailOnIncomplete(),
      'results' => $results,
    ];
  }

  /**
   * Render the single cross-page report after the whole suite has run.
   */
  #[AfterSuite]
  public static function accessibilityAggregateRender(): void {
    static::accessibilityWriteAggregateReport();
  }

  /**
   * Write the aggregate report when at least one scenario produced results.
   *
   * The timestamp is resolved here and passed into the renderer so the render
   * methods stay deterministic for tests.
   */
  protected static function accessibilityWriteAggregateReport(): void {
    if (self::$accessibilityAggregate === []) {
      return;
    }

    $dir = self::$accessibilityAggregateReportDir ?? (getcwd() ?: '.') . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility';
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }

    file_put_contents($dir . '/index.html', static::accessibilityRenderAggregateHtml(self::$accessibilityAggregate, date('Y-m-d H:i')));
  }

  /**
   * Build the full aggregate HTML document.
   *
   * Composes the page wrapper around the body sections. Override
   * accessibilityRenderAggregatePage() to rebrand the page, or
   * accessibilityRenderAggregateSections() to change the body, without
   * rewriting the aggregation logic.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   * @param string $generated
   *   Human-readable generation timestamp.
   */
  protected static function accessibilityRenderAggregateHtml(array $aggregate, string $generated): string {
    return static::accessibilityRenderAggregatePage($generated, static::accessibilityRenderAggregateSections($aggregate));
  }

  /**
   * Compose the aggregate body: summary, pages, rules, per-scenario detail.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   */
  protected static function accessibilityRenderAggregateSections(array $aggregate): string {
    $pages = static::accessibilityAggregatePages($aggregate);
    $rollup = static::accessibilityAggregateRollup($pages);
    $totals = $rollup['totals'];

    return static::accessibilityRenderAggregateSummary((int) array_sum($totals), count($pages), count($aggregate), $totals)
      . static::accessibilityRenderAggregatePages($pages)
      . static::accessibilityRenderAggregateRules($rollup['rules'])
      . static::accessibilityRenderAggregateScenarios($aggregate);
  }

  /**
   * De-duplicate assessed pages by URL across every scenario.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   *
   * @return array<string, array<string, mixed>>
   *   One entry per unique URL, in first-seen order, each holding its
   *   violations, incomplete and passes counts, and visiting scenarios.
   */
  protected static function accessibilityAggregatePages(array $aggregate): array {
    $blank = static::accessibilityBlankUrls();
    $pages = [];

    foreach ($aggregate as $entry) {
      foreach ($entry['results'] ?? [] as $result) {
        $url = (string) ($result['url'] ?? '');

        if (in_array($url, $blank, TRUE)) {
          continue;
        }

        if (!isset($pages[$url])) {
          $pages[$url] = [
            'violations' => $result['result']['violations'] ?? [],
            'incomplete' => count($result['result']['incomplete'] ?? []),
            'passes' => count($result['result']['passes'] ?? []),
            'scenarios' => [],
          ];
        }

        $pages[$url]['scenarios'][(string) ($entry['scenario'] ?? '')] = TRUE;
      }
    }

    return $pages;
  }

  /**
   * Roll violations up by rule and tally totals by impact.
   *
   * Rules are sorted highest-impact first, then by affected-element count.
   *
   * @param array<string, array<string, mixed>> $pages
   *   Per-URL rollup from accessibilityAggregatePages().
   *
   * @return array{rules: array<string, array<string, mixed>>, totals: array<string, int>}
   *   Severity-sorted rules and per-impact totals.
   */
  protected static function accessibilityAggregateRollup(array $pages): array {
    $rank = [self::IMPACT_CRITICAL => 0, self::IMPACT_SERIOUS => 1, self::IMPACT_MODERATE => 2, self::IMPACT_MINOR => 3];
    $rules = [];
    $totals = [
      self::IMPACT_CRITICAL => 0,
      self::IMPACT_SERIOUS => 0,
      self::IMPACT_MODERATE => 0,
      self::IMPACT_MINOR => 0,
    ];

    foreach ($pages as $url => $page) {
      foreach ($page['violations'] ?? [] as $violation) {
        $impact = (string) ($violation['impact'] ?? self::IMPACT_MINOR);
        $totals[$impact] = ($totals[$impact] ?? 0) + 1;

        $rule_id = (string) ($violation['id'] ?? 'unknown');

        if (!isset($rules[$rule_id])) {
          $rules[$rule_id] = [
            'impact' => $impact,
            'help' => (string) ($violation['help'] ?? ''),
            'helpUrl' => (string) ($violation['helpUrl'] ?? ''),
            'pages' => [],
            'nodes' => [],
          ];
        }

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

    uasort($rules, function (array $a, array $b) use ($rank): int {
      $by_impact = ($rank[$a['impact']] ?? 9) <=> ($rank[$b['impact']] ?? 9);

      return $by_impact !== 0 ? $by_impact : count($b['nodes']) <=> count($a['nodes']);
    });

    return ['rules' => $rules, 'totals' => $totals];
  }

  /**
   * Render the summary cards.
   *
   * @param int $total_violations
   *   Total violation count across all pages.
   * @param int $page_count
   *   Number of unique pages assessed.
   * @param int $scenario_count
   *   Number of scenarios that produced results.
   * @param array<string, int> $totals
   *   Violation counts keyed by impact level.
   */
  protected static function accessibilityRenderAggregateSummary(int $total_violations, int $page_count, int $scenario_count, array $totals): string {
    $state = $total_violations > 0 ? 'fail' : 'ok';

    $card = fn(string $class, int $num, string $label): string => sprintf('<div class="card %s"><span class="num">%d</span><span class="lbl">%s</span></div>', $class, $num, $label);

    return '<section class="cards">'
      . $card('', $page_count, 'pages assessed')
      . $card('', $scenario_count, 'scenarios')
      . $card($state, $total_violations, 'violations')
      . $card('crit', $totals[self::IMPACT_CRITICAL] ?? 0, 'critical')
      . $card('ser', $totals[self::IMPACT_SERIOUS] ?? 0, 'serious')
      . $card('mod', $totals[self::IMPACT_MODERATE] ?? 0, 'moderate')
      . $card('min', $totals[self::IMPACT_MINOR] ?? 0, 'minor')
      . '</section>';
  }

  /**
   * Render the de-duplicated table of assessed pages.
   *
   * @param array<string, array<string, mixed>> $pages
   *   Per-URL rollup.
   */
  protected static function accessibilityRenderAggregatePages(array $pages): string {
    $rows = '';

    foreach ($pages as $url => $page) {
      $count = count($page['violations'] ?? []);
      $scenarios = implode(', ', array_keys($page['scenarios'] ?? []));
      $rows .= sprintf(
        '<tr class="%s"><td class="url">%s</td><td class="vtypes">%s</td><td class="n">%d</td><td class="n">%d</td><td class="muted">%s</td></tr>',
        $count > 0 ? 'bad' : 'good',
        htmlspecialchars((string) $url, ENT_QUOTES),
        static::accessibilityRenderAggregateRuleChips($page['violations'] ?? []),
        $page['incomplete'] ?? 0,
        $page['passes'] ?? 0,
        htmlspecialchars($scenarios, ENT_QUOTES)
      );
    }

    return '<section><h2>Pages assessed</h2><p class="meta">Each URL is listed once, even when several scenarios visit it. Violations are broken down by rule, with the number of affected elements.</p>'
      . '<table><thead><tr><th>URL</th><th>Violations by type</th><th>Incomplete</th><th>Passes</th><th>Seen in scenarios</th></tr></thead><tbody>'
      . $rows . '</tbody></table></section>';
  }

  /**
   * Render per-rule violation chips for a page, each with its element count.
   *
   * @param array<int, array<string, mixed>> $violations
   *   Normalized violations for a single page.
   */
  protected static function accessibilityRenderAggregateRuleChips(array $violations): string {
    if ($violations === []) {
      return '<span class="muted">&mdash;</span>';
    }

    $chips = '';

    foreach ($violations as $violation) {
      $impact = strtolower((string) ($violation['impact'] ?? self::IMPACT_MINOR));
      $chips .= sprintf(
        '<span class="vtype %s">%s <b>%d</b></span>',
        htmlspecialchars($impact, ENT_QUOTES),
        htmlspecialchars((string) ($violation['id'] ?? 'unknown'), ENT_QUOTES),
        count($violation['nodes'] ?? [])
      );
    }

    return $chips;
  }

  /**
   * Render the violations grouped by rule.
   *
   * @param array<string, array<string, mixed>> $rules
   *   Per-rule rollup, pre-sorted by severity.
   */
  protected static function accessibilityRenderAggregateRules(array $rules): string {
    if ($rules === []) {
      return '<section><h2>Violations by rule</h2><p class="meta">No violations found.</p></section>';
    }

    $blocks = '';

    foreach ($rules as $rule_id => $rule) {
      $nodes = '';
      foreach ($rule['nodes'] ?? [] as $node) {
        $nodes .= sprintf(
          '<div class="node"><div class="where"><code>%s</code> &middot; <span class="muted">%s</span></div><pre>%s</pre></div>',
          htmlspecialchars((string) $node['target'], ENT_QUOTES),
          htmlspecialchars((string) $node['url'], ENT_QUOTES),
          htmlspecialchars((string) $node['html'], ENT_QUOTES)
        );
      }

      $impact = (string) $rule['impact'];
      $blocks .= sprintf(
        '<div class="rule"><h3><span class="impact %s">%s</span> <span class="rule-id">%s</span></h3>'
        . '<p class="meta">%s &middot; affects %d page(s) &middot; %d element(s) &middot; <a href="%s" target="_blank" rel="noopener">docs</a></p>%s</div>',
        htmlspecialchars($impact, ENT_QUOTES),
        htmlspecialchars($impact, ENT_QUOTES),
        htmlspecialchars((string) $rule_id, ENT_QUOTES),
        htmlspecialchars((string) $rule['help'], ENT_QUOTES),
        count($rule['pages'] ?? []),
        count($rule['nodes'] ?? []),
        htmlspecialchars((string) $rule['helpUrl'], ENT_QUOTES),
        $nodes
      );
    }

    return '<section><h2>Violations by rule</h2><p class="meta">Highest impact first.</p>' . $blocks . '</section>';
  }

  /**
   * Render each scenario's full findings inline, in the order pages were seen.
   *
   * Mirrors the per-scenario report: a scenario heading with its threshold,
   * then per page the rules string, the counts, and the violation and
   * incomplete lists with the same fields.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   The accumulated per-scenario results.
   */
  protected static function accessibilityRenderAggregateScenarios(array $aggregate): string {
    $blank = static::accessibilityBlankUrls();
    $blocks = '';

    foreach ($aggregate as $entry) {
      $sections = '';

      foreach ($entry['results'] ?? [] as $result) {
        $url = (string) ($result['url'] ?? '');

        if (in_array($url, $blank, TRUE)) {
          continue;
        }

        $violations = $result['result']['violations'] ?? [];
        $incomplete = $result['result']['incomplete'] ?? [];

        $sections .= sprintf(
          '<div class="page-detail"><h4>%s</h4><p class="meta">Rules: <code>%s</code> &middot; %d violations &middot; %d incomplete &middot; %d passes</p>%s%s</div>',
          htmlspecialchars($url, ENT_QUOTES),
          htmlspecialchars((string) ($result['rules'] ?? ''), ENT_QUOTES),
          count($violations),
          count($incomplete),
          count($result['result']['passes'] ?? []),
          static::accessibilityRenderAggregateIssueList('Violations', 'violation', $violations),
          static::accessibilityRenderAggregateIssueList('Incomplete (needs human review)', 'incomplete', $incomplete)
        );
      }

      $blocks .= sprintf(
        '<div class="scenario"><h3>%s <span class="muted">%s</span></h3><p class="meta">threshold: <code>%s</code> &middot; fail on incomplete: <code>%s</code></p>%s</div>',
        htmlspecialchars((string) ($entry['scenario'] ?? ''), ENT_QUOTES),
        htmlspecialchars((string) ($entry['feature'] ?? ''), ENT_QUOTES),
        htmlspecialchars((string) ($entry['threshold'] ?? ''), ENT_QUOTES),
        ($entry['failOnIncomplete'] ?? FALSE) ? 'yes' : 'no',
        $sections
      );
    }

    return '<section><h2>Per-scenario detail</h2><p class="meta">Every page each scenario assessed, in order, with its full findings embedded.</p>' . $blocks . '</section>';
  }

  /**
   * Render a single issue list (violations or incomplete) for the aggregate.
   *
   * Matches the per-scenario report fields: impact, rule id, help text, docs
   * link, and the target and HTML of each offending element.
   *
   * @param string $heading
   *   Section heading.
   * @param string $css_class
   *   CSS class applied to each issue (`violation` or `incomplete`).
   * @param array<int, array<string, mixed>> $issues
   *   Normalized issues to render.
   */
  protected static function accessibilityRenderAggregateIssueList(string $heading, string $css_class, array $issues): string {
    if ($issues === []) {
      return sprintf('<h5>%s</h5><p class="meta">None.</p>', htmlspecialchars($heading, ENT_QUOTES));
    }

    $out = sprintf('<h5>%s</h5>', htmlspecialchars($heading, ENT_QUOTES));

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
   * Wrap the rendered sections in a standalone HTML document.
   *
   * Default: a self-contained page with the aggregate's own styles. Override
   * to brand the report without rebuilding the section markup - the caller
   * supplies it as `$body`.
   *
   * @param string $generated
   *   Human-readable generation timestamp.
   * @param string $body
   *   Pre-rendered body sections.
   */
  protected static function accessibilityRenderAggregatePage(string $generated, string $body): string {
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
<p class="meta">One page summarising every accessibility assessment in the run &middot; generated {$generated}</p>
{$body}
</body>
</html>
HTML;
  }

}
