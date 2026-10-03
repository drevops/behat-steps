# Available helpers

### Index of Web helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [AccessibilityTrait](#accessibilitytrait) | 15 | Assess accessibility of rendered pages. |
| [BasicAuthTrait](#basicauthtrait) | 1 | Keep HTTP basic authentication applied across session resets. |
| [CommandTrait](#commandtrait) | 1 | Run local shell commands and assert on their result. |
| [CookieTrait](#cookietrait) | 4 | Verify and inspect browser cookies. |
| [DateTrait](#datetrait) | 3 | Convert relative date expressions into timestamps or formatted dates. |
| [DiagnosticsTrait](#diagnosticstrait) | 12 | Append on-failure diagnostics to the failure message of any failed step. |
| [DropzoneTrait](#dropzonetrait) | 1 | Simulate a real multi-file drag-and-drop gesture onto a Dropzone target. |
| [ElementTrait](#elementtrait) | 4 | Interact with HTML elements using CSS selectors and DOM attributes. |
| [FieldTrait](#fieldtrait) | 4 | Manipulate form fields and verify widget functionality. |
| [FileDownloadTrait](#filedownloadtrait) | 3 | Test file download functionality with content verification. |
| [JavascriptTrait](#javascripttrait) | 1 | Automatically detect JavaScript errors during test execution. |
| [JsonTrait](#jsontrait) | 6 | Assert JSON responses with path and schema checks. |
| [MappingTrait](#mappingtrait) | 2 | Replace `{{ Key }}` tokens in step arguments and table cells. |
| [MessageTrait](#messagetrait) | 3 | Assert status, error, warning and success messages rendered on the page. |
| [MetatagTrait](#metatagtrait) | 11 | Assert `<meta>` tags and head/SEO markup in page markup. |
| [ModalTrait](#modaltrait) | 6 | Interact with and assert modals. |
| [PathTrait](#pathtrait) | 1 | Navigate and verify paths with URL validation. |
| [RandomTrait](#randomtrait) | 10 | Replace random-value tokens in step arguments and table cells. |
| [RegionTrait](#regiontrait) | 2 | Interact with and assert against named page regions. |
| [ResponsiveTrait](#responsivetrait) | 6 | Test responsive layouts with viewport control. |
| [RestTrait](#resttrait) | 2 | Lightweight REST API testing with no Drupal dependencies. |
| [TableTrait](#tabletrait) | 8 | Interact with HTML table elements and assert their content. |
| [WaitTrait](#waittrait) | 1 | Wait for a period of time or for AJAX to finish. |
| [XmlTrait](#xmltrait) | 6 | Assert XML responses with element and attribute checks. |
| [RequestHeadersTrait](#requestheaderstrait) | 1 | Holds the request headers shared by the traits that issue HTTP requests. |
| [TableTransposeTrait](#tabletransposetrait) | 2 | Reads a vertical Gherkin table as 1 set of values per entity. |

### Index of Drupal helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [Drupal\BigPipeTrait](#drupalbigpipetrait) | 2 | Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios. |
| [Drupal\BlockTrait](#drupalblocktrait) | 2 | Manage Drupal blocks. |
| [Drupal\CacheTrait](#drupalcachetrait) | 1 | Invalidate Drupal caches and run cron from within a scenario. |
| [Drupal\ConfigTrait](#drupalconfigtrait) | 2 | Assert and set stored Drupal configuration values with automatic revert. |
| [Drupal\ContentBlockTrait](#drupalcontentblocktrait) | 2 | Manage Drupal content blocks. |
| [Drupal\ContentTrait](#drupalcontenttrait) | 3 | Manage Drupal content with workflow and moderation support. |
| [Drupal\DraggableviewsTrait](#drupaldraggableviewstrait) | 1 | Order items in the Drupal Draggable Views. |
| [Drupal\DrushTrait](#drupaldrushtrait) | 3 | Run Drush commands and assert their output. |
| [Drupal\EckTrait](#drupalecktrait) | 2 | Manage Drupal ECK entities with custom type and bundle creation. |
| [Drupal\EmailTrait](#drupalemailtrait) | 3 | Test Drupal email functionality with content verification. |
| [Drupal\EntityTrait](#drupalentitytrait) | 6 | Create entities of a type that has no dedicated trait. |
| [Drupal\FileTrait](#drupalfiletrait) | 3 | Manage Drupal file entities with upload and storage operations. |
| [Drupal\MediaTrait](#drupalmediatrait) | 4 | Manage Drupal media entities with type-specific field handling. |
| [Drupal\MenuTrait](#drupalmenutrait) | 2 | Manage Drupal menu systems and menu link rendering. |
| [Drupal\ModuleTrait](#drupalmoduletrait) | 4 | Enable and disable Drupal modules with automatic state restoration. |
| [Drupal\ParagraphsTrait](#drupalparagraphstrait) | 2 | Manage Drupal paragraphs entities with structured field data. |
| [Drupal\QueueTrait](#drupalqueuetrait) | 2 | Manage and assert Drupal queue state. |
| [Drupal\StateTrait](#drupalstatetrait) | 1 | Manage and assert Drupal State API values with automatic revert. |
| [Drupal\TaxonomyTrait](#drupaltaxonomytrait) | 2 | Manage Drupal taxonomy terms with vocabulary organization. |
| [Drupal\TestmodeTrait](#drupaltestmodetrait) | 2 | Configure Drupal Testmode module for controlled testing scenarios. |
| [Drupal\UserTrait](#drupalusertrait) | 8 | Manage Drupal users with role and permission assignments. |
| [Drupal\WatchdogTrait](#drupalwatchdogtrait) | 2 | Assert Drupal does not trigger PHP errors during scenarios using Watchdog. |
| [Drupal\WebformTrait](#drupalwebformtrait) | 2 | Manage Drupal webforms. |
| [Drupal\AuthTrait](#drupalauthtrait) | 12 | Creates users and roles, logs them in, and removes them afterwards. |
| [Drupal\EntityLifecycleTrait](#drupalentitylifecycletrait) | 6 | Creates Drupal entities and removes them when the scenario ends. |
| [Drupal\FixtureFileTrait](#drupalfixturefiletrait) | 1 | Resolves a fixture file path for a file or image field. |
| [Drupal\QueryTrait](#drupalquerytrait) | 2 | Reads Drupal state a step asserts on without going through a backend. |

### Index of Toolbox helpers

| Class | Helpers | Description |
| --- | --- | --- |
| [WebRawContext](#webrawcontext) | 21 | Root context carrying the plumbing every suite needs. |

---

## AccessibilityTrait

[Source](src/Steps/Web/AccessibilityTrait.php), [Steps](STEPS.md#accessibilitytrait)

> Assess accessibility of rendered pages.

<details>
  <summary><code>public function accessibilityAssess(string $rules): array</code></summary>

<br/>
Run the engine, normalize the result, record it for the scenario
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityFetchJs(string $url, int $timeout): string|false</code></summary>

<br/>
Read the engine source once from the given location
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetAutoTag(): string</code></summary>

<br/>
Return the base tag name that enables automatic mode (no `@` prefix)
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetCdnUrl(): string</code></summary>

<br/>
Return the URL used by the default accessibilityGetJs() implementation
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetDefaultRules(): string</code></summary>

<br/>
Return the default rule identifier passed to the engine
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFailOnIncomplete(): bool</code></summary>

<br/>
Return TRUE if "incomplete" findings should fail the gate by default
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFailureThreshold(): string</code></summary>

<br/>
Return the default failure threshold
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFetchAttempts(): int</code></summary>

<br/>
Return how many times the engine fetch is attempted before failing
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetFetchTimeout(): int</code></summary>

<br/>
Return the per-attempt timeout, in seconds, for the engine fetch
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetImpacts(): array</code></summary>

<br/>
Return the canonical impact levels in descending severity order
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetJs(): string</code></summary>

<br/>
Return the JavaScript source to inject into the page
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetPrintCli(): bool</code></summary>

<br/>
Return TRUE to print a 1-line per-page summary to the console
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityGetReportDir(): string</code></summary>

<br/>
Return the absolute directory used to write per-scenario reports
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityNormalizeResults(array $raw): array</code></summary>

<br/>
Normalize raw engine output into the canonical shape used by the trait
<br/><br/>

</details>

<details>
  <summary><code>public function accessibilityRunEngine(string $rules): array</code></summary>

<br/>
Execute the engine against the current page and return raw results
<br/><br/>

</details>

## BasicAuthTrait

[Source](src/Steps/Web/BasicAuthTrait.php), [Steps](STEPS.md#basicauthtrait)

> Keep HTTP basic authentication applied across session resets.

<details>
  <summary><code>public function basicAuthApply(): void</code></summary>

<br/>
Apply the resolved credentials to the session
<br/><br/>

</details>

## CommandTrait

[Source](src/Steps/Web/CommandTrait.php), [Steps](STEPS.md#commandtrait)

> Run local shell commands and assert on their result.

<details>
  <summary><code>public function commandGetTimeout(): int</code></summary>

<br/>
The maximum time, in seconds, a command may run before it is terminated
<br/><br/>

</details>

## CookieTrait

[Source](src/Steps/Web/CookieTrait.php), [Steps](STEPS.md#cookietrait)

> Verify and inspect browser cookies.

<details>
  <summary><code>public function cookieAssertExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void</code></summary>

<br/>
Assert that a cookie exists
<br/><br/>

</details>

<details>
  <summary><code>public function cookieAssertNotExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void</code></summary>

<br/>
Assert that a cookie does not exist
<br/><br/>

</details>

<details>
  <summary><code>public function cookieFindByName(string $name, bool $is_partial = FALSE): ?array</code></summary>

<br/>
Find a cookie by exact or partial name
<br/><br/>

</details>

<details>
  <summary><code>public function cookieGetAll(): array</code></summary>

<br/>
Get all cookies
<br/><br/>

</details>

## DateTrait

[Source](src/Steps/Web/DateTrait.php), [Steps](STEPS.md#datetrait)

> Convert relative date expressions into timestamps or formatted dates.

<details>
  <summary><code>public static function dateGetNow(): int</code></summary>

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
  <summary><code>public static function dateRelativeStringHasToken(string $string): bool</code></summary>

<br/>
Check whether a string holds a relative date token
<br/><br/>

</details>

## DiagnosticsTrait

[Source](src/Steps/Web/DiagnosticsTrait.php), [Steps](STEPS.md#diagnosticstrait)

> Append on-failure diagnostics to the failure message of any failed step.

<details>
  <summary><code>public function diagnosticsFindBrowserDriverName(): ?string</code></summary>

<br/>
Return the browser driver's class, or NULL when it is unavailable
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsFindRerunCommand(): ?string</code></summary>

<br/>
Return the command that re-runs just the failing scenario
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsFindStatusCode(): ?int</code></summary>

<br/>
Return the last response status code, or NULL when it is unavailable
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsFindUrl(): ?string</code></summary>

<br/>
Return the current page URL, or NULL when it cannot be determined
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetHeader(): string</code></summary>

<br/>
Return the header line that precedes the diagnostics block
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetJsErrors(): array</code></summary>

<br/>
Return collected JavaScript console error messages
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetRerunBinary(): string</code></summary>

<br/>
Return the binary used in the re-run command. Override to customise
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowBrowserDriver(): bool</code></summary>

<br/>
Return TRUE to include the browser driver class. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowJsErrors(): bool</code></summary>

<br/>
Return TRUE to include JavaScript console errors. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowRerun(): bool</code></summary>

<br/>
Return TRUE to include the re-run command. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowStatusCode(): bool</code></summary>

<br/>
Return TRUE to include the HTTP status code. Override to suppress
<br/><br/>

</details>

<details>
  <summary><code>public function diagnosticsGetShowUrl(): bool</code></summary>

<br/>
Return TRUE to include the current URL. Override to suppress
<br/><br/>

</details>

## DropzoneTrait

[Source](src/Steps/Web/DropzoneTrait.php), [Steps](STEPS.md#dropzonetrait)

> Simulate a real multi-file drag-and-drop gesture onto a Dropzone target.

<details>
  <summary><code>public function dropzoneResolvePath(string $path): string</code></summary>

<br/>
Resolve a fixture path against the Mink `files_path` parameter
<br/><br/>

</details>

## ElementTrait

[Source](src/Steps/Web/ElementTrait.php), [Steps](STEPS.md#elementtrait)

> Interact with HTML elements using CSS selectors and DOM attributes.

<details>
  <summary><code>public function elementExecuteJs(string $selector, string $script)</code></summary>

<br/>
Execute JS on an element provided by the selector
<br/><br/>

</details>

<details>
  <summary><code>public function elementFindHeading(string $heading): ?NodeElement</code></summary>

<br/>
Find a heading whose text matches exactly
<br/><br/>

</details>

<details>
  <summary><code>public function elementGetNth(array $elements, int $index, string $subject): NodeElement</code></summary>

<br/>
Return the element at a 1-based index or throw a clear error
<br/><br/>

</details>

<details>
  <summary><code>public function elementIsVisuallyVisible(string $selector, int $offset)</code></summary>

<br/>
Check whether an element is displayed within the viewport
<br/><br/>

</details>

## FieldTrait

[Source](src/Steps/Web/FieldTrait.php), [Steps](STEPS.md#fieldtrait)

> Manipulate form fields and verify widget functionality.

<details>
  <summary><code>public function fieldDisableFormValidation(?string $selector = NULL): void</code></summary>

<br/>
Disable browser validation for forms
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetAddMoreButtonSelectors(): array</code></summary>

<br/>
CSS selectors for the "Add another item" button
<br/><br/>

</details>

<details>
  <summary><code>public function fieldGetRequiredMarkerSelectors(): array</code></summary>

<br/>
CSS selectors that indicate a required-field marker
<br/><br/>

</details>

<details>
  <summary><code>public function fieldIsMarkedRequired(NodeElement $field_element): bool</code></summary>

<br/>
Check if a given field element is marked as required
<br/><br/>

</details>

## FileDownloadTrait

[Source](src/Steps/Web/FileDownloadTrait.php), [Steps](STEPS.md#filedownloadtrait)

> Test file download functionality with content verification.

<details>
  <summary><code>public function fileDownloadGetTempDir(): string</code></summary>

<br/>
Get temp download dir
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadOpenZip(): ZipArchive</code></summary>

<br/>
Open downloaded ZIP archive and validate contents
<br/><br/>

</details>

<details>
  <summary><code>public function fileDownloadProcess(string $url, array $options = []): array</code></summary>

<br/>
Download file
<br/><br/>

</details>

## JavascriptTrait

[Source](src/Steps/Web/JavascriptTrait.php), [Steps](STEPS.md#javascripttrait)

> Automatically detect JavaScript errors during test execution.

<details>
  <summary><code>public function javascriptAssertErrorsNotExist(): void</code></summary>

<br/>
Assert that no JavaScript errors were collected
<br/><br/>

</details>

## JsonTrait

[Source](src/Steps/Web/JsonTrait.php), [Steps](STEPS.md#jsontrait)

> Assert JSON responses with path and schema checks.

<details>
  <summary><code>public function jsonDecode(string $content): array</code></summary>

<br/>
Decode a JSON string into an array
<br/><br/>

</details>

<details>
  <summary><code>public function jsonDecodeLoose(string $content): mixed</code></summary>

<br/>
Decode JSON content as loosely-typed data
<br/><br/>

</details>

<details>
  <summary><code>public function jsonQuery(string $path): array</code></summary>

<br/>
Run a JSONPath expression against the decoded response
<br/><br/>

</details>

<details>
  <summary><code>public function jsonResolveScalar(string $path): mixed</code></summary>

<br/>
Resolve a JSONPath expression to a single scalar value
<br/><br/>

</details>

<details>
  <summary><code>public function jsonResolveSingle(string $path): mixed</code></summary>

<br/>
Resolve a JSONPath expression to a single matched value
<br/><br/>

</details>

<details>
  <summary><code>public function jsonValidateSchema(string $schema_json): void</code></summary>

<br/>
Validate the response body against a JSON schema
<br/><br/>

</details>

## MappingTrait

[Source](src/Steps/Web/MappingTrait.php), [Steps](STEPS.md#mappingtrait)

> Replace `{{ Key }}` tokens in step arguments and table cells.

<details>
  <summary><code>public function mappingGetValue(string $name): string</code></summary>

<br/>
Returns a mapped value by its key
<br/><br/>

</details>

<details>
  <summary><code>public function mappingSubstitute(string $value): string</code></summary>

<br/>
Substitutes every mapping token found in a single string
<br/><br/>

</details>

## MessageTrait

[Source](src/Steps/Web/MessageTrait.php), [Steps](STEPS.md#messagetrait)

> Assert status, error, warning and success messages rendered on the page.

<details>
  <summary><code>public function messageAssertExistsOfType(string $message, string $type): void</code></summary>

<br/>
Assert that a message of the given type contains the expected text
<br/><br/>

</details>

<details>
  <summary><code>public function messageAssertNotExistsOfType(string $message, string $type): void</code></summary>

<br/>
Assert that no message of the given type contains the expected text
<br/><br/>

</details>

<details>
  <summary><code>public function messageGetSelector(string $type): string</code></summary>

<br/>
Return the configured CSS selector for a message type
<br/><br/>

</details>

## MetatagTrait

[Source](src/Steps/Web/MetatagTrait.php), [Steps](STEPS.md#metatagtrait)

> Assert `<meta>` tags and head/SEO markup in page markup.

<details>
  <summary><code>public function metatagAssertMetaSetExists(array $names, string $label): void</code></summary>

<br/>
Assert that a set of meta tags is present and non-empty
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindCanonicalHref(): ?string</code></summary>

<br/>
Find the canonical URL href
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindMeta(string $meta_name): ?NodeElement</code></summary>

<br/>
Find a meta tag by its "name" or "property" attribute
<br/><br/>

</details>

<details>
  <summary><code>public function metatagFindMetaContent(string $meta_name): ?string</code></summary>

<br/>
Find the content of a meta tag by its "name" or "property" attribute
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetHreflangAlternates(): array</code></summary>

<br/>
Get the hreflang alternates present on the current page
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetRequiredOpenGraphTags(): array</code></summary>

<br/>
The Open Graph meta tags required by "the Open Graph tags should be valid"
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetRequiredTwitterCardTags(): array</code></summary>

<br/>
The Twitter Card tags required by "the Twitter Card tags should be valid"
<br/><br/>

</details>

<details>
  <summary><code>public function metatagGetRobotsDirectives(): array</code></summary>

<br/>
Get the robots meta tag directives as lower-cased tokens
<br/><br/>

</details>

<details>
  <summary><code>public function metatagIsIndexable(): bool</code></summary>

<br/>
Determine whether the current page is indexable
<br/><br/>

</details>

<details>
  <summary><code>public function metatagIsValidHreflang(string $value): bool</code></summary>

<br/>
Determine whether a hreflang value is a well-formed language code
<br/><br/>

</details>

<details>
  <summary><code>public function metatagResponseHasNoindexHeader(): bool</code></summary>

<br/>
Determine whether the X-Robots-Tag response header carries "noindex"
<br/><br/>

</details>

## ModalTrait

[Source](src/Steps/Web/ModalTrait.php), [Steps](STEPS.md#modaltrait)

> Interact with and assert modals.

<details>
  <summary><code>public function modalFind(): ?NodeElement</code></summary>

<br/>
Find the first visible modal, or fall back to the first DOM match
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetCloseSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal close button
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetContentSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal content
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetSelectors(): array</code></summary>

<br/>
Get the CSS selectors for the modal container
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetVisible(): NodeElement</code></summary>

<br/>
Return the first visible modal
<br/><br/>

</details>

<details>
  <summary><code>public function modalGetWaitTimeout(): int</code></summary>

<br/>
Get the timeout in seconds for waiting for the modal to appear
<br/><br/>

</details>

## PathTrait

[Source](src/Steps/Web/PathTrait.php), [Steps](STEPS.md#pathtrait)

> Navigate and verify paths with URL validation.

<details>
  <summary><code>public function pathGetCurrentUrlQuery(): array</code></summary>

<br/>
Get the query parameters of the current URL
<br/><br/>

</details>

## RandomTrait

[Source](src/Steps/Web/RandomTrait.php), [Steps](STEPS.md#randomtrait)

> Replace random-value tokens in step arguments and table cells.

<details>
  <summary><code>public function randomGenerate(string $type, array $args): string|int</code></summary>

<br/>
Dispatches to the type-specific generator
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateEmail(): string</code></summary>

<br/>
Generates a syntactically valid email at the reserved '.test' TLD
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateInt(int $min, int $max): int</code></summary>

<br/>
Generates an integer in '[min, max]' inclusive
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateMachineName(int $length): string</code></summary>

<br/>
Generates a Drupal-shaped machine name (lowercase + underscores)
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateName(int $length): string</code></summary>

<br/>
Generates a 'Random::name()' string with original case preserved
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateString(int $length): string</code></summary>

<br/>
Generates a lowercase string, the default token type
<br/><br/>

</details>

<details>
  <summary><code>public function randomGenerateUuid(): string</code></summary>

<br/>
Generates a UUID v4 string
<br/><br/>

</details>

<details>
  <summary><code>public function randomResolveLiteral(string $literal): string|int</code></summary>

<br/>
Resolves a token literal to its generated value
<br/><br/>

</details>

<details>
  <summary><code>public function randomSubstitute(string $message): string</code></summary>

<br/>
Substitutes every token match in '$message' via 'randomResolveLiteral()'
<br/><br/>

</details>

<details>
  <summary><code>public function randomSubstituteTable(TableNode $table): TableNode</code></summary>

<br/>
Applies 'randomSubstitute()' across every cell in '$table'
<br/><br/>

</details>

## RegionTrait

[Source](src/Steps/Web/RegionTrait.php), [Steps](STEPS.md#regiontrait)

> Interact with and assert against named page regions.

<details>
  <summary><code>public function regionGet(string $region): NodeElement</code></summary>

<br/>
Return a named region on the current page
<br/><br/>

</details>

<details>
  <summary><code>public function regionGetElementByText(string $region, string $selector, string $text): NodeElement</code></summary>

<br/>
Return an element in a region whose text matches exactly
<br/><br/>

</details>

## ResponsiveTrait

[Source](src/Steps/Web/ResponsiveTrait.php), [Steps](STEPS.md#responsivetrait)

> Test responsive layouts with viewport control.

<details>
  <summary><code>public function responsiveGetAllBreakpoints(): array</code></summary>

<br/>
Get all available breakpoints
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveGetBreakpoint(string $breakpoint): string</code></summary>

<br/>
Get breakpoint dimensions by name
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveGetCurrentDimensions(): array</code></summary>

<br/>
Get current viewport dimensions
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveResize(int $width, int $height): void</code></summary>

<br/>
Resize the browser window
<br/><br/>

</details>

<details>
  <summary><code>public function responsiveResizeToBreakpoint(string $breakpoint): void</code></summary>

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

[Source](src/Steps/Web/RestTrait.php), [Steps](STEPS.md#resttrait)

> Lightweight REST API testing with no Drupal dependencies.

<details>
  <summary><code>public function restGetClient(): mixed</code></summary>

<br/>
Get the page client REST requests are sent through
<br/><br/>

</details>

<details>
  <summary><code>public function restResolveUrl(string $url): string</code></summary>

<br/>
Resolve a relative URL against the Mink base URL
<br/><br/>

</details>

## TableTrait

[Source](src/Steps/Web/TableTrait.php), [Steps](STEPS.md#tabletrait)

> Interact with HTML table elements and assert their content.

<details>
  <summary><code>public function tableFindRowByText(string $row_text): ?NodeElement</code></summary>

<br/>
Find a table row containing the given text
<br/><br/>

</details>

<details>
  <summary><code>public function tableGet(string $selector): NodeElement</code></summary>

<br/>
Return the table element matching a CSS selector
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetBodyRowSelector(): string</code></summary>

<br/>
Get the CSS selector for table body rows
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetColumnIndex(NodeElement $table, string $column, string $selector): int</code></summary>

<br/>
Get the index of a column by its header text
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetHeaderSelector(): string</code></summary>

<br/>
Get the CSS selector for table header cells
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetHeaders(NodeElement $table): array</code></summary>

<br/>
Get the header texts from a table element
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetRowByText(string $row_text): NodeElement</code></summary>

<br/>
Return the first row on the page containing the text
<br/><br/>

</details>

<details>
  <summary><code>public function tableGetRows(NodeElement $table): array</code></summary>

<br/>
Get the body rows from a table element
<br/><br/>

</details>

## WaitTrait

[Source](src/Steps/Web/WaitTrait.php), [Steps](STEPS.md#waittrait)

> Wait for a period of time or for AJAX to finish.

<details>
  <summary><code>public function waitGetAjaxTimeout(): int</code></summary>

<br/>
Return the configured AJAX timeout, in seconds
<br/><br/>

</details>

## XmlTrait

[Source](src/Steps/Web/XmlTrait.php), [Steps](STEPS.md#xmltrait)

> Assert XML responses with element and attribute checks.

<details>
  <summary><code>public function xmlParse(string $content): array</code></summary>

<br/>
Parse XML content without altering the cached document
<br/><br/>

</details>

<details>
  <summary><code>public function xmlValidateAtomFeed(): void</code></summary>

<br/>
Validate the response as an Atom feed
<br/><br/>

</details>

<details>
  <summary><code>public function xmlValidateDtd(string $dtd): void</code></summary>

<br/>
Validate the response against a DTD
<br/><br/>

</details>

<details>
  <summary><code>public function xmlValidateRelaxNg(string $schema): void</code></summary>

<br/>
Validate the response against a RelaxNG schema
<br/><br/>

</details>

<details>
  <summary><code>public function xmlValidateRssFeed(): void</code></summary>

<br/>
Validate the response as an RSS 2.0 feed
<br/><br/>

</details>

<details>
  <summary><code>public function xmlValidateXsd(string $schema): void</code></summary>

<br/>
Validate the response against an XSD schema
<br/><br/>

</details>

## RequestHeadersTrait

[Source](src/Helper/Web/RequestHeadersTrait.php)

> Holds the request headers shared by the traits that issue HTTP requests.

<details>
  <summary><code>public function requestHeadersSet(string $name, string $value): void</code></summary>

<br/>
Set a request header for subsequent requests
<br/><br/>

```
$this->requestHeadersSet('X-Acme-Token', 'secret');
```

</details>

## TableTransposeTrait

[Source](src/Helper/Web/TableTransposeTrait.php)

> Reads a vertical Gherkin table as 1 set of values per entity.

<details>
  <summary><code>public function tableTransposeHorizontal(array $entities): TableNode</code></summary>

<br/>
Convert vertical format entities to horizontal TableNode
<br/><br/>

</details>

<details>
  <summary><code>public function tableTransposeVertical(TableNode $table): array</code></summary>

<br/>
Transpose a vertical table format (field/value columns) to entity arrays
<br/><br/>

</details>

## Drupal\BigPipeTrait

[Source](src/Steps/Drupal/BigPipeTrait.php), [Steps](STEPS.md#drupalbigpipetrait)

> Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios.

<details>
  <summary><code>public function bigPipeGetWaitTimeout(): int</code></summary>

<br/>
Maximum time to wait for BigPipe placeholders, in milliseconds
<br/><br/>

</details>

<details>
  <summary><code>public function bigPipeWaitForPlaceholders(int $timeout_ms): void</code></summary>

<br/>
Wait until no BigPipe placeholder markers remain in the DOM
<br/><br/>

</details>

## Drupal\BlockTrait

[Source](src/Steps/Drupal/BlockTrait.php), [Steps](STEPS.md#drupalblocktrait)

> Manage Drupal blocks.

<details>
  <summary><code>public function blockFindByLabel(string $label): ?Block</code></summary>

<br/>
Find a block by its label
<br/><br/>

</details>

<details>
  <summary><code>public function blockGetByLabel(string $label): Block</code></summary>

<br/>
Return the block carrying a label
<br/><br/>

</details>

## Drupal\CacheTrait

[Source](src/Steps/Drupal/CacheTrait.php), [Steps](STEPS.md#drupalcachetrait)

> Invalidate Drupal caches and run cron from within a scenario.

<details>
  <summary><code>public function cacheGetPageCacheBin(): string</code></summary>

<br/>
Get the cache bin used for the page cache
<br/><br/>

</details>

## Drupal\ConfigTrait

[Source](src/Steps/Drupal/ConfigTrait.php), [Steps](STEPS.md#drupalconfigtrait)

> Assert and set stored Drupal configuration values with automatic revert.

<details>
  <summary><code>public function configReadEffective(string $name, string $key): mixed</code></summary>

<br/>
Read an effective configuration value, with overrides applied
<br/><br/>

</details>

<details>
  <summary><code>public function configReadStored(string $name, string $key): mixed</code></summary>

<br/>
Read a stored configuration value, ignoring runtime overrides
<br/><br/>

</details>

## Drupal\ContentBlockTrait

[Source](src/Steps/Drupal/ContentBlockTrait.php), [Steps](STEPS.md#drupalcontentblocktrait)

> Manage Drupal content blocks.

<details>
  <summary><code>public function contentBlockCreateSingle(string $type, array $values): BlockContent</code></summary>

<br/>
Create a block content entity with the specified type and field values
<br/><br/>

</details>

<details>
  <summary><code>public function contentBlockLoadMultiple(string $type, array $conditions = []): array</code></summary>

<br/>
Load multiple content blocks with specified type and conditions
<br/><br/>

</details>

## Drupal\ContentTrait

[Source](src/Steps/Drupal/ContentTrait.php), [Steps](STEPS.md#drupalcontenttrait)

> Manage Drupal content with workflow and moderation support.

<details>
  <summary><code>public function contentGetNidByTitle(string $content_type, string $title): int</code></summary>

<br/>
Return the ID of the node with the specified type and title
<br/><br/>

</details>

<details>
  <summary><code>public function contentGetNodeByTitle(string $content_type, string $title): NodeInterface</code></summary>

<br/>
Return the node with the specified type and title
<br/><br/>

</details>

<details>
  <summary><code>public function contentVisitActionPageWithTitle(string $content_type, string $title, string $action_subpath = ''): void</code></summary>

<br/>
Visit the action page of the content with a specified title
<br/><br/>

</details>

## Drupal\DraggableviewsTrait

[Source](src/Steps/Drupal/DraggableviewsTrait.php), [Steps](STEPS.md#drupaldraggableviewstrait)

> Order items in the Drupal Draggable Views.

<details>
  <summary><code>public function draggableviewsFindNode(string $type, array $conditions): ?NodeInterface</code></summary>

<br/>
Find a node using provided conditions
<br/><br/>

</details>

## Drupal\DrushTrait

[Source](src/Steps/Drupal/DrushTrait.php), [Steps](STEPS.md#drupaldrushtrait)

> Run Drush commands and assert their output.

<details>
  <summary><code>public function drushGetBackend(): DrushCapabilityInterface</code></summary>

<br/>
Return the backend that runs Drush commands
<br/><br/>

</details>

<details>
  <summary><code>public function drushReadOutput(): string</code></summary>

<br/>
Return the output of the most recent Drush command
<br/><br/>

</details>

<details>
  <summary><code>public function drushRunExpectingFailure(string $command, ?string $arguments = NULL): void</code></summary>

<br/>
Run a Drush command expecting a non-zero exit, keeping its output
<br/><br/>

</details>

## Drupal\EckTrait

[Source](src/Steps/Drupal/EckTrait.php), [Steps](STEPS.md#drupalecktrait)

> Manage Drupal ECK entities with custom type and bundle creation.

<details>
  <summary><code>public function eckCreateEntity(EntityStub $stub): void</code></summary>

<br/>
Create a single content entity
<br/><br/>

</details>

<details>
  <summary><code>public function eckLoadMultiple(string $entity_type, string $bundle, array $conditions = []): array</code></summary>

<br/>
Load multiple entities with specified type and conditions
<br/><br/>

</details>

## Drupal\EmailTrait

[Source](src/Steps/Drupal/EmailTrait.php), [Steps](STEPS.md#drupalemailtrait)

> Test Drupal email functionality with content verification.

<details>
  <summary><code>public static function emailExtractLinks(string $string): array</code></summary>

<br/>
Extract all links from provided string
<br/><br/>

</details>

<details>
  <summary><code>public function emailFindMessage(string $field, PyStringNode $string, bool $exact = FALSE): ?array</code></summary>

<br/>
Find an email message whose field contains a value
<br/><br/>

</details>

<details>
  <summary><code>public function emailGetCollectedMessages(): array</code></summary>

<br/>
Get email messages collected during the test
<br/><br/>

</details>

## Drupal\EntityTrait

[Source](src/Steps/Drupal/EntityTrait.php), [Steps](STEPS.md#drupalentitytrait)

> Create entities of a type that has no dedicated trait.

<details>
  <summary><code>public function entityLifecycleCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates an entity of a type that has no dedicated method
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleLanguageCreate(EntityStubInterface $stub): EntityStubInterface|false</code></summary>

<br/>
Creates a language
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleNodeCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a node
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleParseFields(EntityStubInterface $stub, array $ignored_properties = []): void</code></summary>

<br/>
Expands a stub's raw Gherkin values into the storage field shape
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleRegister(EntityInterface $entity): void</code></summary>

<br/>
Registers an entity saved outside the create pipeline for cleanup
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleTermCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a taxonomy term
<br/><br/>

</details>

## Drupal\FileTrait

[Source](src/Steps/Drupal/FileTrait.php), [Steps](STEPS.md#drupalfiletrait)

> Manage Drupal file entities with upload and storage operations.

<details>
  <summary><code>public function fileCreateEntity(string $path, EntityStubInterface $stub, ?string $uri = NULL): FileInterface</code></summary>

<br/>
Create file entity
<br/><br/>

</details>

<details>
  <summary><code>public function fileCreateManagedSingle(string $path, EntityStubInterface $stub, ?string $uri = NULL): FileInterface</code></summary>

<br/>
Create a single managed file
<br/><br/>

</details>

<details>
  <summary><code>public function fileLoadMultiple(array $conditions = []): array</code></summary>

<br/>
Load multiple files with specified conditions
<br/><br/>

</details>

## Drupal\MediaTrait

[Source](src/Steps/Drupal/MediaTrait.php), [Steps](STEPS.md#drupalmediatrait)

> Manage Drupal media entities with type-specific field handling.

<details>
  <summary><code>public function mediaCreateEntity(EntityStubInterface $stub): MediaInterface</code></summary>

<br/>
Create media entity
<br/><br/>

</details>

<details>
  <summary><code>public function mediaCreateSingle(EntityStubInterface $stub): MediaInterface</code></summary>

<br/>
Create a single media item
<br/><br/>

</details>

<details>
  <summary><code>public function mediaLoadMultiple(string $media_type, array $conditions = []): array</code></summary>

<br/>
Load multiple media entities with specified type and conditions
<br/><br/>

</details>

<details>
  <summary><code>public function mediaVisitActionPageWithName(string $media_type, string $name, string $action_subpath = ''): void</code></summary>

<br/>
Visit the action page of the media with a specified name
<br/><br/>

</details>

## Drupal\MenuTrait

[Source](src/Steps/Drupal/MenuTrait.php), [Steps](STEPS.md#drupalmenutrait)

> Manage Drupal menu systems and menu link rendering.

<details>
  <summary><code>public function menuFindByLabel(string $label): ?MenuInterface</code></summary>

<br/>
Find a menu by its label
<br/><br/>

</details>

<details>
  <summary><code>public function menuFindLinkByTitle(string $title, string $menu_name): ?MenuLinkContent</code></summary>

<br/>
Find a menu link by title and menu name
<br/><br/>

</details>

## Drupal\ModuleTrait

[Source](src/Steps/Drupal/ModuleTrait.php), [Steps](STEPS.md#drupalmoduletrait)

> Enable and disable Drupal modules with automatic state restoration.

<details>
  <summary><code>public function moduleDisable(string $module): void</code></summary>

<br/>
Disable a module
<br/><br/>

</details>

<details>
  <summary><code>public function moduleEnable(string $module): void</code></summary>

<br/>
Enable a module
<br/><br/>

</details>

<details>
  <summary><code>public function moduleIsEnabled(string $module): bool</code></summary>

<br/>
Check if a module is enabled
<br/><br/>

</details>

<details>
  <summary><code>public function moduleIsPresent(string $module): bool</code></summary>

<br/>
Check if a module's code is present
<br/><br/>

</details>

## Drupal\ParagraphsTrait

[Source](src/Steps/Drupal/ParagraphsTrait.php), [Steps](STEPS.md#drupalparagraphstrait)

> Manage Drupal paragraphs entities with structured field data.

<details>
  <summary><code>public function paragraphsAttachFromStubToEntity(ContentEntityInterface $parent_entity, string $parent_field, string $paragraph_type, EntityStub $stub, bool $save_entity = TRUE): ParagraphInterface</code></summary>

<br/>
Create a paragraphs item from a stub and attach it to an entity
<br/><br/>

</details>

<details>
  <summary><code>public function paragraphsFindEntity(string $entity_type, string $bundle, string $field_name, string $field_value): ?ContentEntityInterface</code></summary>

<br/>
Find entity
<br/><br/>

</details>

## Drupal\QueueTrait

[Source](src/Steps/Drupal/QueueTrait.php), [Steps](STEPS.md#drupalqueuetrait)

> Manage and assert Drupal queue state.

<details>
  <summary><code>public function queueGetLeaseTime(): int</code></summary>

<br/>
Get the lease time for claiming queue items
<br/><br/>

</details>

<details>
  <summary><code>public function queueGetProcessLimit(): int</code></summary>

<br/>
Get the maximum number of items to process
<br/><br/>

</details>

## Drupal\StateTrait

[Source](src/Steps/Drupal/StateTrait.php), [Steps](STEPS.md#drupalstatetrait)

> Manage and assert Drupal State API values with automatic revert.

<details>
  <summary><code>public function stateReadValue(string $name): array</code></summary>

<br/>
Read a state value, distinguishing stored NULL from a missing key
<br/><br/>

</details>

## Drupal\TaxonomyTrait

[Source](src/Steps/Drupal/TaxonomyTrait.php), [Steps](STEPS.md#drupaltaxonomytrait)

> Manage Drupal taxonomy terms with vocabulary organization.

<details>
  <summary><code>public function taxonomyLoadMultiple(string $vocabulary, array $conditions = []): array</code></summary>

<br/>
Load multiple terms with specified vocabulary and conditions
<br/><br/>

</details>

<details>
  <summary><code>public function taxonomyVisitActionPageWithName(string $vocabulary, string $term_name, string $action_subpath = ''): void</code></summary>

<br/>
Visit the action page of the term with a specified name
<br/><br/>

</details>

## Drupal\TestmodeTrait

[Source](src/Steps/Drupal/TestmodeTrait.php), [Steps](STEPS.md#drupaltestmodetrait)

> Configure Drupal Testmode module for controlled testing scenarios.

<details>
  <summary><code>public static function testmodeDisableTestMode(): void</code></summary>

<br/>
Disable test mode
<br/><br/>

</details>

<details>
  <summary><code>public static function testmodeEnableTestMode(): void</code></summary>

<br/>
Enable test mode
<br/><br/>

</details>

## Drupal\UserTrait

[Source](src/Steps/Drupal/UserTrait.php), [Steps](STEPS.md#drupalusertrait)

> Manage Drupal users with role and permission assignments.

<details>
  <summary><code>public function userAssignRoles(UserCapabilityInterface $backend, EntityStubInterface $stub, string $roles): void</code></summary>

<br/>
Assign the roles named in a comma-separated list to a saved account
<br/><br/>

</details>

<details>
  <summary><code>public function userBuildStub(array $extra_fields = []): EntityStubInterface</code></summary>

<br/>
Build a user stub with a random name, password and email
<br/><br/>

</details>

<details>
  <summary><code>public function userCreateAndLogIn(string $roles, array $extra_fields = []): void</code></summary>

<br/>
Create a user carrying the roles and extra fields, and log in as them
<br/><br/>

</details>

<details>
  <summary><code>public function userExistsByMail(string $mail): bool</code></summary>

<br/>
Check whether a user with the given email address exists
<br/><br/>

</details>

<details>
  <summary><code>public function userGetByName(string $name): UserInterface</code></summary>

<br/>
Return the user with a name
<br/><br/>

</details>

<details>
  <summary><code>public function userLoadMultiple(array $conditions = []): array</code></summary>

<br/>
Load multiple users with specified conditions
<br/><br/>

</details>

<details>
  <summary><code>public function userVisitActionPage(string $name, string $action_subpath = ''): void</code></summary>

<br/>
Visit a user action page
<br/><br/>

</details>

<details>
  <summary><code>public function userVisitPasswordResetLinkForUser(UserInterface $user): void</code></summary>

<br/>
Visit the password reset link for a given user object
<br/><br/>

</details>

## Drupal\WatchdogTrait

[Source](src/Steps/Drupal/WatchdogTrait.php), [Steps](STEPS.md#drupalwatchdogtrait)

> Assert Drupal does not trigger PHP errors during scenarios using Watchdog.

<details>
  <summary><code>public function watchdogAssertErrorsNotExist(string $context): void</code></summary>

<br/>
Assert no errors at or above the severity threshold were logged
<br/><br/>

</details>

<details>
  <summary><code>public function watchdogReadErrors(): array</code></summary>

<br/>
Read the errors logged since the scenario started, and clear them
<br/><br/>

</details>

## Drupal\WebformTrait

[Source](src/Steps/Drupal/WebformTrait.php), [Steps](STEPS.md#drupalwebformtrait)

> Manage Drupal webforms.

<details>
  <summary><code>public function webformLoadAll(string $title): array</code></summary>

<br/>
Load all webforms whose title contains the given string
<br/><br/>

</details>

<details>
  <summary><code>public function webformLoadTemplates(string $title): array</code></summary>

<br/>
Load all webform templates whose title contains the given string
<br/><br/>

</details>

## Drupal\AuthTrait

[Source](src/Helper/Drupal/AuthTrait.php)

> Creates users and roles, logs them in, and removes them afterwards.

<details>
  <summary><code>public function authGetAuthenticator(): AuthenticatorInterface</code></summary>

<br/>
Returns the authenticator that logs a user in and out
<br/><br/>

</details>

<details>
  <summary><code>public function authGetUserRegistry(): UserRegistryInterface</code></summary>

<br/>
Returns the user registry
<br/><br/>

</details>

<details>
  <summary><code>public function authIsLoggedIn(): bool</code></summary>

<br/>
Determines whether a user is logged in for this session
<br/><br/>

</details>

<details>
  <summary><code>public function authLogin(EntityStubInterface $user): void</code></summary>

<br/>
Logs the given user in
<br/><br/>

</details>

<details>
  <summary><code>public function authLogout(bool $fast = FALSE): void</code></summary>

<br/>
Logs the current user out
<br/><br/>

</details>

<details>
  <summary><code>public function authUserCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a user
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates an entity of a type that has no dedicated method
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleLanguageCreate(EntityStubInterface $stub): EntityStubInterface|false</code></summary>

<br/>
Creates a language
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleNodeCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a node
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleParseFields(EntityStubInterface $stub, array $ignored_properties = []): void</code></summary>

<br/>
Expands a stub's raw Gherkin values into the storage field shape
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleRegister(EntityInterface $entity): void</code></summary>

<br/>
Registers an entity saved outside the create pipeline for cleanup
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleTermCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a taxonomy term
<br/><br/>

</details>

## Drupal\EntityLifecycleTrait

[Source](src/Helper/Drupal/EntityLifecycleTrait.php)

> Creates Drupal entities and removes them when the scenario ends.

<details>
  <summary><code>public function entityLifecycleCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates an entity of a type that has no dedicated method
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleLanguageCreate(EntityStubInterface $stub): EntityStubInterface|false</code></summary>

<br/>
Creates a language
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleNodeCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a node
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleParseFields(EntityStubInterface $stub, array $ignored_properties = []): void</code></summary>

<br/>
Expands a stub's raw Gherkin values into the storage field shape
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleRegister(EntityInterface $entity): void</code></summary>

<br/>
Registers an entity saved outside the create pipeline for cleanup
<br/><br/>

</details>

<details>
  <summary><code>public function entityLifecycleTermCreate(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a taxonomy term
<br/><br/>

</details>

## Drupal\FixtureFileTrait

[Source](src/Helper/Drupal/FixtureFileTrait.php)

> Resolves a fixture file path for a file or image field.

<details>
  <summary><code>public function fixtureFileExpandEntityFields(string $entity_type, EntityStubInterface $stub): void</code></summary>

<br/>
Expand fixture file paths for file/image fields on an entity stub
<br/><br/>

</details>

## Drupal\QueryTrait

[Source](src/Helper/Drupal/QueryTrait.php)

> Reads Drupal state a step asserts on without going through a backend.

<details>
  <summary><code>public function queryEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array</code></summary>

<br/>
Load the ids of the entities of a type matching the conditions
<br/><br/>

</details>

<details>
  <summary><code>public function queryNodeIds(string $content_type, array $conditions = []): array</code></summary>

<br/>
Load the ids of the nodes of a content type matching the conditions
<br/><br/>

</details>

## WebRawContext

[Source](src/Behat/Context/WebRawContext.php)

> Root context carrying the plumbing every suite needs.

<details>
  <summary><code>public function __construct(array $config = [])</code></summary>

<br/>
Constructs a WebRawContext object
<br/><br/>

</details>

<details>
  <summary><code>public function backendFor(string $capability): object</code></summary>

<br/>
Returns the highest-priority backend providing the given capability
<br/><br/>

</details>

<details>
  <summary><code>public function browserDriverFor(string $capability): object</code></summary>

<br/>
Returns the adapter providing a browser capability for this session
<br/><br/>

</details>

<details>
  <summary><code>public function browserDriverHas(string $capability): bool</code></summary>

<br/>
Whether this session's browser driver provides a browser capability
<br/><br/>

</details>

<details>
  <summary><code>public function getBackend(string $name): BackendInterface</code></summary>

<br/>
Returns a backend of this scenario by the name its suite gave it
<br/><br/>

</details>

<details>
  <summary><code>public function getBackendRegistry(): BackendRegistryInterface</code></summary>

<br/>
Returns the backend registry
<br/><br/>

</details>

<details>
  <summary><code>public function getBasicAuthenticator(): BasicAuthenticatorInterface</code></summary>

<br/>
Returns the basic authenticator
<br/><br/>

</details>

<details>
  <summary><code>public function getBrowserResolver(): BrowserCapabilityResolver</code></summary>

<br/>
Returns the browser capability resolver, creating it on first use
<br/><br/>

</details>

<details>
  <summary><code>public function getHttpClientFactory(): HttpClientFactoryInterface</code></summary>

<br/>
Returns the factory that builds the detached and bare browsers
<br/><br/>

</details>

<details>
  <summary><code>public function getOption(string $group, string $key): mixed</code></summary>

<br/>
Returns a trait option at whatever type it resolved to
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionArray(string $group, string $key): array</code></summary>

<br/>
Returns a trait option declared as a map
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionBool(string $group, string $key): bool</code></summary>

<br/>
Returns a trait option declared as a boolean
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionFloat(string $group, string $key): float</code></summary>

<br/>
Returns a trait option declared as a float
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionInt(string $group, string $key): int</code></summary>

<br/>
Returns a trait option declared as an integer
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionResolver(): TraitOptionResolverInterface</code></summary>

<br/>
Returns the resolver of the options this context's traits declare
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionResolverFactory(): TraitOptionResolverFactoryInterface</code></summary>

<br/>
Returns the resolver factory, creating a standalone one on first use
<br/><br/>

</details>

<details>
  <summary><code>public function getOptionString(string $group, string $key): string</code></summary>

<br/>
Returns a trait option declared as a string
<br/><br/>

</details>

<details>
  <summary><code>public function getRandom(): Random</code></summary>

<br/>
Returns the backend's random generator
<br/><br/>

</details>

<details>
  <summary><code>public function httpBareClient(array $options = []): AbstractBrowser</code></summary>

<br/>
Returns a one-off browser that carries no scenario state
<br/><br/>

</details>

<details>
  <summary><code>public function httpDetachedClient(array $options = []): AbstractBrowser</code></summary>

<br/>
Returns a one-off browser that acts as the scenario's visitor
<br/><br/>

</details>

<details>
  <summary><code>public function httpPageClient(): AbstractBrowser</code></summary>

<br/>
Returns the browser the Mink session drives
<br/><br/>

</details>


[//]: # (END)
