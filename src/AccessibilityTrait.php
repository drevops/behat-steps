<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
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
      self::$accessibilityBaseDir = $cwd === FALSE ? '' : $cwd;
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

    if (in_array($url, ['', 'about:blank', $this->accessibilityLastCheckedUrl], TRUE)) {
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
    $base = self::$accessibilityBaseDir ?? (getcwd() ?: '');

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
        $lines[] = sprintf('    -> %s', $this->accessibilityStringifyTarget($node['target'] ?? []));
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
        $lines[] = sprintf('    -> %s', $this->accessibilityStringifyTarget($node['target'] ?? []));
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
  protected function accessibilityStringifyTarget(array $target): string {
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
        $target = htmlspecialchars($this->accessibilityStringifyTarget($node['target'] ?? []), ENT_QUOTES);
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
          $target = $this->accessibilityStringifyTarget($node['target'] ?? []);
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

}
