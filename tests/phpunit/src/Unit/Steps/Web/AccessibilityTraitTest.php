<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Mink;
use DrevOps\BehatSteps\Extension\Http\HttpClientFactory;
use DrevOps\BehatSteps\Steps\Web\AccessibilityTrait;
use DrevOps\BehatSteps\Tests\Unit\Steps\Web\Fixtures\RecordingSession;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for AccessibilityTrait.
 */
#[CoversTrait(AccessibilityTrait::class)]
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
    static::resetStaticState();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    static::resetStaticState();

    parent::tearDown();
  }

  #[DataProvider('dataProviderFormatUrl')]
  public function testFormatUrl(string $base_url, string $url, string $expected): void {
    $this->testObject->setMinkParameter('base_url', $base_url);

    $this->assertSame($expected, $this->testObject->accessibilityFormatUrl($url));
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

  public function testGetJsReadsSourceOnce(): void {
    $this->testObject->engineUrl = $this->writeFixture('axe-engine.js', 'ENGINE');

    $first = $this->testObject->accessibilityGetJs();

    // A second call is served from the process cache, so the fixture
    // rewritten at the same path is never read.
    $this->writeFixture('axe-engine.js', 'CHANGED');
    $second = $this->testObject->accessibilityGetJs();

    $this->assertSame('ENGINE', $first);
    $this->assertSame('ENGINE', $second);
    $this->assertSame(1, $this->testObject->engineReads);
  }

  public function testGetJsRetriesUntilReadSucceeds(): void {
    $object = new AccessibilityTraitRetryTestImplementation();
    $object->engineFailures = 2;
    $object->engineContent = 'ENGINE';

    $this->assertSame('ENGINE', $object->accessibilityGetJs());
    $this->assertSame(3, $object->engineReads);
  }

  public function testGetJsStopsAfterConfiguredAttempts(): void {
    $object = new AccessibilityTraitRetryTestImplementation();
    $object->engineFailures = PHP_INT_MAX;
    $object->engineAttempts = 2;

    try {
      $object->accessibilityGetJs();
      $this->fail('Expected a RuntimeException.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('after 2 attempt(s) with a 1 second timeout', $exception->getMessage());
    }

    $this->assertSame(2, $object->engineReads);
  }

  public function testGetJsReadsOnceWhenRetriesAreDisabled(): void {
    $object = new AccessibilityTraitRetryTestImplementation();
    $object->engineFailures = PHP_INT_MAX;
    $object->engineAttempts = 0;

    $this->expectException(\RuntimeException::class);

    try {
      $object->accessibilityGetJs();
    }
    finally {
      $this->assertSame(1, $object->engineReads);
    }
  }

  public function testGetJsRetriesThenFails(): void {
    $this->testObject->engineUrl = static::$tmp . '/absent-engine.js';
    $this->testObject->engineAttempts = 2;
    $this->testObject->engineTimeout = 1;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('after 2 attempt(s) with a 1 second timeout');

    $this->testObject->accessibilityGetJs();
  }

  public function testGetJsTreatsEmptySourceAsFailure(): void {
    $this->testObject->engineUrl = $this->writeFixture('empty-engine.js', '');
    $this->testObject->engineAttempts = 1;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('after 1 attempt(s)');

    $this->testObject->accessibilityGetJs();
  }

  public function testGetJsClampsAttemptsToOne(): void {
    $this->testObject->engineUrl = static::$tmp . '/absent-engine.js';
    $this->testObject->engineAttempts = 0;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('after 1 attempt(s)');

    $this->testObject->accessibilityGetJs();
  }

  public function testGetJsFetchDefaults(): void {
    $object = new AccessibilityTraitFetchDefaultsTestImplementation();

    $this->assertSame(10, $object->accessibilityGetFetchTimeout());
    $this->assertSame(3, $object->accessibilityGetFetchAttempts());
  }

  /**
   * Tests reading the engine source from an HTTP location.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the location answers with.
   * @param string|false $expected
   *   The source expected back, or FALSE when the read fails.
   */
  #[DataProvider('dataProviderFetchJsFromHttpLocation')]
  public function testFetchJsFromHttpLocation(MockResponse $response, string|false $expected): void {
    $requests = [];
    $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $response): MockResponse {
      $requests[] = $options;

      return $response;
    });
    $this->testObject->setHttpClientFactory(new HttpClientFactory($client));

    $this->assertSame($expected, $this->testObject->accessibilityFetchJs('https://cdn.example.org/axe.min.js', 4));
    $this->assertEqualsWithDelta(4.0, $requests[0]['timeout'], 0.001);
  }

  public static function dataProviderFetchJsFromHttpLocation(): array {
    return [
      'a found source' => [new MockResponse('ENGINE'), 'ENGINE'],
      'a missing source' => [new MockResponse('Not found', ['http_code' => 404]), FALSE],
      'an unreachable host' => [new MockResponse('', ['error' => 'Could not resolve host']), FALSE],
    ];
  }

  public function testGetReportDirUsesCapturedBaseDir(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir('/sentinel/base');

    // Changing directory proves the report directory is built from the
    // captured base rather than the live working directory.
    $original = getcwd();
    chdir(static::$tmp);

    try {
      $this->assertSame('/sentinel/base/.logs/test_results/accessibility', $this->testObject->accessibilityGetReportDir());
    }
    finally {
      if (is_string($original)) {
        chdir($original);
      }
    }
  }

  public function testGetReportDirFallsBackToCwdWhenUnset(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);

    $expected = (getcwd() ?: '.') . '/.logs/test_results/accessibility';

    $this->assertSame($expected, $this->testObject->accessibilityGetReportDir());
  }

  /**
   * Tests that the suite hook captures the base dir once and clears the aggregate.
   *
   * @param string|null $base_dir
   *   The base dir captured before the hook runs, NULL when none was.
   */
  #[DataProvider('dataProviderBeforeSuiteCapturesBaseDirAndClearsAggregate')]
  public function testBeforeSuiteCapturesBaseDirAndClearsAggregate(?string $base_dir): void {
    AccessibilityTraitTestImplementation::testSetBaseDir($base_dir);
    AccessibilityTraitTestImplementation::testSetAggregate(static::createSampleAggregate());
    AccessibilityTraitTestImplementation::testSetAggregateReportDir('/sentinel');

    AccessibilityTraitTestImplementation::accessibilityBeforeSuite($this->createBeforeSuiteScope());

    $this->assertSame($base_dir ?? getcwd(), AccessibilityTraitTestImplementation::testGetBaseDir());
    $this->assertSame([], AccessibilityTraitTestImplementation::testGetAggregate());
    $this->assertNull(AccessibilityTraitTestImplementation::testGetAggregateReportDir());
  }

  public static function dataProviderBeforeSuiteCapturesBaseDirAndClearsAggregate(): array {
    return [
      'an unset base dir is captured from the working directory' => [NULL],
      'a captured base dir is kept' => ['/sentinel/base'],
    ];
  }

  #[DataProvider('dataProviderAggregateHtmlContains')]
  public function testAggregateHtmlContains(string $expected): void {
    $html = static::renderSample(static::createSampleAggregate(), '2026-01-02 03:04');

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
      'a deduplicated URL lists every visiting scenario with its feature' => ['Homepage &gt; Home page, Contact &gt; Contact page'],
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
    $html = static::renderSample(static::createSampleAggregate(), '2026-01-02 03:04');

    $image_alt = strpos($html, '<span class="rule-id">image-alt</span>');
    $button_name = strpos($html, '<span class="rule-id">button-name</span>');
    $color_contrast = strpos($html, '<span class="rule-id">color-contrast</span>');

    // Both critical rules precede the serious one. Within the critical group,
    // the rule with more affected elements (image-alt: 2) precedes the one
    // with fewer (button-name: 1).
    $this->assertNotFalse($image_alt);
    $this->assertNotFalse($button_name);
    $this->assertNotFalse($color_contrast);
    $this->assertLessThan($button_name, $image_alt);
    $this->assertLessThan($color_contrast, $button_name);
  }

  public function testAggregateHtmlUsesPathOnlyUrls(): void {
    $html = static::renderSample(static::createSampleAggregate(), '2026-01-02 03:04');

    $this->assertStringContainsString('<td class="url">/contact</td>', $html);
    // No assessed-page URL retains a scheme + host + port.
    $this->assertDoesNotMatchRegularExpression('#://[^/"\s]+:\d+#', $html);
  }

  public function testAggregatePagesDeduplicatesAndSkipsBlankUrls(): void {
    $pages = AccessibilityTraitTestImplementation::callAggregatePages(static::createSampleAggregate());

    $this->assertSame(['/', '/contact'], array_keys($pages));
    $this->assertSame(['Homepage > Home page', 'Contact > Contact page'], array_keys($pages['/']['scenarios']));
    $this->assertCount(2, $pages['/']['violations']);
    $this->assertSame(1, $pages['/']['incomplete']);
    $this->assertSame(3, $pages['/']['passes']);
    $this->assertSame(['Contact > Contact page'], array_keys($pages['/contact']['scenarios']));
  }

  public function testAggregateRollupTalliesAndSortsBySeverity(): void {
    $pages = AccessibilityTraitTestImplementation::callAggregatePages(static::createSampleAggregate());
    $rollup = AccessibilityTraitTestImplementation::callAggregateRollup($pages);

    $this->assertSame(['critical' => 2, 'serious' => 1, 'moderate' => 0, 'minor' => 0], $rollup['totals']);
    $this->assertSame(['image-alt', 'button-name', 'color-contrast'], array_keys($rollup['rules']));
    $this->assertCount(2, $rollup['rules']['image-alt']['nodes']);
    $this->assertSame(['/'], array_keys($rollup['rules']['image-alt']['pages']));
  }

  public function testWriteAggregateReportWritesTimestampedFile(): void {
    $directory = static::$tmp . '/aggregate-report';
    AccessibilityTraitTestImplementation::testSetAggregate(static::createSampleAggregate());
    AccessibilityTraitTestImplementation::testSetAggregateReportDir($directory);

    // First call creates the directory; second call finds it already present.
    AccessibilityTraitTestImplementation::callWriteAggregateReport();
    AccessibilityTraitTestImplementation::callWriteAggregateReport();

    $files = glob($directory . '/accessibility_report_*.html') ?: [];
    $this->assertNotEmpty($files);
    $this->assertStringContainsString('Accessibility report - aggregate', (string) file_get_contents($files[0]));
  }

  public function testWriteAggregateReportDoesNothingWhenEmpty(): void {
    $directory = static::$tmp . '/aggregate-empty';
    AccessibilityTraitTestImplementation::testSetAggregate([]);
    AccessibilityTraitTestImplementation::testSetAggregateReportDir($directory);

    AccessibilityTraitTestImplementation::callWriteAggregateReport();

    $this->assertEmpty(glob($directory . '/accessibility_report_*.html') ?: []);
  }

  public function testAggregateRenderHookWritesReport(): void {
    $directory = static::$tmp . '/aggregate-hook';
    AccessibilityTraitTestImplementation::testSetAggregate(static::createSampleAggregate());
    AccessibilityTraitTestImplementation::testSetAggregateReportDir($directory);

    AccessibilityTraitTestImplementation::accessibilityAfterSuite($this->createAfterSuiteScope());

    $this->assertNotEmpty(glob($directory . '/accessibility_report_*.html') ?: []);
  }

  public function testAggregateFilenameFormat(): void {
    $name = AccessibilityTraitTestImplementation::callAggregateFilename(1750000000);

    $this->assertMatchesRegularExpression('/^accessibility_report_\d{8}_\d{6}\.html$/', $name);
    $this->assertNotSame($name, AccessibilityTraitTestImplementation::callAggregateFilename(1750086400));
  }

  public function testAggregateHtmlRendersCleanRunWithoutViolations(): void {
    $html = static::renderSample(static::createCleanAggregate(), '2026-01-02 03:04');

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
        'fail_on_incomplete' => FALSE,
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

    $html = static::renderSample($aggregate, '2026-01-02 03:04');

    $this->assertStringContainsString('<span class="rule-id">custom-rule</span>', $html);
    $this->assertStringNotContainsString('href=""', $html);
  }

  public function testRenderAggregateConsumesPreparedDataArray(): void {
    $data = [
      'generated' => '2026-01-02 03:04',
      'page_count' => 1,
      'scenario_count' => 1,
      'total_violations' => 1,
      'totals' => ['critical' => 1, 'serious' => 0, 'moderate' => 0, 'minor' => 0],
      'pages' => [
        ['url' => '/', 'violations' => [['id' => 'image-alt', 'impact' => 'critical', 'count' => 2]], 'incomplete' => 0, 'passes' => 1, 'scenarios' => 'Home'],
      ],
      'rules' => [
        ['id' => 'image-alt', 'impact' => 'critical', 'help' => 'Images need alt text', 'helpUrl' => 'https://example.com/image-alt', 'page_count' => 1, 'nodes' => [['url' => '/', 'target' => 'img.logo', 'html' => '<img>']]],
      ],
      'scenarios' => [
        [
          'feature' => 'Home',
          'scenario' => 'Home page',
          'threshold' => 'any',
          'fail_on_incomplete' => FALSE,
          'pages' => [
            ['url' => '/', 'rules' => 'wcag2a', 'violation_count' => 1, 'incomplete_count' => 0, 'passes_count' => 1, 'violations' => [['impact' => 'critical', 'id' => 'image-alt', 'help' => 'Images need alt text', 'helpUrl' => 'https://example.com/image-alt', 'nodes' => [['target' => 'img.logo', 'html' => '<img>']]]], 'incomplete' => []],
          ],
        ],
      ],
    ];

    $html = AccessibilityTraitTestImplementation::accessibilityRenderAggregate($data);

    $this->assertStringContainsString('<div class="card crit"><span class="num">1</span><span class="lbl">critical</span></div>', $html);
    $this->assertStringContainsString('<span class="vtype critical">image-alt <b>2</b></span>', $html);
    $this->assertStringContainsString('<span class="rule-id">image-alt</span>', $html);
    $this->assertStringContainsString('href="https://example.com/image-alt"', $html);
    $this->assertStringContainsString('generated 2026-01-02 03:04', $html);
  }

  public function testAggregateDataShapesRenderReadyValues(): void {
    $data = AccessibilityTraitTestImplementation::callAggregateData(static::createSampleAggregate(), '2026-01-02 03:04');

    $this->assertSame('2026-01-02 03:04', $data['generated']);
    $this->assertSame(2, $data['page_count']);
    $this->assertSame(2, $data['scenario_count']);
    $this->assertSame(3, $data['total_violations']);
    $this->assertSame(['critical' => 2, 'serious' => 1, 'moderate' => 0, 'minor' => 0], $data['totals']);
    $this->assertSame('/', $data['pages'][0]['url']);
    $this->assertSame(['image-alt', 'button-name', 'color-contrast'], array_column($data['rules'], 'id'));
    $this->assertSame('img.logo', $data['rules'][0]['nodes'][0]['target']);
  }

  public function testAggregateCaptureFormatsUrlsAndRecordsEntry(): void {
    $this->testObject->setMinkParameter('base_url', 'http://nginx:8080');

    $this->testObject->callAggregateCapture(
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
    $this->assertFalse($aggregate[0]['fail_on_incomplete']);
    $this->assertSame('/captured/dir', AccessibilityTraitTestImplementation::testGetAggregateReportDir());
  }

  #[DataProvider('dataProviderRenderJunitFailuresByThreshold')]
  public function testRenderJunitFailuresByThreshold(?string $threshold, int $expected_failures): void {
    $xml = $this->testObject->callRenderJunit(static::createJunitResults(), 'Feature', 'Scenario', $threshold);

    $doc = simplexml_load_string($xml);
    $this->assertInstanceOf(\SimpleXMLElement::class, $doc, 'The rendered report is well-formed XML.');
    $this->assertCount($expected_failures, $doc->xpath('//failure') ?: []);
    // Every emitted testcase is counted, so tests stays 5 (3 violations + 2
    // passes) whether a violation is a failure or advisory.
    $this->assertCount(5, $doc->xpath('//testcase') ?: []);
    $this->assertSame('5', (string) $doc['tests']);
    $this->assertSame((string) $expected_failures, (string) $doc['failures']);
    $this->assertSame((string) $expected_failures, (string) $doc->testsuite['failures']);
  }

  public static function dataProviderRenderJunitFailuresByThreshold(): array {
    return [
      'warning (never) fails on nothing' => ['never', 0],
      'default (null) resolves to any and fails on all' => [NULL, 3],
      'any fails on all' => ['any', 3],
      'critical fails on the critical only' => ['critical', 1],
      'serious fails on critical and serious' => ['serious', 2],
      'moderate fails on all three' => ['moderate', 3],
    ];
  }

  public function testRenderJunitWarningKeepsAdvisoryViolationsVisibleWithoutFailing(): void {
    $xml = $this->testObject->callRenderJunit(static::createJunitResults(), 'Feature', 'Scenario', 'never');

    $doc = simplexml_load_string($xml);
    $this->assertInstanceOf(\SimpleXMLElement::class, $doc);
    $this->assertCount(0, $doc->xpath('//failure') ?: []);
    $this->assertStringNotContainsString('<failure', $xml);
    // Every finding stays visible as a passing testcase with <system-out>.
    $this->assertCount(3, $doc->xpath('//system-out') ?: []);
    $this->assertStringContainsString('classname="accessibility.image-alt"', $xml);
    $this->assertStringContainsString('classname="accessibility.link-name"', $xml);
  }

  public function testRenderJunitCountsEveryEmittedTestcase(): void {
    $results = [
      [
        'url' => '/',
        'rules' => 'wcag2a',
        'result' => [
          'violations' => [
            ['id' => 'image-alt', 'impact' => 'critical', 'help' => 'Images must have alternative text', 'helpUrl' => 'https://example.com/image-alt', 'nodes' => [['target' => ['img.a'], 'html' => '<img class="a">'], ['target' => ['img.b'], 'html' => '<img class="b">']]],
          ],
          'incomplete' => [],
          'passes' => [['id' => 'document-title'], ['id' => 'html-has-lang'], ['id' => 'region']],
        ],
      ],
    ];

    $xml = $this->testObject->callRenderJunit($results, 'Feature', 'Scenario', 'any');

    $doc = simplexml_load_string($xml);
    $this->assertInstanceOf(\SimpleXMLElement::class, $doc);
    // 1 <failure> per affected node (2), plus 3 passing testcases: tests and
    // failures count actual emitted <testcase> elements, not violations.
    $this->assertCount(2, $doc->xpath('//failure') ?: []);
    $this->assertCount(5, $doc->xpath('//testcase') ?: []);
    $this->assertSame('5', (string) $doc['tests']);
    $this->assertSame('2', (string) $doc['failures']);
  }

  public function testAssessPagesPassesWhenEveryPageIsClean(): void {
    $sweep = static::createSweep();

    $sweep->accessibilityAssessPages(['/', '/about', 'https://other.example.com/page'], 'wcag2a');

    $this->assertSame(['http://nginx:8080/', 'http://nginx:8080/about', 'https://other.example.com/page'], $sweep->session->visited);
    $this->assertCount(3, $sweep->testGetResults());
    $this->assertSame([], $sweep->testGetSkippedPages());
  }

  public function testAssessPagesAssessesDuplicatePathsOnce(): void {
    $sweep = static::createSweep();

    $sweep->accessibilityAssessPages(['/', '/about', '/'], 'wcag2a');

    $this->assertSame(['http://nginx:8080/', 'http://nginx:8080/about'], $sweep->session->visited);
  }

  public function testAssessPagesFailsOnAnEmptyList(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The list of pages to assess is empty.');

    static::createSweep()->accessibilityAssessPages([], 'wcag2a');
  }

  public function testAssessPagesFailsOnViolationsAfterAssessingEveryPage(): void {
    $sweep = static::createSweep(['http://nginx:8080/bad' => static::createRawViolation()]);

    try {
      $sweep->accessibilityAssessPages(['/bad', '/', '/contact'], 'wcag2a');
      $this->fail('The sweep should fail on violations.');
    }
    catch (ExpectationException $exception) {
      $this->assertStringContainsString('Accessibility gate failed on /bad (rules: wcag2a, threshold: any, fail_on_incomplete: no):', $exception->getMessage());
      $this->assertStringContainsString('violation [critical] image-alt - Images must have alternate text', $exception->getMessage());
      $this->assertStringNotContainsString('Accessibility gate failed on /contact', $exception->getMessage());
      $this->assertStringNotContainsString('skipped', $exception->getMessage());
    }

    $this->assertSame(['http://nginx:8080/bad', 'http://nginx:8080/', 'http://nginx:8080/contact'], $sweep->session->visited);
  }

  public function testAssessPagesAppliesTheThresholdToEveryPage(): void {
    $sweep = static::createSweep(['http://nginx:8080/bad' => static::createRawViolation('serious')]);
    $sweep->setParameters(['steps' => ['accessibility' => ['failure_threshold' => 'critical']]]);

    $sweep->accessibilityAssessPages(['/bad', '/'], 'wcag2a');

    $this->assertCount(2, $sweep->testGetResults());
  }

  public function testAssessPagesInAutomaticModeLeavesTheOutcomeToTheGate(): void {
    $sweep = static::createSweep(['http://nginx:8080/bad' => static::createRawViolation()]);
    $sweep->testSetAutoMode(TRUE);

    $sweep->accessibilityAssessPages(['/bad', '/'], 'wcag2a');

    $this->assertCount(2, $sweep->testGetResults());
    $this->assertSame('http://nginx:8080/', $sweep->testGetLastCheckedUrl());
  }

  public function testAssessPagesSkipsPagesItCannotAssess(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine failed.')]);
    $sweep->session->failingUrls = ['http://nginx:8080/unreachable'];
    $sweep->testSetAutoMode(TRUE);

    $sweep->accessibilityAssessPages(['/broken', '/unreachable', '/', '/broken-again'], 'wcag2a');

    $this->assertSame([
      ['url' => 'http://nginx:8080/broken', 'reason' => 'Engine failed.'],
      ['url' => 'http://nginx:8080/unreachable', 'reason' => 'Unable to reach "http://nginx:8080/unreachable".'],
    ], $sweep->testGetSkippedPages());
    $this->assertCount(2, $sweep->testGetResults());
  }

  public function testAssessPagesMarksSkippedLastPageAsChecked(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine failed.')]);
    $sweep->testSetAutoMode(TRUE);

    $sweep->accessibilityAssessPages(['/', '/broken'], 'wcag2a');

    $this->assertSame('http://nginx:8080/broken', $sweep->testGetLastCheckedUrl());
  }

  public function testAssessPagesKeepsTheLastCheckedUrlWhenTheSessionCannotReportOne(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine failed.')]);
    $sweep->testSetAutoMode(TRUE);
    $sweep->session->failCurrentUrlAfter = 'http://nginx:8080/broken';

    $sweep->accessibilityAssessPages(['/', '/broken'], 'wcag2a');

    $this->assertSame('http://nginx:8080/', $sweep->testGetLastCheckedUrl());
  }

  public function testAssessPagesListsSkippedPagesWhenItFails(): void {
    $sweep = static::createSweep([
      'http://nginx:8080/broken' => new \RuntimeException('Engine failed.'),
      'http://nginx:8080/bad' => static::createRawViolation(),
    ]);

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessageMatches('/Accessibility gate failed on \/bad .*' . preg_quote(PHP_EOL . '  skipped "/broken": Engine failed.', '/') . '$/s');

    $sweep->accessibilityAssessPages(['/broken', '/bad'], 'wcag2a');
  }

  public function testAssessPagesFailsWhenNoPageCouldBeAssessed(): void {
    $sweep = static::createSweep([
      'http://nginx:8080/broken' => new \RuntimeException('Engine failed.'),
      'http://nginx:8080/also-broken' => new \RuntimeException('Engine timed out.'),
    ]);
    $sweep->testSetAutoMode(TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No page could be assessed:' . PHP_EOL . '  skipped "/broken": Engine failed.' . PHP_EOL . '  skipped "/also-broken": Engine timed out.');

    $sweep->accessibilityAssessPages(['/broken', '/also-broken'], 'wcag2a');
  }

  public function testAssessPagesInTableReadsThePathColumn(): void {
    $sweep = static::createSweep();

    $sweep->accessibilityAssessPagesInTable(new TableNode([['label', 'path'], ['Home', ' / '], ['About', '/about']]));
    $sweep->accessibilityAssessPagesInTableForTags('wcag2aa', new TableNode([['path'], ['/contact']]));

    $this->assertSame(['http://nginx:8080/', 'http://nginx:8080/about', 'http://nginx:8080/contact'], $sweep->session->visited);
    $this->assertSame(['wcag2a,wcag2aa', 'wcag2a,wcag2aa', 'wcag2aa'], array_column($sweep->testGetResults(), 'rules'));
  }

  #[DataProvider('dataProviderAssessPagesInTableRejectsTable')]
  public function testAssessPagesInTableRejectsTable(array $rows, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    static::createSweep()->accessibilityAssessPagesInTable(new TableNode($rows));
  }

  public static function dataProviderAssessPagesInTableRejectsTable(): array {
    return [
      'no path column' => [[['url'], ['/']], 'The table of pages has no "path" column.'],
      'an empty path cell' => [[['path'], ['/'], ['  ']], 'The table of pages has an empty "path" cell.'],
      'no rows' => [[['path']], 'The list of pages to assess is empty.'],
    ];
  }

  /**
   * Tests the paths a sitemap lists after following, parsing and filtering.
   *
   * @param array<string, string> $sitemaps
   *   Sitemap bodies keyed by the absolute URL they are served from.
   * @param int|null $max_depth
   *   Deepest path depth to keep.
   * @param int|null $limit
   *   Most paths to return.
   * @param list<string> $expected
   *   The expected paths.
   */
  #[DataProvider('dataProviderGetSitemapPaths')]
  public function testGetSitemapPaths(array $sitemaps, ?int $max_depth, ?int $limit, array $expected): void {
    $sweep = static::createSweep();
    $sweep->sitemaps = $sitemaps;

    $this->assertSame($expected, $sweep->accessibilityGetSitemapPaths('/sitemap.xml', $max_depth, $limit));
  }

  public static function dataProviderGetSitemapPaths(): array {
    $pages = static::createUrlset(['https://www.example.com/', 'https://www.example.com/about', 'https://www.example.com/about/team/', 'https://www.example.com/news?page=2#top', 'https://www.example.com/about']);

    return [
      'the path and query of every page in order' => [['http://nginx:8080/sitemap.xml' => $pages], NULL, NULL, ['/', '/about', '/about/team/', '/news?page=2']],
      'pages up to a path depth' => [['http://nginx:8080/sitemap.xml' => $pages], 1, NULL, ['/', '/about', '/news?page=2']],
      'pages at depth 0 only' => [['http://nginx:8080/sitemap.xml' => $pages], 0, NULL, ['/']],
      'a limit applied after the depth filter' => [['http://nginx:8080/sitemap.xml' => $pages], 1, 2, ['/', '/about']],
      'a limit alone' => [['http://nginx:8080/sitemap.xml' => $pages], NULL, 3, ['/', '/about', '/about/team/']],
      'a sitemap without a namespace' => [['http://nginx:8080/sitemap.xml' => '<urlset><url><loc>https://www.example.com/plain</loc></url></urlset>'], NULL, NULL, ['/plain']],
      'extension locations ignored' => [
        ['http://nginx:8080/sitemap.xml' => '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"><url><loc>https://www.example.com/page</loc><image:image><image:loc>https://www.example.com/image.png</image:loc></image:image></url></urlset>'],
        NULL,
        NULL,
        ['/page'],
      ],
      'empty and malformed locations dropped' => [['http://nginx:8080/sitemap.xml' => static::createUrlset(['', 'http:///broken', '/relative'])], NULL, NULL, ['/relative']],
      'an index followed to every sitemap' => [
        [
          'http://nginx:8080/sitemap.xml' => static::createIndex(['https://www.example.com/sitemap.xml?page=1', 'https://www.example.com/sitemap.xml?page=2']),
          'http://nginx:8080/sitemap.xml?page=1' => static::createUrlset(['https://www.example.com/one']),
          'http://nginx:8080/sitemap.xml?page=2' => static::createUrlset(['https://www.example.com/two', 'https://www.example.com/one']),
        ],
        NULL,
        NULL,
        ['/one', '/two'],
      ],
      'an index listing itself read once' => [
        [
          'http://nginx:8080/sitemap.xml' => static::createIndex(['https://www.example.com/sitemap.xml', 'https://www.example.com/pages.xml']),
          'http://nginx:8080/pages.xml' => static::createUrlset(['https://www.example.com/page']),
        ],
        NULL,
        NULL,
        ['/page'],
      ],
    ];
  }

  #[DataProvider('dataProviderGetSitemapPathsFails')]
  public function testGetSitemapPathsFails(array $sitemaps, ?int $max_depth, string $expected_message): void {
    $sweep = static::createSweep();
    $sweep->sitemaps = $sitemaps;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $sweep->accessibilityGetSitemapPaths('/sitemap.xml', $max_depth);
  }

  public static function dataProviderGetSitemapPathsFails(): array {
    return [
      'a missing sitemap' => [[], NULL, 'The sitemap "/sitemap.xml" returned HTTP status 404.'],
      'an unreachable host' => [['http://nginx:8080/sitemap.xml' => FALSE], NULL, 'Unable to fetch the sitemap "/sitemap.xml": Could not resolve host'],
      'an empty body' => [['http://nginx:8080/sitemap.xml' => " \n"], NULL, 'The sitemap "/sitemap.xml" is not valid XML.'],
      'a body that is not XML' => [['http://nginx:8080/sitemap.xml' => '{"pages": []}'], NULL, 'The sitemap "/sitemap.xml" is not valid XML.'],
      'another root element' => [['http://nginx:8080/sitemap.xml' => '<html><body/></html>'], NULL, 'The sitemap "/sitemap.xml" has the root element "html", but it should be "urlset" or "sitemapindex".'],
      'a missing child sitemap' => [['http://nginx:8080/sitemap.xml' => static::createIndex(['https://www.example.com/gone.xml'])], NULL, 'The sitemap "/gone.xml" returned HTTP status 404.'],
      'no pages' => [['http://nginx:8080/sitemap.xml' => static::createUrlset([])], NULL, 'The sitemap "/sitemap.xml" lists no pages.'],
      'no pages within the depth' => [['http://nginx:8080/sitemap.xml' => static::createUrlset(['https://www.example.com/a/b'])], 1, 'The sitemap "/sitemap.xml" lists no pages with a path depth of at most 1.'],
    ];
  }

  public function testGetSitemapPathsFetchesAsTheVisitor(): void {
    $sweep = static::createSweep();
    $sweep->sitemaps = ['https://cdn.example.com/sitemap.xml' => static::createUrlset(['https://www.example.com/page'])];

    $this->assertSame(['/page'], $sweep->accessibilityGetSitemapPaths('https://cdn.example.com/sitemap.xml'));
    $this->assertSame(['https://cdn.example.com/sitemap.xml'], $sweep->requests);
  }

  #[DataProvider('dataProviderAssessPagesInSitemapSteps')]
  public function testAssessPagesInSitemapSteps(string $method, array $arguments, array $options, array $expected): void {
    $sweep = static::createSweep();
    $sweep->setParameters(['steps' => ['accessibility' => $options]]);
    $sweep->sitemaps = [
      'http://nginx:8080/sitemap.xml' => static::createUrlset(['https://www.example.com/', 'https://www.example.com/about', 'https://www.example.com/about/team']),
      'http://nginx:8080/custom.xml' => static::createUrlset(['https://www.example.com/custom']),
    ];

    $sweep->{$method}(...$arguments);

    $this->assertSame($expected, $sweep->session->visited);
  }

  public static function dataProviderAssessPagesInSitemapSteps(): array {
    return [
      'the default sitemap' => ['accessibilityAssessPagesInDefaultSitemap', [], [], ['http://nginx:8080/', 'http://nginx:8080/about', 'http://nginx:8080/about/team']],
      'the configured sitemap, depth and limit' => ['accessibilityAssessPagesInDefaultSitemap', [], ['sitemap_path' => '/custom.xml', 'sitemap_max_depth' => 1, 'sitemap_limit' => 1], ['http://nginx:8080/custom']],
      'a named sitemap with the configured depth' => ['accessibilityAssessPagesInSitemap', ['/sitemap.xml'], ['sitemap_max_depth' => 0], ['http://nginx:8080/']],
      'a depth argument with the configured limit' => ['accessibilityAssessPagesInSitemapWithDepth', ['/sitemap.xml', '1'], ['sitemap_limit' => 1], ['http://nginx:8080/']],
      'a depth argument overriding the configured depth' => ['accessibilityAssessPagesInSitemapWithDepth', ['/sitemap.xml', '2'], ['sitemap_max_depth' => 0], ['http://nginx:8080/', 'http://nginx:8080/about', 'http://nginx:8080/about/team']],
      'depth and limit arguments' => ['accessibilityAssessPagesInSitemapWithDepthWithLimit', ['/sitemap.xml', '2', '2'], ['sitemap_limit' => 1], ['http://nginx:8080/', 'http://nginx:8080/about']],
    ];
  }

  #[DataProvider('dataProviderAssessPagesInSitemapStepsRejectArgument')]
  public function testAssessPagesInSitemapStepsRejectArgument(string $depth, string $count, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    static::createSweep()->accessibilityAssessPagesInSitemapWithDepthWithLimit('/sitemap.xml', $depth, $count);
  }

  public static function dataProviderAssessPagesInSitemapStepsRejectArgument(): array {
    return [
      'a negative depth' => ['-1', '1', 'The depth must be 0 or greater, but "-1" was given.'],
      'a depth that is not a number' => ['one', '1', 'The depth must be an integer, but "one" was given.'],
      'a limit of 0' => ['1', '0', 'The count must be 1 or greater, but "0" was given.'],
    ];
  }

  #[DataProvider('dataProviderGetSitemapOptions')]
  public function testGetSitemapOptions(array $options, string $path, ?int $max_depth, ?int $limit): void {
    $this->testObject->setParameters(['steps' => ['accessibility' => $options]]);

    $this->assertSame($path, $this->testObject->accessibilityGetSitemapPath());
    $this->assertSame($max_depth, $this->testObject->accessibilityGetSitemapMaxDepth());
    $this->assertSame($limit, $this->testObject->accessibilityGetSitemapLimit());
  }

  public static function dataProviderGetSitemapOptions(): array {
    return [
      'the defaults' => [[], '/sitemap.xml', NULL, NULL],
      'set values' => [['sitemap_path' => '/map.xml', 'sitemap_max_depth' => 0, 'sitemap_limit' => 1], '/map.xml', 0, 1],
      'a negative depth and a limit below 1' => [['sitemap_max_depth' => -5, 'sitemap_limit' => -1], '/sitemap.xml', NULL, NULL],
    ];
  }

  #[DataProvider('dataProviderGetPathDepth')]
  public function testGetPathDepth(string $path, int $expected): void {
    $this->assertSame($expected, $this->testObject->callGetPathDepth($path));
  }

  public static function dataProviderGetPathDepth(): array {
    return [
      'the root' => ['/', 0],
      'an empty path' => ['', 0],
      '1 segment' => ['/about', 1],
      'a trailing slash' => ['/about/', 1],
      '2 segments' => ['/about/team', 2],
      'repeated slashes' => ['/about//team', 2],
      'a query string' => ['/news?section=a/b', 1],
    ];
  }

  public function testEnforceGateListsSkippedPages(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine failed.'), 'http://nginx:8080/bad' => static::createRawViolation()]);
    $sweep->testSetAutoMode(TRUE);
    $sweep->accessibilityAssessPages(['/broken', '/bad'], 'wcag2a');

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage('Auto accessibility gate failed (threshold: any, fail_on_incomplete: no):' . PHP_EOL . '  violation [critical] image-alt on /bad' . PHP_EOL . '  skipped "/broken": Engine failed.');

    $sweep->callEnforceGate();
  }

  public function testEnforceGateIgnoresSkippedPagesAlone(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine failed.')]);
    $sweep->testSetAutoMode(TRUE);
    $sweep->accessibilityAssessPages(['/broken', '/'], 'wcag2a');

    $sweep->callEnforceGate();

    $this->assertCount(1, $sweep->testGetSkippedPages());
  }

  public function testReportsListSkippedPages(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine <failed> & "stopped".')]);
    $sweep->testSetAutoMode(TRUE);
    $sweep->accessibilityAssessPages(['/broken', '/'], 'wcag2a');

    $html = $sweep->accessibilityRenderHtmlSections();
    $this->assertStringContainsString('<section class="page skipped"><h2>/broken</h2><p class="meta">Skipped: Engine &lt;failed&gt; &amp; &quot;stopped&quot;.</p></section>', $html);

    $doc = simplexml_load_string($sweep->callRenderJunit());
    $this->assertInstanceOf(\SimpleXMLElement::class, $doc);
    $this->assertSame('Engine <failed> & "stopped".', (string) ($doc->xpath('//testcase[@classname="accessibility.skipped"]/skipped')[0]['message'] ?? ''));
    $this->assertSame('/broken', (string) ($doc->xpath('//testsuite[@skipped="1"]')[0]['name'] ?? ''));
    $this->assertSame((string) (count($doc->xpath('//testcase') ?: [])), (string) $doc['tests']);

    $sweep->callAggregateCapture('/captured/dir');
    $aggregate = AccessibilityTraitSweepTestImplementation::testGetAggregate();
    $this->assertSame([['url' => '/broken', 'reason' => 'Engine <failed> & "stopped".']], $aggregate[0]['skipped']);

    $report = static::renderSample($aggregate, '2026-01-02 03:04');
    $this->assertStringContainsString('<h2>Pages not assessed</h2>', $report);
    $this->assertStringContainsString('<tr class="bad"><td class="url">/broken</td><td>Engine &lt;failed&gt; &amp; &quot;stopped&quot;.</td><td class="muted">Feature &gt; Scenario</td></tr>', $report);
  }

  public function testAggregateOmitsSkippedSectionWithoutSkippedPages(): void {
    $this->assertStringNotContainsString('Pages not assessed', static::renderSample(static::createSampleAggregate(), '2026-01-02 03:04'));
  }

  public function testAfterScenarioWritesReportsWhenEveryPageWasSkipped(): void {
    $sweep = static::createSweep(['http://nginx:8080/broken' => new \RuntimeException('Engine failed.')]);
    $sweep->setParameters(['steps' => ['accessibility' => ['report_dir' => static::$tmp . '/reports']]]);
    $sweep->accessibilityBeforeScenario($this->createBeforeScenarioScope());

    try {
      $sweep->accessibilityAssessPages(['/broken'], 'wcag2a');
    }
    catch (\RuntimeException) {
      // The sweep fails because no page could be assessed.
    }

    $sweep->accessibilityAfterScenario($this->createAfterScenarioScope());

    $this->assertFileExists(static::$tmp . '/reports/feature__scenario.html');
    $this->assertStringContainsString('skipped message="Engine failed."', (string) file_get_contents(static::$tmp . '/reports/junit-feature__scenario.xml'));
  }

  /**
   * Creates a sweep test implementation over a recording session.
   *
   * @param array<string, array<string, mixed>|\Exception> $engine_results
   *   Raw engine results or exceptions, keyed by the URL they answer for.
   */
  protected static function createSweep(array $engine_results = []): AccessibilityTraitSweepTestImplementation {
    $sweep = new AccessibilityTraitSweepTestImplementation();
    $sweep->session = new RecordingSession(new BrowserKitDriver(new HttpBrowser(new MockHttpClient())));
    $sweep->engineResults = $engine_results;
    $sweep->setMinkParameter('base_url', 'http://nginx:8080');

    $mink = new Mink(['default' => $sweep->session]);
    $mink->setDefaultSessionName('default');
    $sweep->setMink($mink);

    return $sweep;
  }

  /**
   * Creates a raw engine result with 1 violation.
   *
   * @param string $impact
   *   The impact of the violation.
   *
   * @return array<string, mixed>
   *   A raw result in the default engine's shape.
   */
  protected static function createRawViolation(string $impact = 'critical'): array {
    return [
      'violations' => [['id' => 'image-alt', 'impact' => $impact, 'help' => 'Images must have alternate text', 'helpUrl' => 'https://example.com/image-alt', 'nodes' => [['target' => ['img'], 'html' => '<img>']]]],
      'incomplete' => [],
      'passes' => [],
    ];
  }

  /**
   * Creates a sitemap listing the given page locations.
   *
   * @param list<string> $locations
   *   The text of each `loc` element.
   */
  protected static function createUrlset(array $locations): string {
    $urls = array_map(static fn(string $location): string => sprintf('<url><loc>%s</loc></url>', $location), $locations);

    return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode('', $urls) . '</urlset>';
  }

  /**
   * Creates a sitemap index listing the given sitemap locations.
   *
   * @param list<string> $locations
   *   The text of each `loc` element.
   */
  protected static function createIndex(array $locations): string {
    $sitemaps = array_map(static fn(string $location): string => sprintf('<sitemap><loc>%s</loc></sitemap>', $location), $locations);

    return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode('', $sitemaps) . '</sitemapindex>';
  }

  /**
   * Clears the static state the trait keeps for the whole process.
   */
  protected static function resetStaticState(): void {
    AccessibilityTraitTestImplementation::testSetBaseDir(NULL);
    AccessibilityTraitTestImplementation::testSetCachedJs(NULL);
    AccessibilityTraitTestImplementation::testSetAggregate([]);
    AccessibilityTraitTestImplementation::testSetAggregateReportDir(NULL);
    AccessibilityTraitRetryTestImplementation::testSetCachedJs(NULL);
    AccessibilityTraitSweepTestImplementation::testSetAggregate([]);
  }

  /**
   * Renders a sample accumulator through the full data + render pipeline.
   *
   * @param array<int, array<string, mixed>> $aggregate
   *   Sample accumulator.
   * @param string $generated
   *   Fixed generation timestamp.
   */
  protected static function renderSample(array $aggregate, string $generated): string {
    return AccessibilityTraitTestImplementation::accessibilityRenderAggregate(AccessibilityTraitTestImplementation::callAggregateData($aggregate, $generated));
  }

  /**
   * Builds a representative accumulator.
   *
   * The accumulator holds 2 scenarios sharing a URL, a blank tab and
   * mixed-impact findings.
   *
   * @return array<int, array<string, mixed>>
   *   Sample aggregate data in the shape produced by
   *   accessibilityAggregateCapture().
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
        'fail_on_incomplete' => FALSE,
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
        'fail_on_incomplete' => TRUE,
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
   * Builds an accumulator for a run that found no violations at all.
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
        'fail_on_incomplete' => FALSE,
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

  /**
   * Tests the mode and gate the scenario and feature tags resolve to.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param array{bool, string|null, bool|null} $expected
   *   Automatic mode, the threshold override and the incomplete-fail override.
   */
  #[DataProvider('dataProviderBeforeScenarioResolvesTags')]
  public function testBeforeScenarioResolvesTags(array $scenario_tags, array $feature_tags, array $expected): void {
    $this->testObject->accessibilityBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

    $this->assertSame($expected, $this->testObject->testGetTagState());
  }

  public static function dataProviderBeforeScenarioResolvesTags(): array {
    return [
      'no tags' => [[], [], [FALSE, NULL, NULL]],
      'the bare tag on the scenario' => [['accessibility'], [], [TRUE, NULL, NULL]],
      'the bare tag on the feature' => [[], ['accessibility'], [TRUE, NULL, NULL]],
      'a threshold on the feature' => [[], ['accessibility:serious'], [TRUE, 'serious', NULL]],
      'the scenario threshold replaces the feature one' => [['accessibility:critical'], ['accessibility:serious'], [TRUE, 'critical', NULL]],
      'the warning variant' => [['accessibility:warning'], [], [TRUE, 'never', NULL]],
      'the short warning variant' => [['accessibility:warn'], [], [TRUE, 'never', NULL]],
      'the any variant' => [['accessibility:any'], [], [TRUE, 'any', NULL]],
      'the strict variant' => [['accessibility:strict'], [], [TRUE, NULL, TRUE]],
      'a variant in capitals' => [['accessibility:SERIOUS'], [], [TRUE, 'serious', NULL]],
      'an unknown variant switches the mode on alone' => [['accessibility:bogus'], [], [TRUE, NULL, NULL]],
      'an empty variant names nothing' => [['accessibility:'], [], [FALSE, NULL, NULL]],
      'skipped on the feature' => [['accessibility:serious'], ['behat-steps-skip:AccessibilityTrait'], [FALSE, NULL, NULL]],
    ];
  }

  /**
   * Builds a scenario result with 1 violation per impact plus 2 passes.
   *
   * @return array<int, array{url: string, rules: string, result: array<string, mixed>}>
   *   A single-page result: critical, serious and moderate violations (1
   *   affected node each) alongside 2 passing rules.
   */
  protected static function createJunitResults(): array {
    return [
      [
        'url' => '/',
        'rules' => 'wcag2a,wcag2aa',
        'result' => [
          'violations' => [
            ['id' => 'image-alt', 'impact' => 'critical', 'help' => 'Images must have alternative text', 'helpUrl' => 'https://example.com/image-alt', 'nodes' => [['target' => ['img'], 'html' => '<img src="x">']]],
            ['id' => 'link-name', 'impact' => 'serious', 'help' => 'Links must have discernible text', 'helpUrl' => 'https://example.com/link-name', 'nodes' => [['target' => ['a'], 'html' => '<a href="#"></a>']]],
            ['id' => 'region', 'impact' => 'moderate', 'help' => 'All page content should be contained by landmarks', 'helpUrl' => 'https://example.com/region', 'nodes' => [['target' => ['div'], 'html' => '<div></div>']]],
          ],
          'incomplete' => [],
          'passes' => [['id' => 'document-title'], ['id' => 'html-has-lang']],
        ],
      ],
    ];
  }

}

/**
 * Test implementation of AccessibilityTrait.
 */
class AccessibilityTraitTestImplementation extends WebRawContext {

  use AccessibilityTrait;

  /**
   * Source the engine is read from.
   */
  public string $engineUrl = '';

  /**
   * Number of attempts the engine fetch is allowed.
   */
  public int $engineAttempts = 3;

  /**
   * Per-attempt timeout for the engine fetch.
   */
  public int $engineTimeout = 10;

  /**
   * Count of engine URL lookups, 1 per uncached fetch.
   */
  public int $engineReads = 0;

  /**
   * Returns the mode and gate overrides the tags resolved to.
   *
   * @return array{bool, string|null, bool|null}
   *   Automatic mode, the threshold override and the incomplete-fail override.
   */
  public function testGetTagState(): array {
    return [$this->accessibilityAutoMode, $this->accessibilityScenarioThreshold, $this->accessibilityScenarioFailOnIncomplete];
  }

  public static function testSetBaseDir(?string $directory): void {
    static::$accessibilityBaseDir = $directory;
  }

  public static function testGetBaseDir(): ?string {
    return static::$accessibilityBaseDir;
  }

  public static function testSetAggregate(array $aggregate): void {
    static::$accessibilityAggregate = $aggregate;
  }

  public static function testGetAggregate(): array {
    return static::$accessibilityAggregate;
  }

  public static function testSetAggregateReportDir(?string $directory): void {
    static::$accessibilityAggregateReportDir = $directory;
  }

  public static function testGetAggregateReportDir(): ?string {
    return static::$accessibilityAggregateReportDir;
  }

  public static function callAggregateData(array $aggregate, string $generated): array {
    return static::accessibilityAggregateData($aggregate, $generated);
  }

  public static function callAggregatePages(array $aggregate): array {
    return static::accessibilityAggregatePages($aggregate);
  }

  public static function callAggregateRollup(array $pages): array {
    return static::accessibilityAggregateRollup($pages);
  }

  public static function callWriteAggregateReport(): void {
    static::accessibilityWriteAggregateReport();
  }

  public static function callAggregateFilename(int $time): string {
    return static::accessibilityAggregateFilename($time);
  }

  public function callGetPathDepth(string $path): int {
    return $this->accessibilityGetPathDepth($path);
  }

  public function callAggregateCapture(array $results, string $feature, string $scenario, string $directory): void {
    $this->accessibilityResults = $results;
    $this->accessibilityFeatureName = $feature;
    $this->accessibilityScenarioName = $scenario;
    $this->accessibilityAggregateCapture($directory);
  }

  public function callRenderJunit(array $results, string $feature, string $scenario, ?string $threshold = NULL): string {
    $this->accessibilityResults = $results;
    $this->accessibilityFeatureName = $feature;
    $this->accessibilityScenarioName = $scenario;
    $this->accessibilityScenarioThreshold = $threshold;

    return $this->accessibilityRenderJunit();
  }

  public function accessibilityGetCdnUrl(): string {
    $this->engineReads++;

    return $this->engineUrl;
  }

  public function accessibilityGetFetchAttempts(): int {
    return $this->engineAttempts;
  }

  public function accessibilityGetFetchTimeout(): int {
    return $this->engineTimeout;
  }

  public static function testSetCachedJs(?string $js): void {
    static::$accessibilityCachedJs = $js;
  }

}

/**
 * A test implementation that scripts the engine and the detached client.
 */
class AccessibilityTraitSweepTestImplementation extends WebRawContext {

  use AccessibilityTrait;

  /**
   * The session the sweep visits pages through.
   */
  public RecordingSession $session;

  /**
   * Raw engine results or exceptions, keyed by the URL they answer for.
   *
   * @var array<string, array<string, mixed>|\Exception>
   */
  public array $engineResults = [];

  /**
   * Sitemap bodies keyed by URL, or FALSE for a URL whose host is unreachable.
   *
   * @var array<string, string|false>
   */
  public array $sitemaps = [];

  /**
   * URLs the detached client requested.
   *
   * @var list<string>
   */
  public array $requests = [];

  /**
   * {@inheritdoc}
   */
  public function accessibilityRunEngine(string $rules): array {
    $outcome = $this->engineResults[$this->getSession()->getCurrentUrl()] ?? ['violations' => [], 'incomplete' => [], 'passes' => [['id' => 'document-title']]];

    if ($outcome instanceof \Exception) {
      throw $outcome;
    }

    return $outcome;
  }

  /**
   * {@inheritdoc}
   */
  public function httpDetachedClient(array $options = []): AbstractBrowser {
    $client = new MockHttpClient(function (string $method, string $url): MockResponse {
      $this->requests[] = $url;
      $body = $this->sitemaps[$url] ?? NULL;

      if ($body === FALSE) {
        return new MockResponse('', ['error' => 'Could not resolve host']);
      }

      return $body === NULL ? new MockResponse('Not found', ['http_code' => 404]) : new MockResponse($body);
    });

    return new HttpBrowser($client);
  }

  public function testSetAutoMode(bool $is_auto): void {
    $this->accessibilityAutoMode = $is_auto;
  }

  /**
   * Returns the results the scenario collected.
   *
   * @return array<int, array{url: string, rules: string, result: array<string, mixed>}>
   *   The results the scenario collected.
   */
  public function testGetResults(): array {
    return $this->accessibilityResults;
  }

  /**
   * Returns the pages the scenario skipped.
   *
   * @return array<int, array{url: string, reason: string}>
   *   The pages the scenario skipped.
   */
  public function testGetSkippedPages(): array {
    return $this->accessibilitySkippedPages;
  }

  public function testGetLastCheckedUrl(): string {
    return $this->accessibilityLastCheckedUrl;
  }

  public static function testSetAggregate(array $aggregate): void {
    static::$accessibilityAggregate = $aggregate;
  }

  public static function testGetAggregate(): array {
    return static::$accessibilityAggregate;
  }

  public function callEnforceGate(): void {
    $this->accessibilityEnforceGate();
  }

  public function callRenderJunit(): string {
    return $this->accessibilityRenderJunit();
  }

  public function callAggregateCapture(string $directory): void {
    $this->accessibilityFeatureName = 'Feature';
    $this->accessibilityScenarioName = 'Scenario';
    $this->accessibilityAggregateCapture($directory);
  }

}

/**
 * A test implementation that keeps the trait's own engine fetch defaults.
 */
class AccessibilityTraitFetchDefaultsTestImplementation extends WebRawContext {

  use AccessibilityTrait;

}

/**
 * A test implementation that counts reads and controls their outcome.
 */
class AccessibilityTraitRetryTestImplementation extends WebRawContext {

  use AccessibilityTrait;

  /**
   * Count of reads issued by the retry loop.
   */
  public int $engineReads = 0;

  /**
   * Number of leading reads that fail before one succeeds.
   */
  public int $engineFailures = 0;

  /**
   * Source returned by a read that succeeds.
   */
  public string $engineContent = '';

  /**
   * Number of attempts the retry loop is allowed.
   */
  public int $engineAttempts = 3;

  public function accessibilityGetCdnUrl(): string {
    return 'https://example.com/engine.js';
  }

  public function accessibilityGetFetchAttempts(): int {
    return $this->engineAttempts;
  }

  public function accessibilityGetFetchTimeout(): int {
    return 1;
  }

  public function accessibilityFetchJs(string $url, int $timeout): string|false {
    $this->engineReads++;

    return $this->engineReads <= $this->engineFailures ? FALSE : $this->engineContent;
  }

  public static function testSetCachedJs(?string $js): void {
    static::$accessibilityCachedJs = $js;
  }

}
