# Available helpers

### Index of Generic helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [AccessibilityTrait](#accessibilitytrait) | 35 | Assess accessibility of rendered pages. |
| [BasicAuthTrait](#basicauthtrait) | 1 | Keep HTTP basic authentication applied across session resets. |
| [CommandTrait](#commandtrait) | 5 | Run local shell commands and assert on their result. |
| [CookieTrait](#cookietrait) | 4 | Verify and inspect browser cookies. |
| [DateTrait](#datetrait) | 3 | Convert relative date expressions into timestamps or formatted dates. |
| [DiagnosticsTrait](#diagnosticstrait) | 15 | Append on-failure diagnostics to the failure message of any failed step. |
| [DropzoneTrait](#dropzonetrait) | 1 | Simulate a real multi-file drag-and-drop gesture onto a Dropzone target. |
| [ElementTrait](#elementtrait) | 13 | Interact with HTML elements using CSS selectors and DOM attributes. |
| [FieldTrait](#fieldtrait) | 7 | Manipulate form fields and verify widget functionality. |
| [FileDownloadTrait](#filedownloadtrait) | 8 | Test file download functionality with content verification. |
| [JavascriptTrait](#javascripttrait) | 4 | Automatically detect JavaScript errors during test execution. |
| [JsonTrait](#jsontrait) | 11 | Assert JSON responses with path and schema checks. |
| [KeyboardTrait](#keyboardtrait) | 2 | Simulate keyboard interactions in Drupal browser testing. |
| [MappingTrait](#mappingtrait) | 1 | Replace `{{ Key }}` tokens in step arguments and table cells. |
| [MessageTrait](#messagetrait) | 3 | Assert status, error, warning and success messages rendered on the page. |
| [MetatagTrait](#metatagtrait) | 15 | Assert `<meta>` tags and head/SEO markup in page markup. |
| [ModalTrait](#modaltrait) | 7 | Interact with and assert modals. |
| [PathTrait](#pathtrait) | 1 | Navigate and verify paths with URL validation. |
| [RandomTrait](#randomtrait) | 16 | Replace random-value tokens in step arguments and table cells. |
| [RegionTrait](#regiontrait) | 2 | Interact with and assert against named page regions. |
| [ResponsiveTrait](#responsivetrait) | 7 | Test responsive layouts with viewport control. |
| [RestTrait](#resttrait) | 3 | Lightweight REST API testing with no Drupal dependencies. |
| [TableTrait](#tabletrait) | 8 | Interact with HTML table elements and assert their content. |
| [WaitTrait](#waittrait) | 2 | Wait for a period of time or for AJAX to finish. |
| [XmlTrait](#xmltrait) | 13 | Assert XML responses with element and attribute checks. |

### Index of Drupal helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [Drupal\BigPipeTrait](#drupalbigpipetrait) | 4 | Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios. |
| [Drupal\BlockTrait](#drupalblocktrait) | 1 | Manage Drupal blocks. |
| [Drupal\CacheTrait](#drupalcachetrait) | 1 | Invalidate Drupal caches and run cron from within a scenario. |
| [Drupal\ConfigOverrideTrait](#drupalconfigoverridetrait) | 2 | Disable Drupal config overrides from settings.php during a scenario. |
| [Drupal\ConfigTrait](#drupalconfigtrait) | 9 | Assert and set stored Drupal configuration values with automatic revert. |
| [Drupal\ContentBlockTrait](#drupalcontentblocktrait) | 2 | Manage Drupal content blocks. |
| [Drupal\ContentTrait](#drupalcontenttrait) | 4 | Manage Drupal content with workflow and moderation support. |
| [Drupal\DraggableviewsTrait](#drupaldraggableviewstrait) | 1 | Order items in the Drupal Draggable Views. |
| [Drupal\DrushTrait](#drupaldrushtrait) | 4 | Run Drush commands and assert their output. |
| [Drupal\EckTrait](#drupalecktrait) | 3 | Manage Drupal ECK entities with custom type and bundle creation. |
| [Drupal\EmailTrait](#drupalemailtrait) | 9 | Test Drupal email functionality with content verification. |
| [Drupal\FileTrait](#drupalfiletrait) | 3 | Manage Drupal file entities with upload and storage operations. |
| [Drupal\MediaTrait](#drupalmediatrait) | 6 | Manage Drupal media entities with type-specific field handling. |
| [Drupal\MenuTrait](#drupalmenutrait) | 2 | Manage Drupal menu systems and menu link rendering. |
| [Drupal\ModuleTrait](#drupalmoduletrait) | 5 | Enable and disable Drupal modules with automatic state restoration. |
| [Drupal\ParagraphsTrait](#drupalparagraphstrait) | 4 | Manage Drupal paragraphs entities with structured field data. |
| [Drupal\QueueTrait](#drupalqueuetrait) | 3 | Manage and assert Drupal queue state. |
| [Drupal\RedirectTrait](#drupalredirecttrait) | 4 | Manage Drupal redirect entities provided by the contrib `redirect` module. |
| [Drupal\StateTrait](#drupalstatetrait) | 4 | Manage and assert Drupal State API values with automatic revert. |
| [Drupal\TaxonomyTrait](#drupaltaxonomytrait) | 2 | Manage Drupal taxonomy terms with vocabulary organization. |
| [Drupal\TestmodeTrait](#drupaltestmodetrait) | 2 | Configure Drupal Testmode module for controlled testing scenarios. |
| [Drupal\UserTrait](#drupalusertrait) | 8 | Manage Drupal users with role and permission assignments. |
| [Drupal\WatchdogTrait](#drupalwatchdogtrait) | 2 | Assert Drupal does not trigger PHP errors during scenarios using Watchdog. |
| [Drupal\WebformTrait](#drupalwebformtrait) | 3 | Manage Drupal webforms. |

### Index of Context helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [RawContext](#rawcontext) | 31 | Base context carrying the scenario lifecycle. |

---

## AccessibilityTrait

[Source](src/Steps/Generic/AccessibilityTrait.php), [Steps](STEPS.md#accessibilitytrait)

> Assess accessibility of rendered pages.

<details>
  <summary><code>protected function accessibilityAggregateCapture(string $dir): void</code></summary>

<br/>
Record the scenario's formatted results for the suite-level aggregate
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityAggregateData(array $aggregate, string $generated): array</code></summary>

<br/>
Assemble every value the renderer needs into one data array
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityAggregateFilename(int $time): string</code></summary>

<br/>
Build the timestamped aggregate report filename
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityAggregateFindings(array $issues): array</code></summary>

<br/>
Flatten normalized findings into render-ready rows
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityAggregatePages(array $aggregate): array</code></summary>

<br/>
De-duplicate assessed pages by URL across every scenario
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityAggregateRollup(array $pages): array</code></summary>

<br/>
Roll violations up by rule and tally totals by impact
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityAssess(string $rules): array</code></summary>

<br/>
Run the engine, normalize the result, record it for the scenario
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityBlankUrls(): array</code></summary>

<br/>
Return URL values that represent a blank tab rather than a real page
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityEffectiveFailOnIncomplete(): bool</code></summary>

<br/>
Return whether incomplete findings should fail the current scenario
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityEffectiveThreshold(): string</code></summary>

<br/>
Return the active gate threshold for the current scenario
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityEnforceGate(): void</code></summary>

<br/>
Fail the scenario when collected results breach the automatic gate
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityFilterViolations(array $violations, string $threshold): array</code></summary>

<br/>
Filter violations by impact threshold
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityFormatGateMessage(string $url, string $rules, string $threshold, bool $check_incomplete, array $violations, array $incomplete): string</code></summary>

<br/>
Build the human-readable error message for the explicit assertion
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityFormatUrl(string $url): string</code></summary>

<br/>
Format a page URL for display in reports and gate messages
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetAutoTag(): string</code></summary>

<br/>
Return the base tag name that enables automatic mode (no `@` prefix)
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetCdnUrl(): string</code></summary>

<br/>
Return the URL used by the default accessibilityGetJs() implementation
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityGetDefaultImpacts(): array</code></summary>

<br/>
Return the impact levels in descending severity order, statically
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetDefaultRules(): string</code></summary>

<br/>
Return the default rule identifier passed to the engine
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetFailOnIncomplete(): bool</code></summary>

<br/>
Return TRUE if "incomplete" findings should fail the gate by default
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetFailureThreshold(): string</code></summary>

<br/>
Return the default failure threshold
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetImpacts(): array</code></summary>

<br/>
Return the canonical impact levels in descending severity order
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetJs(): string</code></summary>

<br/>
Return the JavaScript source to inject into the page
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetPrintCli(): bool</code></summary>

<br/>
Return TRUE to print a one-line per-page summary to the console
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityGetReportDir(): string</code></summary>

<br/>
Return the absolute directory used to write per-scenario reports
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityNormalizeResults(array $raw): array</code></summary>

<br/>
Normalize raw engine output into the canonical shape used by the trait
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityRenderAggregate(array $data): string</code></summary>

<br/>
Render the entire aggregate report from prepared data
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityRenderHtml(): string</code></summary>

<br/>
Render the scenario-level HTML report from collected results
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityRenderHtmlPage(string $sections): string</code></summary>

<br/>
Wrap the per-URL sections in a standalone HTML page
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityRenderHtmlSections(): string</code></summary>

<br/>
Render the per-URL section markup (one `<section>` per visited URL)
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityRenderIssueList(string $heading, string $css_class, array $issues): string</code></summary>

<br/>
Render a single issue list (violations or incomplete) as HTML
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityRenderJunit(): string</code></summary>

<br/>
Render the scenario-level JUnit XML report from collected results
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityResolveTags(array $tags): void</code></summary>

<br/>
Resolve scenario / feature tags into mode and threshold state
<br/><br/>

</details>

<details>
  <summary><code>protected function accessibilityRunEngine(string $rules): array</code></summary>

<br/>
Execute the engine against the current page and return raw results
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityStringifyTarget(array $target): string</code></summary>

<br/>
Flatten a node target array into a human-readable string
<br/><br/>

</details>

<details>
  <summary><code>protected static function accessibilityWriteAggregateReport(): void</code></summary>

<br/>
Write the aggregate report when at least one scenario produced results
<br/><br/>

</details>

## BasicAuthTrait

[Source](src/Steps/Generic/BasicAuthTrait.php), [Steps](STEPS.md#basicauthtrait)

> Keep HTTP basic authentication applied across session resets.

<details>
  <summary><code>protected function basicAuthApply(): void</code></summary>

<br/>
Apply the resolved credentials to the session
<br/><br/>

</details>

## CommandTrait

[Source](src/Steps/Generic/CommandTrait.php), [Steps](STEPS.md#commandtrait)

> Run local shell commands and assert on their result.

<details>
  <summary><code>protected function commandAssertHasRun(): void</code></summary>

<br/>
Assert that a command has been run in the current scenario
<br/><br/>

</details>

<details>
  <summary><code>protected function commandAssertInteger(string $value, string $label): int</code></summary>

<br/>
Assert that a step argument is an integer and return it
<br/><br/>

</details>

<details>
  <summary><code>protected function commandAssertNumeric(string $value, string $label): float</code></summary>

<br/>
Assert that a step argument is numeric and return it as a float
<br/><br/>

</details>

<details>
  <summary><code>protected function commandGetTimeout(): int</code></summary>

<br/>
The maximum time, in seconds, a command may run before it is terminated
<br/><br/>

</details>

<details>
  <summary><code>protected function commandResetState(): void</code></summary>

<br/>
Reset the captured command state
<br/><br/>

</details>

## CookieTrait

[Source](src/Steps/Generic/CookieTrait.php), [Steps](STEPS.md#cookietrait)

> Verify and inspect browser cookies.

<details>
  <summary><code>protected function cookieExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void</code></summary>

<br/>
Assert that a cookie exists
<br/><br/>

</details>

<details>
  <summary><code>protected function cookieGetAll(): array</code></summary>

<br/>
Get all cookies
<br/><br/>

</details>

<details>
  <summary><code>protected function cookieGetByName(string $name, bool $is_partial = FALSE): ?array</code></summary>

<br/>
Get a cookie by exact or partial name
<br/><br/>

</details>

<details>
  <summary><code>protected function cookieNotExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void</code></summary>

<br/>
Assert that a cookie does not exist
<br/><br/>

</details>

## DateTrait

[Source](src/Steps/Generic/DateTrait.php), [Steps](STEPS.md#datetrait)

> Convert relative date expressions into timestamps or formatted dates.

<details>
  <summary><code>protected static function dateNow(): int</code></summary>

<br/>
Get the current timestamp
<br/><br/>

</details>

<details>
  <summary><code>public static function dateRelativeProcessValue(string $value, ?int $now = NULL): string</code></summary>

<br/>
Process date values to convert relative timestamps to actual values
<br/><br/>

```
Given the following "article" content:
  | title        | created           |
  | test article | [relative:-1 day] |
```

</details>

<details>
  <summary><code>protected static function dateRelativeStringHasToken(string $string): bool</code></summary>

<br/>
Assert that string has a token
<br/><br/>

</details>

## DiagnosticsTrait

[Source](src/Steps/Generic/DiagnosticsTrait.php), [Steps](STEPS.md#diagnosticstrait)

> Append on-failure diagnostics to the failure message of any failed step.

<details>
  <summary><code>protected function diagnosticsAppendToException(Exception $exception): void</code></summary>

<br/>
Append the diagnostics block to an exception's message in place
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsBuildBlock(): string</code></summary>

<br/>
Build the diagnostics block from the enabled fields
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetDriverName(): ?string</code></summary>

<br/>
Return the active Mink driver class, or NULL when it is unavailable
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetHeader(): string</code></summary>

<br/>
Return the header line that precedes the diagnostics block
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetJsErrors(): array</code></summary>

<br/>
Return collected JavaScript console error messages
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetRerunBinary(): string</code></summary>

<br/>
Return the binary used in the re-run command. Override to customise
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetRerunCommand(): ?string</code></summary>

<br/>
Return the command that re-runs just the failing scenario
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetShowDriver(): bool</code></summary>

<br/>
Return TRUE to include the Mink driver class. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetShowJsErrors(): bool</code></summary>

<br/>
Return TRUE to include JavaScript console errors. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetShowRerun(): bool</code></summary>

<br/>
Return TRUE to include the re-run command. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetShowStatusCode(): bool</code></summary>

<br/>
Return TRUE to include the HTTP status code. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetShowUrl(): bool</code></summary>

<br/>
Return TRUE to include the current URL. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetStatusCode(): ?int</code></summary>

<br/>
Return the last response status code, or NULL when it is unavailable
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsGetUrl(): ?string</code></summary>

<br/>
Return the current page URL, or NULL when it cannot be determined
<br/><br/>

</details>

<details>
  <summary><code>protected function diagnosticsRelativePath(string $path): string</code></summary>

<br/>
Shorten a path to be relative to the working directory when possible
<br/><br/>

</details>

## DropzoneTrait

[Source](src/Steps/Generic/DropzoneTrait.php), [Steps](STEPS.md#dropzonetrait)

> Simulate a real multi-file drag-and-drop gesture onto a Dropzone target.

<details>
  <summary><code>protected function dropzoneResolvePath(string $path): string</code></summary>

<br/>
Resolve a fixture path against the Mink `files_path` parameter
<br/><br/>

</details>

## ElementTrait

[Source](src/Steps/Generic/ElementTrait.php), [Steps](STEPS.md#elementtrait)

> Interact with HTML elements using CSS selectors and DOM attributes.

<details>
  <summary><code>protected function elementAssertAttributeWithValue(string $selector, string $attribute, mixed $value, bool $is_exact, bool $is_inverted): void</code></summary>

<br/>
Assert an element with selector and attribute with a value
<br/><br/>

</details>

<details>
  <summary><code>protected function elementAssertCssProperty(string $selector, string $property, string $value, bool $is_exact, bool $is_inverted): void</code></summary>

<br/>
Assert the computed value of a CSS property on an element
<br/><br/>

</details>

<details>
  <summary><code>protected function elementAssertKeyboardFocus(string $selector, bool $is_inverted): void</code></summary>

<br/>
Assert keyboard focus state for an element
<br/><br/>

</details>

<details>
  <summary><code>protected function elementAssertPinnedToTopWithin(string $selector, int $tolerance, bool $is_inverted): void</code></summary>

<br/>
Assert that an element is pinned to the top of the viewport
<br/><br/>

</details>

<details>
  <summary><code>protected function elementAssertStackingOrder(string $selector1, string $selector2, bool $is_above): void</code></summary>

<br/>
Assert the stacking order of two elements
<br/><br/>

</details>

<details>
  <summary><code>protected function elementAssertVisibleFocusOutline(string $selector, bool $is_inverted): void</code></summary>

<br/>
Assert visible focus indicator state for an element
<br/><br/>

</details>

<details>
  <summary><code>protected function elementExecuteJs(string $selector, string $script)</code></summary>

<br/>
Execute JS on an element provided by the selector
<br/><br/>

</details>

<details>
  <summary><code>protected function elementFindHeading(string $heading): ?NodeElement</code></summary>

<br/>
Find a heading whose text matches exactly
<br/><br/>

</details>

<details>
  <summary><code>protected function elementFindNthOrFail(array $elements, int $index, string $subject): NodeElement</code></summary>

<br/>
Return the element at a 1-based index or throw a clear error
<br/><br/>

</details>

<details>
  <summary><code>protected function elementGetScrollIntoViewCenter(): bool</code></summary>

<br/>
Whether to scroll elements to the center of the viewport
<br/><br/>

```
class FeatureContext extends DrupalContext {
  use ElementTrait;
  protected function elementGetScrollIntoViewCenter(): bool {
    return FALSE;
  }
}
```

</details>

<details>
  <summary><code>protected function elementIsVisuallyVisible(string $selector, int $offset)</code></summary>

<br/>
Check whether an element is displayed within the viewport
<br/><br/>

</details>

<details>
  <summary><code>protected function elementNormalizeCssProperty(string $property): string</code></summary>

<br/>
Convert a CSS property name to the form getPropertyValue() expects
<br/><br/>

</details>

<details>
  <summary><code>protected function elementResolveStackingOrder(string $selector1, string $selector2): string</code></summary>

<br/>
Resolve the stacking order of two elements in the browser
<br/><br/>

</details>

## FieldTrait

[Source](src/Steps/Generic/FieldTrait.php), [Steps](STEPS.md#fieldtrait)

> Manipulate form fields and verify widget functionality.

<details>
  <summary><code>protected function fieldCurrentPath(): string</code></summary>

<br/>
The path of the current page, as used in failure messages
<br/><br/>

</details>

<details>
  <summary><code>protected function fieldDisableFormValidation(?string $selector = NULL): void</code></summary>

<br/>
Disable browser validation for forms
<br/><br/>

</details>

<details>
  <summary><code>protected function fieldFillDatetimeHelper(string $label, string $part, string $field, string $value): void</code></summary>

<br/>
Helper method to fill datetime field parts
<br/><br/>

</details>

<details>
  <summary><code>protected function fieldGetAddMoreButtonSelectors(): array</code></summary>

<br/>
CSS selectors for the "Add another item" button
<br/><br/>

</details>

<details>
  <summary><code>protected function fieldGetRequiredMarkerSelectors(): array</code></summary>

<br/>
CSS selectors that indicate a required-field marker
<br/><br/>

</details>

<details>
  <summary><code>protected function fieldIsMarkedRequired(NodeElement $field_element): bool</code></summary>

<br/>
Check if a given field element is marked as required
<br/><br/>

</details>

<details>
  <summary><code>protected function fieldXpathLiteral(string $value): string</code></summary>

<br/>
Wrap a string in an XPath-safe literal
<br/><br/>

</details>

## FileDownloadTrait

[Source](src/Steps/Generic/FileDownloadTrait.php), [Steps](STEPS.md#filedownloadtrait)

> Test file download functionality with content verification.

<details>
  <summary><code>protected function fileDownloadAssertLinkPresent(string $link): NodeElement</code></summary>

<br/>
Assert that an HTML link is present on the page
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadGetTempDir(): string</code></summary>

<br/>
Get temp download dir
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadIsRegex(string $string): bool</code></summary>

<br/>
Check if a string is a regular expression pattern
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadOpenZip(): ZipArchive</code></summary>

<br/>
Open downloaded ZIP archive and validate contents
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadParseHeaders(array $headers): array</code></summary>

<br/>
Extract downloaded file information from the response headers
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadPrepareTempDir(): void</code></summary>

<br/>
Prepare temporary directory for file downloads
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadProcess(string $url, array $options = []): array</code></summary>

<br/>
Download file
<br/><br/>

</details>

<details>
  <summary><code>protected function fileDownloadRemoveTempDir(): void</code></summary>

<br/>
Remove temporary directory for file downloads
<br/><br/>

</details>

## JavascriptTrait

[Source](src/Steps/Generic/JavascriptTrait.php), [Steps](STEPS.md#javascripttrait)

> Automatically detect JavaScript errors during test execution.

<details>
  <summary><code>protected function javascriptAssertNotHasErrors(): void</code></summary>

<br/>
Assert that no JavaScript errors were collected
<br/><br/>

</details>

<details>
  <summary><code>protected function javascriptClearRegistry(): void</code></summary>

<br/>
Clear the JavaScript error registry
<br/><br/>

</details>

<details>
  <summary><code>protected function javascriptCollectFromPage(string $url): void</code></summary>

<br/>
Collect JavaScript errors from the page
<br/><br/>

</details>

<details>
  <summary><code>protected function javascriptInjectCollector(): void</code></summary>

<br/>
Inject JavaScript error collector into the page
<br/><br/>

</details>

## JsonTrait

[Source](src/Steps/Generic/JsonTrait.php), [Steps](STEPS.md#jsontrait)

> Assert JSON responses with path and schema checks.

<details>
  <summary><code>protected function jsonDecode(string $content): array</code></summary>

<br/>
Decode a JSON string into an array
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonDecodeLoose(string $content): mixed</code></summary>

<br/>
Decode JSON content as loosely-typed data
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonEnsureData(): void</code></summary>

<br/>
Ensure the JSON response is decoded and cached
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonQuery(string $path): array</code></summary>

<br/>
Run a JSONPath expression against the decoded response
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonReadFile(string $filename): string</code></summary>

<br/>
Read a fixture file's contents
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonResetState(): void</code></summary>

<br/>
Reset all cached JSON state
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonResolveContent(): string</code></summary>

<br/>
Resolve the response content to assert against
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonResolveScalar(string $path): mixed</code></summary>

<br/>
Resolve a JSONPath expression to a single scalar value
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonResolveSingle(string $path): mixed</code></summary>

<br/>
Resolve a JSONPath expression to a single matched value
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonScalarToString(mixed $value): string</code></summary>

<br/>
Convert a scalar JSON value to its string representation
<br/><br/>

</details>

<details>
  <summary><code>protected function jsonValidateSchema(string $schema_json): void</code></summary>

<br/>
Validate the response body against a JSON schema
<br/><br/>

</details>

## KeyboardTrait

[Source](src/Steps/Generic/KeyboardTrait.php), [Steps](STEPS.md#keyboardtrait)

> Simulate keyboard interactions in Drupal browser testing.

<details>
  <summary><code>protected function keyboardPressKeyOnElementSingle(string $char, ?string $selector): void</code></summary>

<br/>
Press keyboard key, optionally on element
<br/><br/>

</details>

<details>
  <summary><code>protected function keyboardTriggerKey(string $xpath, string $key): void</code></summary>

<br/>
Trigger key on the element
<br/><br/>

</details>

## MappingTrait

[Source](src/Steps/Generic/MappingTrait.php), [Steps](STEPS.md#mappingtrait)

> Replace `{{ Key }}` tokens in step arguments and table cells.

<details>
  <summary><code>protected function mappingSubstitute(string $value): string</code></summary>

<br/>
Substitutes every mapping token found in a single string
<br/><br/>

</details>

## MessageTrait

[Source](src/Steps/Generic/MessageTrait.php), [Steps](STEPS.md#messagetrait)

> Assert status, error, warning and success messages rendered on the page.

<details>
  <summary><code>protected function messageAssert(string $message, string $type): void</code></summary>

<br/>
Assert that a message of the given type contains the expected text
<br/><br/>

</details>

<details>
  <summary><code>protected function messageAssertNot(string $message, string $type): void</code></summary>

<br/>
Assert that no message of the given type contains the expected text
<br/><br/>

</details>

<details>
  <summary><code>protected function messageSelector(string $type): string</code></summary>

<br/>
Resolve the configured CSS selector for a message type
<br/><br/>

</details>

## MetatagTrait

[Source](src/Steps/Generic/MetatagTrait.php), [Steps](STEPS.md#metatagtrait)

> Assert `<meta>` tags and head/SEO markup in page markup.

<details>
  <summary><code>protected function metatagAssertMetaSetPresent(array $names, string $label): void</code></summary>

<br/>
Assert that a set of meta tags is present and non-empty
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagFetchUrl(string $url): string</code></summary>

<br/>
Fetch a URL out of band without disturbing the Mink session
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagFindMeta(string $name): ?NodeElement</code></summary>

<br/>
Find a meta tag by its "name" or "property" attribute
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagGetCanonicalHref(): ?string</code></summary>

<br/>
Get the canonical URL href
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagGetHreflangAlternates(): array</code></summary>

<br/>
Get the hreflang alternates present on the current page
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagGetMetaContent(string $name): ?string</code></summary>

<br/>
Get the content of a meta tag by its "name" or "property" attribute
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagGetRobotsDirectives(): array</code></summary>

<br/>
Get the robots meta tag directives as lower-cased tokens
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagHtmlLinksBackTo(string $html, string $url, string $base_url): bool</code></summary>

<br/>
Determine whether fetched HTML links back to a URL via hreflang
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagIsIndexable(): bool</code></summary>

<br/>
Determine whether the current page is indexable
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagIsValidHreflang(string $value): bool</code></summary>

<br/>
Determine whether a hreflang value is a well-formed language code
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagOpenGraphRequired(): array</code></summary>

<br/>
The Open Graph meta tags required by "the Open Graph tags should be valid"
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagResolveUrl(string $url, ?string $base = NULL): string</code></summary>

<br/>
Resolve an absolute or root-relative URL against a base URL's origin
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagResponseHasNoindexHeader(): bool</code></summary>

<br/>
Determine whether the X-Robots-Tag response header carries "noindex"
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagTablePropertyNames(TableNode $table): array</code></summary>

<br/>
Extract meta tag names from the first column of a table
<br/><br/>

</details>

<details>
  <summary><code>protected function metatagTwitterCardRequired(): array</code></summary>

<br/>
The Twitter Card tags required by "the Twitter Card tags should be valid"
<br/><br/>

</details>

## ModalTrait

[Source](src/Steps/Generic/ModalTrait.php), [Steps](STEPS.md#modaltrait)

> Interact with and assert modals.

<details>
  <summary><code>protected function modalFind(): ?NodeElement</code></summary>

<br/>
Find the first visible modal, or fall back to the first DOM match
<br/><br/>

</details>

<details>
  <summary><code>protected function modalFindElementIn(NodeElement $parent, array $selectors): ?NodeElement</code></summary>

<br/>
Find the first matching element within a parent from a list of selectors
<br/><br/>

</details>

<details>
  <summary><code>protected function modalFindVisible(): NodeElement</code></summary>

<br/>
Find the first visible modal or throw an exception
<br/><br/>

</details>

<details>
  <summary><code>protected function modalGetCloseSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal close button
<br/><br/>

</details>

<details>
  <summary><code>protected function modalGetContentSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal content
<br/><br/>

</details>

<details>
  <summary><code>protected function modalGetSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal container
<br/><br/>

</details>

<details>
  <summary><code>protected function modalGetWaitTimeout(): int</code></summary>

<br/>
Get the timeout in seconds for waiting for the modal to appear
<br/><br/>

</details>

## PathTrait

[Source](src/Steps/Generic/PathTrait.php), [Steps](STEPS.md#pathtrait)

> Navigate and verify paths with URL validation.

<details>
  <summary><code>protected function pathGetCurrentUrlQuery(): array</code></summary>

<br/>
Get the query parameters of the current URL
<br/><br/>

</details>

## RandomTrait

[Source](src/Steps/Generic/RandomTrait.php), [Steps](STEPS.md#randomtrait)

> Replace random-value tokens in step arguments and table cells.

<details>
  <summary><code>protected function randomGenerate(string $type, array $args): string|int</code></summary>

<br/>
Dispatches to the type-specific generator
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGenerateEmail(): string</code></summary>

<br/>
Generates a syntactically valid email at the reserved '.test' TLD
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGenerateInt(int $min, int $max): int</code></summary>

<br/>
Generates an integer in '[min, max]' inclusive
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGenerateMachineName(int $length): string</code></summary>

<br/>
Generates a Drupal-shaped machine name (lowercase + underscores)
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGenerateName(int $length): string</code></summary>

<br/>
Generates a 'Random::name()' string with original case preserved
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGenerateString(int $length): string</code></summary>

<br/>
Generates a lowercase string - the default for unknown shape requests
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGenerateUuid(): string</code></summary>

<br/>
Generates a UUID v4 string
<br/><br/>

</details>

<details>
  <summary><code>protected function randomGetGenerator(): Random</code></summary>

<br/>
Lazily resolves the string generator
<br/><br/>

</details>

<details>
  <summary><code>protected function randomNormalizeArglessArgs(string $type, array $args): array</code></summary>

<br/>
Validates argless types ('email', 'uuid'): refuses any positional args
<br/><br/>

</details>

<details>
  <summary><code>protected function randomNormalizeArgs(string $type, array $args): array</code></summary>

<br/>
Validates and fills defaults so equivalent tokens share a cache key
<br/><br/>

</details>

<details>
  <summary><code>protected function randomNormalizeIntArgs(array $args): array</code></summary>

<br/>
Validates 'int' args (zero args for full range, or two integer bounds)
<br/><br/>

</details>

<details>
  <summary><code>protected function randomNormalizeLengthArgs(string $type, array $args): array</code></summary>

<br/>
Validates length-style args (one optional non-negative integer)
<br/><br/>

</details>

<details>
  <summary><code>protected function randomParseToken(string $literal): array</code></summary>

<br/>
Parses a token literal into '[name, type, args]'
<br/><br/>

</details>

<details>
  <summary><code>protected function randomResolveLiteral(string $literal): string|int</code></summary>

<br/>
Resolves a token literal to its generated value
<br/><br/>

</details>

<details>
  <summary><code>protected function randomSubstitute(string $message): string</code></summary>

<br/>
Substitutes every token match in '$message' via 'randomResolveLiteral()'
<br/><br/>

</details>

<details>
  <summary><code>protected function randomSubstituteTable(TableNode $table): TableNode</code></summary>

<br/>
Applies 'randomSubstitute()' across every cell in '$table'
<br/><br/>

</details>

## RegionTrait

[Source](src/Steps/Generic/RegionTrait.php), [Steps](STEPS.md#regiontrait)

> Interact with and assert against named page regions.

<details>
  <summary><code>protected function regionFindElementByText(string $region, string $selector, string $text): NodeElement</code></summary>

<br/>
Find an element in a region whose text matches exactly
<br/><br/>

</details>

<details>
  <summary><code>protected function regionGet(string $region): NodeElement</code></summary>

<br/>
Return a named region on the current page
<br/><br/>

</details>

## ResponsiveTrait

[Source](src/Steps/Generic/ResponsiveTrait.php), [Steps](STEPS.md#responsivetrait)

> Test responsive layouts with viewport control.

<details>
  <summary><code>protected function responsiveExtractDimensions(string $dimensions, ?string $name = NULL): array</code></summary>

<br/>
Extract and validate dimensions from breakpoint string
<br/><br/>

</details>

<details>
  <summary><code>protected function responsiveGetAllBreakpoints(): array</code></summary>

<br/>
Get all available breakpoints
<br/><br/>

</details>

<details>
  <summary><code>protected function responsiveGetBreakpoint(string $name): string</code></summary>

<br/>
Get breakpoint dimensions by name
<br/><br/>

</details>

<details>
  <summary><code>protected function responsiveGetCurrentDimensions(): array</code></summary>

<br/>
Get current viewport dimensions
<br/><br/>

</details>

<details>
  <summary><code>protected function responsiveResize(int $width, int $height): void</code></summary>

<br/>
Resize the browser window
<br/><br/>

</details>

<details>
  <summary><code>protected function responsiveResizeToBreakpoint(string $breakpoint): void</code></summary>

<br/>
Resize viewport to a named breakpoint
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveSetBreakpoints(array $breakpoints): void</code></summary>

<br/>
Set custom breakpoints
<br/><br/>

</details>

## RestTrait

[Source](src/Steps/Generic/RestTrait.php), [Steps](STEPS.md#resttrait)

> Lightweight REST API testing with no Drupal dependencies.

<details>
  <summary><code>protected function restCreateServerArray(): array</code></summary>

<br/>
Convert stored headers to the server array format for BrowserKit
<br/><br/>

</details>

<details>
  <summary><code>protected function restGetClient(): mixed</code></summary>

<br/>
Get the BrowserKit client from the current Mink driver
<br/><br/>

</details>

<details>
  <summary><code>protected function restResolveUrl(string $url): string</code></summary>

<br/>
Resolve a relative URL against the Mink base URL
<br/><br/>

</details>

## TableTrait

[Source](src/Steps/Generic/TableTrait.php), [Steps](STEPS.md#tabletrait)

> Interact with HTML table elements and assert their content.

<details>
  <summary><code>protected function tableFind(string $selector): NodeElement</code></summary>

<br/>
Find a table element by CSS selector
<br/><br/>

</details>

<details>
  <summary><code>protected function tableFindRowByText(string $text): ?NodeElement</code></summary>

<br/>
Find a table row containing the given text
<br/><br/>

</details>

<details>
  <summary><code>protected function tableGetBodyRowSelector(): string</code></summary>

<br/>
Get the CSS selector for table body rows
<br/><br/>

</details>

<details>
  <summary><code>protected function tableGetColumnIndex(NodeElement $table, string $column, string $selector): int</code></summary>

<br/>
Get the index of a column by its header text
<br/><br/>

</details>

<details>
  <summary><code>protected function tableGetHeaderSelector(): string</code></summary>

<br/>
Get the CSS selector for table header cells
<br/><br/>

</details>

<details>
  <summary><code>protected function tableGetHeaders(NodeElement $table): array</code></summary>

<br/>
Get the header texts from a table element
<br/><br/>

</details>

<details>
  <summary><code>protected function tableGetRowByText(string $row_text): NodeElement</code></summary>

<br/>
Return the first row on the page containing the text
<br/><br/>

</details>

<details>
  <summary><code>protected function tableGetRows(NodeElement $table): array</code></summary>

<br/>
Get the body rows from a table element
<br/><br/>

</details>

## WaitTrait

[Source](src/Steps/Generic/WaitTrait.php), [Steps](STEPS.md#waittrait)

> Wait for a period of time or for AJAX to finish.

<details>
  <summary><code>protected function waitAroundStep(StepScope $scope): void</code></summary>

<br/>
Wait for AJAX around a step when the step changes the page
<br/><br/>

</details>

<details>
  <summary><code>protected function waitGetAjaxTimeout(): int</code></summary>

<br/>
Return the configured AJAX timeout, in seconds
<br/><br/>

</details>

## XmlTrait

[Source](src/Steps/Generic/XmlTrait.php), [Steps](STEPS.md#xmltrait)

> Assert XML responses with element and attribute checks.

<details>
  <summary><code>protected function xmlDirectChildElements(DOMNode $parent, string $name, ?string $namespace = NULL): array</code></summary>

<br/>
Get direct child elements matching a local name and optional namespace
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlEnsureDocument(): void</code></summary>

<br/>
Ensure that an XML document is loaded
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlExtractNamespaces(): array</code></summary>

<br/>
Extract namespaces from the XML document
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlFormatErrors(array $errors): string</code></summary>

<br/>
Format libxml errors into a readable string
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlLoadDocument(string $content): void</code></summary>

<br/>
Load XML content into the document and XPath
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlParse(string $content): array</code></summary>

<br/>
Parse XML content without disturbing the cached document
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlReadFile(string $filename): string</code></summary>

<br/>
Read a fixture file's contents
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlResolveContent(): string</code></summary>

<br/>
Resolve the response content to assert against
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlValidateAtomFeed(): void</code></summary>

<br/>
Validate the response as an Atom feed
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlValidateDtd(string $dtd): void</code></summary>

<br/>
Validate the response against a DTD
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlValidateRelaxNg(string $schema): void</code></summary>

<br/>
Validate the response against a RelaxNG schema
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlValidateRssFeed(): void</code></summary>

<br/>
Validate the response as an RSS 2.0 feed
<br/><br/>

</details>

<details>
  <summary><code>protected function xmlValidateXsd(string $schema): void</code></summary>

<br/>
Validate the response against an XSD schema
<br/><br/>

</details>

## Drupal\BigPipeTrait

[Source](src/Steps/Drupal/BigPipeTrait.php), [Steps](STEPS.md#drupalbigpipetrait)

> Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios.

<details>
  <summary><code>protected function bigPipeApplyServerRenderCookie(): void</code></summary>

<br/>
Set the no-JS cookie when the scenario asked for server-side rendering
<br/><br/>

</details>

<details>
  <summary><code>protected function bigPipeGetWaitTimeout(): int</code></summary>

<br/>
Maximum time to wait for BigPipe placeholders, in milliseconds
<br/><br/>

</details>

<details>
  <summary><code>protected function bigPipeJavascriptIsSupported(): ?bool</code></summary>

<br/>
Whether the active driver can run JavaScript
<br/><br/>

</details>

<details>
  <summary><code>protected function bigPipeWaitForPlaceholders(int $timeout_ms): void</code></summary>

<br/>
Wait until no BigPipe placeholder markers remain in the DOM
<br/><br/>

</details>

## Drupal\BlockTrait

[Source](src/Steps/Drupal/BlockTrait.php), [Steps](STEPS.md#drupalblocktrait)

> Manage Drupal blocks.

<details>
  <summary><code>protected function blockLoadByLabel(string $label): ?Block</code></summary>

<br/>
Load a block by its label
<br/><br/>

</details>

## Drupal\CacheTrait

[Source](src/Steps/Drupal/CacheTrait.php), [Steps](STEPS.md#drupalcachetrait)

> Invalidate Drupal caches and run cron from within a scenario.

<details>
  <summary><code>protected function cacheGetPageCacheBin(): string</code></summary>

<br/>
Get the cache bin used for the page cache
<br/><br/>

</details>

## Drupal\ConfigOverrideTrait

[Source](src/Steps/Drupal/ConfigOverrideTrait.php), [Steps](STEPS.md#drupalconfigoverridetrait)

> Disable Drupal config overrides from settings.php during a scenario.

<details>
  <summary><code>protected function configOverrideClearDriverHeader(): void</code></summary>

<br/>
Clear the driver-level X-Config-No-Override request header
<br/><br/>

</details>

<details>
  <summary><code>protected function configOverrideClearSignal(): void</code></summary>

<br/>
Clear the process-level and REST-level X-Config-No-Override signal
<br/><br/>

</details>

## Drupal\ConfigTrait

[Source](src/Steps/Drupal/ConfigTrait.php), [Steps](STEPS.md#drupalconfigtrait)

> Assert and set stored Drupal configuration values with automatic revert.

<details>
  <summary><code>protected function configArrayContainsValue(array $data, string $expected): bool</code></summary>

<br/>
Recursively determine whether an array holds an expected scalar value
<br/><br/>

</details>

<details>
  <summary><code>protected function configCastValue(string $value): mixed</code></summary>

<br/>
Cast a string value from a step into the shape stored in configuration
<br/><br/>

</details>

<details>
  <summary><code>protected function configCompareContains(mixed $actual, string $expected, bool $should_contain, string $name, string $key, string $descriptor): void</code></summary>

<br/>
Assert containment between an actual configuration value and an expected one
<br/><br/>

</details>

<details>
  <summary><code>protected function configCompareEquals(mixed $actual, string $expected, bool $should_match, string $name, string $key, string $descriptor): void</code></summary>

<br/>
Assert equality between an actual configuration value and an expected one
<br/><br/>

</details>

<details>
  <summary><code>protected function configReadEffective(string $name, string $key): mixed</code></summary>

<br/>
Read an effective configuration value, with overrides applied
<br/><br/>

</details>

<details>
  <summary><code>protected function configReadStored(string $name, string $key): mixed</code></summary>

<br/>
Read a stored configuration value, ignoring runtime overrides
<br/><br/>

</details>

<details>
  <summary><code>protected function configSnapshot(string $name): void</code></summary>

<br/>
Snapshot a configuration object's original data on first write
<br/><br/>

</details>

<details>
  <summary><code>protected function configStringifyValue(mixed $value): string</code></summary>

<br/>
Stringify a configuration value for comparison and error messages
<br/><br/>

</details>

<details>
  <summary><code>protected function configValueContains(mixed $actual, string $expected): bool</code></summary>

<br/>
Determine whether a configuration value contains an expected value
<br/><br/>

</details>

## Drupal\ContentBlockTrait

[Source](src/Steps/Drupal/ContentBlockTrait.php), [Steps](STEPS.md#drupalcontentblocktrait)

> Manage Drupal content blocks.

<details>
  <summary><code>protected function contentBlockCreateSingle(string $type, array $values): BlockContent</code></summary>

<br/>
Create a block content entity with the specified type and field values
<br/><br/>

</details>

<details>
  <summary><code>protected function contentBlockLoadMultiple(string $type, array $conditions = []): array</code></summary>

<br/>
Load multiple content blocks with specified type and conditions
<br/><br/>

</details>

## Drupal\ContentTrait

[Source](src/Steps/Drupal/ContentTrait.php), [Steps](STEPS.md#drupalcontenttrait)

> Manage Drupal content with workflow and moderation support.

<details>
  <summary><code>protected function contentAssertPathModuleEnabled(): void</code></summary>

<br/>
Throw when the `path` module is not enabled
<br/><br/>

</details>

<details>
  <summary><code>protected function contentLoadNodeByTitle(string $content_type, string $title): NodeInterface</code></summary>

<br/>
Load the node with the specified type and title
<br/><br/>

</details>

<details>
  <summary><code>protected function contentResolveNidByTitle(string $content_type, string $title): int</code></summary>

<br/>
Resolve the ID of the node with the specified type and title
<br/><br/>

</details>

<details>
  <summary><code>protected function contentVisitActionPageWithTitle(string $content_type, string $title, string $action_subpath = ''): void</code></summary>

<br/>
Visit the action page of the content with a specified title
<br/><br/>

</details>

## Drupal\DraggableviewsTrait

[Source](src/Steps/Drupal/DraggableviewsTrait.php), [Steps](STEPS.md#drupaldraggableviewstrait)

> Order items in the Drupal Draggable Views.

<details>
  <summary><code>protected function draggableviewsFindNode(string $type, array $conditions): ?NodeInterface</code></summary>

<br/>
Find a node using provided conditions
<br/><br/>

</details>

## Drupal\DrushTrait

[Source](src/Steps/Drupal/DrushTrait.php), [Steps](STEPS.md#drupaldrushtrait)

> Run Drush commands and assert their output.

<details>
  <summary><code>protected function drushDriver(): DrushDriver</code></summary>

<br/>
Return the Drush driver
<br/><br/>

</details>

<details>
  <summary><code>protected function drushFixArgument(string $argument): string</code></summary>

<br/>
Restore quotes escaped by the Gherkin parser
<br/><br/>

</details>

<details>
  <summary><code>protected function drushReadOutput(): string</code></summary>

<br/>
Return the output of the most recent Drush command
<br/><br/>

</details>

<details>
  <summary><code>protected function drushRunExpectingFailure(string $command, ?string $arguments = NULL): void</code></summary>

<br/>
Run a Drush command expecting a non-zero exit, keeping its output
<br/><br/>

</details>

## Drupal\EckTrait

[Source](src/Steps/Drupal/EckTrait.php), [Steps](STEPS.md#drupalecktrait)

> Manage Drupal ECK entities with custom type and bundle creation.

<details>
  <summary><code>protected function eckCreateEntities(string $entity_type, string $bundle, TableNode $table): void</code></summary>

<br/>
Create custom content entities
<br/><br/>

</details>

<details>
  <summary><code>protected function eckCreateEntity(EntityStub $stub): void</code></summary>

<br/>
Create a single content entity
<br/><br/>

</details>

<details>
  <summary><code>protected function eckLoadMultiple(string $entity_type, string $bundle, array $conditions = []): array</code></summary>

<br/>
Load multiple entities with specified type and conditions
<br/><br/>

</details>

## Drupal\EmailTrait

[Source](src/Steps/Drupal/EmailTrait.php), [Steps](STEPS.md#drupalemailtrait)

> Test Drupal email functionality with content verification.

<details>
  <summary><code>protected function emailAssertLinkNumber(string $link_number): int</code></summary>

<br/>
Convert a link number step argument into a positive integer
<br/><br/>

</details>

<details>
  <summary><code>protected static function emailDeleteMailSystemOriginal(): void</code></summary>

<br/>
Remove the original mail system value
<br/><br/>

</details>

<details>
  <summary><code>protected static function emailExtractLinks(string $string): array</code></summary>

<br/>
Extract all links from provided string
<br/><br/>

</details>

<details>
  <summary><code>protected function emailFindMessage(string $field, PyStringNode $string, bool $exact = FALSE): ?array</code></summary>

<br/>
Find an email message whose field contains a value
<br/><br/>

</details>

<details>
  <summary><code>protected function emailGetCollectedMessages(): array</code></summary>

<br/>
Get email messages collected during the test
<br/><br/>

</details>

<details>
  <summary><code>protected static function emailGetMailSystemDefault(string $type = 'default'): mixed</code></summary>

<br/>
Get the default mail system value
<br/><br/>

</details>

<details>
  <summary><code>protected static function emailGetMailSystemOriginal(string $type = 'default'): mixed</code></summary>

<br/>
Get the original mail system value
<br/><br/>

</details>

<details>
  <summary><code>protected static function emailSetMailSystemDefault(string $type, mixed $value): void</code></summary>

<br/>
Set the default mail system value
<br/><br/>

</details>

<details>
  <summary><code>protected static function emailSetMailSystemOriginal(string $type, mixed $value): void</code></summary>

<br/>
Set the original mail system value
<br/><br/>

</details>

## Drupal\FileTrait

[Source](src/Steps/Drupal/FileTrait.php), [Steps](STEPS.md#drupalfiletrait)

> Manage Drupal file entities with upload and storage operations.

<details>
  <summary><code>protected function fileCreateEntity(string $path, EntityStub $stub, ?string $uri = NULL): FileInterface</code></summary>

<br/>
Create file entity
<br/><br/>

</details>

<details>
  <summary><code>protected function fileCreateManagedSingle(string $path, EntityStub $stub, ?string $uri = NULL): FileInterface</code></summary>

<br/>
Create a single managed file
<br/><br/>

</details>

<details>
  <summary><code>protected function fileLoadMultiple(array $conditions = []): array</code></summary>

<br/>
Load multiple files with specified conditions
<br/><br/>

</details>

## Drupal\MediaTrait

[Source](src/Steps/Drupal/MediaTrait.php), [Steps](STEPS.md#drupalmediatrait)

> Manage Drupal media entities with type-specific field handling.

<details>
  <summary><code>protected function mediaCreateEntity(EntityStub $stub): MediaInterface</code></summary>

<br/>
Create media entity
<br/><br/>

</details>

<details>
  <summary><code>protected function mediaCreateSingle(EntityStub $stub): MediaInterface</code></summary>

<br/>
Create a single media item
<br/><br/>

</details>

<details>
  <summary><code>protected function mediaExpandEntityFields(EntityStub $stub): void</code></summary>

<br/>
Expand parsed fields into expected field values based on field type
<br/><br/>

</details>

<details>
  <summary><code>protected function mediaExpandEntityFieldsFixtures(EntityStub $stub): void</code></summary>

<br/>
Expand entity fields with fixture values
<br/><br/>

</details>

<details>
  <summary><code>protected function mediaLoadMultiple(string $type, array $conditions = []): array</code></summary>

<br/>
Load multiple media entities with specified type and conditions
<br/><br/>

</details>

<details>
  <summary><code>protected function mediaVisitActionPageWithName(string $media_type, string $name, string $action_subpath = ''): void</code></summary>

<br/>
Visit the action page of the media with a specified name
<br/><br/>

</details>

## Drupal\MenuTrait

[Source](src/Steps/Drupal/MenuTrait.php), [Steps](STEPS.md#drupalmenutrait)

> Manage Drupal menu systems and menu link rendering.

<details>
  <summary><code>protected function menuLoadByLabel(string $label): ?MenuInterface</code></summary>

<br/>
Load a menu by its label
<br/><br/>

</details>

<details>
  <summary><code>protected function menuLoadLinkByTitle(string $title, string $menu_name): ?MenuLinkContent</code></summary>

<br/>
Get a menu link by title and menu name
<br/><br/>

</details>

## Drupal\ModuleTrait

[Source](src/Steps/Drupal/ModuleTrait.php), [Steps](STEPS.md#drupalmoduletrait)

> Enable and disable Drupal modules with automatic state restoration.

<details>
  <summary><code>protected function moduleDisable(string $module): void</code></summary>

<br/>
Disable a module
<br/><br/>

</details>

<details>
  <summary><code>protected function moduleEnable(string $module): void</code></summary>

<br/>
Enable a module
<br/><br/>

</details>

<details>
  <summary><code>protected function moduleIsEnabled(string $module): bool</code></summary>

<br/>
Check if a module is enabled
<br/><br/>

</details>

<details>
  <summary><code>protected function moduleIsPresent(string $module): bool</code></summary>

<br/>
Check if a module's code is present
<br/><br/>

</details>

<details>
  <summary><code>protected function moduleStoreOriginalState(string $module): void</code></summary>

<br/>
Store original module state if not already stored
<br/><br/>

</details>

## Drupal\ParagraphsTrait

[Source](src/Steps/Drupal/ParagraphsTrait.php), [Steps](STEPS.md#drupalparagraphstrait)

> Manage Drupal paragraphs entities with structured field data.

<details>
  <summary><code>protected function paragraphsAttachFromStubToEntity(ContentEntityInterface $parent_entity, string $parent_field_name, string $paragraph_bundle, EntityStub $stub, bool $save_entity = TRUE): ParagraphInterface</code></summary>

<br/>
Create a paragraphs item from a stub and attach it to an entity
<br/><br/>

</details>

<details>
  <summary><code>protected function paragraphsExpandEntityFields(EntityStub $stub): void</code></summary>

<br/>
Expand parsed fields into expected field values based on field type
<br/><br/>

</details>

<details>
  <summary><code>protected function paragraphsFindEntity(string $entity_type, string $bundle, string $field_name, string $field_value): ?ContentEntityInterface</code></summary>

<br/>
Find entity
<br/><br/>

</details>

<details>
  <summary><code>protected function paragraphsValidateEntityHasField(string $entity_type, string $bundle, string $field_name): void</code></summary>

<br/>
Validate that an entity has a field
<br/><br/>

</details>

## Drupal\QueueTrait

[Source](src/Steps/Drupal/QueueTrait.php), [Steps](STEPS.md#drupalqueuetrait)

> Manage and assert Drupal queue state.

<details>
  <summary><code>protected function queueGetLeaseTime(): int</code></summary>

<br/>
Get the lease time for claiming queue items
<br/><br/>

</details>

<details>
  <summary><code>protected function queueGetProcessLimit(): int</code></summary>

<br/>
Get the maximum number of items to process
<br/><br/>

</details>

<details>
  <summary><code>protected function queueTrackName(string $queue_name): void</code></summary>

<br/>
Track a queue name for cleanup
<br/><br/>

</details>

## Drupal\RedirectTrait

[Source](src/Steps/Drupal/RedirectTrait.php), [Steps](STEPS.md#drupalredirecttrait)

> Manage Drupal redirect entities provided by the contrib `redirect` module.

<details>
  <summary><code>protected function redirectFormatRow(string $from, string $to, string $status_code): string</code></summary>

<br/>
Format a redirect row for inclusion in an assertion failure message
<br/><br/>

</details>

<details>
  <summary><code>protected function redirectNormalizeDestination(string $uri): string</code></summary>

<br/>
Normalize a destination URI the same way `Redirect::setRedirect()` does
<br/><br/>

</details>

<details>
  <summary><code>protected function redirectNormalizeSource(string $path): string</code></summary>

<br/>
Normalize a source path the same way the `redirect` module stores it
<br/><br/>

</details>

<details>
  <summary><code>protected function redirectNormalizeStatusCode(?string $value): int</code></summary>

<br/>
Normalize the status code value from a table cell
<br/><br/>

</details>

## Drupal\StateTrait

[Source](src/Steps/Drupal/StateTrait.php), [Steps](STEPS.md#drupalstatetrait)

> Manage and assert Drupal State API values with automatic revert.

<details>
  <summary><code>protected function stateNormalizeValue(string $value): mixed</code></summary>

<br/>
Normalize a string value from a step into the shape actually stored
<br/><br/>

</details>

<details>
  <summary><code>protected function stateReadValue(string $name): array</code></summary>

<br/>
Read a state value, distinguishing stored NULL from a missing key
<br/><br/>

</details>

<details>
  <summary><code>protected function stateStoreOriginalValue(string $name): void</code></summary>

<br/>
Store the original state value for a key on first access
<br/><br/>

</details>

<details>
  <summary><code>protected function stateStringifyValue(mixed $value): string</code></summary>

<br/>
Stringify a state value for comparison and error messages
<br/><br/>

</details>

## Drupal\TaxonomyTrait

[Source](src/Steps/Drupal/TaxonomyTrait.php), [Steps](STEPS.md#drupaltaxonomytrait)

> Manage Drupal taxonomy terms with vocabulary organization.

<details>
  <summary><code>protected function taxonomyLoadMultiple(string $vocabulary, array $conditions = []): array</code></summary>

<br/>
Load multiple terms with specified vocabulary and conditions
<br/><br/>

</details>

<details>
  <summary><code>protected function taxonomyVisitActionPageWithName(string $vocabulary, string $term_name, string $action_subpath = ''): void</code></summary>

<br/>
Visit the action page of the term with a specified name
<br/><br/>

</details>

## Drupal\TestmodeTrait

[Source](src/Steps/Drupal/TestmodeTrait.php), [Steps](STEPS.md#drupaltestmodetrait)

> Configure Drupal Testmode module for controlled testing scenarios.

<details>
  <summary><code>protected static function testmodeDisableTestMode(): void</code></summary>

<br/>
Disable test mode
<br/><br/>

</details>

<details>
  <summary><code>protected static function testmodeEnableTestMode(): void</code></summary>

<br/>
Enable test mode
<br/><br/>

</details>

## Drupal\UserTrait

[Source](src/Steps/Drupal/UserTrait.php), [Steps](STEPS.md#drupalusertrait)

> Manage Drupal users with role and permission assignments.

<details>
  <summary><code>protected function userAssignRoles(UserCapabilityInterface $driver, EntityStubInterface $stub, string $roles): void</code></summary>

<br/>
Assign the roles named in a comma-separated list to a saved account
<br/><br/>

</details>

<details>
  <summary><code>protected function userBuildStub(array $extra_fields = []): EntityStubInterface</code></summary>

<br/>
Build a user stub with a random name, password and email
<br/><br/>

</details>

<details>
  <summary><code>protected function userCreateAndLogIn(string $roles, array $extra_fields = []): void</code></summary>

<br/>
Create a user carrying the roles and extra fields, and log in as them
<br/><br/>

</details>

<details>
  <summary><code>protected function userExistsByMail(string $mail): bool</code></summary>

<br/>
Check whether a user with the given email address exists
<br/><br/>

</details>

<details>
  <summary><code>protected function userLoadByName(string $name): ?UserInterface</code></summary>

<br/>
Load a user by name
<br/><br/>

</details>

<details>
  <summary><code>protected function userLoadMultiple(array $conditions = []): array</code></summary>

<br/>
Load multiple users with specified conditions
<br/><br/>

</details>

<details>
  <summary><code>protected function userVisitActionPage(string $name, string $action_subpath = ''): void</code></summary>

<br/>
Visit a user action page
<br/><br/>

</details>

<details>
  <summary><code>protected function userVisitPasswordResetLinkForUser(UserInterface $user): void</code></summary>

<br/>
Visit the password reset link for a given user object
<br/><br/>

</details>

## Drupal\WatchdogTrait

[Source](src/Steps/Drupal/WatchdogTrait.php), [Steps](STEPS.md#drupalwatchdogtrait)

> Assert Drupal does not trigger PHP errors during scenarios using Watchdog.

<details>
  <summary><code>protected function watchdogAssertNotHasErrors(string $context): void</code></summary>

<br/>
Assert no errors at or above the severity threshold were logged
<br/><br/>

</details>

<details>
  <summary><code>protected function watchdogParseMessageTypes(array $tags = [], string $prefix = 'watchdog:'): array</code></summary>

<br/>
Parse scenario tags into message types
<br/><br/>

```
@watchdog:my_module_type @watchdog:my_other_module_type
```

</details>

## Drupal\WebformTrait

[Source](src/Steps/Drupal/WebformTrait.php), [Steps](STEPS.md#drupalwebformtrait)

> Manage Drupal webforms.

<details>
  <summary><code>protected function webformLoadAll(string $title): array</code></summary>

<br/>
Load all webforms whose title contains the given string
<br/><br/>

</details>

<details>
  <summary><code>protected function webformMachineName(string $title): string</code></summary>

<br/>
Generate a sanitized machine name from a title
<br/><br/>

</details>

<details>
  <summary><code>protected function webformTemplates(string $title): array</code></summary>

<br/>
Load all webform templates whose title contains the given string
<br/><br/>

</details>

## RawContext

[Source](src/Behat/Context/RawContext.php)

> Base context carrying the scenario lifecycle.

<details>
  <summary><code>public function assertDrupal(): DrupalDriverInterface</code></summary>

<br/>
Asserts the scenario can reach Drupal's API, and returns the driver
<br/><br/>

</details>

<details>
  <summary><code>protected function captureScalarBaseFields(EntityStubInterface $stub): array</code></summary>

<br/>
Captures the scalar values on an entity stub
<br/><br/>

</details>

<details>
  <summary><code>protected function deleteStub(EntityStubInterface $stub, DriverInterface $driver): void</code></summary>

<br/>
Routes a stub to the right per-type driver delete method
<br/><br/>

</details>

<details>
  <summary><code>protected function dispatchHooks(string $scopeClass, EntityStubInterface $stub): void</code></summary>

<br/>
Dispatches the hooks registered for a scope
<br/><br/>

</details>

<details>
  <summary><code>protected function entityCleanupSkippedTypes(ScenarioScope $scope): array</code></summary>

<br/>
Collects the entity types named in per-type cleanup bypass tags
<br/><br/>

</details>

<details>
  <summary><code>public function entityCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates an entity of a type that has no dedicated method
<br/><br/>

</details>

<details>
  <summary><code>public function entityRegister(EntityInterface $entity): void</code></summary>

<br/>
Registers an entity saved outside the create pipeline for cleanup
<br/><br/>

</details>

<details>
  <summary><code>public function getAuthenticationManager(): AuthenticationManagerInterface</code></summary>

<br/>
Returns the authentication manager
<br/><br/>

</details>

<details>
  <summary><code>protected function getContentDriver(): ContentCapabilityInterface</code></summary>

<br/>
Resolves the active driver as a content-capable instance
<br/><br/>

</details>

<details>
  <summary><code>public function getDriver(?string $name = NULL): DriverInterface</code></summary>

<br/>
Returns the active driver
<br/><br/>

</details>

<details>
  <summary><code>public function getDriverManager(): DriverManagerInterface</code></summary>

<br/>
Returns the driver manager
<br/><br/>

</details>

<details>
  <summary><code>public function getDrupalSelector(string $name): string</code></summary>

<br/>
Returns a specific CSS selector
<br/><br/>

</details>

<details>
  <summary><code>public function getDrupalText(string $name): string</code></summary>

<br/>
Returns a specific Drupal text value
<br/><br/>

</details>

<details>
  <summary><code>protected function getFieldParser(string $entity_type, FieldClassifierInterface $classifier, ?string $bundle = NULL): EntityFieldParserInterface</code></summary>

<br/>
Builds the entity-field parser for one parsing call
<br/><br/>

</details>

<details>
  <summary><code>public function getMapping(string $name): string</code></summary>

<br/>
Returns a mapped value by its key
<br/><br/>

</details>

<details>
  <summary><code>public function getParameter(string $name): mixed</code></summary>

<br/>
Returns a specific extension parameter
<br/><br/>

</details>

<details>
  <summary><code>public function getRandom(): Random</code></summary>

<br/>
Returns the driver's random generator
<br/><br/>

</details>

<details>
  <summary><code>public function getUserManager(): UserManagerInterface</code></summary>

<br/>
Returns the user manager
<br/><br/>

</details>

<details>
  <summary><code>public function languageCreate(EntityStubInterface $stub): EntityStubInterface|false</code></summary>

<br/>
Creates a language
<br/><br/>

</details>

<details>
  <summary><code>public function loggedIn(): bool</code></summary>

<br/>
Determines whether a user is logged in for this session
<br/><br/>

</details>

<details>
  <summary><code>public function login(EntityStubInterface $user): void</code></summary>

<br/>
Logs the given user in
<br/><br/>

</details>

<details>
  <summary><code>public function logout(bool $fast = FALSE): void</code></summary>

<br/>
Logs the current user out
<br/><br/>

</details>

<details>
  <summary><code>public function nodeCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a node
<br/><br/>

</details>

<details>
  <summary><code>protected function parseCreatedEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void</code></summary>

<br/>
Expands a stub's values during creation, when the driver can classify them
<br/><br/>

</details>

<details>
  <summary><code>public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void</code></summary>

<br/>
Expands a stub's raw Gherkin values into the storage field shape
<br/><br/>

</details>

<details>
  <summary><code>protected function resolveVocabularyMachineName(string $identifier): string</code></summary>

<br/>
Resolves a vocabulary identifier to its machine name
<br/><br/>

</details>

<details>
  <summary><code>protected function restoreScalarBaseFields(EntityStubInterface $stub, array $scalars): void</code></summary>

<br/>
Restores scalar values previously captured
<br/><br/>

</details>

<details>
  <summary><code>protected function shouldCleanup(): bool</code></summary>

<br/>
Determines whether scenario cleanup should run
<br/><br/>

</details>

<details>
  <summary><code>protected function skipTag(string $name, ScenarioScope $scope): bool</code></summary>

<br/>
Determines whether a scenario opts out of a hook
<br/><br/>

</details>

<details>
  <summary><code>public function termCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a taxonomy term
<br/><br/>

</details>

<details>
  <summary><code>public function userCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a user
<br/><br/>

</details>


[//]: # (END)
