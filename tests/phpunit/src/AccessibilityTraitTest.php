<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\AccessibilityTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for AccessibilityTrait.
 */
#[CoversClass(AccessibilityTrait::class)]
class AccessibilityTraitTest extends UnitTestCase {

  /**
   * A test implementation of AccessibilityTrait.
   */
  protected AccessibilityTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new AccessibilityTraitTestImplementation();
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);
    AccessibilityTraitTestImplementation::accessibilityAggregateReset();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);
    AccessibilityTraitTestImplementation::accessibilityAggregateReset();

    parent::tearDown();
  }

  #[DataProvider('dataProviderFormatUrl')]
  public function testFormatUrl(string $base_url, string $url, string $expected): void {
    $this->testObject->baseUrl = $base_url;

    $this->assertSame($expected, $this->testObject->testFormatUrl($url));
  }

  public static function dataProviderFormatUrl(): array {
    return [
      'path under base' => ['http://nginx:8080', 'http://nginx:8080/contact', '/contact'],
      'base root with trailing slash' => ['http://nginx:8080', 'http://nginx:8080/', '/'],
      'base root without trailing slash' => ['http://nginx:8080', 'http://nginx:8080', '/'],
      'nested path' => ['http://nginx:8080', 'http://nginx:8080/a/b/c', '/a/b/c'],
      'query string preserved' => ['http://nginx:8080', 'http://nginx:8080/search?q=x', '/search?q=x'],
      'fragment preserved' => ['http://nginx:8080', 'http://nginx:8080/page#main', '/page#main'],
      'base configured with trailing slash' => ['http://nginx:8080/', 'http://nginx:8080/contact', '/contact'],
      'cross-origin kept absolute' => ['http://nginx:8080', 'https://example.com/page', 'https://example.com/page'],
      'similar prefix not stripped' => ['http://nginx:8080', 'http://nginx:8080extra/foo', 'http://nginx:8080extra/foo'],
      'no base url returns input unchanged' => ['', 'http://nginx:8080/contact', 'http://nginx:8080/contact'],
    ];
  }

  public function testGetReportDirUsesCapturedBaseDir(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir('/sentinel/base');

    // chdir() to a different directory to prove the report directory anchors to
    // the captured base rather than the live working directory.
    $original = getcwd();
    chdir(static::locationsTmp());

    try {
      $this->assertSame('/sentinel/base' . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility', $this->testObject->testGetReportDir());
    }
    finally {
      if (is_string($original)) {
        chdir($original);
      }
    }
  }

  public function testGetReportDirFallsBackToCwdWhenUnset(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);

    $expected = (getcwd() ?: '.') . DIRECTORY_SEPARATOR . '.logs/test_results/accessibility';

    $this->assertSame($expected, $this->testObject->testGetReportDir());
  }

  public function testCaptureBaseDirSetsWhenUnset(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);

    AccessibilityTraitTestImplementation::accessibilityCaptureBaseDir();

    $this->assertSame(getcwd(), AccessibilityTraitTestImplementation::testGetBaseDir());
  }

  public function testCaptureBaseDirDoesNotOverwrite(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir('/sentinel/base');

    AccessibilityTraitTestImplementation::accessibilityCaptureBaseDir();

    $this->assertSame('/sentinel/base', AccessibilityTraitTestImplementation::testGetBaseDir());
  }

  #[DataProvider('dataProviderAggregateHtmlContains')]
  public function testAggregateHtmlContains(string $expected): void {
    $html = AccessibilityTraitTestImplementation::testRenderAggregateHtml(static::createSampleAggregate(), '2026-01-02 03:04');

    $this->assertStringContainsString($expected, $html);
  }

  public static function dataProviderAggregateHtmlContains(): array {
    return [
      'aggregate title' => ['<h1>Accessibility report - aggregate</h1>'],
      'generation timestamp passed in by the writer' => ['generated 2026-01-02 03:04'],
      'seven summary cards in the specified order with counts' => [
        '<section class="cards">'
        . '<div class="card "><span class="num">2</span><span class="lbl">pages assessed</span></div>'
        . '<div class="card "><span class="num">2</span><span class="lbl">scenarios</span></div>'
        . '<div class="card fail"><span class="num">3</span><span class="lbl">violations</span></div>'
        . '<div class="card crit"><span class="num">2</span><span class="lbl">critical</span></div>'
        . '<div class="card ser"><span class="num">1</span><span class="lbl">serious</span></div>'
        . '<div class="card mod"><span class="num">0</span><span class="lbl">moderate</span></div>'
        . '<div class="card min"><span class="num">0</span><span class="lbl">minor</span></div>'
        . '</section>',
      ],
      'critical chip carries its affected-element count' => ['<span class="vtype critical">image-alt <b>2</b></span>'],
      'serious chip carries its affected-element count' => ['<span class="vtype serious">color-contrast <b>1</b></span>'],
      'second page chip' => ['<span class="vtype critical">button-name <b>1</b></span>'],
      'a deduplicated URL lists every visiting scenario' => ['Home page, Contact page'],
      'rule rollup links to the axe docs' => ['href="https://dequeuniversity.com/rules/axe/4.11/image-alt"'],
      'rule rollup reports affected pages and elements' => ['affects 1 page(s) &middot; 2 element(s)'],
      'scenario detail shows the default threshold' => ['threshold: <code>any</code>'],
      'scenario detail shows the overridden threshold' => ['threshold: <code>critical</code>'],
      'scenario detail shows fail-on-incomplete' => ['fail on incomplete: <code>yes</code>'],
      'scenario detail shows the rules string' => ['Rules: <code>wcag2a,wcag2aa</code>'],
      'scenario detail shows the page counts' => ['2 violations &middot; 1 incomplete &middot; 3 passes'],
      'scenario detail has a violations heading' => ['<h5>Violations</h5>'],
      'scenario detail has an incomplete heading' => ['<h5>Incomplete (needs human review)</h5>'],
      'scenario detail renders the incomplete finding' => ['<span class="rule-id">aria-valid-attr</span>'],
      'scenario detail renders an element target' => ['img.logo'],
      'scenario detail renders the escaped element HTML' => ['&lt;img src=&quot;logo.png&quot;&gt;'],
    ];
  }

  public function testAggregateHtmlSortsRulesBySeverityThenElementCount(): void {
    $html = AccessibilityTraitTestImplementation::testRenderAggregateHtml(static::createSampleAggregate(), '2026-01-02 03:04');

    $image_alt = strpos($html, '<span class="rule-id">image-alt</span>');
    $button_name = strpos($html, '<span class="rule-id">button-name</span>');
    $color_contrast = strpos($html, '<span class="rule-id">color-contrast</span>');

    // Both critical rules precede the serious one, and within the critical
    // group the rule with more affected elements (image-alt: 2) precedes the
    // one with fewer (button-name: 1).
    $this->assertNotFalse($image_alt);
    $this->assertNotFalse($button_name);
    $this->assertNotFalse($color_contrast);
    $this->assertLessThan($button_name, $image_alt);
    $this->assertLessThan($color_contrast, $button_name);
  }

  public function testAggregateHtmlUsesPathOnlyUrls(): void {
    $html = AccessibilityTraitTestImplementation::testRenderAggregateHtml(static::createSampleAggregate(), '2026-01-02 03:04');

    $this->assertStringContainsString('<td class="url">/contact</td>', $html);
    // No assessed-page URL retains a scheme + host + port.
    $this->assertDoesNotMatchRegularExpression('#://[^/"\s]+:\d+#', $html);
  }

  public function testAggregatePagesDeduplicatesAndSkipsBlankUrls(): void {
    $pages = AccessibilityTraitTestImplementation::testAggregatePages(static::createSampleAggregate());

    $this->assertSame(['/', '/contact'], array_keys($pages));
    $this->assertSame(['Home page', 'Contact page'], array_keys($pages['/']['scenarios']));
    $this->assertCount(2, $pages['/']['violations']);
    $this->assertSame(1, $pages['/']['incomplete']);
    $this->assertSame(3, $pages['/']['passes']);
    $this->assertSame(['Contact page'], array_keys($pages['/contact']['scenarios']));
  }

  public function testAggregateRollupTalliesAndSortsBySeverity(): void {
    $pages = AccessibilityTraitTestImplementation::testAggregatePages(static::createSampleAggregate());
    $rollup = AccessibilityTraitTestImplementation::testAggregateRollup($pages);

    $this->assertSame(['critical' => 2, 'serious' => 1, 'moderate' => 0, 'minor' => 0], $rollup['totals']);
    $this->assertSame(['image-alt', 'button-name', 'color-contrast'], array_keys($rollup['rules']));
    $this->assertCount(2, $rollup['rules']['image-alt']['nodes']);
    $this->assertSame(['/'], array_keys($rollup['rules']['image-alt']['pages']));
  }

  public function testWriteAggregateReportWritesTimestampedFile(): void {
    $dir = static::locationsTmp() . DIRECTORY_SEPARATOR . 'aggregate-report';
    AccessibilityTraitTestImplementation::testSetAggregate(static::createSampleAggregate());
    AccessibilityTraitTestImplementation::testSetAggregateReportDir($dir);

    // First call creates the directory; second call finds it already present.
    AccessibilityTraitTestImplementation::testWriteAggregateReport();
    AccessibilityTraitTestImplementation::testWriteAggregateReport();

    $files = glob($dir . '/accessibility_report_*.html') ?: [];
    $this->assertNotEmpty($files);
    $this->assertStringContainsString('Accessibility report - aggregate', (string) file_get_contents($files[0]));
  }

  public function testWriteAggregateReportDoesNothingWhenEmpty(): void {
    $dir = static::locationsTmp() . DIRECTORY_SEPARATOR . 'aggregate-empty';
    AccessibilityTraitTestImplementation::testSetAggregate([]);
    AccessibilityTraitTestImplementation::testSetAggregateReportDir($dir);

    AccessibilityTraitTestImplementation::testWriteAggregateReport();

    $this->assertEmpty(glob($dir . '/accessibility_report_*.html') ?: []);
  }

  public function testAggregateRenderHookWritesReport(): void {
    $dir = static::locationsTmp() . DIRECTORY_SEPARATOR . 'aggregate-hook';
    AccessibilityTraitTestImplementation::testSetAggregate(static::createSampleAggregate());
    AccessibilityTraitTestImplementation::testSetAggregateReportDir($dir);

    AccessibilityTraitTestImplementation::accessibilityAggregateRender();

    $this->assertNotEmpty(glob($dir . '/accessibility_report_*.html') ?: []);
  }

  public function testAggregateFilenameFormat(): void {
    $name = AccessibilityTraitTestImplementation::testAggregateFilename(1750000000);

    $this->assertMatchesRegularExpression('/^accessibility_report_\d{8}_\d{6}\.html$/', $name);
    // A different moment yields a different filename, proving the timestamp is used.
    $this->assertNotSame($name, AccessibilityTraitTestImplementation::testAggregateFilename(1750086400));
  }

  public function testAggregateHtmlRendersCleanRunWithoutViolations(): void {
    $html = AccessibilityTraitTestImplementation::testRenderAggregateHtml(static::createCleanAggregate(), '2026-01-02 03:04');

    $this->assertStringContainsString('<div class="card ok"><span class="num">0</span><span class="lbl">violations</span></div>', $html);
    $this->assertStringContainsString('No violations found.', $html);
    $this->assertStringContainsString('<span class="muted">&mdash;</span>', $html);
  }

  public function testAggregateRulesOmitDocsLinkWhenHelpUrlEmpty(): void {
    $aggregate = [
      [
        'feature' => 'Homepage',
        'scenario' => 'Home page',
        'threshold' => 'any',
        'failOnIncomplete' => FALSE,
        'results' => [
          [
            'url' => '/',
            'rules' => 'wcag2a',
            'result' => [
              'violations' => [['id' => 'custom-rule', 'impact' => 'serious', 'help' => 'A rule with no docs URL', 'helpUrl' => '', 'nodes' => [['target' => ['div'], 'html' => '<div></div>']]]],
              'incomplete' => [],
              'passes' => [],
            ],
          ],
        ],
      ],
    ];

    $html = AccessibilityTraitTestImplementation::testRenderAggregateHtml($aggregate, '2026-01-02 03:04');

    $this->assertStringContainsString('<span class="rule-id">custom-rule</span>', $html);
    $this->assertStringNotContainsString('href=""', $html);
  }

  public function testAggregateResetClearsState(): void {
    AccessibilityTraitTestImplementation::testSetAggregate(static::createSampleAggregate());
    AccessibilityTraitTestImplementation::testSetAggregateReportDir('/sentinel');

    AccessibilityTraitTestImplementation::accessibilityAggregateReset();

    $this->assertSame([], AccessibilityTraitTestImplementation::testGetAggregate());
    $this->assertNull(AccessibilityTraitTestImplementation::testGetAggregateReportDir());
  }

  public function testAggregateCaptureFormatsUrlsAndRecordsEntry(): void {
    $this->testObject->baseUrl = 'http://nginx:8080';

    $this->testObject->testCapture(
      [['url' => 'http://nginx:8080/contact', 'rules' => 'wcag2a', 'result' => ['violations' => [], 'incomplete' => [], 'passes' => []]]],
      'My feature',
      'My scenario',
      '/captured/dir'
    );

    $aggregate = AccessibilityTraitTestImplementation::testGetAggregate();
    $this->assertCount(1, $aggregate);
    $this->assertSame('/contact', $aggregate[0]['results'][0]['url']);
    $this->assertSame('My feature', $aggregate[0]['feature']);
    $this->assertSame('My scenario', $aggregate[0]['scenario']);
    $this->assertSame('any', $aggregate[0]['threshold']);
    $this->assertFalse($aggregate[0]['failOnIncomplete']);
    $this->assertSame('/captured/dir', AccessibilityTraitTestImplementation::testGetAggregateReportDir());
  }

  /**
   * Build a representative accumulator with two scenarios, a shared URL, a blank tab, and mixed-impact findings.
   *
   * @return array<int, array<string, mixed>>
   *   Sample aggregate data in the shape produced by accessibilityAggregateCapture().
   */
  protected static function createSampleAggregate(): array {
    $image_alt = [
      'id' => 'image-alt',
      'impact' => 'critical',
      'help' => 'Images must have alternate text',
      'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.11/image-alt',
      'nodes' => [
        ['target' => ['img.logo'], 'html' => '<img src="logo.png">'],
        ['target' => ['img.hero'], 'html' => '<img src="hero.png">'],
      ],
    ];
    $color_contrast = [
      'id' => 'color-contrast',
      'impact' => 'serious',
      'help' => 'Elements must have sufficient colour contrast',
      'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.11/color-contrast',
      'nodes' => [
        ['target' => ['a.muted'], 'html' => '<a href="#">link</a>'],
      ],
    ];
    $aria_incomplete = [
      'id' => 'aria-valid-attr',
      'impact' => 'moderate',
      'help' => 'ARIA attributes must be valid',
      'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.11/aria-valid-attr',
      'nodes' => [
        ['target' => ['div#widget'], 'html' => '<div aria-foo="bar"></div>'],
      ],
    ];
    $button_name = [
      'id' => 'button-name',
      'impact' => 'critical',
      'help' => 'Buttons must have discernible text',
      'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.11/button-name',
      'nodes' => [
        ['target' => ['button'], 'html' => '<button></button>'],
      ],
    ];

    return [
      [
        'feature' => 'Homepage',
        'scenario' => 'Home page',
        'threshold' => 'any',
        'failOnIncomplete' => FALSE,
        'results' => [
          [
            'url' => '/',
            'rules' => 'wcag2a,wcag2aa',
            'result' => [
              'violations' => [$image_alt, $color_contrast],
              'incomplete' => [$aria_incomplete],
              'passes' => [['id' => 'document-title'], ['id' => 'html-lang'], ['id' => 'region']],
            ],
          ],
        ],
      ],
      [
        'feature' => 'Contact',
        'scenario' => 'Contact page',
        'threshold' => 'critical',
        'failOnIncomplete' => TRUE,
        'results' => [
          [
            'url' => '/',
            'rules' => 'wcag2a',
            'result' => [
              'violations' => [$image_alt, $color_contrast],
              'incomplete' => [],
              'passes' => [['id' => 'document-title']],
            ],
          ],
          [
            'url' => '/contact',
            'rules' => 'wcag2a',
            'result' => [
              'violations' => [$button_name],
              'incomplete' => [],
              'passes' => [['id' => 'document-title']],
            ],
          ],
          [
            'url' => 'about:blank',
            'rules' => 'wcag2a',
            'result' => ['violations' => [], 'incomplete' => [], 'passes' => []],
          ],
        ],
      ],
    ];
  }

  /**
   * Build an accumulator for a run that found no violations at all.
   *
   * @return array<int, array<string, mixed>>
   *   Sample aggregate data with a single clean page.
   */
  protected static function createCleanAggregate(): array {
    return [
      [
        'feature' => 'Homepage',
        'scenario' => 'Clean home',
        'threshold' => 'any',
        'failOnIncomplete' => FALSE,
        'results' => [
          [
            'url' => '/',
            'rules' => 'wcag2a',
            'result' => ['violations' => [], 'incomplete' => [], 'passes' => [['id' => 'document-title']]],
          ],
        ],
      ],
    ];
  }

}

/**
 * Test implementation of AccessibilityTrait.
 */
class AccessibilityTraitTestImplementation {

  use AccessibilityTrait;

  /**
   * Base URL returned by the stubbed Mink parameter accessor.
   */
  public string $baseUrl = '';

  public function getMinkParameter(string $name): mixed {
    return $name === 'base_url' ? $this->baseUrl : NULL;
  }

  public function testFormatUrl(string $url): string {
    return $this->accessibilityFormatUrl($url);
  }

  public function testGetReportDir(): string {
    return $this->accessibilityGetReportDir();
  }

  public static function testSetBaseDir(?string $dir): void {
    self::$accessibilityBaseDir = $dir;
  }

  public static function testGetBaseDir(): ?string {
    return self::$accessibilityBaseDir;
  }

  public static function testSetAggregate(array $aggregate): void {
    self::$accessibilityAggregate = $aggregate;
  }

  public static function testGetAggregate(): array {
    return self::$accessibilityAggregate;
  }

  public static function testSetAggregateReportDir(?string $dir): void {
    self::$accessibilityAggregateReportDir = $dir;
  }

  public static function testGetAggregateReportDir(): ?string {
    return self::$accessibilityAggregateReportDir;
  }

  public static function testRenderAggregateHtml(array $aggregate, string $generated): string {
    return static::accessibilityRenderAggregateHtml($aggregate, $generated);
  }

  public static function testAggregatePages(array $aggregate): array {
    return static::accessibilityAggregatePages($aggregate);
  }

  public static function testAggregateRollup(array $pages): array {
    return static::accessibilityAggregateRollup($pages);
  }

  public static function testWriteAggregateReport(): void {
    static::accessibilityWriteAggregateReport();
  }

  public static function testAggregateFilename(int $time): string {
    return static::accessibilityAggregateFilename($time);
  }

  public function testCapture(array $results, string $feature, string $scenario, string $dir): void {
    $this->accessibilityResults = $results;
    $this->accessibilityFeatureName = $feature;
    $this->accessibilityScenarioName = $scenario;
    $this->accessibilityAggregateCapture($dir);
  }

}
