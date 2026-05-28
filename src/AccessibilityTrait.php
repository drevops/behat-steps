<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;

/**
 * Assess accessibility of rendered pages.
 *
 * - Explicit step assertion or automatic mode via `@accessibility*` tags.
 * - Per-scenario threshold and gate composition via tags.
 * - One HTML and one JUnit XML report per scenario, one section per URL.
 *
 * Tool-agnostic. The default engine is axe-core fetched from a CDN, but any
 * tool can be plugged in by overriding `accessibilityRunEngine()` (perform
 * the assessment, return raw results) and `accessibilityNormalizeResults()`
 * (remap raw output into the canonical shape the rest of the trait expects).
 *
 * Canonical result shape:
 * @code
 * [
 *   'violations' => [
 *     [
 *       'id'      => 'rule-id',
 *       'impact'  => 'critical|serious|moderate|minor',
 *       'help'    => 'Human-readable help text',
 *       'helpUrl' => 'https://...',
 *       'nodes'   => [
 *         ['target' => ['css selector'], 'html' => '<element />'],
 *       ],
 *     ],
 *   ],
 *   'incomplete' => [ ...same shape as violations... ],
 *   'passes'     => [ ['id' => 'rule-id'], ... ],
 * ]
 * @endcode
 */
trait AccessibilityTrait {

  /**
   * In-memory cache for the engine JavaScript source - fetched once per run.
   */
  protected static ?string $accessibilityCachedJs = NULL;

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
    $slug = $this->accessibilitySlug($this->accessibilityFeatureName) . '__' . $this->accessibilitySlug($this->accessibilityScenarioName);
    file_put_contents($dir . '/' . $slug . '.html', $this->accessibilityRenderHtml());
    file_put_contents($dir . '/junit-' . $slug . '.xml', $this->accessibilityRenderJunit());

    if (!$this->accessibilityAutoMode) {
      return;
    }

    $threshold = $this->accessibilityEffectiveThreshold();
    $check_incomplete = $this->accessibilityEffectiveFailOnIncomplete();
    $messages = [];

    foreach ($this->accessibilityResults as $r) {
      foreach ($this->accessibilityFilterViolations($r['result']['violations'] ?? [], $threshold) as $v) {
        $messages[] = sprintf('  violation [%s] %s on %s', $v['impact'] ?? 'unknown', $v['id'] ?? '', $r['url']);
      }
      if ($check_incomplete) {
        foreach ($r['result']['incomplete'] ?? [] as $i) {
          $messages[] = sprintf('  incomplete [%s] %s on %s', $i['impact'] ?? 'unknown', $i['id'] ?? '', $r['url']);
        }
      }
    }

    if ($messages !== []) {
      throw new ExpectationException(
        sprintf(
          "Auto accessibility gate failed (threshold=%s, fail_on_incomplete=%s):\n%s",
          $threshold,
          $check_incomplete ? 'yes' : 'no',
          implode("\n", $messages)
        ),
        $this->getSession()->getDriver()
      );
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
   * Default: axe-core v4.11.4 from jsDelivr CDN. Override to pin a different
   * version or serve from a private mirror.
   */
  protected function accessibilityGetCdnUrl(): string {
    return 'https://cdn.jsdelivr.net/npm/axe-core@4.11.4/axe.min.js';
  }

  /**
   * Return the absolute directory used to write per-scenario reports.
   *
   * Default: `.logs/test_results/accessibility/` relative to the current
   * working directory. Override to return an already-absolute path.
   */
  protected function accessibilityGetReportDir(): string {
    return getcwd() . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility';
  }

  /**
   * Return the base tag name that enables automatic mode (no `@` prefix).
   *
   * The trait recognises this exact tag plus suffix variants
   * (`<tag>-critical`, `<tag>-serious`, `<tag>-moderate`, `<tag>-minor`,
   * `<tag>-warning`, `<tag>-strict`, `<tag>-any`) for per-scenario gate
   * configuration. Default: `accessibility`. Override to shorten (e.g.
   * `a11y` or `axe`).
   */
  protected function accessibilityGetAutoTag(): string {
    return 'accessibility';
  }

  /**
   * Return the default rule identifier passed to the engine.
   *
   * Default: `wcag2a,wcag2aa` (axe-core's WCAG 2.0/2.1 A and AA tag set).
   * Override to use a different rule identifier expected by your engine.
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
   * Return the impact levels in descending severity order.
   *
   * Default: axe-core's `critical, serious, moderate, minor`. Override to
   * match your engine's severity vocabulary.
   *
   * @return array<int, string>
   *   Impact level names ordered from most severe to least.
   */
  protected function accessibilityGetImpacts(): array {
    return ['critical', 'serious', 'moderate', 'minor'];
  }

  /**
   * Execute the engine against the current page and return RAW results.
   *
   * Default: injects accessibilityGetJs(), runs axe.run() with the given
   * rule tags, returns axe-core's native output. Override to call a
   * different engine (pa11y, Lighthouse, custom JS, etc.). The return value
   * is fed to accessibilityNormalizeResults() before any other trait logic
   * touches it, so the raw shape does not have to match the canonical
   * shape.
   *
   * @param string $rules
   *   Engine-specific rule identifier (e.g. axe-core tag list).
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
   * Canonical shape (see class docblock for full detail):
   * `['violations' => [...], 'incomplete' => [...], 'passes' => [...]]`
   *
   * Default: identity. axe-core's output already matches the canonical
   * shape, so no remap is needed. Override when wiring a different engine
   * to translate its native output (e.g. pa11y's `issues[]` array, or
   * Lighthouse's `audits` object) into the canonical structure.
   *
   * @param array<string, mixed> $raw
   *   Raw result from accessibilityRunEngine().
   *
   * @return array<string, mixed>
   *   Normalized result.
   */
  protected function accessibilityNormalizeResults(array $raw): array {
    return $raw;
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
      $url,
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
      sprintf(
        'Accessibility gate failed on %s (rules: %s, threshold: %s, fail_on_incomplete: %s):',
        $url,
        $rules,
        $threshold,
        $check_incomplete ? 'yes' : 'no'
      ),
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
   * Convert an arbitrary string into a filesystem-safe slug for filenames.
   */
  protected function accessibilitySlug(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

    return trim($s, '-') ?: 'untitled';
  }

  /**
   * Render the scenario-level HTML report from collected results.
   */
  protected function accessibilityRenderHtml(): string {
    $title = htmlspecialchars($this->accessibilityFeatureName . ' > ' . $this->accessibilityScenarioName, ENT_QUOTES);
    $body_sections = [];

    foreach ($this->accessibilityResults as $r) {
      $url = htmlspecialchars((string) $r['url'], ENT_QUOTES);
      $rules = htmlspecialchars((string) $r['rules'], ENT_QUOTES);
      $violations = $r['result']['violations'] ?? [];
      $incomplete = $r['result']['incomplete'] ?? [];
      $passes_count = count($r['result']['passes'] ?? []);

      $section = sprintf(
        '<section class="page"><h2>%s</h2><p class="meta">Rules: <code>%s</code> &middot; %d violations &middot; %d incomplete &middot; %d passes</p>',
        $url,
        $rules,
        count($violations),
        count($incomplete),
        $passes_count
      );

      $section .= $this->accessibilityRenderIssueList('Violations', 'violation', $violations);
      $section .= $this->accessibilityRenderIssueList('Incomplete (needs human review)', 'incomplete', $incomplete);
      $section .= '</section>';
      $body_sections[] = $section;
    }

    $body = implode("\n", $body_sections);
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
{$body}
</body>
</html>
HTML;
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

      $out .= sprintf(
        '<div class="issue %s"><span class="impact %s">%s</span><span class="rule-id">%s</span> &mdash; %s',
        htmlspecialchars($css_class, ENT_QUOTES),
        $impact_safe,
        $impact_safe,
        $id,
        $help
      );

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
      $url = $r['url'];
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
        $cases_xml .= sprintf(
          '<testcase classname="accessibility.%s" name="%s passed"/>',
          htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES),
          htmlspecialchars($rule_id, ENT_XML1 | ENT_QUOTES)
        );
      }

      $suites_xml .= sprintf(
        '<testsuite name="%s" tests="%d" failures="%d" errors="0">%s</testsuite>',
        htmlspecialchars((string) $url, ENT_XML1 | ENT_QUOTES),
        $tests,
        $failures,
        $cases_xml
      );
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
