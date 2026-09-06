# Migration guide

## Unified step text

Placeholder names, articles and `Given` verbs drifted as traits were added, so the same idea ended up written several different ways: an XML attribute was `:attribute` in 4 steps and `:attribute_name` in 2, a taxonomy vocabulary answered to 3 different names, and a handful of `Given` steps had no verb at all. 73 steps now follow one set of conventions.

- A step that names its target (`:element`, `:path`, `:key`, `:field`) compares against `:value`. `:text` is now reserved for steps that assert on a whole body with no named target, such as `the modal should contain :text`.
- A bundle placeholder is named after its entity type - `:content_type`, `:media_type`, `:content_block_type`, `:vocabulary`. Steps that are deliberately entity-agnostic keep `:bundle` (`EckTrait`, and the parent lookup in `ParagraphsTrait`).
- A bundle placeholder that qualifies an entity noun comes before it, as in `the :media_type media`. One that is itself the subject follows its noun, as in `the media type :media_type`.
- Every noun takes an article, `URL` is uppercase, and a named value reads `the value :value` rather than `the :value value`.
- Placeholder names are `snake_case`.
- A `Given` states a fact in the present tense. Verbless steps gained `exist`, bare noun phrases gained a verb, and the `has been cleared` family became `is empty`. Steps that already read `is empty`, `is enabled` or `is disabled` were left alone: they mirror the `should be ...` assertion they pair with, and forcing them into an `exists` form would say something different.

Placeholder names are part of the contract even when the surrounding words are identical. Behat binds a step argument to the method parameter of the same name, so a rename reaches any context that overrides the step method or calls it directly.

Three steps were relying on Behat's positional fallback because their parameter never matched their placeholder. Their step text is unchanged, but the method signatures are not: `MediaTrait::mediaRemoveType()` now takes `$media_type`, and `SearchApiTrait::searchApiIndexContent()` and `searchApiDoIndex()` now take `$content_type` and `$count`.

### CacheTrait

| Before | After |
| --- | --- |
| `Given the page cache for the path :path has been cleared` | `Given the page cache for the path :path is empty` |
| `Given the page cache for the paths matching :path_pattern has been cleared` | `Given the page cache for the paths matching :path_pattern is empty` |
| `Given the render cache has been cleared` | `Given the render cache is empty` |

### ConfigTrait

| Before | After |
| --- | --- |
| `Given the following config values:` | `Given the following config values exist:` |

### ContentBlockTrait

| Before | After |
| --- | --- |
| `When I edit the :type content block with the description :description` | `When I edit the :content_block_type content block with the description :description` |
| `Then the content block type :type should exist` | `Then the content block type :content_block_type should exist` |
| `Given the following :type content blocks do not exist:` | `Given the following :content_block_type content blocks do not exist:` |
| `Given the following :type content blocks exist:` | `Given the following :content_block_type content blocks exist:` |
| `Given the following :type content blocks with fields:` | `Given the following :content_block_type content blocks with fields exist:` |

### ContentTrait

| Before | After |
| --- | --- |
| `Given the following :type content with fields:` | `Given the following :content_type content with fields exist:` |

### DraggableviewsTrait

| Before | After |
| --- | --- |
| `When I save the draggable views items of the view :view_id and the display :view_display_id for the :bundle content in the following order:` | `When I save the draggable views items of the view :view_id and the display :view_display_id for the :content_type content in the following order:` |

### EmailTrait

| Before | After |
| --- | --- |
| `Then an email should be sent to the :address` | `Then an email should be sent to the address :address` |
| `Then no emails should have been sent to the :address` | `Then no emails should have been sent to the address :address` |

### FieldTrait

| Before | After |
| --- | --- |
| `When I fill in the WYSIWYG field :field with the :value` | `When I fill in the WYSIWYG field :field with the value :value` |
| `When I fill in the field :selector with :value` | `When I fill in the field :selector with the value :value` |
| `Given browser validation for the form :selector is disabled` | `Given the browser validation for the form :selector is disabled` |
| `Then the field :name should exist` | `Then the field :field should exist` |
| `Then the field :name should have :enabled_or_disabled state` | `Then the field :field should have the :enabled_or_disabled state` |
| `Then the field :name should not exist` | `Then the field :field should not exist` |

### FileDownloadTrait

| Before | After |
| --- | --- |
| `Then the downloaded file name should contain :file_name_part` | `Then the downloaded file name should contain :partial_name` |

### FileTrait

| Before | After |
| --- | --- |
| `Given the following managed files:` | `Given the following managed files exist:` |
| `Given the unmanaged file at the URI :uri exists with :content` | `Given the unmanaged file at the URI :uri exists with the content :content` |

### IframeTrait

| Before | After |
| --- | --- |
| `When I switch to iframe with locator :locator` | `When I switch to the iframe with the selector :selector` |

### JsonTrait

| Before | After |
| --- | --- |
| `Given the response JSON content is the following:` | `Given the response JSON is the following:` |
| `Given the response JSON from the file :filename` | `Given the response JSON is loaded from the file :filename` |

### MediaTrait

| Before | After |
| --- | --- |
| `Given :media_type media type does not exist` | `Given the media type :media_type does not exist` |
| `When I edit the media :media_type with the name :name` | `When I edit the :media_type media with the name :name` |
| `When I visit the media :media_type delete page with the name :name` | `When I visit the :media_type media delete page with the name :name` |
| `When I visit the media :media_type revisions page with the name :name` | `When I visit the :media_type media revisions page with the name :name` |
| `When I visit the media :media_type with the name :name` | `When I visit the :media_type media with the name :name` |
| `Then the :media_type media type should exist` | `Then the media type :media_type should exist` |
| `Then the :media_type media type should not exist` | `Then the media type :media_type should not exist` |
| `Given the following :bundle media with fields:` | `Given the following :media_type media with fields exist:` |
| `Given the following media :media_type do not exist:` | `Given the following :media_type media do not exist:` |
| `Given the following media :media_type exist:` | `Given the following :media_type media exist:` |

### MenuTrait

| Before | After |
| --- | --- |
| `Given the following menus:` | `Given the following menus exist:` |

### MetatagTrait

| Before | After |
| --- | --- |
| `Then the :metaName meta tag should not contain any HTML tags` | `Then the :meta_name meta tag should not contain any HTML tags` |

### PathTrait

| Before | After |
| --- | --- |
| `Then current url should have the :param parameter` | `Then the current URL should have the :param parameter` |
| `Then current url should have the :param parameter with the :value value` | `Then the current URL should have the :param parameter with the value :value` |
| `Then current url should not have the :param parameter` | `Then the current URL should not have the :param parameter` |
| `Then current url should not have the :param parameter with the :value value` | `Then the current URL should not have the :param parameter with the value :value` |
| `Given the basic authentication with the username :username and the password :password` | `Given the basic authentication has the username :username and the password :password` |

### ResponseTrait

| Before | After |
| --- | --- |
| `Then the response header :header_name should contain the value :header_value` | `Then the response header :name should contain the value :value` |
| `Then the response header :header_name should not contain the value :header_value` | `Then the response header :name should not contain the value :value` |
| `Then the response should contain the header :header_name` | `Then the response should contain the header :name` |
| `Then the response should not contain the header :header_name` | `Then the response should not contain the header :name` |

### ResponsiveTrait

| Before | After |
| --- | --- |
| `Given the following responsive breakpoints:` | `Given the following responsive breakpoints exist:` |

### RestTrait

| Before | After |
| --- | --- |
| `Given a REST header :name with value :value` | `Given the REST header :name has the value :value` |

### StateTrait

| Before | After |
| --- | --- |
| `Given the following state values:` | `Given the following state values exist:` |

### TableTrait

| Before | After |
| --- | --- |
| `Then the :rowText row should contain the following:` | `Then the :row_text row should contain the following:` |

### TaxonomyTrait

| Before | After |
| --- | --- |
| `When I visit the :vocabulary_machine_name term delete page with the name :term_name` | `When I visit the :vocabulary term delete page with the name :term_name` |
| `When I visit the :vocabulary_machine_name term edit page with the name :term_name` | `When I visit the :vocabulary term edit page with the name :term_name` |
| `When I visit the :vocabulary_machine_name term page with the name :term_name` | `When I visit the :vocabulary term page with the name :term_name` |
| `Given the following :vocabulary terms with fields:` | `Given the following :vocabulary terms with fields exist:` |
| `Given the following :vocabulary_machine_name vocabulary terms do not exist:` | `Given the following :vocabulary terms do not exist:` |
| `Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist` | `Then the taxonomy term :term_name from the vocabulary :vocabulary should exist` |
| `Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist` | `Then the taxonomy term :term_name from the vocabulary :vocabulary should not exist` |
| `Then the vocabulary :machine_name should not exist` | `Then the vocabulary :vocabulary should not exist` |
| `Then the vocabulary :machine_name with the name :name should exist` | `Then the vocabulary :vocabulary with the name :name should exist` |

### UserTrait

| Before | After |
| --- | --- |
| `Given the following roles:` | `Given the following roles exist:` |
| `Given the following users with fields:` | `Given the following users with fields exist:` |
| `Given the role :role_name with the permissions :permissions` | `Given the role :role_name has the permissions :permissions` |

### WebformTrait

| Before | After |
| --- | --- |
| `Given a webform :title from template :template` | `Given the webform :title exists from the template :template` |

### XmlTrait

| Before | After |
| --- | --- |
| `Then the XML attribute :attribute on element :element should be equal to :text` | `Then the XML attribute :attribute on element :element should be equal to :value` |
| `Then the XML attribute :attribute on element :element should not be equal to :text` | `Then the XML attribute :attribute on element :element should not be equal to :value` |
| `Then the XML attribute :attribute_name on element :element should contain :text` | `Then the XML attribute :attribute on element :element should contain :value` |
| `Then the XML attribute :attribute_name on element :element should not contain :text` | `Then the XML attribute :attribute on element :element should not contain :value` |
| `Then the XML element :element should be equal to :text` | `Then the XML element :element should be equal to :value` |
| `Then the XML element :element should contain :text` | `Then the XML element :element should contain :value` |
| `Then the XML element :element should not be equal to :text` | `Then the XML element :element should not be equal to :value` |
| `Then the XML element :element should not contain :text` | `Then the XML element :element should not contain :value` |
| `Given the response content from the file :filename` | `Given the response XML is loaded from the file :filename` |
| `Given the response content is the following:` | `Given the response XML is the following:` |

## Optional dependencies moved to `require-dev` and `suggest`

Trait-specific packages are no longer hard `require` dependencies. They now live in `require-dev` (so this library's own test suite still runs) and `suggest`, matching the existing treatment of `justinrainbow/json-schema`. Projects that relied on transitive installation must add the packages they use to their own `composer.json`.

| Package | Add it to your `require-dev` when you use |
| --- | --- |
| `drupal/drupal-extension` | any Drupal trait (`DrevOps\BehatSteps\Drupal\*`) |
| `softcreatr/jsonpath` | `JsonTrait` JSON path steps (`the JSON path ... should ...`) |

`@javascript` scenarios need a JavaScript-capable Mink driver. The steps are driver agnostic, so install **one** of these interchangeable drivers - both run the full `@javascript` suite and both are exercised by this library's CI:

- `lullabot/mink-selenium2-driver` - drives a Selenium/WebDriver server.
- `dmore/behat-chrome-extension` - drives headless Chrome directly over the Chrome DevTools Protocol, with no Selenium server.

`behat/behat` and `behat/mink` remain hard `require` dependencies. For example, a project that uses the Drupal traits and runs JavaScript scenarios with headless Chrome adds:

```bash
composer require --dev drupal/drupal-extension dmore/behat-chrome-extension
```

## Unified entity cleanup

Traits that create Drupal entities now register them in a single shared registry and delete them in reverse creation order through one `helperEntityCleanupAfterScenario` hook, instead of each trait running its own after-scenario cleanup.

The per-trait cleanup skip tags have been removed. Replace them as follows:

| Removed tag                                    | Replacement                                                                                          |
| ---------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `@behat-steps-skip:mediaAfterScenario`         | `@behat-steps-entity-cleanup-skip:media`                                                             |
| `@behat-steps-skip:contentBlockAfterScenario`  | `@behat-steps-entity-cleanup-skip:block_content`                                                     |
| `@behat-steps-skip:paragraphsAfterScenario`    | `@behat-steps-entity-cleanup-skip:paragraph`                                                         |
| `@behat-steps-skip:eckAfterScenario`           | `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID`                                                    |
| `@behat-steps-skip:menuAfterScenario`          | `@behat-steps-entity-cleanup-skip:menu` and/or `@behat-steps-entity-cleanup-skip:menu_link_content`  |
| `@behat-steps-skip:redirectAfterScenario`      | `@behat-steps-entity-cleanup-skip:redirect`                                                          |
| `@behat-steps-skip:blockAfterScenario`         | `@behat-steps-entity-cleanup-skip:block`                                                             |
| `@behat-steps-skip:webformAfterScenario`       | `@behat-steps-entity-cleanup-skip:webform`                                                           |

To skip cleanup of every registered entity at once, use `@behat-steps-skip:helperEntityCleanupAfterScenario`.

`FileTrait` keeps its own `@behat-steps-skip:fileAfterScenario` tag, which now covers only unmanaged files; managed file entities it creates are cleaned up by the shared registry and can be kept with `@behat-steps-entity-cleanup-skip:file`.

## Trait methods prefixed with their trait name

Every method a trait contributes now begins with the trait's own name, so that traits mixed into one context cannot collide. Rename any call or override in a consumer context:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\DraggableviewsTrait` | `draggableViewsSaveBundleOrder()` | `draggableviewsSaveBundleOrder()` |
| `Drupal\DraggableviewsTrait` | `draggableViewsFindNode()` | `draggableviewsFindNode()` |
| `Drupal\HelperTrait` | `entityRegister()` | `helperEntityRegister()` |
| `Drupal\HelperTrait` | `entityRegisterId()` | `helperEntityRegisterId()` |
| `Drupal\HelperTrait` | `entityCleanupAfterScenario()` | `helperEntityCleanupAfterScenario()` |
| `Drupal\HelperTrait` | `entityCleanupRun()` | `helperEntityCleanupRun()` |
| `Drupal\HelperTrait` | `entityCleanupDelete()` | `helperEntityCleanupDelete()` |
| `Drupal\HelperTrait` | `entityCleanupSkippedTypes()` | `helperEntityCleanupSkippedTypes()` |
| `Drupal\HelperTrait` | `$entityRegistry` | `$helperEntityRegistry` |
| `Drupal\HelperTrait` | `ENTITY_CLEANUP_EXCLUDED_TYPES` | `HELPER_ENTITY_CLEANUP_EXCLUDED_TYPES` |
| `Drupal\MenuTrait` | `loadMenuByLabel()` | `menuLoadByLabel()` |
| `Drupal\MenuTrait` | `loadMenuLinkByTitle()` | `menuLoadLinkByTitle()` |
| `WaitTrait` | `waitWaitForSeconds()` | `waitSeconds()` |
| `WaitTrait` | `waitForAjaxToFinish()` | `waitForAjax()` |

Gherkin step text is unchanged, so feature files need no edit for the renames above. One tag does change, because it names the hook method it skips:

| Old tag | New tag |
| --- | --- |
| `@behat-steps-skip:entityCleanupAfterScenario` | `@behat-steps-skip:helperEntityCleanupAfterScenario` |

`Drupal\OverrideTrait::createNodes()`, `::createUsers()`, `::iAmLoggedInAsUserWithRole()` and `Drupal\TaxonomyTrait::createTerms()` override Drupal Extension context methods and keep their names.

## Query parameter presence

`PathTrait` tested for a query parameter with `empty()`, which reads a parameter carrying `0` or an empty string as absent. Presence is now `array_key_exists()`, so `?page=0` and `?debug=` are parameters that are in the URL, and their value is compared separately.

| Step | `?filter=0` before | `?filter=0` after |
| --- | --- | --- |
| `Then the current URL should have the :param parameter` | fails | passes |
| `Then the current URL should have the :param parameter with the value :value` | fails | passes for the value `0` |
| `Then the current URL should not have the :param parameter` | passes | fails |
| `Then the current URL should not have the :param parameter with the value :value` | passes | fails for the value `0` |

A scenario that asserted a falsy parameter away with `Then the current URL should not have the "filter" parameter` now needs to name the value it excludes:

```gherkin
Then the current URL should not have the "filter" parameter with the value "recent"
```

## Unified assertion exceptions

Assertion steps used to throw whatever their trait happened to reach for: `ExpectationException` in most places, plain `\Exception` in 8 traits, `\RuntimeException` in `XmlTrait`'s format check, and `\InvalidArgumentException` in 2 select-option steps. The type is part of the contract - consumers catch on it - so it now follows one rule.

| Failure | Exception |
| --- | --- |
| An assertion fails and the step can reach the page | `Behat\Mink\Exception\ExpectationException` |
| An assertion fails and the step has no Mink session | `DrevOps\BehatSteps\Exception\AssertionException` |
| An expected element, field, link or selector is missing | `Behat\Mink\Exception\ElementNotFoundException` (a subclass of `ExpectationException`) |
| Anything that is not an assertion - an invalid step argument, an unmet prerequisite, an infrastructure error | `\RuntimeException` |
| A step needs a driver capability the current driver lacks | `Behat\Mink\Exception\UnsupportedDriverActionException` |

`ExpectationException` requires a Mink driver as its second constructor argument, so traits that never touch the browser cannot construct it. Those traits throw `AssertionException` instead, which carries the same meaning without the dependency.

If your project catches an exception from one of these steps, update the type:

| Trait | Was | Now |
| --- | --- | --- |
| `CommandTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\ConfigTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\ModuleTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\StateTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\RedirectTrait` (`the following redirects should (not) exist:`) | `\Exception` | `AssertionException` |
| `MetatagTrait` (all `Then` steps) | `\Exception` | `ExpectationException` |
| `XmlTrait` (`the response should be in XML format`) | `\RuntimeException` | `ExpectationException` |
| `FieldTrait` (`the option ... should (not) exist within the select element ...`) | `\InvalidArgumentException` | `ElementNotFoundException` for a missing select, `ExpectationException` for the option |
| `Drupal\CacheTrait` (`the page cache for the path(s) ... is empty`) | `\InvalidArgumentException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...`) | `\InvalidArgumentException` | `\RuntimeException` |

3 failure messages changed along with their type:

| Step | Was | Now |
| --- | --- | --- |
| `the response should be in XML format` | `Failed to load XML. Errors: ...` | `The response is not valid XML: ...` |
| `the option :option should exist within the select element :selector` | `Element "..." is not found.` / `Option "..." is not found in select "...".` | `Select with id\|name\|label "..." not found.` / `The option "..." was not found in the select "..." on the page ....` |
| `the option :option should not exist within the select element :selector` | `Element "..." is not found.` / `Option "..." is found in select "...", but should not.` | `Select with id\|name\|label "..." not found.` / `The option "..." was found in the select "..." on the page ..., but should not exist.` |

Behat reports every one of these as a failed step either way, so a scenario that simply runs to a failure behaves the same. Only code that catches a specific type, or asserts on the message text, needs changing.

## Tightened public surface

A handful of trait members exposed more than the surrounding code intended. Each one is reachable from a consuming context, so they're grouped here as breaking changes rather than fixed quietly. A `PublicSurfaceTest` now holds each of these conventions, so the surface stays deliberate from here on.

### Internal helpers are now `protected`

Neither method is a step or a hook, and both were only ever called from step methods in their own trait. Calling them from outside the context object no longer works; calling them from inside it is unchanged.

| Method | Was | Now |
| --- | --- | --- |
| `KeyboardTrait::keyboardPressKeyOnElementSingle()` | `public` | `protected` |
| `FileDownloadTrait::fileDownloadAssertLinkPresent()` | `public` | `protected` |

`DateTrait::dateRelativeProcessValue()` and `ResponsiveTrait::responsiveSetBreakpoints()` stay public and are now documented as API in their docblocks. `DateTrait` also stays static on purpose: `dateNow()` is the supported seam for pinning the clock, and overriding it in your `FeatureContext` still works exactly as before.

### Constants carry their trait prefix

PHP treats two composed traits declaring the same constant name as a fatal error, so a generic name like `IMPACT_CRITICAL` is a collision waiting to happen in someone else's context.

| Constant | Replacement |
| --- | --- |
| `AccessibilityTrait::IMPACT_CRITICAL` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_CRITICAL` |
| `AccessibilityTrait::IMPACT_SERIOUS` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_SERIOUS` |
| `AccessibilityTrait::IMPACT_MODERATE` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_MODERATE` |
| `AccessibilityTrait::IMPACT_MINOR` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_MINOR` |
| `Drupal\BigPipeTrait::DEFAULT_WAIT_TIMEOUT` | `Drupal\BigPipeTrait::BIG_PIPE_DEFAULT_WAIT_TIMEOUT` |

`Drupal\HelperTrait::HELPER_ENTITY_CLEANUP_EXCLUDED_TYPES`, renamed in the section above, is also now explicitly `protected` instead of implicitly public.

### `FieldTrait` no longer re-exports the keyboard steps

`FieldTrait` composed `KeyboardTrait` without calling it, so a context composing only `FieldTrait` silently received every keyboard step. That composition is gone. If your context relies on those steps, compose the trait directly:

```php
use DrevOps\BehatSteps\KeyboardTrait;

class FeatureContext extends DrupalContext {

  use FieldTrait;
  use KeyboardTrait;

}
```

### Hooks declare their scope parameter

Hook methods used to come in 3 shapes: taking and using the scope, taking and ignoring it, or declaring no parameter at all. They all declare it now, used or not, so there's one signature to match when you override one. If you override any of these in your `FeatureContext`, add the parameter:

| Hook | New signature |
| --- | --- |
| `AccessibilityTrait::accessibilityAggregateRender()` | `(AfterSuiteScope $scope)` |
| `AccessibilityTrait::accessibilityAggregateReset()` | `(BeforeSuiteScope $scope)` |
| `AccessibilityTrait::accessibilityCaptureBaseDir()` | `(BeforeSuiteScope $scope)` |
| `CommandTrait::commandAfterScenario()` | `(AfterScenarioScope $scope)` |
| `CommandTrait::commandBeforeScenario()` | `(BeforeScenarioScope $scope)` |
| `Drupal\BigPipeTrait::bigPipeWaitBeforeStep()` | `(BeforeStepScope $scope)` |
| `JsonTrait::jsonAfterScenario()` | `(AfterScenarioScope $scope)` |
| `JsonTrait::jsonBeforeScenario()` | `(BeforeScenarioScope $scope)` |
| `XmlTrait::xmlAfterScenario()` | `(AfterScenarioScope $scope)` |
| `XmlTrait::xmlBeforeScenario()` | `(BeforeScenarioScope $scope)` |

### Properties declare native types

5 properties relied on a `@var` docblock with no native type. They're typed now, which narrows what a subclass may assign to them.

| Property | Type |
| --- | --- |
| `Drupal\FileTrait::$filesUnmanagedUris` | `array` |
| `Drupal\RedirectTrait::$redirectAllowedStatusCodes` | `array` |
| `Drupal\WatchdogTrait::$watchdogMessageTypes` | `array` |
| `Drupal\WatchdogTrait::$watchdogScenarioStartTime` | `?int` |
| `FileDownloadTrait::$fileDownloadDownloadedFileInfo` | `array` |
