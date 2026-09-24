# Migration guide

## Per-trait configuration

Every configurable trait now declares its options in a `<prefix>ConfigSchema()` method, and the extension carries their profile-wide defaults under a new `steps` section. Each group is named after the trait that declares it, in snake case: `JavascriptTrait` reads `javascript`, `BigPipeTrait` reads `big_pipe`, `FileDownloadTrait` reads `file_download`. [STEPS.md](STEPS.md) lists the options of each trait beside its steps.

An option resolves through the declaration default, then `behat_steps: steps:`, then the context's `config` argument, then the feature tag, then the scenario tag.

### Three options moved out of the extension root

They are read only by a trait, never by a container service, so they became overridable per context.

| Before | After |
| --- | --- |
| `'ajax_timeout' => 5` | `'steps' => ['wait' => ['ajax_timeout' => 5]]` |
| `'selectors' => ['messages' => [...]]` | `'steps' => ['message' => ['selectors' => [...]]]` |
| `'mappings' => ['paths' => [...]]` | `'steps' => ['mapping' => ['groups' => ['paths' => [...]]]]` |

```php
// Before.
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'ajax_timeout' => 5,
  'selectors' => [
    'messages' => ['default' => '.messages', 'error' => '.messages--error'],
    'logged_in_selector' => 'body.user-logged-in',
  ],
  'mappings' => ['paths' => ['User Login' => '/user/login']],
]));

// After.
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'selectors' => ['logged_in_selector' => 'body.user-logged-in'],
  'steps' => [
    'wait' => ['ajax_timeout' => 5],
    'message' => ['selectors' => ['default' => '.messages', 'error' => '.messages--error']],
    'mapping' => ['groups' => ['paths' => ['User Login' => '/user/login']]],
  ],
]));
```

The other `selectors` keys, `login_form_selector` and `logged_in_selector`, stay where they are: a container service reads them. A `selectors: messages:` left behind fails the container build with a message naming its new path, because the `selectors` node keeps the keys it does not declare and would otherwise accept it and never read it.

### `WebRawContext` takes a `config` argument

`WebRawContext::__construct(array $config = [])` is the new constructor, and every shipped and consuming context inherits it without redeclaring one. A context that already declares a constructor adds the parameter and forwards it:

```php
// Before.
class UiContext extends WebRawContext {

  public function __construct(protected string $fixtures_path) {
  }

}

// After.
class UiContext extends WebRawContext {

  public function __construct(protected string $fixtures_path, array $config = []) {
    parent::__construct($config);
  }

}
```

A group or an option the context cannot serve is an error naming what it accepts, so a typo fails while Behat builds the context. The extension's `steps` section is read permissively instead: a group there may name a trait only one of the registered contexts composes.

### `getMapping()` became `mappingGetValue()`

The mapping lookup moved off `ParametersTrait` and onto `MappingTrait`, which is where the groups it reads are now configured. A context that calls it directly renames the call; a context that only uses the `{{ Key }}` token is unaffected.

| Before | After |
| --- | --- |
| `$this->getMapping('User Login')` | `$this->mappingGetValue('User Login')` |

### `@error` no longer disables the Watchdog check

`WatchdogTrait` and `JavascriptTrait` each split their single switch into two independent options, which the existing tags now map onto.

| Switch | Extension or context | Scenario tag |
| --- | --- | --- |
| Do not collect at all | `'watchdog' => ['enabled' => FALSE]` | `@behat-steps-skip:WatchdogTrait` |
| Collect, do not fail | `'watchdog' => ['fail_on_errors' => FALSE]` | `@error` |
| Do not collect at all | `'javascript' => ['enabled' => FALSE]` | `@behat-steps-skip:JavascriptTrait` |
| Collect, do not fail | `'javascript' => ['fail_on_errors' => FALSE]` | `@js-errors` |

`@error` used to leave the start time unset, which disabled collection entirely. It now means `fail_on_errors = FALSE`: the errors the scenario logged are still read and cleared from the `watchdog` table, and the scenario is not failed. A scenario that relied on `@error` leaving rows behind for a later assertion reads them before the scenario ends, or uses `@behat-steps-skip:WatchdogTrait` instead.

`@error` and `@js-errors` are also read on the `Feature:` line now, as every other tag of this package already was, so either one there covers every scenario in that feature.

### Three transform traits can be switched off

`RandomTrait`, `MappingTrait` and `DateTrait` register suite-wide `#[Transform]` callbacks, which used to rewrite every matching step argument and table cell in the run with no way to opt out. `@behat-steps-skip:RandomTrait` silently did nothing; it now suppresses the transform, as do `@behat-steps-skip:MappingTrait` and `@behat-steps-skip:DateTrait` and the matching `enabled` option.

Each of the three resolves that decision in a `BeforeScenario` hook, so all three now require the composing context to extend `WebRawContext`. `DateTrait` and `RandomTrait` previously composed into any class.

`ModalTrait`, `TableTrait`, `ElementTrait` and `CommandTrait` require `WebRawContext` for the same reason: they read a declared option.

### `BigPipeTrait` reads its timeout from configuration

`BIG_PIPE_DEFAULT_WAIT_TIMEOUT` is gone, and `$bigPipeWaitTimeout` now defaults to `NULL`, meaning "take the configured option". Assigning it still overrides the wait for one scenario.

| Before | After |
| --- | --- |
| `$this->bigPipeWaitTimeout = 2000;` | Unchanged, or `'steps' => ['big_pipe' => ['wait_timeout' => 2000]]` |
| `self::BIG_PIPE_DEFAULT_WAIT_TIMEOUT` | `'steps' => ['big_pipe' => ['wait_timeout' => 10000]]` |

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

## I-prefixed step text and placeholder types

A further six steps changed, on two conventions the unification pass above did not cover: a `Given` or `Then` step does not begin with `I`, and a placeholder names the value's role rather than its type. The two sets do not overlap - check both when upgrading.

A `Given` step states a precondition rather than narrating an action, so it does not begin with `I`:

| Before | After |
| --- | --- |
| `Given I accept all confirmation dialogs` | `Given confirmation dialogs are accepted` |
| `Given I do not accept any confirmation dialogs` | `Given confirmation dialogs are declined` |

A `Then` step begins with the entity being asserted rather than with `I`:

| Before | After |
| --- | --- |
| `Then I should see the modal` | `Then the modal should be displayed` |
| `Then I should not see the modal` | `Then the modal should not be displayed` |

Placeholders name the value's role rather than its type, so `:number` became `:offset`:

| Before | After |
| --- | --- |
| `Then the element :selector should be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should be displayed within a viewport with a top offset of :offset pixels` |
| `Then the element :selector should not be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should not be displayed within a viewport with a top offset of :offset pixels` |

This also renames the `$number` argument of `ElementTrait::elementAssertVisuallyVisibleWithOffset()` and `ElementTrait::elementAssertNotVisuallyVisibleWithOffset()` to `$offset`, which matters only if you call either method with named arguments.

## Optional dependencies moved to `require-dev` and `suggest`

Trait-specific packages are no longer hard `require` dependencies. They now live in `require-dev` (so this library's own test suite still runs) and `suggest`, matching the existing treatment of `justinrainbow/json-schema`. Projects that relied on transitive installation must add the packages they use to their own `composer.json`.

| Package | Add it to your `require-dev` when you use |
| --- | --- |
| `drupal/drupal-extension` | any Drupal trait (`DrevOps\BehatSteps\Steps\Drupal\*`) |
| `softcreatr/jsonpath` | `JsonTrait` JSON path steps (`the JSON path ... should ...`) |

`@javascript` scenarios need a JavaScript-capable Mink driver. The steps are driver agnostic, so install **one** of these interchangeable drivers - both run the full `@javascript` suite and both are exercised by this library's CI:

- `lullabot/mink-selenium2-driver` - drives a Selenium/WebDriver server.
- `dmore/behat-chrome-extension` - drives headless Chrome directly over the Chrome DevTools Protocol, with no Selenium server.

`behat/behat` and `behat/mink` remain hard `require` dependencies. For example, a project that uses the Drupal traits and runs JavaScript scenarios with headless Chrome adds:

```bash
composer require --dev drupal/drupal-extension dmore/behat-chrome-extension
```

## Behat extensions registered in the Behat configuration

`drupal/drupal-extension` is no longer a dependency, so the two extensions it supplied are replaced by two this package supplies:

| Old | New |
| --- | --- |
| `Drupal\MinkExtension` | `DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension` |
| `Drupal\DrupalExtension` | `DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension` |

Both keep their configuration keys and option trees, so every option under them - `base_url`, `files_path`, `javascript_session`, `selenium2`, `browserkit_http`, `drupal_root` - is set exactly as before. The three driver-selection keys are the exception; see [Capability-based driver resolution](#capability-based-driver-resolution).

`MinkExtension` wraps `Behat\MinkExtension\ServiceContainer\MinkExtension` and replaces the factory behind `browserkit_http` so the driver runs on Drupal's own `DrupalTestBrowser` rather than a plain Symfony `HttpBrowser`. Without it a session reaches Drupal without the cookie handling a login depends on.

`ajax_timeout` belongs on `BehatStepsExtension`:

```yaml
extensions:
  DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
    ajax_timeout: 10
```

Setting it on `MinkExtension` still works and still applies, and reports itself as deprecated.

## Capability-based driver resolution

`@api` no longer selects a driver, and the `default_driver`, `api_driver` and `drush_driver` options are replaced by one `drivers` list under `behat_steps`. That list names the drivers a scenario may reach, in precedence order, and a step resolves the driver by the capability it needs.

| Before | After |
| --- | --- |
| `'default_driver' => 'blackbox'` | A configuration that declares no `drivers` list gets every registered driver, in registration order |
| `'api_driver' => 'drupal'` | `'drivers' => ['drupal', 'blackbox']` |
| `'drush_driver' => 'drush'` | Add `'drush'` to the `drivers` list |
| `@api` on a scenario | Nothing. A step that needs Drupal resolves `CoreCapabilityInterface` from the configured list |
| `@drush` on a scenario | Nothing. `DrushTrait` resolves `DrushCapabilityInterface` |

```php
// Before.
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'default_driver' => 'blackbox',
  'api_driver' => 'drupal',
  'drush_driver' => 'drush',
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));

// After.
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drivers' => ['drupal', 'drush', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));
```

Every name in the list has to be a driver the extension registers, so a `drivers` entry keeps company with the settings block that registers it: `drupal` needs `drupal:`, `drush` needs `drush:`, and `blackbox` is always registered. Naming one without its block fails the container build.

Then remove `@api` from every scenario and feature. It is not a tag of this package any more, and a configuration that lists a Drupal driver reaches Drupal without it.

To run one scenario against a different driver, tag it `@driver:NAME`, where `NAME` is a name the `drivers` list holds. The tag moves that driver to the front of the scenario's order; it never adds a driver the list does not hold. A name outside the list fails at scenario start.

An entry may be keyed, so a profile can swap the implementation behind a name without editing any Gherkin: `'drivers' => ['api' => 'drupal', 'blackbox']` in one profile and `'drivers' => ['api' => 'acme-jsonapi', 'blackbox']` in another both answer `@driver:api`. Restricting a run to a narrower driver set is a profile's job - the package reads nothing from a Behat suite's settings.

```gherkin
@driver:drush
Scenario: The cache is cleared over the command line
  Given the cache is empty
```

Two consequences are worth checking in an existing project:

- **A step that used to fail for a missing `@api` now succeeds.** The tag no longer gates anything, so a scenario that reached a Drupal step without it used to throw and now runs. Where that gate was load-bearing, run those scenarios under a profile whose `drivers` list excludes the Drupal driver.
- **`RawContext::assertDrupal()` is gone.** A custom step that called it calls `$this->driverFor(CoreCapabilityInterface::class);` instead. `RawContext::getDriver()` now takes a name and no longer defaults to "the current driver"; a call with no argument becomes `driverFor()` naming the capability the caller needs.

## DrupalExtension step text mapped to the v4 vocabulary

The Drupal Extension's contexts are gone. Their behaviour lives in the step traits, re-expressed in the one grammar the docs linter enforces: tuple placeholders, no regex, no optional words, and a `Then` that starts with the subject rather than `I`.

The suite registers `Behat\MinkExtension\Context\MinkContext` for the base browser vocabulary, so `I am on`, `I go to`, `I should see`, `I fill in`, `I press`, `I follow`, `I check`, `I select`, `I attach the file`, `the response status code should be` and the other upstream Mink steps are unchanged. The table below covers only the steps the Drupal Extension added on top.

### Session and users

| Before | After |
| --- | --- |
| `Given I am an anonymous user` | `Given the user is anonymous` |
| `Given I am not logged in` | `Given the user is anonymous` |
| `When I log out` | `When I log out` |
| `Given I am logged in as a user with the :role role(s)` | `When I log in as a user with the :roles role(s)` |
| `Given I am logged in as a/an :role` | `When I log in as a user with the :roles role(s)` |
| `Given I am logged in as a user with the :role role(s) and I have the following fields:` | `When I log in as a user with the :roles role(s) and the following fields:` |
| `Given I am logged in as a user with the :permissions permission(s)` | `When I log in as a user with the :permissions permission(s)` |
| `Given I am logged in as :name` | `When I log in as the user :name` |
| `Given the following users:` | `Given the following users exist:` |

### Content, terms, entities and languages

| Before | After |
| --- | --- |
| `Given the following :type content:` | `Given the following :content_type content exist:` |
| `Given the following :vocabulary terms:` | `Given the following :vocabulary terms exist:` |
| `Given the following :type entities:` | `Given the following :entity_type entities exist:` |
| `Given the/these (following )languages are available:` | `Given the following languages exist:` |
| `Given a/an :type with the title :title` | `Given the following :content_type content exist:` then `When I visit the :content_type content page with the title :title` |
| `Given a/an :type content with the title :title` | as above |
| `Given I am viewing a/an :type with the title :title` | as above |
| `Given I am viewing a/an :type content with the title :title` | as above |
| `Given I am viewing a/an :type with the following fields:` | `Given the following :content_type content exist:` then `When I visit the :content_type content page with the title :title` |
| `Given I am viewing a/an :type content with the following fields:` | as above |
| `Given I am viewing my :type with the title :title` | `Given the following :content_type content exist:` with an `author` column, then visit the page |
| `Given I am viewing my :type content with the title :title` | as above |
| `Given a/an :vocabulary term with the name :name` | `Given the following :vocabulary terms exist:` then `When I visit the :vocabulary term page with the name :term_name` |
| `Given I am viewing a/an :vocabulary term with the name :name` | as above |
| `Then I should be able to edit the :type` | `Given the following :content_type content exist:`, `When I visit the :content_type content edit page with the title :title`, `Then the response status code should be 200` |
| `Then I should be able to edit the :type content` | as above |

### Cache, cron, batch and queues

| Before | After |
| --- | --- |
| `Given the cache has been cleared` | `Given the cache is empty` |
| `Given I run cron` | `When I run cron` |
| `Given I wait for the batch job to finish` | `When I wait for the batch job to finish` |
| `Given the following item is in the system queue:` | `Given the following item is in the :queue queue:` |

### Navigation, buttons, headings and fields

| Before | After |
| --- | --- |
| `Given I am at :path` | `When I visit :path` then `Then the response status code should be 200` |
| `When I visit :path` | `When I visit :path` |
| `When I click :link` | `When I follow :link` (Mink) |
| `Given for :field I enter :value` | `When I fill in :field with :value` (Mink) |
| `Given I enter :value for :field` | `When I fill in :value for :field` (Mink) |
| `When I press the :button button` | `When I press :button` (Mink) |
| `Given I check the box :checkbox` | `When I check :checkbox` (Mink) |
| `Given I uncheck the box :checkbox` | `When I uncheck :checkbox` (Mink) |
| `When I select the radio button :label` | `When I choose the radio button :selector` |
| `When I select the radio button :label with the id :id` | `When I choose the radio button :selector` with the id as the selector |
| `Given I press the :char key in the :field field` | `When I press the key :key on the element :selector` |
| `When I :action details labelled :summary` | `When I click on the element :selector` targeting the `summary` element |
| `When I drag element :source onto element :target` | dropped; use a JavaScript step in the project's own context |
| `Then I should get a :code HTTP response` | `Then the response status code should be :code` (Mink) |
| `Then I should not get a :code HTTP response` | `Then the response status code should not be :code` (Mink) |
| `Then I should see the text :text` | `Then I should see :text` (Mink) |
| `Then I should not see the text :text` | `Then I should not see :text` (Mink) |
| `Then I should see the link :link` | `Then the link :link with the href :href should exist` - the replacement matches on the target too, so supply the URL the link points at |
| `Then I should not see the link :link` | `Then the link :link with the href :href should not exist` - as above |
| `Then I should not visibly see the link :link` | `Then the element :selector should not be displayed` |
| `Then I should see the button :button` | `Then the button :button should exist` |
| `Then I should see the :button button` | `Then the button :button should exist` |
| `Then I should not see the button :button` | `Then the button :button should not exist` |
| `Then I should not see the :button button` | `Then the button :button should not exist` |
| `Then I should see the heading :heading` | `Then the heading :heading should exist` |
| `Then I should not see the heading :heading` | `Then the heading :heading should not exist` |

### Regions

Every region step drops the optional `( region)` suffix and names the region last, so one phrasing covers each action.

| Before | After |
| --- | --- |
| `When I follow/click :link in the :region( region)` | `When I click the link :link in the region :region` |
| `Given I press :button in the :region( region)` | `When I press the button :button in the region :region` |
| `Given I fill in :field with :value in the :region( region)` | `When I fill in the field :field with :value in the region :region` |
| `Given I fill in :value for :field in the :region( region)` | `When I fill in the field :field with :value in the region :region` |
| `Given I check :locator in the :region( region)` | `When I check the checkbox :checkbox in the region :region` |
| `Given I uncheck :checkbox in the :region( region)` | `When I uncheck the checkbox :checkbox in the region :region` |
| `Then I should see( the text) :text in the :region( region)` | `Then the region :region should contain the text :text` |
| `Then I should not see( the text) :text in the :region( region)` | `Then the region :region should not contain the text :text` |
| `Then I should see the heading :heading in the :region( region)` | `Then the region :region should contain the heading :heading` |
| `Then I should see the :heading heading in the :region( region)` | `Then the region :region should contain the heading :heading` |
| `Then I should see the link :link in the :region( region)` | `Then the link :link should exist in the region :region` |
| `Then I should not see the link :link in the :region( region)` | `Then the link :link should not exist in the region :region` |
| `Then I should see the button :button in the :region( region)` | `Then the button :button should exist in the region :region` |
| `Then I should see the :button button in the :region( region)` | `Then the button :button should exist in the region :region` |
| `Then I should not see the button :button in the :region( region)` | `Then the button :button should not exist in the region :region` |
| `Then I should not see the :button button in the :region( region)` | `Then the button :button should not exist in the region :region` |
| `Then I should see the :tag element in the :region( region)` | `Then the element :selector should exist in the region :region` |
| `Then I should not see the :tag element in the :region( region)` | `Then the element :selector should not exist in the region :region` |
| `Then I should see :text in the :tag element in the :region( region)` | `Then the element :selector in the region :region should have the text :text` |
| `Then I should not see :text in the :tag element in the :region( region)` | `Then the element :selector in the region :region should not have the text :text` |
| `Then I should see the :tag element with the :attribute attribute set to :value in the :region( region)` | `Then the element :selector in the region :region should have the attribute :attribute with the value :value` |
| `Then I should see :text in the :tag element with the :attribute attribute set to :value in the :region( region)` | `Then the element :selector with the text :text in the region :region should have the attribute :attribute with the value :value` |
| `Then I should see :text in the :tag element with the :property CSS property set to :value in the :region( region)` | `Then the element :selector with the text :text in the region :region should have the CSS property :property with the value :value` |

### Messages

The `( containing)` variants are gone: every message step matches on a substring, which is what both forms always did.

| Before | After |
| --- | --- |
| `Then I should see the message( containing) :message` | `Then the message :message should exist` |
| `Then I should not see the message( containing) :message` | `Then the message :message should not exist` |
| `Then I should see the error message( containing) :message` | `Then the error message :message should exist` |
| `Then I should not see the error message( containing) :message` | `Then the error message :message should not exist` |
| `Then I should see the success message( containing) :message` | `Then the success message :message should exist` |
| `Then I should not see the success message( containing) :message` | `Then the success message :message should not exist` |
| `Then I should see the warning message( containing) :message` | `Then the warning message :message should exist` |
| `Then I should not see the warning message( containing) :message` | `Then the warning message :message should not exist` |
| `Then I should see the following error message(s):` | `Then the following error messages should exist:` |
| `Then I should not see the following error messages:` | `Then the following error messages should not exist:` |
| `Then I should see the following success messages:` | `Then the following success messages should exist:` |
| `Then I should not see the following success messages:` | `Then the following success messages should not exist:` |
| `Then I should see the following warning message(s):` | `Then the following warning messages should exist:` |
| `Then I should not see the following warning messages:` | `Then the following warning messages should not exist:` |

The message tables lose their header row: each row is a message, with no `error messages` heading cell.

### Table rows

| Before | After |
| --- | --- |
| `Given I click :link in the :rowText row` | `When I click the link :link in the row :row_text` |
| `Given I press :button in the :rowText row` | `When I press the button :button in the row :row_text` |
| `Then I should see the text :text in the :rowText row` | `Then the row :row_text should contain the text :text` |
| `Then I should not see the text :text in the :rowText row` | `Then the row :row_text should not contain the text :text` |
| `Then I should see the :link in the :rowText row` | `Then the link :link should exist in the row :row_text` |
| `Then I should not see the :link in the :rowText row` | `Then the link :link should not exist in the row :row_text` |

### Mail

`Steps\Drupal\EmailTrait` carries the mail vocabulary. It collects mail through Drupal's test mail collector, so a scenario enables collection with the `@email` tag or `When I enable the test email system`, and the assertions read the collected messages.

The Drupal Extension's `new` mail family tracked messages sent since the previous assertion. Clear the queue explicitly instead: `When I clear the test email system queue` leaves only the messages a later action produces.

| Before | After |
| --- | --- |
| `When I send the following mail:` | dropped; trigger the site behaviour that sends the mail |
| `When I send the following email:` | dropped; trigger the site behaviour that sends the mail |
| `Then the following (e)mail(s) should have been sent:` | `Then the email field :field should contain:` |
| `Then the following (e)mail(s) should have been sent to :to:` | `Then an email should be sent to the address :address with the content:` |
| `Then the following (e)mail(s) should have been sent with the subject :subject:` | `Then the email field :field should be:` against `subject` |
| `Then the following (e)mail(s) should have been sent to :to with the subject :subject:` | the two steps above, combined |
| `Then the following new (e)mail(s) should have been sent...` | clear the queue, then use the non-`new` step |
| `Then there should be a total of :count (e)mail(s) sent` | `Then the number of sent emails should be :count` |
| `Then there should be a total of :count (e)mail(s) sent to :to` | `Then the number of emails sent to the address :address should be :count` |
| `Then there should be a total of :count (e)mail(s) sent with the subject :subject` | `Then the number of emails sent with the subject :subject should be :count` |
| `Then there should be a total of :count new (e)mail(s) sent...` | clear the queue, then use the non-`new` step |
| `Then (a )(an )(e)mail(s) should have been sent with the attachment(s) :attachments` | `Then the file :file_name should be attached to the email with the subject :subject` |
| `Then (a )(an )(e)mail(s) should have been sent to :to with the attachment(s) :attachments` | as above |
| `When I follow the link to :urlFragment from the (e)mail` | `When I follow the link containing :url_fragment in the email` |
| `When I follow the link to :urlFragment from the (e)mail to :to` | as above |
| `When I follow the link to :urlFragment from the (e)mail with the subject :subject` | `When I follow link number :link_number in the email with the subject :subject` |

### Config

| Before | After |
| --- | --- |
| `Given I set the configuration item :name with key :key to :value` | `Given the config :name key :key has the value :value` |
| `Given I set the configuration item :name with key :key with the following values:` | `Given the following config values exist:` |

### Drush

| Before | After |
| --- | --- |
| `Given I run drush :command` | `When I run the drush command :command` |
| `Given I run drush :command :arguments` | `When I run the drush command :command with the arguments :arguments` |
| `Given I run the failing drush command :command` | `When I run the failing drush command :command` |
| `Given I run the failing drush command :command :arguments` | `When I run the failing drush command :command with the arguments :arguments` |
| `When I print the last drush output` | `When I print the last drush output` |
| `Then the drush output should contain :output` | `Then the drush output should contain the value :value` |
| `Then the drush output should not contain :output` | `Then the drush output should not contain the value :value` |
| `Then the drush output should match :regex` | `Then the drush output should match the pattern :pattern` |

### AJAX and debugging

| Before | After |
| --- | --- |
| `Given I wait for AJAX to finish` | `When I wait for AJAX to finish` |
| `When (I )break` | dropped; use a debugger or `When I print last response` (Mink) |

Random-value tokens (`[?name:type]`) and mapping tokens (`{{ Key }}`) are unchanged: `Steps\Web\RandomTrait` and `Steps\Web\MappingTrait` carry them, and a context composes the trait instead of registering `RandomContext` or `MappingContext`.

## Unified entity cleanup

Every entity a creation step or the driver creates is registered on `DrupalApiTrait` and deleted in reverse creation order by one `cleanEntities` hook. There is no second registry and no exclusion list, so a node, a term and a media item created in one scenario come down in the order that respects the references between them.

An entity a project saves through Drupal's API in its own step joins that teardown only when the step registers it, which it does with `$this->entityRegister($entity)`. Without that call the entity survives the scenario.

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

To skip cleanup of every registered entity at once, use `@behat-steps-skip:cleanEntities`. The companion hooks take `@behat-steps-skip:cleanUsers` and `@behat-steps-skip:cleanRoles`.

`FileTrait` keeps its own `@behat-steps-skip:fileAfterScenario` tag, which now covers only unmanaged files; managed file entities it creates are cleaned up by the shared registry and can be kept with `@behat-steps-entity-cleanup-skip:file`.

## Trait namespaces re-rooted under `Steps`

The step vocabulary now lives in one subtree, split by the context each trait needs. Generic traits moved from `DrevOps\BehatSteps\` to `DrevOps\BehatSteps\Steps\Web\`, and Drupal traits from `DrevOps\BehatSteps\Drupal\` to `DrevOps\BehatSteps\Steps\Drupal\`. The trait names themselves are unchanged, so a consumer context only has to update its `use` statements:

```php
// Before.
use DrevOps\BehatSteps\CookieTrait;
use DrevOps\BehatSteps\Drupal\ContentTrait;

// After.
use DrevOps\BehatSteps\Steps\Web\CookieTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
```

`DrevOps\BehatSteps\Exception\AssertionException` did not move.

## The context layer is one chain

`RawContext` used to carry the Drupal entity lifecycle, so a non-Drupal project extending it inherited a class that knew about taxonomy vocabularies. It is gone. `WebRawContext` is the root, and each class below it adds one half of the vocabulary:

```
        Behat\MinkExtension\Context\RawMinkContext
                          |
                    WebRawContext
                          |
                     WebContext
                          |
                   DrupalContext
```

| Extend | When |
| --- | --- |
| `DrupalContext` | The suite tests a Drupal site and wants all 57 step traits |
| `WebContext` | The suite tests a web page and wants the 28 web step traits |
| `WebRawContext` | The project picks its own traits, and composes `DrupalApiTrait` when it needs the Drupal lifecycle |

A context that extended `RawContext` extends `WebRawContext` instead:

```php
// Before.
class UiContext extends RawContext {

  use JavascriptTrait;
  use WaitTrait;

}

// After.
class UiContext extends WebRawContext {

  use JavascriptTrait;
  use WaitTrait;

}
```

### The Drupal lifecycle moved into `DrupalApiTrait`

These methods moved from `RawContext` into `DrevOps\BehatSteps\Helper\DrupalApiTrait`: `nodeCreate()`, `userCreate()`, `termCreate()`, `entityCreate()`, `languageCreate()`, `entityRegister()`, `parseEntityFields()`, `login()`, `logout()`, `loggedIn()`, `getUserManager()`, `setUserManager()`, `cleanEntities()`, `cleanUsers()`, `cleanRoles()`, `clearStaticCaches()` and `alterNodeParameters()`. `DrupalContext` composes the trait, so a context extending it needs no change.

A context that wants the lifecycle without the Drupal vocabulary composes the trait and declares `DrupalApiInterface`, the contract the Drupal step traits require of their host:

```php
class SpecContext extends WebRawContext implements DrupalApiInterface {

  use DrupalApiTrait;

}
```

`UserAwareInterface` is gone: `getUserManager()` and `setUserManager()` are declared on `DrupalApiInterface`, and the context initializer injects the user manager into any context implementing it.

### One context registers, not two

`DrupalContext` used to ship 7 of the Drupal traits and 10 of the web ones. It now extends `WebContext` and composes all 29 `Steps\Drupal` traits on top of its 28 web ones, so `$suite->addContext(DrupalContext::class)` alone gives a Drupal suite all 57 step traits.

Registering `WebContext` beside `DrupalContext` is fatal, because the 28 web traits would register their steps twice. `WebContext::assertOneContext()` runs on `BeforeSuite` and names the real mistake rather than letting Behat report a `RedundantStepException` about an arbitrary step.

Registering either context beside a hand-composed context that already carries one of the same traits is a `RedundantStepException` too: two registered contexts cannot compose the same trait. Drop the trait from the hand-composed context, or register the shipped context instead of it.

There is no way to remove an inherited step, so a Drupal project cannot take the Drupal step traits without the 28 web ones. A project whose own step text collides with a shipped web step drops to `WebRawContext` and composes what it wants by hand.

Scoped configuration follows the chain. `WebContext` accepts the `javascript`, `modal`, `wait`, `message`, `mapping` and `diagnostics` groups, and `DrupalContext` accepts those plus `watchdog`, `big_pipe`, `cache`, `queue` and `email`. A group no trait in the chain declares is an error at construction, naming what that context does accept.

## Traits declare the host they need

Every trait that reaches beyond its own methods states what it needs from its host. A web trait carries `@phpstan-require-extends`, naming `Behat\MinkExtension\Context\RawMinkContext` when a Mink session is all it touches and `DrevOps\BehatSteps\Behat\Context\WebRawContext` when it reads the driver or the extension configuration. A Drupal trait carries both that annotation and `@phpstan-require-implements \DrevOps\BehatSteps\Behat\Context\DrupalApiInterface`, because a trait cannot implement an interface but the class composing it can.

Composition is unchanged at run time, but a project running PHPStan gets an error when a context uses a trait without extending the class or declaring the interface that trait needs. The fix is to extend the named class and declare the named interface, which is what the trait already assumed.

## Every trait carries a marker

`DrevOps\BehatSteps\Attribute\Steps` marks a trait that registers Gherkin, and `DrevOps\BehatSteps\Attribute\Helper` marks one that registers none. Every trait under `src/Steps` carries `#[Steps]`, every trait under `src/Helper` carries `#[Helper]`, and `scripts/lint-markers.php` fails a trait that carries neither, a `#[Steps]` trait composing another `#[Steps]` trait, or a `#[Helper]` trait registering a step.

A consuming project does not have to mark its own traits. The markers route the reference documentation and hold the rules that `CONTRIBUTING.md` used to state in prose.

## Step traits no longer compose other step traits

Shared logic lives in step-free helper traits under `DrevOps\BehatSteps\Helper`, each named for one concern, so that composing one trait cannot pull in another trait's steps.

| Trait | Old | New |
| --- | --- | --- |
| `Steps\Drupal\ContentTrait` | `contentLoadMultiple()` | `Helper\DrupalApiTrait::loadNodeIds()` |
| `Steps\Web\RestTrait` | `$restHeaders` | `Helper\RequestHeadersTrait::$requestHeaders`, read and written through `setRequestHeader()`, `unsetRequestHeader()`, `getRequestHeaders()` and `resetRequestHeaders()` |

`Steps\Drupal\SearchApiTrait` composed `ContentTrait` and so registered every content step alongside its own; it now reads `loadNodeIds()` off its host and registers only the Search API steps. A context that relied on that indirect composition has to compose `ContentTrait` itself.

`Steps\Drupal\ConfigOverrideTrait` set its `X-Config-No-Override` signal on `RestTrait`'s property when it found one. It writes to the header bag instead. The bag is per context, so a suite that wants the signal on `RestTrait`'s own requests composes both traits into one context rather than registering the two shipped ones; the browser header, the `$_SERVER` entry and the environment variable reach the site either way.

A helper trait composed by a step trait and by the context under it holds one slot of state, so both reach the same bag.

### The two `HelperTrait`s became 5 concern-named traits

`Steps\Web\HelperTrait` and `Steps\Drupal\HelperTrait` are gone. Their members live under `DrevOps\BehatSteps\Helper`, each trait named for the one concern it holds, and every method carries a name of its own instead of the `helper` prefix:

| Old member | New member |
| --- | --- |
| `helperSetLastStepLine()` | `Helper\LastStepTrait::setLastStepLine()` |
| `helperIsLastStep()` | `Helper\LastStepTrait::isLastStep()` |
| `$helperLastStepLine` | `Helper\LastStepTrait::$lastStepLine` |
| `helperSetRequestHeader()` | `Helper\RequestHeadersTrait::setRequestHeader()` |
| `helperUnsetRequestHeader()` | `Helper\RequestHeadersTrait::unsetRequestHeader()` |
| `helperGetRequestHeaders()` | `Helper\RequestHeadersTrait::getRequestHeaders()` |
| `helperResetRequestHeaders()` | `Helper\RequestHeadersTrait::resetRequestHeaders()` |
| `$helperRequestHeaders` | `Helper\RequestHeadersTrait::$requestHeaders` |
| `helperFixStepArgument()` | `Helper\StringTrait::fixStepArgument()` |
| `helperNormalizeWhitespace()` | `Helper\StringTrait::normalizeWhitespace()` |
| `helperSplitCommaSeparated()` | `Helper\StringTrait::splitCommaSeparated()` |
| `helperSlug()` | `Helper\StringTrait::slug()` |
| `helperIsJavascriptSupported()` | `Helper\JavascriptSupportTrait::isJavascriptSupported()` |
| `helperTransposeVerticalTable()` | `Helper\DrupalApiTrait::transposeVerticalTable()` |
| `helperBuildHorizontalTable()` | `Helper\DrupalApiTrait::buildHorizontalTable()` |
| `helperExpandEntityFieldsFixtures()` | `Helper\DrupalApiTrait::expandEntityFieldsFixtures()` |
| `helperLooksLikeCompoundCell()` | `Helper\DrupalApiTrait::looksLikeCompoundCell()` |
| `helperExpandCompoundCellFixtures()` | `Helper\DrupalApiTrait::expandCompoundCellFixtures()` |
| `helperResolveFixtureFile()` | `Helper\DrupalApiTrait::resolveFixtureFile()` |
| `helperManagedFileExists()` | `Helper\DrupalApiTrait::managedFileExists()` |
| `helperLoadNodeIds()` | `Helper\DrupalApiTrait::loadNodeIds()` |
| `helperAssertModuleEnabled()` | `Helper\DrupalApiTrait::assertModuleEnabled()` |

A context that composed a `HelperTrait` to reach one of these composes the trait holding it instead:

```php
// Before.
use DrevOps\BehatSteps\Steps\Web\HelperTrait;

// After.
use DrevOps\BehatSteps\Helper\StringTrait;
```

Extending `WebRawContext` needs no `use` statement for the 4 web helper traits, and extending `DrupalContext` needs none for `DrupalApiTrait`: each already composes them.

`setRequestHeader()`, `isJavascriptSupported()` and the `DrupalApiTrait` members named on `DrupalApiInterface` are `public` and published in [HELPERS.md](HELPERS.md). Every other helper stays `protected`.

## Trait methods prefixed with their trait name

Every method a trait contributes now begins with the trait's own name, so that traits mixed into one context cannot collide. Rename any call or override in a consumer context:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\DraggableviewsTrait` | `draggableViewsSaveBundleOrder()` | `draggableviewsSaveBundleOrder()` |
| `Drupal\DraggableviewsTrait` | `draggableViewsFindNode()` | `draggableviewsFindNode()` |
| `Drupal\HelperTrait` | `entityRegister()` | `Helper\DrupalApiTrait::entityRegister()` |
| `Drupal\MenuTrait` | `loadMenuByLabel()` | `menuLoadByLabel()` |
| `Drupal\MenuTrait` | `loadMenuLinkByTitle()` | `menuLoadLinkByTitle()` |
| `WaitTrait` | `waitWaitForSeconds()` | `waitSeconds()` |
| `WaitTrait` | `waitForAjaxToFinish()` | `waitForAjax()` |

Gherkin step text is unchanged, so feature files need no edit for the renames above. One tag does change, because it names the hook method it skips:

| Old tag | New tag |
| --- | --- |
| `@behat-steps-skip:entityCleanupAfterScenario` | `@behat-steps-skip:cleanEntities` |

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
| `MetatagTrait` (all `Then` steps) | `\Exception` | `ExpectationException`; `ElementNotFoundException` when the meta tag itself is missing; `\RuntimeException` when an hreflang alternate page returns an HTTP error |
| `XmlTrait` (`the response should be in XML format`) | `\RuntimeException` | `ExpectationException` |
| `FieldTrait` (`the option ... should (not) exist within the select element ...`) | `\InvalidArgumentException` | `ElementNotFoundException` for a missing select or a missing option, `ExpectationException` for an option that exists but should not |
| `Drupal\CacheTrait` (`the page cache for the path(s) ... is empty`) | `\InvalidArgumentException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...`) | `\InvalidArgumentException` | `\RuntimeException` |
| `TableTrait` (any table step, when the table or the row is missing) | `ExpectationException` | `ElementNotFoundException` |
| `ModalTrait` (`I close the modal`, `I click on ... in the modal`, `the modal should (not) contain ...`, when the close button, the content element or the target element is missing) | `ExpectationException` | `ElementNotFoundException` |
| `FieldTrait` (`I unselect ... from ...` and `the option ... should not be selected within the select element ...`, when the option is missing; `I fill in the multi-value field ...`, when an input row is missing) | `ExpectationException` | `ElementNotFoundException` |
| `XmlTrait` (every `the XML element ...` and `the XML attribute ... on element ...` step, when the element is missing) | `ExpectationException` | `ElementNotFoundException` |
| `JsonTrait` (an invalid JSONPath expression, an invalid regular expression, a count that is not an integer, a schema that is not JSON) | `ExpectationException` | `\RuntimeException` |
| `TableTrait` (`the table ... should be sorted by ... in ... order`, with a direction other than `ascending` or `descending`) | `ExpectationException` | `\RuntimeException` |
| `ElementTrait` (`... with the index ...`, with an index below 1; `... pinned to the top of the viewport within ... pixels`, with a negative tolerance) | `ExpectationException` | `\RuntimeException` |
| `Drupal\EmailTrait` (`I follow link number ...`, with a link number that is not a positive integer) | `ExpectationException` | `\RuntimeException` |
| `FieldTrait` (`I fill in the WYSIWYG field ...`, when the field has no `id` attribute) | `ExpectationException` | `\RuntimeException` |
| `XmlTrait` (`I print last XML response`, when the document cannot be serialised) | `ExpectationException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...` without an element, when nothing has focus) | `ExpectationException` | `\RuntimeException` |
| `Drupal\BlockTrait` (every `Given the block ...` step, when the block does not exist) | `ExpectationException` | `\RuntimeException` |
| `WaitTrait` (`I wait for AJAX to finish` and `I wait for ... second(s) for AJAX to finish`, without a JavaScript driver) | `\RuntimeException` | `UnsupportedDriverActionException` |
| `FieldTrait` (`I fill in the multi-value field ...`, without a JavaScript driver) | `\RuntimeException` | `UnsupportedDriverActionException` |

14 failure messages changed along with their type:

| Step | Was | Now |
| --- | --- | --- |
| `the response should be in XML format` | `Failed to load XML. Errors: ...` | `The response is not valid XML: ...` |
| `the option :option should exist within the select element :selector` | `Element "..." is not found.` / `Option "..." is not found in select "...".` | `Select with id\|name\|label "..." not found.` / `Option in the select "..." with value\|text "..." not found.` |
| `the option :option should not exist within the select element :selector` | `Element "..." is not found.` / `Option "..." is found in select "...", but should not.` | `Select with id\|name\|label "..." not found.` / `The option "..." was found in the select "..." on the page ..., but should not exist.` |
| `I unselect :option from :selector` | `The option "..." was not found in the select "...".` | `Option in the select "..." with value\|text "..." not found.` |
| `the option :option should not be selected within the select element :selector` | `The option "..." was not found in the select "..." on the page ....` | `Option in the select "..." with value\|text "..." not found.` |
| `I fill in the multi-value field :field with the following values:` | `Could not locate input row N for multi-value field "...".` | `Input row of the multi-value field "..." with index "N" not found.` |
| every `the table ...` step, when the table is missing | `Table with selector "..." not found.` | `Table matching css "..." not found.` |
| every `... the row ...` step, when the row is missing | `Table row containing text "..." not found.` | `Table row with text "..." not found.` |
| `I close the modal` | `The modal close button was not found.` | `Modal close button matching css "..." not found.` |
| `I click on :selector in the modal` | `The element "..." was not found in the modal.` | `Element in the modal with css\|id\|name\|title\|alt\|value\|text "..." not found.` |
| `the modal should (not) contain :text` | `The modal content element was not found.` | `Modal content element matching css "..." not found.` |
| every `the XML element ...` and `the XML attribute ... on element ...` step, when the element is missing | `The XML element "..." was not found.` | `XML element matching xpath "..." not found.` |
| `the meta tag should exist with the following attributes:` | `Meta tag with specified attributes was not found: {...}.` | `Meta tag with attributes "{...}" not found.` |
| `the :meta_name meta tag should not contain any HTML tags` | `Meta tag with name or property "..." not found.` | `Meta tag with name\|property "..." not found.` |

The same rule now covers the driver layer and the Behat managers, which used to throw `\InvalidArgumentException` and plain `\Exception` for an invalid argument or an unmet prerequisite. If your project calls the driver or a manager directly and catches on the type, update it:

| Class | Was | Now |
| --- | --- | --- |
| `Driver\Core\Core` (an unknown entity type, bundle, vocabulary, user, language, severity or handler class) | `\InvalidArgumentException` / `\Exception` | `\RuntimeException` |
| `Driver\Core\Field\*Handler` (a malformed field value, an unreadable file, a missing referenced entity) | `\InvalidArgumentException` / `\Exception` | `\RuntimeException` |
| `Behat\Manager\DriverManager::getDriver()` and `setDefaultDriverName()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Behat\Manager\UserManager::getUser()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Behat\Selector\RegionSelector::translateToXPath()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Driver\Exception\CreationAliasResolutionException` | extends `\InvalidArgumentException` | extends `Driver\Exception\Exception` |

`CreationAliasResolutionException` is no longer a `\LogicException`, so a `catch (\InvalidArgumentException)` or `catch (\LogicException)` no longer catches it; catch the class itself.

Behat reports every one of these as a failed step either way, so a scenario that simply runs to a failure behaves the same. Only code that catches a specific type, or asserts on the message text, needs changing.

## Tightened public surface

A handful of trait members exposed more than the surrounding code intended. Each one is reachable from a consuming context, so they're grouped here as breaking changes rather than fixed quietly. A `PublicSurfaceTest` now holds each of these conventions, so the surface stays deliberate from here on.

### The toolbox is now `public`

Visibility marks the API: a `public` method that Behat does not register is the toolbox, listed in [HELPERS.md](HELPERS.md) and covered by semantic versioning, and a `protected` one is an implementation detail. 162 helpers a project calls from its own step definitions were promoted to `public` for this, and the rest stayed `protected`.

PHP refuses to narrow an inherited method, so a context that overrides one of the promoted helpers as `protected` no longer loads:

```
Fatal error: Access level to FeatureContext::bigPipeGetWaitTimeout() must be public (as in class ...)
```

Change the `protected` keyword to `public` on any override of a method [HELPERS.md](HELPERS.md) lists. The body and signature are unchanged, and overriding still works exactly as before.

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

### `FieldTrait` no longer re-exports the keyboard steps

`FieldTrait` composed `KeyboardTrait` without calling it, so a context composing only `FieldTrait` silently received every keyboard step. That composition is gone. If your context relies on those steps, compose the trait directly:

```php
use DrevOps\BehatSteps\Steps\Web\KeyboardTrait;

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

## One shape per naming idea

Method names carried six shapes for "assert the negative", two spellings of "normalize", and two shapes for a consumer override point. They are trait members a consumer calls or overrides, so each is renamed rather than aliased. Gherkin step text, step parameter names and method bodies are unchanged, so no `.feature` file needs an edit.

`CONTRIBUTING.md` states the settled conventions and `tests/phpunit/src/TraitMethodNamingTest.php` enforces them.

### Negation is spelled `Not`, in one slot

`Not` sits immediately after `Assert<Subject>`, directly before the predicate it negates, so a negative name is its positive counterpart with `Not` inserted and nothing else changed. The determiner `No`, the copula `Is`, and antonyms standing in for a negation are gone.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\EmailTrait` | `emailAssertNoMessagesSent()` | `emailAssertMessagesNotSent()` |
| `Drupal\EmailTrait` | `emailAssertNoMessagesSentToAddress()` | `emailAssertMessagesNotSentToAddress()` |
| `Drupal\FileTrait` | `fileAssertUnmanagedHasNoContent()` | `fileAssertUnmanagedNotHasContent()` |
| `Drupal\UserTrait` | `userAssertHasNoRoles()` | `userAssertNotHasRoles()` |
| `Drupal\UserTrait` | `userAssertIsBlocked()` | `userAssertBlocked()` |
| `Drupal\UserTrait` | `userAssertIsNotBlocked()` | `userAssertNotBlocked()` |
| `Drupal\WatchdogTrait` | `watchdogAssertNoErrors()` | `watchdogAssertNotHasErrors()` |
| `ElementTrait` | `elementAssertIsNotPinnedToTop()` | `elementAssertNotPinnedToTop()` |
| `ElementTrait` | `elementAssertIsNotVisible()` | `elementAssertNotVisible()` |
| `ElementTrait` | `elementAssertIsNotVisuallyVisibleWithOffset()` | `elementAssertNotVisuallyVisibleWithOffset()` |
| `ElementTrait` | `elementAssertIsPinnedToTop()` | `elementAssertPinnedToTop()` |
| `ElementTrait` | `elementAssertIsPinnedToTopWithTolerance()` | `elementAssertPinnedToTopWithTolerance()` |
| `ElementTrait` | `elementAssertIsVisible()` | `elementAssertVisible()` |
| `ElementTrait` | `elementAssertIsVisuallyHidden()` | `elementAssertNotVisuallyVisible()` |
| `ElementTrait` | `elementAssertIsVisuallyVisible()` | `elementAssertVisuallyVisible()` |
| `ElementTrait` | `elementAssertIsVisuallyVisibleWithOffset()` | `elementAssertVisuallyVisibleWithOffset()` |
| `ElementTrait` | `elementAssertPinnedToTop()` (protected helper) | `elementAssertPinnedToTopWithin()` |
| `FileDownloadTrait` | `fileDownloadAssertNoZipContainsPartial()` | `fileDownloadAssertZipNotContainsPartial()` |
| `JavascriptTrait` | `javascriptAssertNoErrors()` | `javascriptAssertNotHasErrors()` |
| `JsonTrait` | `jsonAssertResponseIsJson()` | `jsonAssertResponseJson()` |
| `JsonTrait` | `jsonAssertResponseIsNotJson()` | `jsonAssertResponseNotJson()` |
| `LinkTrait` | `linkAssertLinkIsAbsolute()` | `linkAssertAbsolute()` |
| `LinkTrait` | `linkAssertLinkIsNotAbsolute()` | `linkAssertNotAbsolute()` |
| `MetatagTrait` | `metatagAssertNoHtml()` | `metatagAssertNotContainsHtml()` |
| `PathTrait` | `pathAssertUrlHasNoParameter()` | `pathAssertUrlNotHasParameter()` |
| `PathTrait` | `pathAssertUrlHasNoParameterWithValue()` | `pathAssertUrlNotHasParameterWithValue()` |
| `XmlTrait` | `xmlAssertResponseIsXml()` | `xmlAssertResponseXml()` |
| `XmlTrait` | `xmlAssertResponseIsNotXml()` | `xmlAssertResponseNotXml()` |

`ElementTrait::elementAssertPinnedToTop()` appears on both sides of that table. The public step took the name once its copula was dropped, and the protected helper that backs all three pinned-to-top steps moved to `elementAssertPinnedToTopWithin()`, after the tolerance it takes.

Three `Drupal\EmailTrait` methods asserted an exact match under names that gave no way to derive one from the other. They now carry the `Equals` predicate the rest of the library uses.

| Old | New |
| --- | --- |
| `emailAssertMessageField()` | `emailAssertMessageFieldEquals()` |
| `emailAssertMessageFieldNotExact()` | `emailAssertMessageFieldNotEquals()` |
| `emailAssertMessageHeader()` | `emailAssertMessageHeaderEquals()` |

### `ResponseTrait` header assertions read subject first

Two of the four header assertions were verb-first and two subject-first. The existence pair joins the value pair, so `Header` opens the predicate in all four.

| Old | New |
| --- | --- |
| `responseAssertContainsHeader()` | `responseAssertHeaderExists()` |
| `responseAssertNotContainsHeader()` | `responseAssertHeaderNotExists()` |

`responseAssertHeaderContains()` and `responseAssertHeaderNotContains()` are unchanged.

### `Normalize`, not `Normalise`

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\StateTrait` | `stateNormaliseValue()` | `stateNormalizeValue()` |
| `ElementTrait` | `elementNormaliseCssProperty()` | `elementNormalizeCssProperty()` |

### Consumer override points are `Get`-prefixed

A documented override point that supplies a value now reads `<trait>Get<Noun>()`, booleans included. The ones that already did are unchanged.

| Trait | Old | New |
| --- | --- | --- |
| `CommandTrait` | `commandTimeout()` | `commandGetTimeout()` |
| `DiagnosticsTrait` | `diagnosticsHeader()` | `diagnosticsGetHeader()` |
| `DiagnosticsTrait` | `diagnosticsRerunBinary()` | `diagnosticsGetRerunBinary()` |
| `DiagnosticsTrait` | `diagnosticsShowDriver()` | `diagnosticsGetShowDriver()` |
| `DiagnosticsTrait` | `diagnosticsShowJsErrors()` | `diagnosticsGetShowJsErrors()` |
| `DiagnosticsTrait` | `diagnosticsShowRerun()` | `diagnosticsGetShowRerun()` |
| `DiagnosticsTrait` | `diagnosticsShowStatusCode()` | `diagnosticsGetShowStatusCode()` |
| `DiagnosticsTrait` | `diagnosticsShowUrl()` | `diagnosticsGetShowUrl()` |
| `ElementTrait` | `elementScrollIntoViewCenter()` | `elementGetScrollIntoViewCenter()` |
