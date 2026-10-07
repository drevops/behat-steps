# Contributing

Below are some guidelines for developing and maintaining the Behat steps.

New here? [docs/architecture/](docs/architecture/README.md) walks through how the pieces fit together - the trait library, the generated step documentation, and the fixture site the tests run against - with UML component, class and sequence diagrams.

## Steps format

A consistent steps format is essential for the readability and maintainability
of tests. Follow these guidelines:

- **General Guidelines**:
  - Avoid using regular expressions to define a step definition. Use tuple
    format instead for better clarity and maintainability.
  - Use descriptive placeholder names to help users quickly understand the
    expected value: `content_type` instead of `type`.
  - Use `the following` for tabled content.
  - For anything identified by a property, use `with`: <code>Then the link :
    link <b>with</b> the title :title should exist</code>
  - Avoid optional words like `(the|a)`. Provide a single form instead to ensure
    consistency. The `(s)` plural token is the exception: a step whose noun
    agrees with a count or a list keeps it, as in `:count row(s)` and
    `the role(s) :roles`, so both forms read naturally.
  - Omit unnecessary suffixes like `on the page` since it is implied.
  - All method names should begin with the trait name: `userAssertHasRoles()` for `UserTrait`. The prefix is the trait name minus its `Trait` suffix with the first letter lowercased, and the character after it is uppercase: `menuFindByLabel()`, not `findMenuByLabel()`. The prefix is not also the verb: `waitSeconds()`, not `waitWaitForSeconds()`. It applies to every member a trait mixes into the context - steps, helpers, properties and constants - since any of them can collide with another trait's. `tests/phpunit/src/TraitMethodNamingTest.php` enforces it.

- **Placeholders**:
  - One concept gets one name across every trait, so reuse an existing name before inventing a synonym: `:name` for anything identified by its name, `:index` for a 1-based position, `:value` for a value, `:address` for an email address. `ahoy lint-docs` rejects the synonyms listed in `docs.php`'s `placeholder_synonyms()`.
  - A placeholder that names a thing follows its noun: `the queue :queue`, `the module :module`, `the region :region`. Only a bundle before the entity noun it qualifies (`the :media_type media`), a count before its unit (`:count item(s)`) and a closed-set qualifier (`the :enabled_or_disabled state`, `in :direction order`) come first.
  - Every noun takes an article, `URL` is uppercase, and a step never opens with a placeholder: `the :content_type content with the title :title should not exist`.
  - A value reads `the value :value`, never `the :value value` or a bare `:value`.
  - A step that names its target (`:element`, `:path`, `:key`, `:field`) compares against `:value`. `:text` is only for a step asserting on a whole body with no named target, such as `the modal should contain :text`.
  - A partial match reads `a <thing> containing :partial_<thing>`, as in `a cookie with a name containing :partial_name`.
  - Placeholder names are `snake_case`, spelled out rather than abbreviated (`:name`, not `:param`), and identical to the method parameter, because Behat binds a step argument by name.

- **Settled wording**: each idea reads 1 way, even where another phrasing would read just as well.
  - A step that opens a page reads `I visit the ... page` and names the page it opens: `I visit the :content_type content edit page with the title :title`. It never reads `I edit the ...`, because the step only navigates.
  - A click reads `I click on the ...`: `I click on the link :link in the region :region`.
  - The viewport is `the viewport`, never `a viewport`.
  - A `<select>` is `the select :selector`, never `the select element :selector`.
  - An assertion negates with `should not`, never with the determiner `no` or the perfect tense: `an email should not be sent`, not `no emails should have been sent`.
  - `ahoy lint-docs` rejects the replaced phrases, listed in `docs.php`'s `rejected_step_phrases()`, an `I visit` step that names no page or link, and a `Then` step that opens with `no`.

- **`Given`**:
  - Defines test prerequisites—conditions or data that must exist before the
    test runs.
  - Use words like `exists` or `have`.
  - Avoid using `should` or `should not` (these are reserved for assertions).
  - Never refer to the person: no `I`, `my`, `me`, `we`, `us` or `our` anywhere in the step. A precondition is a fact about the world, not something the person does.

- **`When`**:
  - Describes an action and must contain an action verb.
  - Use the format `When I <verb>`. The step must start with `I` followed by a space - the first person is what separates an action from a precondition.

- **`Then`**:
  - Specifies assertions and expectations.
  - Use `should` and `should not` to clearly indicate assertions.
  - Start the step with the entity being asserted, e.g.,
    `Then the link with a title :title exists`.
  - Never refer to the person: no `I`, `my`, `me`, `we`, `us` or `our` anywhere in the step. Start with the entity being asserted.
  - A qualifier that narrows the entity, such as `in the region :region`, `within the select :selector` or `on the element :element`, reads with it, before `should`: `the link :link in the region :region should exist`, not `the link :link should exist in the region :region`. A qualifier of the predicate's own object stays with the object, as in `the response should match the XSD schema in the file :filename`, and a step that takes a table or a PyString still names it last: `the meta tag should exist with the following attributes:`. `ahoy lint-docs` rejects a qualifier that ends the step after `exist` or `be <state>`.
  - Methods should include the `Assert` prefix, e.g., `userAssertHasRoles()`.

We have some automated check for the steps format.
Run `ahoy lint-docs` to validate the format of the steps.

## Step arguments

Behat binds every placeholder as a string, and a Turnip pattern can't make a placeholder optional. A step method declares exactly that: a required `string` for each placeholder, named as the placeholder is, plus a trailing `TableNode` or `PyStringNode` for a step that ends with a colon. Nothing is nullable, nothing has a default, and nothing else is accepted. `tests/phpunit/src/StepArgumentTest.php` enforces it.

### Numbers

A step that needs a number parses its placeholder with `Helper\Web\StringTrait` before it uses the value:

```php
#[Then('the table :selector should have :count row(s)')]
public function tableAssertRowCount(string $selector, string $count): void {
  $count = $this->stringParseInteger($count, 'count', 0);

  // ...
}
```

`stringParseInteger()` accepts what `FILTER_VALIDATE_INT` accepts, and `stringParseNumber()` accepts what `FILTER_VALIDATE_FLOAT` accepts. Both throw `\RuntimeException` naming the argument, which is the exception an invalid step argument throws everywhere in this package: `The count must be an integer, but "abc" was given.` Pass the smallest value the step accepts as `$min`, and a value below it fails the same way: `The count must be 0 or greater, but "-1" was given.` Leave `$min` out when the helper the value goes to checks the range itself, as `elementGetNth()` does for an index, so each range rule lives in 1 place.

A native `int` parameter would hand the check to PHP. Behat calls a step without strict types, so PHP reads `1e3` as 1000 and `3.5` as 3 with a deprecation notice, and `abc` fails with a `TypeError` that Behat reports as `Type error: ...` rather than the `\RuntimeException` a consumer catches.

### Optional arguments

A step has none. A variation of a step is a step of its own. When 2 steps differ only by a qualifier one of them leaves out, both call a shared helper rather than one calling the other with `NULL`: `linkAssertExistsWithHref()` and `linkAssertExistsWithHrefWithinElement()` both call `linkAssertHrefMatches()`. A flag that only PHP code passes belongs on a helper too.

When leaving out a helper's string argument means "not given", the parameter defaults to `NULL`, with a nullable type, and `NULL` is the one value that means it: `contentVisitActionPageWithTitle()` opens the content page for a `NULL` subpath, and `configGet()` returns the whole configuration object for a `NULL` key. A parameter never defaults to an empty string, so `''` stays a value a caller passes on purpose: `cookieAssertExists()` skips the value check for `NULL` and asserts an empty value for `''`. A default that is a value in its own right stays, as `DrushBackend`'s `$binary = 'drush'` does. `tests/phpunit/src/OptionalParameterTest.php` rejects an empty-string default on every class, interface and trait under `src/`.

## Failure messages

A project's own tests assert on failure messages, so each one reads 1 way:

- It names what it reports about the way its step does, with the article: `the element :selector` fails with `The element "..." is not displayed within the viewport.`, and `the config :name with the key :key` with `The config "..." with the key "..." ...`. A message reporting that nothing in a set matched keeps its quantifier, as `No hreflang alternate links were found on the page.` does.
- It quotes every value it names in double quotes, the page URL included, and puts the noun before the value: `the attribute "..."`, not `the "..." attribute`.
- It ends with a period, and closes a broken expectation with `, but it should not` or `, but it should be`.

Which exception carries the message depends on what failed, as the [exception table](README.md#exceptions) lists. A missing element on the page or in an XML response is an `ElementNotFoundException`. An attribute, a JSON path or a table column isn't an element, so a missing one is an `ExpectationException`.

## Method naming conventions

Every method a trait contributes begins with the trait's own name, so that traits mixed into one context cannot collide. `tests/phpunit/src/TraitMethodNamingTest.php` enforces this, along with the assertion, negation, action, helper verb, hook, lookup, word order, batch and spelling conventions below.

`TraitMethodNamingTest`, `PublicSurfaceTest` and `MemberOrderTest` pick their subjects the same way: every trait under `src/Steps` and `src/Helper`, which are the traits this package names itself and flattens into a context. A helper trait is held to its own full name, so `Helper\Drupal\EntityLifecycleTrait` carries `entityLifecycleCreateNode()` and leaves the `entity` prefix to `Steps\Drupal\EntityTrait`. The traits under `src/Behat` are out of scope - their names are the ones Behat's and Mink's interfaces dictate - and `src/Backend` is composed into nothing.

### Assertions

An assertion method reads `<trait>Assert<Subject><Predicate>`, with `Assert` directly after the prefix and nowhere else. Every `Then` step is an assertion, and so is a helper that only checks something and fails with an assertion exception, so it's `cookieAssertExists()`, not `cookieExists()`, and `xmlAssertResponseMatchesXsd()`, not `xmlValidateXsd()`. A helper that returns what it found or produced is named for that instead, even when a miss fails the step: `elementGetNth()` returns an element, `jsonGetValue()` the value at a JSON path and `jsonDecodeLoose()` the decoded value. `Assert` always says what it asserts: `messageAssertExistsOfType()`, not a bare `messageAssert()`.

- **Existence**: never `Present`, `Absent` or `Missing`.
  - Singular subjects → `Exists` or `NotExists` (e.g., `fieldAssertExists()`, `taxonomyAssertVocabularyNotExists()`)
  - Plural subjects → `Exist` or `NotExist` (e.g., `redirectAssertExist()`)
- **Containment**: always `Contains` or `NotContains`, never `Includes` (e.g., `xmlAssertElementContains()`, `responseAssertHeaderNotContains()`)
- **Subject first**: the thing being asserted about precedes what is asserted of it, as in `responseAssertHeaderExists()` rather than `responseAssertContainsHeader()`. The subject is what the step asserts about, so `the row containing :partial_text should contain the value :value` is `tableAssertRowContains()`, not `tableAssertTextInRow()`.
- **Qualifiers last**: a qualifier that narrows the subject, opened by a word such as `With`, `By`, `In`, `Within`, `Of` or `Containing`, follows the predicate, as in `mediaAssertExistsWithName()` and `blockAssertNotExistsInRegion()`. That keeps the slot after the subject free for `Not`: `cookieAssertNotExistsWithName()`, not `cookieAssertWithNameNotExists()`.
- **`Has` names something the subject holds**, as in `userAssertHasRoles()` and `elementAssertHasKeyboardFocus()`. It never stands in for another predicate: a value compared against reads `Equals` or `Contains` (`stateAssertValueEquals()`, not `stateAssertHasValue()`), and entries that must be absent read `NotExist` (`watchdogAssertErrorsNotExist()`).
- **No copula**: `Assert` already states that the subject is something, so `Is` is dropped - `elementAssertVisible()`, not `elementAssertIsVisible()`.

A check that throws `\RuntimeException` on a bad step argument or a missing precondition isn't an assertion, so it isn't named `Assert`. It takes the verb for what it does instead: `stringParseInteger()` turns a step argument into an integer, and `commandRequireRun()` fails when no command has run yet.

`TraitMethodNamingTest` reads every name for this shape: `Assert` right after the prefix with something after it, no qualifier ahead of `Not` or `Exists`, no `Includes` or `Present`, no `Has` before a compared value, and an assertion exception from every `Assert` method that throws one directly. A `Has` standing in for existence looks just like one naming something held, so review holds that half of the `Has` rule.

### Negation

`Not` is the only negation particle, and it sits immediately after `Assert<Subject>`, directly before the predicate it negates. A negative name is its positive counterpart with `Not` inserted and nothing else changed.

| Instead of | Write |
| --- | --- |
| `userAssertHasNoRoles()` | `userAssertNotHasRoles()` |
| `emailAssertNoMessagesSent()` | `emailAssertMessageNotSent()` |
| `userAssertIsNotBlocked()` | `userAssertNotBlocked()` |
| `elementAssertIsVisuallyHidden()` | `elementAssertNotVisuallyVisible()` |
| `metatagAssertWithAttributesNotExists()` | `metatagAssertNotExistsWithAttributes()` |

The determiner `No`, the copula `Is`, an antonym standing in for a negation, and `DoesNot` or `DoNot` are all out. A method follows its step, so a step reading `should not` takes `Not`, while a step that names a state of its own takes that state's word: `the module :module should be disabled` is `moduleAssertDisabled()` and `the command should fail` is `commandAssertFailure()`, because the step asserts that state rather than negating another. `TraitMethodNamingTest` fails a `should not` step whose method carries no `Not`, and pairs every `should not` step with its `should` twin in the same trait, failing a pair whose method names differ by anything but `Not`.

### Actions

A step that opens a page only navigates, so its method opens with `Visit` and names the page the way the step does: `I visit the :media_type media edit page with the name :name` is `mediaVisitEditPageWithName()`, and `I visit the profile delete page of the user :name` is `userVisitProfileDeletePage()`. A noun the prefix already carries isn't repeated, so a node's page is `contentVisitPageWithTitle()` while a term's is `taxonomyVisitTermPageWithName()`.

A qualifier that the step opens with `with` reads `With` in the name, and a second one repeats it, in the order the step gives them: `I follow the link with the index :index in the email with a subject containing :partial_subject` is `emailFollowLinkWithIndexWithSubjectContaining()`.

`TraitMethodNamingTest` fails an `I visit` step whose method doesn't open with `Visit`, or doesn't carry the `Page` or `Link` its step names ahead of the first qualifier.

### Consumer override points

A documented override point that supplies a value is `<trait>Get<Noun>()`, booleans included - `modalGetWaitTimeout()`, `commandGetTimeout()`, `accessibilityGetFailOnIncomplete()`, `diagnosticsGetShowUrl()`. A method that computes rather than supplies keeps a verb describing what it does, as in `accessibilityResolveTags()` or `restResolveUrl()`.

### Helpers carry a verb

Every published helper names what it does with a verb: `messageGetSelector()`, not `messageSelector()`. A yes-or-no question takes `Is` or `Has`, as in `authIsLoggedIn()` and `metatagResponseHasNoindexHeader()`. A verb in the trait prefix counts, as `query` does in `queryEntityIds()`.

`TraitMethodNamingTest` reads the words of every public helper against its `VERBS` list, so a helper built on a verb the toolbox hasn't used yet adds that verb to the list in the same change. Steps take their verb from the step text and hooks are named for their event, so the check skips both.

### Hooks

A hook is named `<trait><Event>`, for the event it runs on rather than what it does: `timeAfterScenario()`, `watchdogBeforeScenario()`, `contentBeforeNodeCreate()`. 2 methods can't share a name, so a trait registers 1 hook per event. When a trait has 2 jobs on 1 event, its hook calls a protected helper for each, in the order they need to run: `authAfterScenario()` runs `authCleanUsers()` and then `authCleanRoles()`.

Behat runs every hook on an event even when an earlier one fails, and reports each failure, but 2 calls inside 1 hook get neither for free. A hook running 2 teardowns still runs the second when the first throws, then rethrows the first failure. When both throw, it throws 1 `\RuntimeException` that names both and keeps the first as its previous exception, as `authAfterScenario()` does.

`TraitMethodNamingTest` fails a hook named anything but `<trait><Event>`.

### Lookups

A lookup's verb says what it does when nothing matches, so a caller knows whether to check for `NULL` or catch an exception without opening the docblock.

- **`Find`** returns `NULL` when nothing matches, and its return type is nullable: `tableFindRowByText()`, `blockFindByLabel()`, `cookieFindByName()`.
- **`Get`** throws when nothing matches and never returns `NULL`, so its return type excludes `NULL`: `tableGetRowByText()`, `blockGetByLabel()`, `regionGet()`. A consumer override point is a `Get` for the same reason - it always supplies a value.
- **`Load`** loads a set and returns an empty array when nothing matches, as `userLoadMultiple()` does, or loads a document into the trait's own state, as `xmlLoadDocument()` does. A lookup for 1 item is a `Find` or a `Get`, never a `Load`.

A trait that needs both contracts for one lookup declares the pair, and the `Get` calls the `Find`: `tableGetRowByText()` throws where `tableFindRowByText()` returns `NULL`. `Resolve` isn't a lookup verb. It derives a value from its input, as `restResolveUrl()` turns a relative URL into an absolute one.

A set of entities comes back loaded, never as bare IDs: every `<trait>LoadMultiple()` returns the loaded entities keyed by entity ID, so reading 1 of them tells you what the rest return. When you only need the IDs, call `queryEntityIds()`, the query each `LoadMultiple()` that takes conditions runs before it loads. `webformLoadMultiple()` takes a partial title instead.

`TraitMethodNamingTest` reads each declared return type: a `Find` must allow `NULL`, a `Get` must exclude it, and a `Load` must return `array` or `void`. A `Get` declaring `mixed`, such as `restGetClient()`, leaves the test no type to read, so review holds it to the same rule. The native type can't say what an array holds, so each `LoadMultiple()` has a kernel test under `tests/phpunit/src/Kernel/Steps/Drupal/` that pins its entities and keys instead.

### The verb comes first

A method that creates, deletes or changes an entity names the verb before the entity. In a trait the verb follows the prefix: `entityLifecycleCreateNode()`, `authCreateUser()` and `menuCreateLinkMultiple()`, never `entityLifecycleNodeCreate()`. A capability interface carries no prefix, so the verb opens the name: `createNode()`, `deleteTerm()`, `placeBlock()` and `addUserRole()`. A backend of your own implements those names as they stand.

An entity-create hook is named for its event, so `BeforeNodeCreate` and `contentBeforeNodeCreate()` keep the event's word order.

`TraitMethodNamingTest` holds `Create`, `Delete` and `Load` directly after a trait's prefix. It skips hooks and the `Visit` methods, whose object is a page, as in `contentVisitDeletePageWithTitle()`. `CapabilityMethodNamingTest` fails a capability method that carries `Create` anywhere but at the start, and a `create<Noun>()` with no `delete<Noun>()` on the same interface.

### Several entities at once end in `Multiple`

A method that creates, deletes or loads several entities at once ends in `Multiple`, the way Drupal's own `loadMultiple()` does: `contentCreateMultiple()` for `the following :content_type content exist:`, `taxonomyDeleteMultiple()` for `the following :vocabulary terms do not exist:`, and `userLoadMultiple()` for a set. A `With` qualifier still comes last, so the vertical-table step is `contentCreateMultipleWithFields()`.

The method for 1 entity is the same name without the suffix, so nothing carries `Single`: `mediaCreate()` saves 1 media item, and `mediaCreateMultiple()` saves the rows of a table. The noun stays singular, so it's `userCreateRoleMultiple()`, not `userCreateRoles()`.

`TraitMethodNamingTest` fails a `Create`, `Delete` or `Load` method that carries `Single`, a create or delete step over a `the following` table without `Multiple`, and a `Load` returning an array without it. A plural noun looks like any other word to the test, so review holds that half.

### Spelling

`Normalize`, not `Normalise`, in method names and in prose.

`Login` and `Logout`, not `LogIn` and `LogOut`, in method, interface and configuration key names alike: `authLogin()`, `FastLogoutInterface`, `login_url`. Step text and prose keep the verb, so a step reads `When I log in as the user :name`. `TraitMethodNamingTest` checks the trait methods, and review holds interfaces and configuration keys to the same spelling.

## Class naming conventions

A class name states the role the class plays, so a reader can tell a lookup table apart from a service that acts without opening the file. Two shapes cover everything under `src/Behat`:

- **`<Noun>Registry`** holds things and looks them up. `BackendRegistry` registers backends and resolves one by capability; `UserRegistry` stores the users a scenario created and tracks the current one.
- **An agent noun** performs an action. `Authenticator` logs a user in and out; `BasicAuthenticator` applies HTTP Basic credentials to a session.

`Manager` is not a role, so it names nothing. Do not reach for it, or for `Handler`, `Helper` or `Service` as a class suffix - each would describe every class in the package equally well.

A namespace follows the same rule. It's named for the role its classes share, as `Registry` and `Listener` are, or for the concern they serve, as `Auth` and `Config` are, so the registries live in `Behat\Registry` and the authenticators in `Behat\Auth`. `ClassNamingTest` fails a class or a namespace under `src/Behat` whose name ends in `Manager`, `Handler`, `Helper` or `Service`.

An accessor is named for what it returns, after its trait prefix where one applies: `getBackendRegistry()`, `authGetUserRegistry()`. A name and its return type cannot disagree, so renaming a class renames its accessors with it.

### A capability wrapper is not a class

Do not write a class whose only job is to forward to a capability interface. The capability interface already is the abstraction, and a trait reaches it through `backendFor(SomeCapabilityInterface::class)` on `WebRawContext`. A wrapper adds a second name for the same contract, a second place to keep in step, and nothing else - which is why the one that existed was never wired into the container.

A class earns its place when it holds state across calls, composes more than one collaborator, or decides something the capability cannot. Forwarding 4 methods and renaming them on the way through is none of those.

## Final classes

A concrete class under `src/` is `final` unless a project is meant to extend it. Sealing a class that has shipped breaks every project extending it, while opening one breaks nothing, so a new class starts `final` and is opened once a project has a reason to extend it.

These are the extension points. Each one stays open, along with every class that extends or implements it:

- **The contexts**: `WebRawContext`, `WebContext` and `DrupalContext`, one of which a project's own context extends.
- **The backends**: a project backend may extend `DrupalBackend`, `DrushBackend` or `BlackboxBackend` rather than implement `BackendInterface` from scratch.
- **`Core`**: a version-specific core extends it and registers its own field handlers and creation aliases.
- **The field handlers**: a handler for another field type extends a shipped one, the way `OgStandardReferenceHandler` extends `EntityReferenceHandler`.
- **The browser adapters**: an adapter for a browser driver derived from a shipped one extends that driver's adapter.
- **`HttpClientFactory`**: a package replaces the `behat_steps.http_client_factory` service with a subclass, as [docs/http-clients.md](docs/http-clients.md) describes.

`DocumentElement` stays open as well. It's installed in place of Mink's own `DocumentElement`, which other code extends. A class that another class under `src/` extends, such as `ParseException`, is open because PHP requires it.

Every other class is replaced rather than extended. A suite that swaps in its own registry or authenticator through a `*.class` container parameter implements that class's interface, and option resolution is replaced through `TraitOptionResolverFactoryInterface`. A final class keeps its members `protected`, like every other class here, so `rector.php` skips the Rector rules that would make them private.

`tests/phpunit/src/ExtensionPointTest.php` fails a class that's neither final nor an extension point, and an extension point that's final. Opening a class adds it, or the type it extends, to `EXTENSION_POINTS` there, with the reason a project extends it.

## Backend, browser driver and HTTP client

3 things sit close together in this codebase, and each has 1 name. Use it in identifiers, docblocks and prose alike.

| Term | What it is | Where it shows up |
| --- | --- | --- |
| **Backend** | What a step resolves a capability from: Drupal in-process, Drush or Blackbox | `src/Backend`, `BackendInterface`, the `backends` list and the `@backend:` tag, `backendFor()` and `getBackend()` on `WebRawContext`, `BackendRegistry` |
| **Browser driver** | Mink's driver behind the session: BrowserKit, Selenium2 or Chrome | `browserDriverFor()` and `browserDriverHas()` on `WebRawContext`, the adapters and capabilities under `src/Behat/Mink` |
| **HTTP client** | What a step sends its own request through: the page, detached or bare client | `httpPageClient()`, `httpDetachedClient()` and `httpBareClient()` on `WebRawContext`, `HttpClientFactory` |

Mink owns the word "driver" across the Behat ecosystem, so no name this package owns uses it for anything else. `Backend` in one of our identifiers means a backend, and `Driver` or `BrowserDriver` means Mink's. In prose, Mink's is a "browser driver", never a "Mink driver" or "the session's driver", and that holds inside `src/Behat/Mink` too. An HTTP client is neither. It's a BrowserKit `AbstractBrowser`, so a docblock may call the object a browser.

Each side has a counterpart on the other, and the names keep the 2 apart:

| Backend | Browser driver |
| --- | --- |
| `DrevOps\BehatSteps\Backend\BackendInterface` | `Behat\Mink\Driver\DriverInterface` |
| `DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException`, thrown by `backendFor()` | `Behat\Mink\Exception\UnsupportedDriverActionException`, thrown by `browserDriverFor()` |
| `$this->getBackend($name)`, a backend by the name its suite gave it | `$this->getSession()->getDriver()`, the browser driver |

A file that needs both of a pair imports each under its own name, with no alias.

## The helper API

The package is 2 products in 1: the vocabulary (the steps) and the toolbox (the helpers the steps are built on). A project that outgrows the raw vocabulary stops calling the toolbox from Gherkin and starts calling it from PHP, so the helpers are public API in the same sense the step text is. [docs/scenario-styles.md](docs/scenario-styles.md) argues why.

**Visibility is the marker.** A `public` method is the toolbox; a `protected` one is an implementation detail that promises nothing and may change in any release. Nothing else distinguishes the two, which is why a helper worth calling from a project's own step definitions is declared `public` and one that only serves the machinery stays `protected`.

A member is published in [HELPERS.md](HELPERS.md) when all of the following hold. Everything published is covered by semantic versioning.

- It is declared by a trait under `src/Steps` or `src/Helper`, or by a class in `docs.php`'s `TOOLBOX_CLASSES`.
- It is `public`. A `private` member cannot be reached from a composing context and has no place in a trait.
- It begins with its trait's name, which is the collision rule every trait member follows anyway.
- It carries no `#[Given]`, `#[When]`, `#[Then]`, `#[Transform]` or hook attribute. Those are registered with Behat and belong to the vocabulary.
- Its docblock carries no `@internal`.

Three obligations follow from publishing a member:

- **It needs a summary.** `ahoy lint-docs` fails on a published helper with no docblock description, because the reference would carry a blank entry, and `PublicSurfaceTest` fails on the same thing from the other side. A `{@inheritdoc}` docblock is resolved to the interface or parent that declares the method, so implementing an interface is enough.
- **It is named as carefully as a step.** The naming conventions above apply to a helper exactly as they do to a step method.
- **It shows up in review.** [HELPERS.md](HELPERS.md) is committed and gated by `--fail-on-change`, so promoting a method to `public` lands in the diff with its signature and summary. That diff is what keeps the surface deliberate: a method cannot be narrowed again before the next major.

Withdraw a member that exists only to serve the machinery with `@internal`, naming who calls it:

```php
/**
 * Sets the backend registry.
 *
 * @internal
 *   Injection point called by the context initializer.
 */
```

### Thin step bodies

A step body is a thin wrapper over named helpers, so every behavior a scenario can reach is also reachable from a project's own step definitions. A step parses its arguments, calls helpers and guards on what they return. Everything else belongs in a helper.

[tests/phpunit/src/StepBodyTest.php](tests/phpunit/src/StepBodyTest.php) reads every step under `src/Steps` and holds it to that shape:

- **No step calls another step of its trait.** When 2 steps share logic, it moves into a helper both call, typed to what each caller has, so nothing builds a `TableNode` or passes `'1'` just to fit another step's signature. `blockCreateInstance()` hands the block it just created to `blockApplyConfiguration()` instead of making `blockConfigure()` look it up again by label.
- **No `switch`, `match`, `try`, closure or arrow function.** Each one is logic of its own.
- **A loop only dispatches.** Its body holds guards and then at most 1 statement, as in `foreach ($table->getHash() as $row) { $this->taxonomyCreate($vocabulary, $row); }`.
- **At most 4 statements, guards aside.** A guard is an `if` with no `else` whose body only throws. A step that needs a fifth statement is usually doing a helper's job.

## Member ordering within a trait

Traits lay their members out in this order:

1. Trait composition (`use`), then constants, then properties.
2. Hooks (`#[BeforeScenario]`, `#[AfterStep]` and the like).
3. `Given` steps, then `When` steps, then `Then` steps.
4. Helpers, public and protected together. Visibility marks what is published, not where a member sits, so a helper stays next to the ones it reads with.

Within each of those groups, keep the members in whatever order reads best - the rule settles the groups, not what happens inside one. `tests/phpunit/src/MemberOrderTest.php` enforces it.

Reordering an existing trait into this layout leaves [STEPS.md](STEPS.md) untouched. `docs.php` already groups steps by `Given`, `When` and `Then` and keeps source order inside each group, so this layout only applies a sort the generated documentation applies anyway.

## Unsettled style questions

These 5 style questions have no dominant form in this codebase. Every form listed is correct and behavior-identical where it appears, and converging any of them would churn 25 to 400 sites for no functional gain. Match the surrounding file and do not convert existing code from one form to the other as a drive-by change.

- **Nullable-object absence**: `if (!$element)` (~40 sites) and `=== NULL` (~35 sites) are both accepted. `is_null()` is not - it has been converged away.
- **Array emptiness**: `empty($array)` (~30 sites) and `$array === []` (~25 sites) are both accepted. Newer code leans strict, which is a weak preference rather than a rule.
- **Docblock tag order and `@code` indentation**: `@param` before `@code` and the reverse both appear, as do flush and indented example bodies. `docs.php` renders `@code` bodies into [STEPS.md](STEPS.md), so changing indentation reflows the generated documentation.
- **Test method names**: `test<Scenario>` (~410 methods, as in `testAnUnsetParameterIsNull()`), `test<Method><Scenario>` (~290, as in `testApplyAfterCreateIgnoresNonArrayValues()`) and `test<Method>` (~85, as in `testNormalize()`) are all accepted. A third of the test classes mix shapes, so name a new test like the existing tests of the same method, or like the rest of its class when there are none.
- **Data provider form**: a generator declared as `\Iterator` (~80 providers) and a plain array declared as `array` (~65) are both accepted. `iterable` is not - it has been converged away, so the return type always tells the 2 apart.

## Settled style questions

Each of these has 1 answer. Write new code this way, and where a tool holds the form, its name is given.

- **Line breaks**: `"\n"` reads and `PHP_EOL` writes. Code that splits, matches, trims or replaces line breaks in text it received - a docblock, a file, command output, page text or a PyString - uses `"\n"`, because splitting on it handles both `\n` and `\r\n` text, while `PHP_EOL` on Windows misses every `\n`-only line. Console output, exception and assertion messages, and generated documentation and reports end their lines with `PHP_EOL`, and a string built with `PHP_EOL` is split with `PHP_EOL` too. A line break spliced into a nowdoc or heredoc template, or into text built only to be searched or parsed in place, stays `"\n"` so the result never mixes line endings.
- **Paths**: a path is built with `/`, which PHP accepts on every platform: `$directory . '/' . $file`, `__DIR__ . '/fixtures'`. `DIRECTORY_SEPARATOR` only compares against, splits or trims a path the platform returned - from `realpath()`, `getcwd()`, `getFileName()` or a directory iterator - because on Windows that path holds backslashes.
- **Closures**: a closure or arrow function that never touches `$this` is `static`. `SlevomatCodingStandard.Functions.StaticClosure` fails the rest in `ahoy lint`, and `ahoy lint-fix` adds the keyword. The sniff can't see a closure that calls an instance method through `self::` or `static::`, which still needs `$this`, so check those by hand.
- **Empty bodies**: an empty class, interface, trait, enum, function or closure body is `{}` on its declaration line, a promoted constructor's included: `) {}`. `tests/phpunit/src/EmptyBodyTest.php` enforces it.
- **Constant types**: every constant under `src/` declares its native type, in a class, an interface or a trait alike: `public const string CONFIG_KEY = 'behat_steps';`. A subclass that redeclares one declares the same type, or PHP refuses to load it. `tests/phpunit/src/TypedConstantTest.php` enforces it.
- **Booleans**: a single-word boolean local or parameter reads as a question with `is_`: `$is_found`, `$is_exact`, `$is_strict`. `has_` names something held, as in `$has_title`, and `should_` an expectation, as in `$should_match`. A test's expected value stays `$expected` whatever its type.
- **Local names**: a caught exception is `$exception`. A directory path is `$directory`, and a qualified one keeps the short suffix, as in `$features_dir`, matching the `report_dir` and `temp_dir` options. The stub a create call returns is `$created`, a value saved to be restored is `$original` or `$original_<what>`, a `\ReflectionProperty` is `$property` or `$<name>_property`, never `_prop`, and a test's expected exception message is `$expected_message`.
- **Dynamic calls**: a method name held in a variable is braced, as in `$this->{$method}()`.
- **Declaring methods**: a reader names the method it reads with `methodFor()` and a `METHOD_SUFFIX` constant, so `ConfigSchemaReader::methodFor()` gives `<prefix>ConfigSchema()` and `PrerequisiteReader::methodFor()` gives `<prefix>Prerequisites()`.
- **No `Helper` method**: a method is named for what it does, as `fieldFillDatetimeInput()` fills 1 input, so `Helper` names nothing on a method, just as it names nothing on a class.
- **Calls into a trait**: an instance method is called through `$this->`. A static method a trait declares is called through `static::`, because `self::` binds at compile time to the class the trait was flattened into: a shipped context composes the trait and a project subclasses that context, so `self::` would reach past the project's override. `DateTrait::dateGetNow()` is the documented example.
- **Data providers**: a provider is named `dataProvider` followed by its test's name without the `test` prefix, and it's declared after that test, so each provider serves exactly 1 test and renaming a test renames its provider. `tests/phpunit/src/DataProviderConventionTest.php` enforces both, along with the return types above.

## Test suite conventions

The PHPUnit suite under `tests/phpunit/src/` holds each of these in 1 form. [tests/phpunit/src/TestConventionTest.php](tests/phpunit/src/TestConventionTest.php) enforces all 7.

- **Base class**: a unit test extends `UnitTestCase`, directly or through a base that does, and a kernel test extends Drupal's `KernelTestBase`. `UnitTestCase` gives each test a workspace that `tearDown()` removes, along with the hook scope builders and the helpers below, so call `parent::setUp()` and `parent::tearDown()` when you override them.
- **Fixtures**: a unit test writes a fixture through `writeFixture()`, which creates the parent directories inside that workspace, and reads the workspace path from `static::$tmp`. Nothing calls `file_put_contents()`, `touch()` or `copy()` itself, so no fixture outlives its test or lands in the repository. An empty directory is the one thing `writeFixture()` can't make, and `mkdir()` stays for it.
- **Reflection**: a class held in a variable, whether a name a test discovered or an object, is reflected through `static::reflect()`, which narrows a string to a class string. A `::class` constant goes to `new \ReflectionClass()`, which keeps the class type PHPStan reads.
- **Static members**: a test reaches a static member through `static::`, so a subclass that redeclares it is honored, the same way a trait does. `self::` stays where late static binding has nothing to resolve: in a constant expression such as a property default or a constant value, where PHP rejects `static::`; on a final class, a final member or a private member, where Rector's `ConvertStaticToSelfRector` requires it; and in an anonymous class, which nothing can extend. `self::fail()` is the common case, because PHPUnit declares its assertions final.
- **Helper names**: a test helper names what it does with a verb, like a published helper: `createUserStub()`, `collectRegisteredSteps()`, `readSignificantTokens()`. A yes-or-no question opens with `is` or `has`, as in `hasCallToAny()`. `TestConventionTest::HELPER_VERBS` lists the verbs in use, so a helper built on a new verb adds it there in the same change.
- **Test doubles**: a double reaches into the code under test through 2 prefixes that production code never declares, so a test-only method can't override a production one by accident. `call<Method>()` runs a protected method and is named after it without its trait prefix, as `callSlug()` runs `stringSlug()`. `test<Accessor>()` reads or writes protected state, as `testGetExitCode()` and `testSetRoles()` do. Every other public method on a double overrides or implements a production method and keeps its name, as `httpDetachedClient()` does.
- **Test double names**: a context composing the trait under test is named `<Trait>TestImplementation`, with any qualifier for a variant before the suffix, as in `AccessibilityTraitRetryTestImplementation`. Any other double is `<Qualifier><Role>`, the qualifier saying what sets it apart: `RecordingDrushBackend`, `ParserExposingDrushBackend`, `InjectedPermissionsCore`. `Test` and `Testable` don't say what sets a double apart, so neither qualifies a name.

## Behat harness conventions

The Behat harness in `tests/behat/bootstrap/` - `FeatureContext`, `FeatureContextTrait` and `BehatCliTrait` - holds these conventions from `src/`:

- **Tags**: a tag is read through `Tag::has()` or `Tag::values()`, passing the scope and a constant that names the tag, and no hook attribute filters on a tag. A feature hook has no scenario, so it passes the feature.
- **Numbers**: a numeric step argument is parsed with `stringParseInteger()`, so `sleep for five seconds` fails instead of sleeping for 0 seconds.
- **Hooks**: a hook is named `<prefix><Event>` and declared before the steps.
- **Constants**: every constant declares a native type.

The harness steps keep their own wording, placeholders, patterns and signatures rather than follow [Steps format](#steps-format) and [Step arguments](#step-arguments). They aren't published, and renaming one means editing every `.feature` line that uses it: every `@test-trait` scenario opens with `Given some behat configuration` and `And scenario steps:`. `BehatCliContext.php` holds none of these conventions, because it's kept in step with an upstream copy.

`TraitMethodNamingTest`, `MemberOrderTest`, `PublicSurfaceTest` and `TagReadTest` read only `src/`, since each of them also holds a vocabulary rule the harness doesn't follow. The list above is held in review rather than by a test.

## Layers

The package ships 3 layers, and the dependency only runs one way: `Steps` on `Behat` on `Backend`.

- **`src/Backend`** is the part that talks to Drupal: it bootstraps a site in-process or shells out to Drush, creates entities, and expands field values into their storage shape. It knows nothing about Behat or Mink, which is what keeps it usable outside a Behat run.
- **`src/Behat`** is the integration: `ServiceContainer/BehatStepsExtension` reads the `behat_steps` configuration and builds the container, `Registry/` holds the backend, user and scenario tag registries, `Auth/` holds the authenticator and the basic authenticator, `Context/` holds the 3 context classes, `Mink/` holds the browser capabilities, their adapters and the `browserkit_http` browser driver factory, `Http/` holds the factory behind the detached and bare HTTP clients, `Prerequisite/` holds the prerequisite declarations and their reader, and `Hook/`, `Listener/`, `Selector/` and `Generator/` carry the entity-creation hooks, the per-scenario backend selection and skip-tag check, the `region` Mink selector and the starter-class generator.
- **`src/Helper`** holds the step-free traits a step trait and a context both compose, split into `Web/` (last-step tracking, the request header bag, string shaping, table transposition) and `Drupal/` (the entity lifecycle, authentication, static caches, fixture files, direct queries). They register no Gherkin, so composing one twice shares its state instead of registering a step twice, and every member carries its trait's prefix so a name cannot collide once flattened.
- **`src/Steps`** is the step vocabulary - traits a context mixes in. `Web/` holds the ones that drive a page, `Drupal/` the ones that need a Drupal site, and the directory a trait sits in is the context [STEPS.md](STEPS.md) groups it under.

`Context/` is one chain. `WebRawContext` carries the plumbing, composes 3 of the web helper traits (`LastStepTrait`, `RequestHeadersTrait` and `StringTrait`) and registers no steps; `WebContext` extends it and composes every trait under `Steps/Web`; `DrupalContext` extends that and composes every trait under `Steps/Drupal`. `ContextCompositionTest` holds that directory-to-context coverage in both directions, and holds the chain to one composition of each trait, because a subclass re-composing a parent's trait registers its steps twice.

A trait names the host it needs with `@phpstan-require-extends`, and composes the helper traits its own body calls. `ContextCompositionTest` walks every step trait and fails one that reaches a helper member it does not compose, so the plumbing a trait needs travels with it rather than being assumed of the host.

That is what keeps `src/Helper` a library rather than a catch-all: teardown lives with the concern that creates the thing being torn down. A trait that creates entities composes `EntityLifecycleTrait` and so brings the `AfterScenario` pass that removes them; a context composing no such trait runs no entity teardown at all. Trait flattening is idempotent, so the fourteen traits that compose it still yield one registry, one hook and one deletion pass in reverse creation order.

A trait's directory is its classification, so nothing has to be declared twice: `src/Steps` registers Gherkin and `src/Helper` registers none. [scripts/lint-traits.php](scripts/lint-traits.php) fails a step trait composing another step trait, and a helper trait carrying a step or transform attribute. A helper may carry a hook: the trait that owns a teardown carries the hook that runs it. Shared logic goes in a helper trait under `src/Helper` named for its concern, composed by whoever needs it.

## What a trait needs from the backend

A step is only as portable as the backend behind it, so each trait falls into one of four bands. Which band a trait is in decides which capability its steps resolve, and therefore which suites can run them.

- **Nothing.** Every trait under `src/Steps/Web` except `MessageTrait`, `RegionTrait`, `MappingTrait` and `BasicAuthTrait` reads and drives the page through Mink alone. They run on any backend, against any site, with no Drupal at all.
- **Extension configuration, but no backend.** `MessageTrait`, `RegionTrait` and `MappingTrait` read the `selectors`, `regions` and `mappings` maps that `BehatStepsExtension` injects, and `BasicAuthTrait` reads the basic authenticator. They need the extension registered, not a bootstrapped site.
- **A narrow capability.** `CacheTrait`'s clear and cron steps, `DrushTrait` and the user and content creation steps resolve one named capability (`CacheCapabilityInterface`, `CronCapabilityInterface`, `DrushCapabilityInterface`, `UserCapabilityInterface`, `ContentCapabilityInterface`, `RoleCapabilityInterface`). They work on any backend implementing it, which for most is the Drush backend as well as the in-process one.
- **Drupal's API in this process.** Every other trait under `src/Steps/Drupal` calls into `\Drupal::` directly, which only a backend that bootstraps Drupal in-process can serve. Those steps resolve `CoreCapabilityInterface`.

A step names a capability and never a backend. `WebRawContext::backendFor()` walks the scenario's backend order, returns the first backend implementing that capability and bootstraps only that one; when none does, it throws an `UnsupportedBackendActionException` naming the capability and the order. The order itself comes from the `backends` list under `behat_steps` and the `@backend:NAME` tag, documented in [docs/configuration.md](docs/configuration.md#backend-resolution).

Every create and delete on a capability interface follows 1 contract, so teardown code can delete whatever a scenario created without checking first. A create returns the stub, flagged as saved once the backend holds the created entity: `createLanguage()` leaves a language that already exists alone and returns the stub unsaved, and `createRole()`, which takes no stub, builds a `user_role` one. A backend running outside the test process holds no entity, so the Drush backend's `createUser()` and `createRole()` return their stubs unsaved, carrying the new `uid` or `id` value. A delete returns `void` and does nothing when its target doesn't exist. A stub with no identifier at all still throws, since that's a malformed argument rather than a miss. [tests/phpunit/src/CapabilityContractTest.php](tests/phpunit/src/CapabilityContractTest.php) holds every create and delete to its return type, and the backend tests pin the miss.

## Sending a request from a trait

A step that sends its own HTTP request picks 1 of 3 clients on `WebRawContext` by what the response is for, and the method it calls says which:

| The response | Call | Used by |
| --- | --- | --- |
| Becomes the page the next steps read | `httpPageClient()` | `RestTrait` |
| Belongs to the scenario's visitor, but isn't the page | `httpDetachedClient()` | `FileDownloadTrait`, the hreflang return-link check in `MetatagTrait` |
| Carries nothing of the scenario | `httpBareClient()` | The engine fetch in `AccessibilityTrait` |

Never build a client of your own, whether that's cURL, `file_get_contents()` over HTTP or a `new HttpBrowser()`. It wouldn't get the connection settings a project declares on its `browserkit_http` session, so a suite on a staging site with a self-signed certificate would pass on the page and fail on the download. A trait calling any of the 3 carries `@phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext`.

The page client exists only under BrowserKit, and throws `UnsupportedDriverActionException` in a JavaScript session, so a step built on it fails there by design. The detached and bare clients work under every browser driver. In a `@javascript` scenario they're a sidecar: the browser holds the page while the step sends its request from PHP, so a download keeps working there. Keep a detached or bare response in the trait's own state, as `FileDownloadTrait` does, or inside the step, as the hreflang check does. Never write it to the session, or the next page assertion reads the wrong content.

In a unit test, the test implementation overrides `httpDetachedClient()` or `httpBareClient()` to return an `HttpBrowser` over Symfony's `MockHttpClient`, as `FileDownloadTraitTest` and `MetatagTraitTest` do. [docs/http-clients.md](docs/http-clients.md) covers the settings, the identity the detached client carries, and the extension points.

A new step that touches `\Drupal::` calls `$this->backendFor(CoreCapabilityInterface::class);` as its first statement. That is the only sanctioned bootstrap: nothing else may assume the container exists. A step whose trait declares prerequisites calls `$this->assertPrerequisites(__TRAIT__)` next, as [Deciding whether a trait acts](#deciding-whether-a-trait-acts) describes.

[scripts/lint-layers.php](scripts/lint-layers.php) holds both boundaries. It reads every file of each declared layer and fails on any code reference into the namespaces that layer excludes: imports, type declarations, and class names reached through a string. `src/Backend` excludes `Behat` and `Mink`; `src/Steps/Web`, `WebRawContext`, `WebContext` and the 4 web helper traits exclude `Drupal`, apart from `Drupal\Component\Utility\Random`, which ships in `drupal/core-utility` and every consumer loads already. A prose mention in a comment is fine - it's the code references that matter. `ahoy lint` runs it.

## Behat 4 readiness

`composer.json` declares `behat/behat: ^3.33.0 || ^4.0@alpha` and `friends-of-behat/mink-extension: ^2.7.5 || ^3.0@alpha`, so a consumer can install this library on either Behat major. `prefer-stable` keeps a default install on the stable pair; Behat 4 arrives only when a project asks for it.

`src/Behat` plugs into 5 Behat extension points, and each one is written to satisfy Behat 3.33 and Behat 4 at the same time. Keep it that way when touching them.

- **Signatures are typed for Behat 4, widened for Behat 3.** Behat 4 types its interfaces where 3.33 leaves them untyped, so implementations declare the Behat 4 return type (`ClassGenerator::supportsSuiteAndClass(): bool`, `HookScope::getName(): string`, `FilterableHook::filterMatches(): bool`, `Extension::getConfigKey(): string`) and keep the parameter untyped or `mixed` so the 3.33 interface is not narrowed.
- **The `browserkit_http` factory is registered from `BehatStepsExtension::initialize()`.** The factory extends Mink's own, records each session's `http_client_parameters` through `buildDriver()` for the transport the library's own requests share, and builds the session exactly as Mink does. Mink declares its own `MinkExtension` `final` from version 3, the release that carries Behat 4 support, so it can't be subclassed, and a wrapper would take the `mink` key away from any other Mink extension a project registers. `initialize()` runs once every extension is activated and before any configuration tree is built, so it hands the factory to `registerDriverFactory()` on whichever Mink extension holds the key - the hook every browser driver extension uses - and that works on both majors.
- **`BackendListener` reads the event, not the removed interface.** Behat 4 drops `ScenarioLikeTested`. Both `ScenarioTested::BEFORE` and `ExampleTested::BEFORE` carry a `BeforeScenarioTested`, which declares `getFeature()` and `getScenario()` itself in both versions, so the listener type-hints that class.
- **`EntityHook` resolves the hook callable for the installed major.** Behat 4 types the callee constructor as `callable`, and `[class-string, method]` isn't callable for an instance method. `ContextMethodCallableFactory` wraps such methods on Behat 4 and is absent on Behat 3, so `EntityHook::resolveCallable()` uses it only when the class exists and passes the pair `HookAttributeReader` builds through unchanged otherwise. It returns `mixed`, because the 2 majors type that parameter differently and PHPStan only analyzes against the installed one. Behat's own parameter type checks the value at runtime.
- **The `context.class_generator.simple` override survives by service id.** Behat collects generators by tag before an activated extension's `process()` runs and injects them as references, so replacing the definition behind that id swaps the class in both versions.

The test suite follows the same rule. Behat 4 reads only PHP configuration and ignores docblock annotations, so the suite runs from [behat.php](behat.php), `BehatCliTrait` writes a `behat.php` for every nested run, and every step and hook - in `src/` and in `tests/behat/bootstrap/` - is declared with a PHP attribute. Behat 3.33 reads both the same way. Both configurations list every Mink session under `sessions` instead of using the browser driver name shorthand, because Mink 3.0.0-ALPHA.1 reads the shorthand with an `Undefined array key "sessions"` warning.

[behat.dist.php](behat.dist.php) is the reference a consumer copies from, so it sets every option `BehatStepsExtension` accepts. `BehatDistConfigTest` names any option missing from it, which is what keeps it complete as the extension grows. The same test runs the reference suite through `WebContext::assertOneContext()`, so a project copying the file never starts with 2 contexts that both carry the web steps. Behat never loads it here, because `behat.php` takes precedence.

## Reading tags

Behat 3 strips the `@` from a tag by default and Behat 4 keeps it, while `TaggedNodeInterface::hasTag()` compares strictly, so a bare-name comparison that matches on one major silently fails on the other.

Read tags through [`Tag`](src/Behat/Tag.php), never through `hasTag()` or `getTags()` directly. A hook passes its scope to one of 3 readers, which read the scenario's tags together with its feature's, so a tag on the `Feature:` line applies to every scenario below it:

```php
// A flag: '@testmode' on the scenario or on its feature.
if (Tag::has($scope, self::TESTMODE_TAG)) {
  // ...
}

// The values of a parametrized tag: '@watchdog:php @watchdog:cron' gives
// 'php' and 'cron', feature tags first.
$types = Tag::values($scope, self::WATCHDOG_TAG);

// The on/off state of each value: '@module:help @module:!contextual' gives
// ['help' => TRUE, 'contextual' => FALSE]. A later tag for a value replaces
// an earlier one, so a scenario tag overrides a feature tag.
$modules = Tag::valueStates($scope, self::MODULE_TAG);
```

Every tag has the same syntax: a flag stands alone, a parametrized tag takes its value after `Tag::SEPARATOR` (`:`), and `Tag::NEGATION` (`!`) before a value switches it off. A tag with nothing after the separator names no value.

A trait names each tag it reads in a constant carrying its prefix, such as `TestmodeTrait::TESTMODE_TAG`, and documents the tag in `tag_registry()` in [docs.php](docs.php). `Tag::JAVASCRIPT` names the tag Mink reads to run a scenario in its JavaScript session. `TagReadTest` fails on a trait that passes a reader a string literal or a single node, or calls `Tag::all()`, `Tag::on()` or `Tag::normalize()`, and `DocsTest` fails on a tag constant the registry does not list.

A filter in a hook attribute, as in `#[AfterScenario('@acme')]`, reads its tag outside the readers, so neither test sees it. A scenario or feature hook filters on `@javascript` alone, the tag Mink reads itself, and reads any other tag in its body through a reader. `TagReadTest` fails on a hook attribute that filters on anything else.

A reader also takes a single node, for a hook that ranks the 2 lines itself: `ResponsiveTrait` validates the `@breakpoint:` tag of the scenario and of its feature separately. `Tag::all()`, `Tag::on()` and `Tag::normalize()` return raw lists for code outside the traits, such as a listener. Nothing outside `Tag` calls `getTags()` or `hasTag()`, so `grep` finds any new one.

## Skipping a trait's hooks

A consumer switches a trait's hooks off with `@behat-steps-skip:<TraitName>` on a scenario or a feature, or for a whole profile or context with the trait's `enabled` option. The tag names a trait and never a hook, and it switches off every hook that trait registers. `SkipTagListener` fails the run at scenario start on a skip tag whose value is not a trait name, so a tag that would switch nothing off cannot pass unnoticed.

A scenario hook asks the guard in 1 of 2 shapes, naming its own trait. A hook that acts returns when its trait is skipped:

```php
#[AfterScenario]
public function acmeAfterScenario(AfterScenarioScope $scope): void {
  if ($this->skipTag(__TRAIT__, $scope)) {
    return;
  }

  // ...
}
```

Resetting the trait's own properties may come before the guard, because a reset changes nothing a skipped scenario keeps. `bigPipeBeforeScenario()` clears its flags and then guards.

A step hook's scope carries no scenario tags, and neither does a transform's, so a trait whose step hooks or transforms need the answer records it in a property instead:

```php
#[BeforeScenario]
public function acmeBeforeScenario(BeforeScenarioScope $scope): void {
  $this->acmeEnabled = !$this->skipTag(__TRAIT__, $scope);
}
```

The hook assigns the answer rather than returning, so a property that defaults to on is switched off for a skipped scenario. `DateTrait` and `MappingTrait` read theirs in a transform and `WaitTrait` in a step hook, and an `AfterScenario` hook may read the same property instead of a guard of its own.

`__TRAIT__` resolves to the trait the code is written in, so the guard cannot name the wrong trait or fall out of step with a rename.

Two other kinds of scenario hook carry no guard, because they have nothing to switch off:

- A hook that only resets in-memory state - its trait's own properties, or a static cache in this process - holds nothing a scenario would want to keep.
- A hook that acts only on its trait's own activation tag, such as `@breakpoint:`, is switched off by removing the tag.

`tests/phpunit/src/SkipGuardTest.php` holds all of this. It fails a scenario hook that asks the guard in neither shape and isn't listed in its `UNGUARDED_HOOKS` with a reason, a `skipTag()` call naming anything but `__TRAIT__`, and a trait that reads a skip tag directly.

## Deciding whether a trait acts

A trait's hooks and steps answer 3 separate questions, and each has 1 mechanism. Keeping them apart is what lets a trait stay quiet where it should and fail where it should.

| Question | Mechanism | When the answer is no |
| --- | --- | --- |
| Opt-in: is the trait switched on? | `skipTag(__TRAIT__, $scope)`, which reads the `enabled` option and the skip tag | The hook returns quietly |
| Activation: does this scenario ask for it? | The trait's own tag check, such as `Tag::has($scope, self::TESTMODE_TAG)` | The hook returns quietly |
| Prerequisites: does the site provide what it needs? | The trait's `<prefix>Prerequisites()`, checked by `assertPrerequisites(__TRAIT__)` | The scenario fails, naming what's missing |

Don't merge them into 1 boolean. Opted out means the trait does nothing, while opted in without its prerequisites means the scenario can't be trusted, so the first returns and the second throws.

A setup hook applies them in that order:

```php
#[BeforeScenario]
public function acmeBeforeScenario(BeforeScenarioScope $scope): void {
  if ($this->skipTag(__TRAIT__, $scope) || !Tag::has($scope, self::ACME_TAG)) {
    return;
  }

  $this->assertPrerequisites(__TRAIT__, $scope);

  // ...
}
```

### Declaring prerequisites

A trait declares what it needs from the site in a `<prefix>Prerequisites()` method, named by its prefix like `<prefix>ConfigSchema()`, and states each prerequisite through a backend capability:

```php
protected function acmePrerequisites(): array {
  return [
    Prerequisite::capability(CoreCapabilityInterface::class),
    Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('acme'), 'the "acme" module from the "drupal/acme" package is enabled'),
  ];
}
```

- `Prerequisite::capability()` holds when a backend in the scenario's list provides the capability.
- `Prerequisite::check()` takes a static closure whose only parameter is typed to a capability interface. The checker hands it a backend providing that capability, and the closure returns whether the prerequisite holds. The closure gets nothing else: a condition that depends on an option, a tag or a step argument is opt-in, activation or input validation, not a prerequisite.
- A description is a clause completing "requires that", in lower case with no closing period. It appears both in the failure message and in the Prerequisites table `docs.php` renders into [STEPS.md](STEPS.md).

A module the trait needs is declared. A core module counts the same as a contrib one, since a site can uninstall it: `FileTrait` declares `file`, `MediaTrait` `media` and `TaxonomyTrait` `taxonomy`, as `WebformTrait` declares `webform`. A module it only adapts to, such as `pathauto`, is asked with `$this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled()`, which reuses a backend the scenario already reached, so the question never starts a second backend the way `backendFor()` would under `@backend:drush`. Either way, module state goes through `ModuleCapabilityInterface`, never `\Drupal::moduleHandler()->moduleExists()`.

### Where a trait checks them

- **A step** calls `$this->assertPrerequisites(__TRAIT__)` right after resolving its backend. It checks only when a scenario uses it, so a suite that never runs a webform step never needs `webform`. A public helper a project calls from its own steps checks the same way, as `webformLoadMultiple()` and `mediaLoadMultiple()` do.
- **Only the steps that need every declaration** check, because `assertPrerequisites()` checks the trait's whole list. A module only some steps need is checked in those steps alone: `ContentTrait` checks `path` in its path alias step, and `FileTrait` checks `file` in its managed file steps. A step that creates through a narrow capability rather than Drupal's API, such as `TaxonomyTrait`'s term creation, checks nothing, so it keeps running on any backend providing that capability.
- **A setup hook** checks at scenario start, straight after its guard, and passes its scope: `$this->assertPrerequisites(__TRAIT__, $scope)`. The `enabled` option and the skip tag stop a trait's hooks but never its steps, so only a hook's failure names them as a way out.
- **A check at step scope** that reads what a prerequisite provides checks again first, in case the scenario removed it.
- **A teardown** never throws for an unmet prerequisite. It asks `$this->prerequisitesMet(__TRAIT__)`, or reads a flag its setup set, and undoes only what the setup did, so it can't replace a failure the scenario already recorded.

The checker reads each trait's declarations once per context class and run, since a context can redeclare the declaring method, and evaluates them in order. For each capability it goes through `anyBackendFor()`, which reuses a backend the scenario already reached before trying the first one listed, so checking never starts a second backend. A capability with no check still reaches its backend, so declaring `CoreCapabilityInterface` first is what keeps the rest in-process: once it has resolved `drupal`, a module check runs there even under `@backend:drush`. Answers aren't cached, because a tag, a step or an out-of-process command can install or uninstall a module at any time.

`tests/phpunit/src/PrerequisiteDeclarationsTest.php` holds all of this. It fails a malformed declaration, a trait that declares prerequisites but never checks them or checks prerequisites it never declares, a check naming anything but `__TRAIT__`, and a `moduleExists()` call anywhere under `src/Steps` or `src/Helper`.

## Dependency policy

Keep the `require` section of `composer.json` minimal - it should contain only what **every** consumer needs regardless of which traits they use.

- **`require`**: the framework and browser abstraction that virtually all steps build on - `php`, `behat/behat`, `behat/mink` - plus what the backend and Behat layers need at runtime. Both ship in `src/`, so every consumer loads them: `drupal/core-utility`, `symfony/process` for the Drush backend, and `friends-of-behat/mink-extension`, `symfony/config`, `symfony/dependency-injection`, `symfony/event-dispatcher` for the extension, its config schema and `WebRawContext`'s Mink ancestor.
- **`require-dev` + `suggest`**: any package used by only a subset of traits. List it in `require-dev` so this library's own test suite still exercises it, **and** in `suggest` with a message naming the exact trait(s) or step(s) that need it (as `justinrainbow/json-schema` does for `JsonTrait`).

A package that code under `src/` calls is declared in its own right, even when a `require` dependency already installs it. That arrival is the other package's implementation detail, which it can drop in any release. `symfony/filesystem` is the case in point: `symfony/config` requires it, yet it sits in `require` itself, because `FileTrait`'s setup hook builds a `Filesystem` on every scenario of a context composing the trait, as `DrupalContext` does, and `FileDownloadTrait` builds one for its download directory.

When a new trait needs a package, decide up front: trait-specific packages go in `require-dev` + `suggest`, never in `require`. Demoting a package from `require` to `suggest` later is a breaking change for consumers relying on transitive installation, so batch such demotions into the next major release and document them in [MIGRATION.md](MIGRATION.md).

### Dependency patches

A patch against this repository's own vendor directory lives at `patches/<vendor>/<package>/<name>.patch`, so the package it applies to is its own directory and the file name becomes its description. `composer.json` declares none. These are separate from the fixture site's patches, which the Drupal 12 fixture declares itself - see [Patched contrib](#patched-contrib).

[scripts/provision.php](scripts/provision.php) writes the `extra.patches` map over this package's own `composer.json`, applies it with `composer patches-relock` and `composer patches-repatch`, and restores the file afterwards. The declaration is not committed because Composer Patches registers a `Dependencies` resolver that reads `extra.patches` from every installed dependency and resolves relative paths against the consuming project's root - a project requiring this package would look for the patch inside its own tree and fail.

## Local environment setup

Install [Docker](https://www.docker.com/), [Pygmy](https://github.com/pygmystack/pygmy), [Ahoy](https://github.com/ahoy-cli/ahoy)
and shut down local web services (Apache/Nginx, MAMP etc)

- Checkout project repository in one of
  the [supported Docker directories](https://docs.docker.com/docker-for-mac/osxfs/#access-control).
- `pygmy up`
- `ahoy build`
- Access the built site at `http://<directory>.docker.amazee.io/`, where `<directory>` is the name of your checkout directory. `ahoy info` prints the exact URL. Each checkout gets its own hostname, so several clones can run side by side.

Use `ahoy --help` to see the list of available commands.

## Running tests

There are 3 types of tests in this repository: unit tests, kernel tests and Behat tests.

### Unit and kernel tests

Both suites are declared in [phpunit.xml](phpunit.xml) and run against the
fixture site, because the backend layer and the tests around it resolve Drupal
classes from there. Run `ahoy build` first.

Tests live under `tests/phpunit/src/` in a directory named after their suite:
`Unit/` and `Kernel/`. Anything outside `Kernel/` belongs to the unit suite.
Inside a suite directory the path mirrors `src/`, so
`src/Helper/Web/StringTrait.php` is tested by
`tests/phpunit/src/Unit/Helper/Web/StringTraitTest.php`. Tests with no
counterpart in `src/` - the docs generator, the layer linter and the
convention tests - sit at the root of `tests/phpunit/src/`.

```bash
ahoy test-unit      # Run the unit suite

ahoy test-kernel    # Run the kernel suite

ahoy test-coverage  # Run both suites and write one coverage report
```

Coverage is collected across both suites in a single run, so the report covers
everything the tests reach. Its output paths are declared in
[phpunit.xml](phpunit.xml).

### Behat tests

Behat tests are used as functional/integration tests to validate the
functionality of the traits. These Behat tests run in the same way they
would be run in your project: one context,
[FeatureContext.php](tests/behat/bootstrap/FeatureContext.php), extends
`DrupalContext` and carries the test-only overrides, and is then ran on the
pre-configured [fixture Drupal site](tests/behat/fixtures_drupal/d11)
using [test features](tests/behat/features).

Run `ahoy build` to setup a fixture Drupal site in the `build` directory.

```bash
ahoy test-bdd                # Run all Behat tests

ahoy test-bdd path/to/file   # Run all Behat scenarios in specific feature file

ahoy test-bdd -- --tags=wip  # Run all Behat scenarios tagged with `@wip` tag
```

Every step the library registers needs at least 1 scenario that runs it. Behat only resolves a step definition when a scenario uses it, so a pattern no scenario reaches can ship unusable without the suite noticing. [tests/phpunit/src/StepScenarioCoverageTest.php](tests/phpunit/src/StepScenarioCoverageTest.php) enforces this in the unit suite: it matches every registered pattern against the steps the feature files run and fails on any step nothing reaches. Steps a `@test-trait` scenario hands to its nested run count, since they do run. Steps in a scenario the suite filters out, such as one tagged `@test-skipped`, don't.

The nested runs find the repository through `BehatCliTrait::behatCliGetRootPath()`, which derives the root from the trait's own location, and [rector.php](rector.php) derives its paths from `__DIR__` the same way. Neither writes `/app`, so both follow the checkout wherever it lives. The service hosts in the configuration, such as `nginx` and `chrome`, stay as they are: they name the Docker services the suite runs against, not paths.

### Static fixtures

Static fixture files - HTML pages, XML, JSON, images, archives - live in [tests/behat/fixtures](tests/behat/fixtures).

Traits with no Drupal dependency are tested against those files served by a PHP built-in server instead of the fixture Drupal site. Tag the scenario `@phpserver` and address the file directly:

```gherkin
@phpserver
Scenario: Assert that an element exists
  When I visit "http://cli:8888/elements.html"
  Then the element "#top" should exist
```

The server runs for the duration of a tagged scenario and serves `tests/behat/fixtures` at its root, so an edited fixture applies on the next run.

Drupal traits are tested through the fixture site, which receives a copy of the same directory in `build/web/sites/default/files` during provisioning. Run `ahoy copy-files` after editing a fixture that such a scenario reaches through a Drupal path.

### Coverage markers

`@codeCoverageIgnoreStart` and `@codeCoverageIgnoreEnd` suppress **genuinely unreachable** defensive code. They are not a way to hide an untested branch.

The distinction matters because PHPUnit drops ignored lines from the report entirely. Ignoring a line that a test would have covered lowers the reported rate, which is merely wasteful. Ignoring a line that no test covers *raises* it, which reports progress that does not exist. `codecov/patch` and `codecov/project` are required checks, so that second case shifts the baseline every later pull request is measured against.

Mark a block only when a test cannot reach it in this environment:

- An I/O failure that cannot be provoked - `file_get_contents()` returning `FALSE` on a file just written, `tempnam()` failing.
- A type guard that the preceding call makes impossible - `!$node instanceof NodeInterface` directly after a successful load.
- A capability branch for a backend the suite does not run.

Do not mark a branch that a scenario could reach. In particular:

- **Skip-tag guards** (`@behat-steps-skip:<TraitName>`) are reachable by definition - add a scenario carrying the tag.
- **Argument validation** driven by a step parameter is reachable by passing an invalid value.

If a reachable branch has no test, the fix is the test, not the marker.

### Debugging tests

- `ahoy debug`
- Set breakpoint
- Run tests with `ahoy test-bdd` - your IDE will pickup an incoming debug
  connection

## Continuous integration

[.github/workflows/test.yml](.github/workflows/test.yml) runs 2 jobs, and between them they cover all 3 test surfaces in this repository.

### Lint

1 job, on PHP 8.4. It checks that `composer.json` is normalized, then `ahoy lint` runs `composer validate`, `parallel-lint`, `phpcs`, `phpstan`, `rector --dry-run`, `gherkinlint`, [scripts/lint-layers.php](scripts/lint-layers.php) and [scripts/lint-traits.php](scripts/lint-traits.php), and `ahoy lint-docs` checks [STEPS.md](STEPS.md) for drift. Both are the commands you run locally, and the job is green only when both are.

The job provisions before it lints, and PHPStan needs it to. `ahoy lint` points the analyzer at the fixture with `DRUPAL_ROOT` and `DRUPAL_VENDOR_ROOT`, which `mglaman/phpstan-drupal` reads only with the patch [scripts/provision.php](scripts/provision.php) applies to it. Run `ahoy build` before `ahoy lint` on a fresh checkout, or PHPStan aborts before it analyzes anything.

### Test matrix

| Legs | What they prove |
|---|---|
| PHP 8.3 / 8.4 / 8.5 x Drupal 11 x `normal` / `lowest` x Behat 3 | The library works across the supported PHP range against both the newest and the oldest resolvable dependencies. The `lowest` legs are what hold the Behat 3.33 floor. |
| 2 x `chrome_headless` | The steps drive a browser without Selenium, over the Chrome DevTools Protocol. That browser driver is Drupal-version independent, so the 2 legs take their breadth from the PHP axis. Both stay on `normal` deps: `dmore/behat-chrome-extension` hands the browser driver `domWaitTimeout` and `socketTimeout`, which the oldest `dmore/chrome-mink-driver` it accepts does not define, so a `lowest` resolution cannot boot Chrome at all. |
| PHP 8.3 / 8.4 / 8.5 x Drupal 11 x `normal` / `lowest` x Behat 4 | The same unit, kernel and Behat suites pass on Behat 4. See [Behat 4 legs](#behat-4-legs). |
| PHP 8.5 x Drupal 12 x `normal` / `lowest` x Behat 4 | The next core major, on a patched contrib set. See [Drupal versions](#drupal-versions). |

The unit and kernel suites run on every leg that is not driven by a Behat profile, since a profile changes how the Behat suite runs and not what PHPUnit covers.

Coverage is produced on 1 Selenium leg and 1 `chrome_headless` leg, both on Behat 3, merged by Codecov into a single report, and its upload fails the leg rather than passing quietly. Test artifacts (`.logs`) are uploaded from every leg.

### Behat 4 legs

Each Behat 4 leg provisions the fixture with `BEHAT=4`. [scripts/provision.php](scripts/provision.php) narrows the `composer.json` constraint with `composer update --with="behat/behat:^4"`, and removes `dmore/behat-chrome-extension`, which has no release that accepts Behat 4. That is why Behat 4 has no `chrome_headless` leg.

On Drupal 11 it also removes `dvdoug/behat-code-coverage`, which accepts Behat 4 only from 5.5. That release, and 5.4 before it, needs `phpunit/php-code-coverage` 12, while Drupal 11's `drupal/core-dev` holds the fixture on PHPUnit 11.5, which requires `^11.0.12`. The fixture therefore resolves 5.3.7, the newest release that still takes PHPUnit 11, and that one caps `behat/behat` at `^3`. The `composer.json` constraint is open at `^5.3.7`, so the repository root, running PHPUnit 12, does install 5.5 - only the Drupal 11 fixture is held back. [behat.php](behat.php) registers the coverage extension only when it is installed.

Every leg names the major it runs, as in `Test PHP 8.3, Drupal 11, Behat 3, Deps normal`, so a check name says what it covered without a lookup. The branch ruleset requires checks by name, so renaming a leg means updating the required checks on `4.x` to match.

`BEHAT` reaches the container through `ahoy`, so provisioning the fixture for Behat 4 locally is a matter of setting it. Run `ahoy provision` to switch back to Behat 3:

```bash
BEHAT=4 ahoy provision
ahoy test-bdd
```

### Drupal versions

Each major has its own fixture directory under [tests/behat/fixtures_drupal](tests/behat/fixtures_drupal), addressed as `d${DRUPAL_VERSION}`, and `DRUPAL_VERSION` defaults to `11` everywhere it is read. Renovate leaves Composer major updates alone, so moving to a new core major is a deliberate change rather than an automatic one.

Drupal 12 is pinned to `~12.0.0-beta1` and runs 2 legs of its own - PHP 8.5, Behat 4, `normal` and `lowest` - so the `normal` / `lowest` pair covers both majors. Getting there takes a patched contrib set, because Drupal 12 and Symfony 8 broke most of what the fixture installs. See [Patched contrib](#patched-contrib).

Drupal 12 constrains its own grid hard:

- Drupal 12 requires PHP 8.5, so PHP 8.3 and PHP 8.4 are out.
- Drupal 12 requires Symfony 8, and `behat/behat` 3.33 - the newest Behat 3 - requires `symfony/yaml ^5.4 || ^6.4 || ^7.0`, so Behat 3 is out. `behat/behat` 4.0 accepts Symfony 8.
- `dmore/behat-chrome-extension` accepts Behat 3 only, so `chrome_headless` is out.
- Drupal 12 raises the database floor to MariaDB 10.11, which is why [docker-compose.yml](docker-compose.yml) runs `uselagoon/mariadb-10.11-drupal`. Drupal 11 asks for 10.6 or newer, so one image serves both majors.

Building the Drupal 12 fixture takes 3 packages that the Drupal 11 fixture does not:

- `mglaman/composer-drupal-lenient`, with every contrib module the fixture installs on its `extra.drupal-lenient.allowed-list`. Most of those modules have no release declaring `drupal/core ^12`, and the plugin strips the core constraint so they install anyway. The hosted lenient endpoint on drupal.org is not used - it currently redirects to a page that does not exist. A Composer plugin only shapes a solve it is already installed for, and the fixture has no solution until this one runs, so [scripts/provision.php](scripts/provision.php) installs it globally before the build update; Composer loads global plugins for local projects.
- `drush/drush ^14@dev`. No tagged Drush release accepts Symfony 8. This is why the fixture sets `minimum-stability` to `dev` with `prefer-stable`.
- `drupal/scheduled_transitions ^2.9.0@beta`, the first release declaring Drupal 12.

The Drush backend supports Drush 13, the first release that runs Drupal 11, and newer: the Drupal 11 fixture installs 13.x and the Drupal 12 fixture `14.x-dev`. It runs each command by its canonical colon-separated name, such as `pm:install` or `core:cron`, which every supported release registers, and never by a legacy alias such as `pm-enable` or `cron`. `DrushBackendMethodsTest` fails a command issued in any other form, and the `@backend:drush` scenarios run the cache, cron and user commands against the Drush each fixture installs.

The fixture also takes `drupal/core` from source rather than dist. From 12.0.0-beta1 the release package no longer carries core's test files, which the PHPUnit bootstrap and the Kernel suite need, and [scripts/provision.php](scripts/provision.php) passes no `--prefer-dist` so the per-package setting holds.

Every contrib module carries a floor in `d12/composer.json` at the oldest release known to work on Drupal 12. An older release predates the major and fails on it whatever the patches do - `drupal/token` at its lowest resolvable release declares no return type on `getSubscribedEvents()` - and a patch written against one release does not apply to another. Without the floors the `lowest` leg fails before a single scenario runs.

The floors cover contrib only. `lowest` still resolves the oldest usable version of the library's own dependencies, which is what those legs are for.

Relaxing the Composer solve is only half of it. Drupal reads `core_version_requirement` from each extension's `.info.yml` and refuses to enable one that excludes the running major, so after the update [scripts/provision.php](scripts/provision.php) appends `|| ^12` to that key across the installed contrib extensions. The rewrite touches the throwaway `build/` tree only, never the fixture sources.

Drupal 12 removes `contact`, `history` and `shortcut` from core, so `d12/config/sync` carries neither those modules nor the config that depended on them, and the `ModuleTrait` scenarios use `syslog` and `contextual`, which both majors ship. 12.0.0-beta1 goes further: it moves Olivero, Claro and Search out of core, which the fixture installs from contrib under the same machine names, and removes Toolbar and the Syndicate block, which the fixture drops.

To take the Drupal 12 fixture as far as its legs do, set the 3 variables they set. `ahoy build` resets the containers, so the PHP version has to be on the build as well as the provisioning:

```bash
PHP_VERSION=8.5 DRUPAL_VERSION=12 BEHAT=4 ahoy build
```

The suites then run against it with `DRUPAL_VERSION=12 BEHAT=4` in front of `ahoy test-unit`, `ahoy test-kernel` and `ahoy test-bdd`.

### Patched contrib

Getting contrib installed is not the same as getting it to run. Drupal 12 and Symfony 8 between them broke 13 of the modules the fixture installs, so `d12/composer.json` carries a patch for each under `extra.patches`, applied by `cweagans/composer-patches`. The patches live in `d12/patches/` and fall into 5 groups:

| What changed | Modules |
| --- | --- |
| Drupal 12 removed the magic `original` property | `redirect`, `webform` |
| Symfony 8 removed `Request::get()` | `webform` |
| Drupal 12 removed annotation-only plugin discovery | `ctools`, `webform` |
| Drupal 12 and Symfony 8 tightened method signatures | `date_recur`, `dynamic_entity_reference`, `eck`, `entity_reference_revisions`, `paragraphs`, `pathauto`, `profile`, `redirect`, `scheduled_transitions`, `state_machine`, `time_field`, `webform` |
| Drupal 12 stopped discovering `template_preprocess_HOOK()` | `eck` |

Drupal 12 also deleted the Archiver plugin system, which `webform` injected but never used, and moved the `text_with_summary` field type out of `text` into a module of its own, which the fixture now requires and enables.

Every patch is generated against the released source rather than against the `build/` tree, which carries whatever the last provisioning applied. A patch generated from `build/` silently loses any hunk the tree already has.

A patch stops being needed the day its module ships a Drupal 12 release, at which point both the patch and the module's floor come out.

### Verifying the import landed

`drush cim` can enable the modules, abort on a fatal raised while it creates config entities, and still exit 0. The site then comes up with its modules enabled and none of the content types, fields, webforms or entity types the suite asserts on. [scripts/provision.php](scripts/provision.php) therefore reads a config entity only the fixture defines and fails when the import did not land, on every major - without that check a Drupal 12 build reports success on an empty site.

### Coverage

Coverage is not collected on the Drupal 12 legs. Drupal 12 brings PHPUnit 12, so `dvdoug/behat-code-coverage` 5.5 does install there and Behat 4 coverage becomes possible for the first time, but the coverage report stays on the settled Drupal 11 legs while core 12 is a pre-release.

## Updating fixture site

- Build the fixture site and make the required changes
- `ahoy drush cex -y`
- `ahoy update-fixtures` to copy configuration
  changes from build directory to the fixtures directory

### Validating and updating documentation

[docs.php](docs.php) is the only generator of reference documentation. One run
writes every generated region:

| Target | Holds |
| --- | --- |
| [STEPS.md](STEPS.md) and the index in [README.md](README.md) | The step vocabulary |
| [HELPERS.md](HELPERS.md) | The toolbox |
| [docs/configuration.md](docs/configuration.md) | The extension options and the tag reference |

The same run validates the [steps format](#steps-format), that every published
helper carries a summary, that tags resolve against `tag_registry()`, and that
every environment variable the source reads is documented.

```
ahoy update-docs  # Update documentation

ahoy lint-docs    # Check documentation for errors
```

An extension option and a tag are both generated from their definition in the
source, so neither can be added without appearing in the reference. Write the
option's `->info()` in
[BehatStepsExtension](src/Behat/ServiceContainer/BehatStepsExtension.php) and
the tag's description in `tag_registry()`, then run `ahoy update-docs`. Never
hand-edit inside a generated region.
