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
 * Accessibility testing via axe-core.
 *
 * Provides two ways to assess accessibility within Behat scenarios:
 *
 *  1. Explicit step ('Then the current page should pass accessibility
 *     checks'). Throws on violations at the point the step runs.
 *
 *  2. Automatic mode - tag the scenario or feature with '@axe' or a
 *     variant such as '@axe-critical', '@axe-warning', '@axe-strict'.
 *     axe-core runs automatically after every step that changes the URL.
 *     Violations are collected silently during the scenario and reported
 *     once at the end so every visited URL gets a full assessment.
 *
 * Reports
 * -------
 * Each scenario that runs axe at least once produces a single HTML and
 * a single JUnit XML file in the report directory (default
 * '.logs/test_results/a11y/'). Both reports contain one section per URL
 * checked during the scenario - so if a scenario visits three pages,
 * you get one HTML file with three sections (and one JUnit file with
 * three test suites). No per-page files; the unit of aggregation is
 * the scenario.
 *
 * What axe-core returns
 * ---------------------
 * axe-core groups its findings into four buckets per page:
 *
 *   - violations:    Rules axe confidently failed (for example an <img>
 *                    with no alt, contrast 3.13:1). These are the
 *                    "errors".
 *   - incomplete:    Rules axe could not definitively determine - they
 *                    need human review (for example a contrast check
 *                    over a gradient where axe cannot compute the
 *                    actual value).
 *   - passes:        Rules that succeeded.
 *   - inapplicable:  Rules that do not apply (no images on the page
 *                    means image-alt has nothing to check). Ignored.
 *
 * Each violation has an "impact" level, defined by axe-core:
 *
 *   - critical:  Severe issue, blocks users.
 *   - serious:   Significant barrier.
 *   - moderate:  Real but workable issue.
 *   - minor:     Cosmetic or edge case.
 *
 * Gate (when a scenario fails)
 * ----------------------------
 * By default, the gate fails on any axe violation regardless of impact;
 * "incomplete" is surfaced in the report but does not fail. Both
 * posture decisions are configurable per-scenario via tags or globally
 * via overriding the protected methods listed under "Extension points":
 *
 *   - '@axe'              Auto-mode, default threshold.
 *   - '@axe-critical'     Auto-mode, fail only on critical impact.
 *   - '@axe-serious'      Auto-mode, fail on critical or serious.
 *   - '@axe-moderate'     Auto-mode, fail on critical/serious/moderate.
 *   - '@axe-minor'        Auto-mode, fail on any impact.
 *   - '@axe-warning'      Auto-mode, never fail (log only).
 *   - '@axe-strict'       Also fail on "incomplete" findings.
 *
 * Multiple tags compose: '@axe-critical @axe-strict' means "fail only
 * on critical impact violations, and also fail on any incomplete".
 *
 * Extension points
 * ----------------
 * The using class can override any of the protected getter methods to
 * change defaults without touching this trait:
 *
 *   - getA11yJs()                Source of the axe-core JavaScript.
 *   - getA11yCdnUrl()            URL the default getA11yJs() fetches.
 *   - getA11yAxeVersion()        Version string used to build the URL.
 *   - getA11yReportDir()         Where to write report files.
 *   - getA11yAutoTag()           Tag name that enables automatic mode.
 *   - getA11yDefaultRules()      WCAG rule tags used when none passed.
 *   - getA11yFailureThreshold()  Default gate threshold.
 *   - getA11yFailOnIncomplete()  Whether incomplete fails the gate.
 *   - getA11yImpacts()           Impact order (critical first).
 *
 * Skip processing with tags: `@behat-steps-skip:A11yTrait`
 *
 * Automatic mode example:
 * @code
 * @javascript @axe
 * Scenario: Visit pages with accessibility checks running automatically
 *   Given I visit "/home"
 *   When I click "About"
 *   Then I should see "About Us"
 * @endcode
 *
 * Explicit assertion example:
 * @code
 * @javascript
 * Scenario: Assert accessibility at a specific point
 *   Given I visit "/contact"
 *   Then the current page should pass accessibility checks
 * @endcode
 */
trait A11yTrait {

  protected const A11Y_AXE_VERSION = '4.11.4';

  protected const A11Y_CDN_TEMPLATE = 'https://cdn.jsdelivr.net/npm/axe-core@%s/axe.min.js';

  protected const A11Y_REPORT_DIR = '.logs/test_results/a11y';

  protected const A11Y_AUTO_TAG = 'axe';

  protected const A11Y_DEFAULT_RULES = 'wcag2a,wcag2aa';

  protected const A11Y_DEFAULT_THRESHOLD = 'any';

  protected const A11Y_IMPACTS = ['critical', 'serious', 'moderate', 'minor'];

  /**
   * In-memory cache for the axe-core source - fetched once per process.
   */
  protected static ?string $a11yCachedJs = NULL;

  /**
   * Axe results collected during the current scenario.
   *
   * @var array<int, array{url: string, rules: string, result: array<string, mixed>}>
   */
  protected array $a11yResults = [];

  /**
   * Feature title captured at scenario start for report metadata.
   */
  protected string $a11yFeatureName = '';

  /**
   * Scenario title captured at scenario start for report metadata.
   */
  protected string $a11yScenarioName = '';

  /**
   * Whether automatic mode (per-step axe-core run) is enabled.
   */
  protected bool $a11yAutoMode = FALSE;

  /**
   * URL of the last page checked in automatic mode to avoid duplicate runs.
   */
  protected string $a11yLastCheckedUrl = '';

  /**
   * Per-scenario threshold override resolved from tags.
   */
  protected ?string $a11yScenarioThreshold = NULL;

  /**
   * Per-scenario incomplete-fail override resolved from tags.
   */
  protected ?bool $a11yScenarioFailOnIncomplete = NULL;

  /**
   * Initialize accessibility state for the scenario.
   */
  #[BeforeScenario]
  public function a11ySetupScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:A11yTrait')) {
      return;
    }

    $this->a11yResults = [];
    $this->a11yFeatureName = $scope->getFeature()->getTitle() ?? 'feature';
    $this->a11yScenarioName = $scope->getScenario()->getTitle() ?? 'scenario';
    $this->a11yLastCheckedUrl = '';
    $this->a11yAutoMode = FALSE;
    $this->a11yScenarioThreshold = NULL;
    $this->a11yScenarioFailOnIncomplete = NULL;

    $tags = array_merge(
      $scope->getFeature()->getTags() ?? [],
      $scope->getScenario()->getTags() ?? []
    );
    $this->a11yResolveTags($tags);
  }

  /**
   * Run axe-core after each step when in automatic mode.
   */
  #[AfterStep]
  public function a11yAutoAssess(AfterStepScope $scope): void {
    if ($scope->getFeature()->hasTag('behat-steps-skip:A11yTrait')) {
      return;
    }

    if (!$this->a11yAutoMode) {
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

    if (in_array($url, ['', 'about:blank', $this->a11yLastCheckedUrl], TRUE)) {
      return;
    }

    $this->a11yRunAxe($this->getA11yDefaultRules());
  }

  /**
   * Write reports and enforce the gate at the end of the scenario.
   */
  #[AfterScenario]
  public function a11yFinalizeScenario(AfterScenarioScope $scope): void {
    if ($scope->getFeature()->hasTag('behat-steps-skip:A11yTrait')) {
      return;
    }

    if ($this->a11yResults === []) {
      return;
    }

    $dir = $this->getA11yReportDir();
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }
    $slug = $this->a11ySlug($this->a11yFeatureName) . '__' . $this->a11ySlug($this->a11yScenarioName);
    file_put_contents($dir . '/' . $slug . '.html', $this->a11yRenderHtml());
    file_put_contents($dir . '/junit-' . $slug . '.xml', $this->a11yRenderJunit());

    if (!$this->a11yAutoMode) {
      return;
    }

    $threshold = $this->a11yEffectiveThreshold();
    $check_incomplete = $this->a11yEffectiveFailOnIncomplete();
    $messages = [];

    foreach ($this->a11yResults as $r) {
      foreach ($this->a11yFilterViolations($r['result']['violations'] ?? [], $threshold) as $v) {
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
          "Auto a11y gate failed (threshold=%s, fail_on_incomplete=%s):\n%s",
          $threshold,
          $check_incomplete ? 'yes' : 'no',
          implode("\n", $messages)
        ),
        $this->getSession()->getDriver()
      );
    }
  }

  /**
   * Assert that the current page passes axe-core accessibility checks.
   *
   * @code
   * Then the current page should pass accessibility checks
   * @endcode
   */
  #[Then('the current page should pass accessibility checks')]
  public function a11yAssertCurrentPage(): void {
    $this->a11yAssertCurrentPageForTags($this->getA11yDefaultRules());
  }

  /**
   * Assert that the current page passes axe-core checks for the given tags.
   *
   * @code
   * Then the current page should pass accessibility checks for tags "wcag2a"
   * @endcode
   */
  #[Then('the current page should pass accessibility checks for tags :rules')]
  public function a11yAssertCurrentPageForTags(string $rules): void {
    $result = $this->a11yRunAxe($rules);

    $threshold = $this->a11yEffectiveThreshold();
    $check_incomplete = $this->a11yEffectiveFailOnIncomplete();

    $violations = $this->a11yFilterViolations($result['violations'] ?? [], $threshold);
    $incomplete = $check_incomplete ? ($result['incomplete'] ?? []) : [];

    if ($violations === [] && $incomplete === []) {
      return;
    }

    throw new ExpectationException(
      $this->a11yFormatGateMessage(
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
   * Returns the axe-core JavaScript source to inject into the page.
   *
   * Default: fetched once per process from getA11yCdnUrl(). Override
   * to ship axe-core from a vendored package, asset path, or anywhere
   * else.
   */
  protected function getA11yJs(): string {
    if (self::$a11yCachedJs !== NULL) {
      return self::$a11yCachedJs;
    }

    $content = @file_get_contents($this->getA11yCdnUrl());
    if ($content === FALSE || $content === '') {
      throw new \RuntimeException(sprintf('Failed to fetch axe-core from %s', $this->getA11yCdnUrl()));
    }

    self::$a11yCachedJs = $content;

    return $content;
  }

  /**
   * Returns the URL used by the default getA11yJs() implementation.
   */
  protected function getA11yCdnUrl(): string {
    return sprintf(self::A11Y_CDN_TEMPLATE, $this->getA11yAxeVersion());
  }

  /**
   * Returns the axe-core version string used to build the CDN URL.
   */
  protected function getA11yAxeVersion(): string {
    return self::A11Y_AXE_VERSION;
  }

  /**
   * Returns the absolute directory used to write per-scenario reports.
   *
   * Override to return an already-absolute path if your project needs to
   * write reports outside the current working directory.
   */
  protected function getA11yReportDir(): string {
    return getcwd() . DIRECTORY_SEPARATOR . self::A11Y_REPORT_DIR;
  }

  /**
   * Returns the base tag name that enables automatic mode (no `@` prefix).
   */
  protected function getA11yAutoTag(): string {
    return self::A11Y_AUTO_TAG;
  }

  /**
   * Returns the comma-separated WCAG rule tags used when none are specified.
   */
  protected function getA11yDefaultRules(): string {
    return self::A11Y_DEFAULT_RULES;
  }

  /**
   * Returns the default failure threshold (impact level or 'any'/'never').
   */
  protected function getA11yFailureThreshold(): string {
    return self::A11Y_DEFAULT_THRESHOLD;
  }

  /**
   * Returns TRUE if "incomplete" findings should fail the gate by default.
   */
  protected function getA11yFailOnIncomplete(): bool {
    return FALSE;
  }

  /**
   * Returns the impact levels in descending severity order.
   *
   * @return array<int, string>
   *   Impact level names ordered from most severe to least.
   */
  protected function getA11yImpacts(): array {
    return self::A11Y_IMPACTS;
  }

  /**
   * Resolve scenario / feature tags into mode and threshold state.
   *
   * @param array<int, string> $tags
   *   Combined feature and scenario tags.
   */
  protected function a11yResolveTags(array $tags): void {
    $auto_tag = $this->getA11yAutoTag();
    $impacts = $this->getA11yImpacts();

    foreach ($tags as $tag) {
      if ($tag === $auto_tag) {
        $this->a11yAutoMode = TRUE;
        continue;
      }

      if (!str_starts_with($tag, $auto_tag . '-')) {
        continue;
      }

      $variant = strtolower(substr($tag, strlen((string) $auto_tag) + 1));
      $this->a11yAutoMode = TRUE;

      if ($variant === 'warning' || $variant === 'warn') {
        $this->a11yScenarioThreshold = 'never';
      }
      elseif ($variant === 'strict') {
        $this->a11yScenarioFailOnIncomplete = TRUE;
      }
      elseif ($variant === 'any') {
        $this->a11yScenarioThreshold = 'any';
      }
      elseif (in_array($variant, $impacts, TRUE)) {
        $this->a11yScenarioThreshold = $variant;
      }
    }
  }

  /**
   * Returns the active gate threshold for the current scenario.
   */
  protected function a11yEffectiveThreshold(): string {
    return $this->a11yScenarioThreshold ?? $this->getA11yFailureThreshold();
  }

  /**
   * Returns whether incomplete findings should fail the current scenario.
   */
  protected function a11yEffectiveFailOnIncomplete(): bool {
    return $this->a11yScenarioFailOnIncomplete ?? $this->getA11yFailOnIncomplete();
  }

  /**
   * Filter the axe-core violations array by impact threshold.
   *
   * @param array<int, array<string, mixed>> $violations
   *   Raw violations from axe-core.
   * @param string $threshold
   *   One of 'any', 'never', or an impact level from getA11yImpacts().
   *
   * @return array<int, array<string, mixed>>
   *   Violations meeting or exceeding the threshold.
   */
  protected function a11yFilterViolations(array $violations, string $threshold): array {
    if ($threshold === 'never') {
      return [];
    }

    if ($threshold === 'any') {
      return $violations;
    }

    $impacts = $this->getA11yImpacts();
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
   * Execute axe-core against the current page and record the result.
   *
   * @param string $rules
   *   Comma-separated axe rule tags (e.g. 'wcag2a,wcag2aa').
   *
   * @return array<string, mixed>
   *   The axe-core result array.
   */
  protected function a11yRunAxe(string $rules): array {
    $session = $this->getSession();
    $driver = $session->getDriver();
    $driver->executeScript($this->getA11yJs());

    $tag_list = json_encode(array_map(trim(...), explode(',', $rules)));
    $driver->executeScript(sprintf(
      'window.__axeResults = null; axe.run(document, { runOnly: { type: "tag", values: %s } }).then(function (r) { window.__axeResults = r; }).catch(function (e) { window.__axeResults = { error: String(e) }; });',
      $tag_list
    ));

    $session->wait(30000, 'window.__axeResults !== null');
    $results = $session->evaluateScript('return window.__axeResults;');

    if (!is_array($results)) {
      throw new \RuntimeException('axe-core did not return results.');
    }

    if (isset($results['error'])) {
      throw new \RuntimeException('axe-core failed: ' . $results['error']);
    }

    $url = $session->getCurrentUrl();
    $this->a11yResults[] = ['url' => $url, 'rules' => $rules, 'result' => $results];
    $this->a11yLastCheckedUrl = $url;

    fwrite(STDOUT, sprintf("\n[a11y] %s: %d violations, %d passes, %d incomplete (rules: %s)\n",
      $url,
      count($results['violations'] ?? []),
      count($results['passes'] ?? []),
      count($results['incomplete'] ?? []),
      $rules
    ));

    return $results;
  }

  /**
   * Build the human-readable error message for the explicit assertion.
   *
   * @param string $url
   *   URL of the page that was assessed.
   * @param string $rules
   *   Rule set used.
   * @param string $threshold
   *   Effective gate threshold.
   * @param bool $check_incomplete
   *   Whether incomplete findings fail the gate.
   * @param array<int, array<string, mixed>> $violations
   *   Filtered violations.
   * @param array<int, array<string, mixed>> $incomplete
   *   Incomplete findings to include.
   */
  protected function a11yFormatGateMessage(string $url, string $rules, string $threshold, bool $check_incomplete, array $violations, array $incomplete): string {
    $lines = [sprintf(
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
        $lines[] = sprintf('    -> %s', $this->a11yStringifyTarget($node['target'] ?? []));
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
        $lines[] = sprintf('    -> %s', $this->a11yStringifyTarget($node['target'] ?? []));
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Flatten an axe-core node target array into a human-readable string.
   *
   * @param array<int, mixed> $target
   *   The 'target' value from an axe-core node.
   */
  protected function a11yStringifyTarget(array $target): string {
    return implode(' > ', array_map(fn($t): string => is_array($t) ? implode(' ', $t) : (string) $t, $target));
  }

  /**
   * Convert an arbitrary string into a filesystem-safe slug for filenames.
   */
  protected function a11ySlug(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

    return trim($s, '-') ?: 'untitled';
  }

  /**
   * Render the scenario-level HTML report from collected axe-core results.
   */
  protected function a11yRenderHtml(): string {
    $title = htmlspecialchars($this->a11yFeatureName . ' > ' . $this->a11yScenarioName, ENT_QUOTES);
    $body_sections = [];

    foreach ($this->a11yResults as $r) {
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

      $section .= $this->a11yRenderIssueList('Violations', 'violation', $violations);
      $section .= $this->a11yRenderIssueList('Incomplete (needs human review)', 'incomplete', $incomplete);
      $section .= '</section>';
      $body_sections[] = $section;
    }

    $body = implode("\n", $body_sections);
    $axe_version = htmlspecialchars($this->getA11yAxeVersion(), ENT_QUOTES);
    $threshold = htmlspecialchars($this->a11yEffectiveThreshold(), ENT_QUOTES);
    $fail_on_incomplete = $this->a11yEffectiveFailOnIncomplete() ? 'yes' : 'no';

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
<p class="meta">{$title} &middot; axe-core {$axe_version} &middot; threshold: <code>{$threshold}</code> &middot; fail on incomplete: <code>{$fail_on_incomplete}</code></p>
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
  protected function a11yRenderIssueList(string $heading, string $css_class, array $issues): string {
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
        $target = htmlspecialchars($this->a11yStringifyTarget($node['target'] ?? []), ENT_QUOTES);
        $html = htmlspecialchars(trim((string) ($node['html'] ?? '')), ENT_QUOTES);
        $out .= sprintf('<div class="node"><strong>%s</strong><br><code>%s</code></div>', $target, $html);
      }
      $out .= '</div>';
    }

    return $out;
  }

  /**
   * Render the scenario-level JUnit XML report from collected axe-core results.
   */
  protected function a11yRenderJunit(): string {
    $suites_xml = '';
    $total_tests = 0;
    $total_failures = 0;

    foreach ($this->a11yResults as $r) {
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
          $target = $this->a11yStringifyTarget($node['target'] ?? []);
          $html = trim((string) ($node['html'] ?? ''));

          $message = sprintf('[%s] %s', $impact, $help);
          $details = sprintf("URL: %s\nRule: %s\nTarget: %s\nHTML: %s\nDocs: %s", $url, $rule_id, $target, $html, $help_url);

          $cases_xml .= sprintf(
            '<testcase classname="a11y.%s" name="%s"><failure type="%s" message="%s">%s</failure></testcase>',
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
          '<testcase classname="a11y.%s" name="%s passed"/>',
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
      htmlspecialchars($this->a11yFeatureName . ' > ' . $this->a11yScenarioName, ENT_XML1 | ENT_QUOTES),
      $total_tests,
      $total_failures,
      $suites_xml
    );
  }

}
