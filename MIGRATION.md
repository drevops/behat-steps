# Migration guide

This guide takes a project from behat-steps 3.14 to 4.0. Every "before" name in it is one a 3.14 project had: from this package, or from the Drupal Extension 6 and Drupal Driver 3 that its Drupal traits built on, which 4.0 no longer uses.

## Per-trait configuration

Trait options are new in v4. 3.x tuned a trait by overriding a `Get` method in your `FeatureContext`, such as `modalGetSelectors()`, `tableGetHeaderSelector()` or `queueGetProcessLimit()`. Those methods are still there and now return the matching option, so an override still wins; [The toolbox is now `public`](#the-toolbox-is-now-public) covers the visibility it needs. The option itself can be set without code: for a profile under the extension's `steps` section, for 1 context through its `config` argument, and, where the option declares a tag, for 1 feature or scenario. Each group is named after the trait that declares it, in snake case: `JavascriptTrait` reads `javascript`, `BigPipeTrait` reads `big_pipe`, `FileDownloadTrait` reads `file_download`. [STEPS.md](STEPS.md) lists the options of each trait beside its steps, and [Trait options](docs/configuration.md#trait-options) covers how a trait of your own declares and reads one.

An option resolves through the declaration default, then `behat_steps: steps:`, then the context's `config` argument, then the feature tag, then the scenario tag.

### 3 Drupal Extension settings moved under `steps`

`ajax_timeout`, `selectors: messages:` and `mappings` sat at the root of the `Drupal\DrupalExtension` settings. Only a trait reads them, never a container service, so they moved under `steps`, where a context can override them as well.

| Before, under `Drupal\DrupalExtension` | After, under `DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension` |
| --- | --- |
| `ajax_timeout` | `steps.wait.ajax_timeout` |
| `selectors.messages` | `steps.message.selectors` |
| `mappings` | `steps.mapping.groups` |

```yaml
# Before.
default:
  extensions:
    Drupal\DrupalExtension:
      ajax_timeout: 5
      selectors:
        messages:
          default: '.messages'
          error: '.messages--error'
        logged_in_selector: 'body.user-logged-in'
      mappings:
        paths:
          User Login: /user/login

# After.
default:
  extensions:
    DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
      selectors:
        logged_in_selector: 'body.user-logged-in'
      steps:
        wait:
          ajax_timeout: 5
        message:
          selectors:
            default: '.messages'
            error: '.messages--error'
        mapping:
          groups:
            paths:
              User Login: /user/login
```

The same settings in `behat.php`, which Behat 4 requires:

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'selectors' => ['logged_in_selector' => 'body.user-logged-in'],
  'steps' => [
    'wait' => ['ajax_timeout' => 5],
    'message' => ['selectors' => [
      'default' => '.messages',
      'error' => '.messages--error',
    ]],
    'mapping' => ['groups' => ['paths' => ['User Login' => '/user/login']]],
  ],
]));
```

The other `selectors` keys, `login_form_selector` and `logged_in_selector`, stay under `selectors`: a container service reads them. A `selectors: messages:` left behind fails the container build with a message naming its new path, because the `selectors` node keeps the keys it doesn't declare and would otherwise accept it and never read it. An `ajax_timeout` or a `mappings` left at the root fails it too, with `Unrecognized option "ajax_timeout" under "behat_steps"`.

### `WebRawContext` takes a `config` argument

`WebRawContext::__construct(array $config = [])` takes the trait options that differ between 2 contexts of one profile, and every shipped and consuming context inherits it without redeclaring one. The Drupal Extension's `RawDrupalContext` declared no constructor, so a 3.x context that declares its own never called a parent one. Once it extends `WebRawContext`, `WebContext` or `DrupalContext`, it adds the parameter and forwards it:

```php
// Before.
use Drupal\DrupalExtension\Context\RawDrupalContext;

class UiContext extends RawDrupalContext {

  public function __construct(protected string $fixtures_path) {
  }

}

// After.
use DrevOps\BehatSteps\Behat\Context\WebRawContext;

class UiContext extends WebRawContext {

  public function __construct(
    protected string $fixtures_path,
    array $config = [],
  ) {
    parent::__construct($config);
  }

}
```

A constructor that doesn't forward it fails as soon as a hook or a step reads an option, with `UiContext declares a constructor that does not call parent::__construct(), so its "config" argument was never set. Add "array $config = []" to the constructor and forward it.` [Context constructor arguments](docs/configuration.md#context-constructor-arguments) shows how a suite passes `config`.

A group or an option the context cannot serve is an error naming what it accepts, so a typo fails while Behat builds the context. The extension's `steps` section is read permissively instead: a group there may name a trait only one of the registered contexts composes.

### `getMapping()` became `mappingGetValue()`

The mapping lookup moved off the Drupal Extension's `ParametersTrait`, which `RawDrupalContext` composed, and onto `MappingTrait`, which is where the groups it reads are now configured. A context that calls it directly composes `MappingTrait`, or extends `WebContext` or `DrupalContext`, and renames the call. A context that only uses the `{{ Key }}` token changes no code: `MappingTrait` reads the same token the Drupal Extension's `MappingContext` did.

| Before | After |
| --- | --- |
| `$this->getMapping('User Login')` | `$this->mappingGetValue('User Login')` |

### `@error` no longer disables the Watchdog check

`WatchdogTrait` and `JavascriptTrait` each read 2 independent options, `enabled` and `fail_on_errors`, and their tags map onto them. `JavascriptTrait`'s tags mean what they meant in 3.x. `WatchdogTrait`'s don't: in 3.x, `@error` and the 2 hook-method skip tags, `@behat-steps-skip:watchdogSetScenario` and `@behat-steps-skip:watchdogAfterStep`, all switched the check off. [One skip tag per trait](#one-skip-tag-per-trait) maps those 2 to `@behat-steps-skip:WatchdogTrait`.

| Switch | Extension or context | Scenario tag |
| --- | --- | --- |
| Do not collect at all | `'watchdog' => ['enabled' => FALSE]` | `@behat-steps-skip:WatchdogTrait` |
| Collect, do not fail | `'watchdog' => ['fail_on_errors' => FALSE]` | `@error` |
| Do not collect at all | `'javascript' => ['enabled' => FALSE]` | `@behat-steps-skip:JavascriptTrait` |
| Collect, do not fail | `'javascript' => ['fail_on_errors' => FALSE]` | `@js-errors` |

`@error` used to leave the start time unset, which disabled collection entirely. It now means `fail_on_errors = FALSE`: the errors the scenario logged are still read and cleared from the `watchdog` table, and the scenario is not failed. A scenario that relied on `@error` leaving rows behind for a later assertion reads them before the scenario ends, or uses `@behat-steps-skip:WatchdogTrait` instead.

`@error` and `@js-errors` are also read on the `Feature:` line now, so either one there covers every scenario in that feature. [Tags on the `Feature:` line apply to every scenario](#tags-on-the-feature-line-apply-to-every-scenario) covers the rest of the tags.

### Three transform traits can be switched off

`DateTrait`, `RandomTrait` and `MappingTrait` register suite-wide `#[Transform]` callbacks. In 3.x, `DateTrait` rewrote every matching step argument and table cell in the run with no way to opt out, and the `[?name:type]` and `{{ Key }}` tokens came from the Drupal Extension's `RandomContext` and `MappingContext`, which a suite either registered or didn't. `@behat-steps-skip:DateTrait`, `@behat-steps-skip:RandomTrait` and `@behat-steps-skip:MappingTrait` now switch a transform off for a scenario or a feature, and each trait's `enabled` option switches it off for a profile or a context.

Each of the 3 resolves that decision in a `BeforeScenario` hook, so each needs a context extending `WebRawContext`. In 3.x, `DateTrait` composed into any class, and `RandomContext` and `MappingContext` were contexts of their own.

`ModalTrait`, `TableTrait`, `ElementTrait` and `CommandTrait` need `WebRawContext` for the same reason: they read a declared option. In 3.x, `CommandTrait` composed into any class and the other 3 needed only Mink's `RawMinkContext`. [Four traits now need the library's context](#four-traits-now-need-the-librarys-context) names the web traits that still run on `RawMinkContext`; every other one now needs `WebRawContext`.

### `BigPipeTrait` reads its timeout from configuration

`DEFAULT_WAIT_TIMEOUT` is gone, and `$bigPipeWaitTimeout` now defaults to `NULL`, meaning "take the configured option". Assigning it still overrides the wait for one scenario.

| Before | After |
| --- | --- |
| `$this->bigPipeWaitTimeout = 2000;` | Unchanged, or `'steps' => ['big_pipe' => ['wait_timeout' => 2000]]` |
| `self::DEFAULT_WAIT_TIMEOUT` | `'steps' => ['big_pipe' => ['wait_timeout' => 10000]]` |

## Unified step text

Placeholder names, articles and `Given` verbs drifted as traits were added, so the same idea ended up written several different ways: an XML attribute was `:attribute` in 4 steps and `:attribute_name` in 2, a taxonomy vocabulary answered to 3 different names, and a handful of `Given` steps had no verb at all. 89 steps now follow one set of conventions.

- A step that names its target (`:element`, `:path`, `:key`, `:field`) compares against `:value`. `:text` is now reserved for steps that assert on a whole body with no named target, such as `the modal should contain :text`.
- A bundle placeholder is named after its entity type - `:content_type`, `:media_type`, `:content_block_type`, `:vocabulary`. Steps that are deliberately entity-agnostic keep `:bundle` (`EckTrait`, and the parent lookup in `ParagraphsTrait`).
- A bundle placeholder that qualifies an entity noun comes before it, as in `the :media_type media`. One that is itself the subject follows its noun, as in `the media type :media_type`.
- Every noun takes an article, `URL` is uppercase, and a named value reads `the value :value` rather than `the :value value`.
- Placeholder names are `snake_case`.
- A `Given` states a fact in the present tense. Verbless steps gained `exist`, bare noun phrases gained a verb, and the `has been cleared` family became `is empty`. Steps that already read `is empty`, `is enabled` or `is disabled` were left alone: they mirror the `should be ...` assertion they pair with, and forcing them into an `exists` form would say something different.

Placeholder names are part of the contract even when the surrounding words are identical. Behat binds a step argument to the method parameter of the same name, so a rename reaches any context that overrides the step method or calls it directly.

3 steps relied on Behat's positional fallback because their parameter never matched their placeholder. Their placeholder names are unchanged, but the method signatures are not: `MediaTrait::mediaRemoveType()`, now `mediaDeleteType()`, takes `$media_type` where it took `$type`, `SearchApiTrait::searchApiIndexContent()` takes `$content_type` where it took `$type`, and `searchApiDoIndex()`, now `searchApiRunIndexing()`, takes `$count` where it took `$limit`.

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
| `Given the config :name key :key has the value :value` | `Given the config :name with the key :key has the value :value` |
| `Then the config :name key :key should have the value :value` | `Then the config :name with the key :key should have the value :value` |
| `Then the config :name key :key should not have the value :value` | `Then the config :name with the key :key should not have the value :value` |
| `Then the config :name key :key should contain the value :value` | `Then the config :name with the key :key should contain the value :value` |
| `Then the config :name key :key should not contain the value :value` | `Then the config :name with the key :key should not contain the value :value` |
| `Then the config :name key :key should have the effective value :value` | `Then the config :name with the key :key should have the effective value :value` |
| `Then the config :name key :key should not have the effective value :value` | `Then the config :name with the key :key should not have the effective value :value` |
| `Then the config :name key :key should contain the effective value :value` | `Then the config :name with the key :key should contain the effective value :value` |
| `Then the config :name key :key should not contain the effective value :value` | `Then the config :name with the key :key should not contain the effective value :value` |

### ContentBlockTrait

| Before | After |
| --- | --- |
| `When I edit the :type content block with the description :description` | `When I visit the :content_block_type content block edit page with the description :description` |
| `Then the content block type :type should exist` | `Then the content block type :content_block_type should exist` |
| `Given the following :type content blocks do not exist:` | `Given the following :content_block_type content blocks do not exist:` |
| `Given the following :type content blocks exist:` | `Given the following :content_block_type content blocks exist:` |
| `Given the following :type content blocks with fields:` | `Given the following :content_block_type content blocks with fields exist:` |

### ContentTrait

| Before | After |
| --- | --- |
| `Given the following :type content with fields:` | `Given the following :content_type content with fields exist:` |
| `When I set the path alias of the :content_type content with the title :title to :alias` | `When I set the path alias of the :content_type content with the title :title to the alias :alias` |
| `Given the following :content_type content does not exist:` | `Given the following :content_type content do not exist:` |

### DraggableviewsTrait

| Before | After |
| --- | --- |
| `When I save the draggable views items of the view :view_id and the display :view_display_id for the :bundle content in the following order:` | `When I save the draggable views items of the view :view_id and the display :view_display_id for the :content_type content in the following order:` |

### EmailTrait

| Before | After |
| --- | --- |
| `Then an email should be sent to the :address` | `Then an email should be sent to the address :address` |
| `Then no emails should have been sent to the :address` | `Then an email should not be sent to the address :address` |
| `Then the email header :header should exactly be:` | `Then the email header :header should be:` |

### FieldTrait

| Before | After |
| --- | --- |
| `When I fill in the WYSIWYG field :field with the :value` | `When I fill in the WYSIWYG field :field with the value :value` |
| `When I fill in the field :selector with :value` | `When I fill in the field :selector with the value :value` |
| `Given browser validation for the form :selector is disabled` | `Given the browser validation for the form :selector is disabled` |
| `Then the field :name should exist` | `Then the field :field should exist` |
| `Then the field :name should have :enabled_or_disabled state` | `Then the field :field should have the :enabled_or_disabled state` |
| `Then the field :name should not exist` | `Then the field :field should not exist` |
| `When I fill in the date part of the datetime field :label with :date` | `When I fill in the date part of the datetime field :label with the date :date` |
| `When I fill in the time part of the datetime field :label with :time` | `When I fill in the time part of the datetime field :label with the time :time` |

### FileDownloadTrait

| Before | After |
| --- | --- |
| `Then the downloaded file name should contain :file_name_part` | `Then the downloaded file name should contain :partial_name` |

### FileTrait

| Before | After |
| --- | --- |
| `Given the following managed files:` | `Given the following managed files exist:` |
| `Given the unmanaged file at the URI :uri exists with :content` | `Given the unmanaged file at the URI :uri exists with the content :content` |
| `Then an unmanaged file at the URI :uri should contain :content` | `Then an unmanaged file at the URI :uri should contain the value :value` |
| `Then an unmanaged file at the URI :uri should not contain :content` | `Then an unmanaged file at the URI :uri should not contain the value :value` |

### IframeTrait

| Before | After |
| --- | --- |
| `When I switch to iframe with locator :locator` | `When I switch to the iframe :selector` |

### JsonTrait

| Before | After |
| --- | --- |
| `Given the response JSON content is the following:` | `Given the response JSON is the following:` |
| `Given the response JSON from the file :filename` | `Given the response JSON is loaded from the file :filename` |

### MediaTrait

| Before | After |
| --- | --- |
| `Given :media_type media type does not exist` | `Given the media type :media_type does not exist` |
| `When I edit the media :media_type with the name :name` | `When I visit the :media_type media edit page with the name :name` |
| `When I visit the media :media_type delete page with the name :name` | `When I visit the :media_type media delete page with the name :name` |
| `When I visit the media :media_type revisions page with the name :name` | `When I visit the :media_type media revisions page with the name :name` |
| `When I visit the media :media_type with the name :name` | `When I visit the :media_type media page with the name :name` |
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
| `Then the :metaName meta tag should not contain any HTML tags` | `Then the meta tag :name should not contain any HTML tags` |

### PathTrait

| Before | After |
| --- | --- |
| `Then current url should have the :param parameter` | `Then the current URL should have the query parameter :name` |
| `Then current url should have the :param parameter with the :value value` | `Then the current URL should have the query parameter :name with the value :value` |
| `Then current url should not have the :param parameter` | `Then the current URL should not have the query parameter :name` |
| `Then current url should not have the :param parameter with the :value value` | `Then the current URL should not have the query parameter :name with the value :value` |
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
| `Then the :rowText row should contain the following:` | `Then the row containing :partial_text should contain the following:` |

### TaxonomyTrait

| Before | After |
| --- | --- |
| `When I visit the :vocabulary_machine_name term delete page with the name :term_name` | `When I visit the :vocabulary term delete page with the name :name` |
| `When I visit the :vocabulary_machine_name term edit page with the name :term_name` | `When I visit the :vocabulary term edit page with the name :name` |
| `When I visit the :vocabulary_machine_name term page with the name :term_name` | `When I visit the :vocabulary term page with the name :name` |
| `Given the following :vocabulary terms with fields:` | `Given the following :vocabulary terms with fields exist:` |
| `Given the following :vocabulary_machine_name vocabulary terms do not exist:` | `Given the following :vocabulary terms do not exist:` |
| `Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should exist` | `Then the taxonomy term :name from the vocabulary :vocabulary should exist` |
| `Then the taxonomy term :term_name from the vocabulary :vocabulary_machine_name should not exist` | `Then the taxonomy term :name from the vocabulary :vocabulary should not exist` |
| `Then the vocabulary :machine_name should not exist` | `Then the vocabulary :vocabulary should not exist` |
| `Then the vocabulary :machine_name with the name :name should exist` | `Then the vocabulary :vocabulary with the name :name should exist` |

### UserTrait

| Before | After |
| --- | --- |
| `Given the following roles:` | `Given the following roles exist:` |
| `Given the following users with fields:` | `Given the following users with fields exist:` |
| `Given the role :role_name with the permissions :permissions` | `Given the role :role has the permissions :permissions` |

### WebformTrait

| Before | After |
| --- | --- |
| `Given a webform :title from template :template` | `Given the webform :title exists from the template :template` |

### XmlTrait

| Before | After |
| --- | --- |
| `Then the XML attribute :attribute on element :element should be equal to :text` | `Then the XML attribute :attribute on the element :element should be equal to the value :value` |
| `Then the XML attribute :attribute on element :element should not be equal to :text` | `Then the XML attribute :attribute on the element :element should not be equal to the value :value` |
| `Then the XML attribute :attribute_name on element :element should contain :text` | `Then the XML attribute :attribute on the element :element should contain the value :value` |
| `Then the XML attribute :attribute_name on element :element should not contain :text` | `Then the XML attribute :attribute on the element :element should not contain the value :value` |
| `Then the XML element :element should be equal to :text` | `Then the XML element :element should be equal to the value :value` |
| `Then the XML element :element should contain :text` | `Then the XML element :element should contain the value :value` |
| `Then the XML element :element should not be equal to :text` | `Then the XML element :element should not be equal to the value :value` |
| `Then the XML element :element should not contain :text` | `Then the XML element :element should not contain the value :value` |
| `Given the response content from the file :filename` | `Given the response XML is loaded from the file :filename` |
| `Given the response content is the following:` | `Given the response XML is the following:` |

## I-prefixed step text and placeholder types

6 more steps change under 2 further conventions: a `Given` or `Then` step does not begin with `I`, and a placeholder names the value's role rather than its type. None of them is listed under [Unified step text](#unified-step-text), so check both sections when upgrading.

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
| `Then the element :selector should be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should be displayed within the viewport with a top offset of :offset pixels` |
| `Then the element :selector should not be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should not be displayed within the viewport with a top offset of :offset pixels` |

This also renames the `$number` argument of `ElementTrait::elementAssertIsVisuallyVisibleWithOffset()` and `ElementTrait::elementAssertIsNotVisuallyVisibleWithOffset()` to `$offset`. The methods themselves become `elementAssertVisuallyVisibleWithOffset()` and `elementAssertNotVisuallyVisibleWithOffset()` under [Negation is spelled `Not`, in one slot](#negation-is-spelled-not-in-one-slot), and `$offset` is a `string` (see [Signatures](#signatures)), so a context that overrides or calls either method updates it.

## Step text follows the documented grammar

The steps below broke the step-text rules in [CONTRIBUTING.md](CONTRIBUTING.md#steps-format) and follow them now. Only the wording and the placeholder names changed. The one behavior tied to a placeholder name, `[relative:...]` token expansion, is described below the rules.

- A placeholder that names a thing follows its noun: `the queue :queue`, `the module :module`, `the dropzone :selector`. A bundle still comes before the entity noun it qualifies (`the :media_type media`), and a count before its unit (`:count item(s)`).
- Every noun takes an article: `on the element :element`, `to the URL :url`, `the system time`, `the last XML response`.
- A value reads `the value :value`, so `should be equal to :value` became `should be equal to the value :value`.
- A step that names its target compares against `:value`, so the region, row and command output assertions take `:value` where they took `:text`. `the modal should contain :text` keeps `:text`, because it asserts on a whole body with no named target.
- A qualifier that narrows the asserted subject, such as `in the region :region` or `within the select :selector`, reads with the subject before `should`: `the block :label in the region :region should exist`. A step that takes a table or a PyString still names it last.
- A partial match reads `a <thing> containing :partial_<thing>`, as the cookie steps already did. The table row steps find a row by part of its text, so they read `the row containing :partial_text`, and their methods take `$partial_text` where they took `$row_text`.
- `:param` became `:name`, the placeholder every other named thing uses, and an email link's position became `:index`, as it is in `I follow the link :link with the index :index`.

Each v3 step is listed once, in the first of these sections whose rules change it, and its row gives the v4 text directly. The query parameter, meta tag and table row steps, and the XML comparisons, are under [Unified step text](#unified-step-text). Steps that are new in v4 have no row here: the Drupal Extension steps they replace are in the [DrupalExtension mapping](#drupalextension-step-text-mapped-to-the-v4-vocabulary).

A renamed placeholder renames the method parameter behind it, because Behat binds a step argument to the parameter of the same name. 2 steps changed a placeholder name and nothing else, so their feature files need no edit. Their methods are renamed too, under [A qualifier on an action is `With`](#a-qualifier-on-an-action-is-with), and take `$index` as a `string` (see [Signatures](#signatures)), so a context that overrides or calls either method updates it:

| Method | Before | After |
| --- | --- | --- |
| `ElementTrait::elementFollowLinkByIndex()`, now `elementFollowLinkWithIndex()` | `$text` | `$link` |
| `ElementTrait::elementPressButtonByIndex()`, now `elementPressButtonWithIndex()` | `$label` | `$button` |

`DateTrait` expands `[relative:...]` tokens in `:partial_value` arguments as well as in `:value`, `:datetime` and `:expected_value` ones. The region, row and command output assertions now take `:value`, so a token in their argument is expanded rather than compared as written.

### AccessibilityTrait

| Before | After |
| --- | --- |
| `Then the current page should pass accessibility checks for tags :rules` | `Then the current page should pass accessibility checks for the tags :tags` |

### BlockTrait

| Before | After |
| --- | --- |
| `Given the instance of :admin_label block exists with the following configuration:` | `Given the instance of the block :admin_label exists with the following configuration:` |
| `Given the block :label has the following :condition condition configuration:` | `Given the block :label has the condition :condition with the following configuration:` |
| `Given the block :label has the :condition condition removed` | `Given the block :label has the condition :condition removed` |
| `Then the block :label should exist in the :region region` | `Then the block :label in the region :region should exist` |
| `Then the block :label should not exist in the :region region` | `Then the block :label in the region :region should not exist` |

### CommandTrait

| Before | After |
| --- | --- |
| `Then the command output should contain :text` | `Then the command output should contain the value :value` |
| `Then the command output should not contain :text` | `Then the command output should not contain the value :value` |
| `Then the command output should be :text` | `Then the command output should be equal to the value :value` |
| `Then the command error output should contain :text` | `Then the command error output should contain the value :value` |

### ContentTrait

| Before | After |
| --- | --- |
| `When I change the moderation state of the :content_type content with the title :title to the :new_state state` | `When I change the moderation state of the :content_type content with the title :title to the state :new_state` |
| `Then :content_type content with the title :title should not exist` | `Then the :content_type content with the title :title should not exist` |
| `Then :content_type content with the title :title should be published` | `Then the :content_type content with the title :title should be published` |
| `Then :content_type content with the title :title should not be published` | `Then the :content_type content with the title :title should not be published` |

### DropzoneTrait

| Before | After |
| --- | --- |
| `When I drop the file :path on the :selector dropzone` | `When I drop the file :filename on the dropzone :selector` |
| `When I drop the following files on the :selector dropzone:` | `When I drop the following files on the dropzone :selector:` |

### EckTrait

| Before | After |
| --- | --- |
| `When I visit eck :bundle :entity_type entity with the title :title` | `When I visit the eck :bundle :entity_type entity page with the title :title` |
| `When I edit eck :bundle :entity_type entity with the title :title` | `When I visit the eck :bundle :entity_type entity edit page with the title :title` |

### ElementTrait

| Before | After |
| --- | --- |
| `Then the element :selector with the attribute :attribute and the value containing :value should exist` | `Then the element :selector with the attribute :attribute and a value containing :partial_value should exist` |
| `Then the element :selector with the attribute :attribute and the value containing :value should not exist` | `Then the element :selector with the attribute :attribute and a value containing :partial_value should not exist` |
| `Then the element :selector should have the CSS property :property with the value containing :value` | `Then the element :selector should have the CSS property :property with a value containing :partial_value` |
| `Then the element :selector should not have the CSS property :property with the value containing :value` | `Then the element :selector should not have the CSS property :property with a value containing :partial_value` |

### EmailTrait

| Before | After |
| --- | --- |
| `When I follow link number :link_number in the email with the subject :subject` | `When I follow the link with the index :index in the email with the subject :subject` |
| `When I follow link number :link_number in the email with the subject containing :subject` | `When I follow the link with the index :index in the email with a subject containing :partial_subject` |
| `Then the file :file_name should be attached to the email with the subject containing :subject` | `Then the file :filename should be attached to the email with a subject containing :partial_subject` |

### FieldTrait

| Before | After |
| --- | --- |
| `When I unselect :option from :selector` | `When I unselect the option :option from the select :selector` |
| `When I fill in the datetime field :label with date :date and time :time` | `When I fill in the datetime field :label with the date :date and the time :time` |
| `When I fill in the start datetime field :label with date :date and time :time` | `When I fill in the start datetime field :label with the date :date and the time :time` |
| `When I fill in the end datetime field :label with date :date and time :time` | `When I fill in the end datetime field :label with the date :date and the time :time` |

### JsonTrait

| Before | After |
| --- | --- |
| `When I print last JSON response` | `When I print the last JSON response` |
| `Then the JSON path :path should be equal to :value` | `Then the JSON path :path should be equal to the value :value` |
| `Then the JSON path :path should not be equal to :value` | `Then the JSON path :path should not be equal to the value :value` |
| `Then the JSON path :path should contain :value` | `Then the JSON path :path should contain the value :value` |
| `Then the JSON path :path should not contain :value` | `Then the JSON path :path should not contain the value :value` |

### ModalTrait

| Before | After |
| --- | --- |
| `When I click on :selector in the modal` | `When I click on the element :selector in the modal` |

### ModuleTrait

| Before | After |
| --- | --- |
| `Given the :module module is enabled` | `Given the module :module is enabled` |
| `Given the :module module is disabled` | `Given the module :module is disabled` |
| `Then the :module module should be enabled` | `Then the module :module should be enabled` |
| `Then the :module module should be disabled` | `Then the module :module should be disabled` |

### QueueTrait

| Before | After |
| --- | --- |
| `Given the :queue queue is empty` | `Given the queue :queue is empty` |
| `When I process :count item(s) from the :queue queue` | `When I process :count item(s) from the queue :queue` |
| `When I process the :queue queue` | `When I process the queue :queue` |
| `Then the :queue queue should have :count item(s)` | `Then the queue :queue should have :count item(s)` |
| `Then the :queue queue should be empty` | `Then the queue :queue should be empty` |

### ResponsiveTrait

| Before | After |
| --- | --- |
| `When I set the viewport to the :breakpoint breakpoint` | `When I set the viewport to the breakpoint :breakpoint` |

### RestTrait

| Before | After |
| --- | --- |
| `When I send a REST :method request to :url` | `When I send a REST :method request to the URL :url` |
| `When I send a REST :method request to :url with body:` | `When I send a REST :method request to the URL :url with the body:` |

### TableTrait

| Before | After |
| --- | --- |
| `Then the table :selector should be sorted by :column in :direction order` | `Then the table :selector should be sorted by the column :column in :direction order` |

### TimeTrait

| Before | After |
| --- | --- |
| `When I set system time to :value` | `When I set the system time to the value :value` |
| `When I reset system time` | `When I reset the system time` |

### UserTrait

| Before | After |
| --- | --- |
| `When I visit :name user profile page` | `When I visit the profile page of the user :name` |
| `When I visit :name user profile edit page` | `When I visit the profile edit page of the user :name` |
| `When I visit :name user profile delete page` | `When I visit the profile delete page of the user :name` |
| `When I visit the password reset link for :name` | `When I visit the password reset link for the user :name` |

### XmlTrait

| Before | After |
| --- | --- |
| `When I print last XML response` | `When I print the last XML response` |
| `Then the XML attribute :attribute on element :element should exist` | `Then the XML attribute :attribute on the element :element should exist` |
| `Then the XML attribute :attribute on element :element should not exist` | `Then the XML attribute :attribute on the element :element should not exist` |

## One wording per step idea

A handful of ideas read 2 ways in v3. Most navigation steps said `I visit the ... page`, while 2 dropped `page` and 3 said `I edit ...` although they only open the edit form. This library's click steps said `I click on` and the Drupal Extension's said `I click`, the viewport was both `the viewport` and `a viewport`, a `<select>` was both `the select` and `the select element`, an email address was `:address` in one trait and `:mail` in another, an email that must not be sent was both `an email should not be sent` and `no emails should have been sent`, and the meta robots steps said `include` where the rest of the vocabulary says `contain`. Each idea now reads 1 way:

- A step that opens a page reads `I visit the ... page` and names the page it opens.
- A click reads `I click on the ...`.
- The viewport is `the viewport`, and a `<select>` is `the select :selector`.
- An email address is `:address` everywhere, and an email link's position reads `with the index :index` in the step text and `WithIndex` in the method names.
- An email that must not be sent reads `an email should not be sent`, as the 2 content checks already did, so each negative pairs with the positive `an email should be sent ...`.
- Containment reads `contain`.

`ahoy lint-docs` and `TraitMethodNamingTest` reject the replaced forms, so they don't come back.

The media and content block navigation steps are listed under [Unified step text](#unified-step-text), the ECK navigation steps under [Step text follows the documented grammar](#step-text-follows-the-documented-grammar), and the 2 viewport offset steps under [I-prefixed step text and placeholder types](#i-prefixed-step-text-and-placeholder-types). `I click on the link :link in the region :region` and `I click on the link :link in the row containing :partial_text` are new in v4: they replace the Drupal Extension's `I follow/click :link in the :region( region)` and `I click :link in the :rowText row`, as the [DrupalExtension mapping](#drupalextension-step-text-mapped-to-the-v4-vocabulary) shows. The steps below are listed only here.

### ElementTrait

| Before | After |
| --- | --- |
| `Then the element :selector should be displayed within a viewport` | `Then the element :selector should be displayed within the viewport` |
| `Then the element :selector should not be displayed within a viewport` | `Then the element :selector should not be displayed within the viewport` |

### EmailTrait

| Before | After |
| --- | --- |
| `Then no emails should have been sent` | `Then an email should not be sent` |

`Then no emails should have been sent to the :address` is listed under [Unified step text](#unified-step-text).

### FieldTrait

| Before | After |
| --- | --- |
| `Then the option :option should exist within the select element :selector` | `Then the option :option within the select :selector should exist` |
| `Then the option :option should not exist within the select element :selector` | `Then the option :option within the select :selector should not exist` |
| `Then the option :option should be selected within the select element :selector` | `Then the option :option within the select :selector should be selected` |
| `Then the option :option should not be selected within the select element :selector` | `Then the option :option within the select :selector should not be selected` |

### MetatagTrait

| Before | After |
| --- | --- |
| `Then the meta robots should include :directive` | `Then the meta robots should contain :directive` |
| `Then the meta robots should not include :directive` | `Then the meta robots should not contain :directive` |

### Method names

A method behind a navigation step opens with `Visit` and names the page the way its step does, and `Drupal\EmailTrait` names a link's position `WithIndex`, as `ElementTrait` now does too: its 3 `ByIndex` methods are listed under [A qualifier on an action is `With`](#a-qualifier-on-an-action-is-with). Rename any call or override in a consumer context:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\ContentBlockTrait` | `contentBlockEditBlockContentWithDescription()` | `contentBlockVisitEditPageWithDescription()` |
| `Drupal\ContentTrait` | `contentVisitViewWithTitle()` | `contentVisitPageWithTitle()` |
| `Drupal\EckTrait` | `eckEditEntityWithTitle()` | `eckVisitEntityEditPageWithTitle()` |
| `Drupal\EmailTrait` | `emailFollowLinkNumber()` | `emailFollowLinkWithIndexWithSubject()` |
| `Drupal\EmailTrait` | `emailFollowLinkNumberWithSubjectContaining()` | `emailFollowLinkWithIndexWithSubjectContaining()` |
| `Drupal\MediaTrait` | `mediaEditWithName()` | `mediaVisitEditPageWithName()` |
| `Drupal\MediaTrait` | `mediaVisitViewWithName()` | `mediaVisitPageWithName()` |
| `Drupal\MediaTrait` | `mediaVisitDeleteWithName()` | `mediaVisitDeletePageWithName()` |
| `Drupal\MediaTrait` | `mediaVisitRevisionsWithName()` | `mediaVisitRevisionsPageWithName()` |
| `Drupal\UserTrait` | `userVisitProfile()` | `userVisitProfilePage()` |
| `Drupal\UserTrait` | `userVisitOwnProfile()` | `userVisitOwnProfilePage()` |
| `Drupal\UserTrait` | `userEditProfile()` | `userVisitProfileEditPage()` |
| `Drupal\UserTrait` | `userEditOwnProfile()` | `userVisitOwnProfileEditPage()` |
| `Drupal\UserTrait` | `userDeleteProfile()` | `userVisitProfileDeletePage()` |
| `Drupal\UserTrait` | `userDeleteOwnProfile()` | `userVisitOwnProfileDeletePage()` |

`userEditProfile()` and `userDeleteProfile()` never edited or deleted anything: like the rest of the table, they open a page. The protected helper behind the 2 email link steps is listed under [Only an assertion is named `Assert`](#only-an-assertion-is-named-assert).

`the user with the email :address should exist` and its negative took `:mail`. Their step text is unchanged, so no feature file needs an edit, but the parameter behind the placeholder is renamed. The methods are renamed too, under [A qualifier on an action is `With`](#a-qualifier-on-an-action-is-with), so a context that overrides or calls either method updates its name as well:

| Method | Before | After |
| --- | --- | --- |
| `UserTrait::userAssertExistsByMail()`, now `userAssertExistsWithMail()` | `$mail` | `$address` |
| `UserTrait::userAssertNotExistsByMail()`, now `userAssertNotExistsWithMail()` | `$mail` | `$address` |

A taxonomy term's name, a role and a file name each had 2 placeholder names: `:term_name` where every other named entity reads `:name`, `:role_name` beside its own `:roles`, and `:file_name` or `:path` where the XML and JSON steps read `:filename`. Each now has 1. A placeholder name never appears in a feature file, so the placeholder rename alone changes no `.feature` file, but the parameter behind each placeholder is renamed:

| Method | Before | After |
| --- | --- | --- |
| `TaxonomyTrait::taxonomyVisitTermPageWithName()`, `taxonomyVisitTermEditPageWithName()`, `taxonomyVisitTermDeletePageWithName()`, `taxonomyVisitActionPageWithName()` | `$vocabulary_machine_name`, `$term_name` | `$vocabulary`, `$name` |
| `TaxonomyTrait::taxonomyAssertTermExistsByName()`, `taxonomyAssertTermNotExistsByName()`, now `taxonomyAssertTermExistsWithName()`, `taxonomyAssertTermNotExistsWithName()` | `$term_name`, `$vocabulary_machine_name` | `$name`, `$vocabulary` |
| `UserTrait::userCreateRole()` | `$role_name` | `$role` |
| `EmailTrait::emailAssertMessageContainsAttachmentWithName()`, now `emailAssertMessageContainsAttachmentWithSubject()` | `$file_name` | `$filename` |
| `EmailTrait::emailAssertMessageContainsAttachmentWithSubjectContaining()` | `$file_name`, `$subject` | `$filename`, `$partial_subject` |
| `DropzoneTrait::dropzoneDropFile()` | `$path` | `$filename` |

`FileTrait::fileAssertUnmanagedHasContent()` and `fileAssertUnmanagedHasNoContent()`, now `fileAssertUnmanagedContains()` and `fileAssertUnmanagedNotContains()`, take `$value` where they took `$content`, because their steps now read `the value :value`, as the `FileTrait` table under [Unified step text](#unified-step-text) shows.

### Failure messages

| Trait | Before | After |
| --- | --- | --- |
| ElementTrait | Element(s) defined by "..." selector is not displayed within a viewport. | The element "..." is not displayed within the viewport. |
| ElementTrait | Element(s) defined by "..." selector is not displayed within a viewport with a top offset of N pixels. | The element "..." is not displayed within the viewport with a top offset of N pixels. |
| Drupal\EmailTrait | The link with number N was not found among N links. | The link with the index N was not found among N links. |

The 2 negative viewport messages, which ended in `, but should not be.`, are listed under [Failure messages read one way](#failure-messages-read-one-way), and the link number message under [A step method takes only what its step binds](#a-step-method-takes-only-what-its-step-binds).

## Requirements

4.0 needs PHP 8.3 or newer, where 3.14 ran on 8.2, and a Drupal site on Drupal 11 or 12: its `drupal/core-utility` requirement can't be met by Drupal 10's core. It installs on Behat 3 or Behat 4, where 3.14 refused Behat 4. Behat 4 also needs `friends-of-behat/mink-extension` 3, which has only an alpha release so far, so the installation section of the [README](README.md) shows how to require both.

`drupal/drupal-extension` is no longer needed (see [Behat extensions registered in the Behat configuration](#behat-extensions-registered-in-the-behat-configuration)), and removing it removes the packages it required. A Drupal project that never required `lullabot/mink-selenium2-driver` itself got it from the Drupal Extension, so a suite that runs `@javascript` scenarios through Selenium now requires the driver directly:

```bash
composer require --dev lullabot/mink-selenium2-driver
```

`dmore/behat-chrome-extension` is the alternative that drives headless Chrome with no Selenium server, from 1.5.0 on Behat 4; install 1 of the 2. `softcreatr/jsonpath` and `justinrainbow/json-schema` stay optional, as they were in 3.14.

## `drupal/drupal-extension` and `drupal/drupal-driver` conflict with this package

This package now replaces both. Its own Behat extension and step traits take over from `drupal/drupal-extension`, and its Drupal, Drush and Blackbox backends from `drupal/drupal-driver`. It conflicts with every version of both, so Composer won't install it beside either one. Remove the extension before updating this package:

```bash
composer remove --dev drupal/drupal-extension
composer require --dev drevops/behat-steps:^4
```

`drupal/drupal-driver` usually arrives through the extension and leaves with it. If your own `composer.json` requires it, add it to the `composer remove` command. Any other package that requires either one blocks the update too, and Composer's error names it.

## Behat extensions registered in the Behat configuration

3.14 asked a Drupal project to install `drupal/drupal-extension` for its Drupal traits. 4.0 doesn't use it, so remove it from your `composer.json`. Its Mink extension is replaced by Mink's own, and its Drupal extension by the one this package supplies:

| Old | New |
| --- | --- |
| `Drupal\MinkExtension` | `Behat\MinkExtension` |
| `Drupal\DrupalExtension` | `DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension` |

In `behat.yml`:

```yaml
# Before.
extensions:
  Drupal\MinkExtension:
    base_url: http://your-site.local
    sessions:
      browserkit_http:
        browserkit_http: ~
  Drupal\DrupalExtension:
    drupal:
      drupal_root: web

# After.
extensions:
  Behat\MinkExtension:
    base_url: http://your-site.local
    sessions:
      browserkit_http:
        browserkit_http: ~
  DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
    drupal:
      drupal_root: web
```

In `behat.php`, which Behat 4 requires, `Behat\MinkExtension` is the `Behat\MinkExtension\ServiceContainer\MinkExtension` class:

```php
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

$profile
  ->withExtension(new Extension(MinkExtension::class, [
    'base_url' => 'http://your-site.local',
    'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
  ]))
  ->withExtension(new Extension(BehatStepsExtension::class, [
    'drupal' => ['drupal_root' => 'web'],
  ]));
```

The Mink options, such as `base_url`, `files_path`, `javascript_session`, `selenium2` and `browserkit_http`, carry over under `Behat\MinkExtension`. Most `Drupal\DrupalExtension` settings carry over under `BehatStepsExtension` with the same name and value: `blackbox`, `drupal`, `drush`, `regions`, `login_field`, `login_wait`, the `text` keys other than `log_in` and `log_out`, and `selectors: login_form_selector` and `selectors: logged_in_selector`. These don't:

| 3.14 | 4.0 |
| --- | --- |
| `Drupal\DrupalExtension`: `default_driver`, `api_driver`, `drush_driver` | `backends`, see [Capability-based backend resolution](#capability-based-backend-resolution) |
| `Drupal\DrupalExtension`: `ajax_timeout`, `selectors: messages:`, `mappings` | under `steps`, see [3 Drupal Extension settings moved under `steps`](#3-drupal-extension-settings-moved-under-steps) |
| `Drupal\DrupalExtension`: `text: log_in:`, `text: log_out:` | `text: login:`, `text: logout:`, see [`Login` and `Logout`, not `LogIn` and `LogOut`](#login-and-logout-not-login-and-logout) |
| `Drupal\DrupalExtension`: `region_map`, the spelling Drupal Extension 6.0 still accepted | `regions` |
| `Drupal\DrupalExtension`: `suppress_deprecations` | Nothing. Remove it |
| `Drupal\MinkExtension`: `guzzle_request_options` under a `browserkit_http` session | `http_client_parameters`, below |

A key left under its old name fails the container build: `region_map`, `suppress_deprecations` and the `*_driver` keys with an `Unrecognized option` error, and `selectors: messages:`, `text: log_in:` and `text: log_out:` with a message naming the new key. The `regions` map keeps its shape, so the region steps resolve the same names as before:

```yaml
# Before.
Drupal\DrupalExtension:
  regions:
    Header: '#header'
    Content: '#main'

# After.
DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
  regions:
    Header: '#header'
    Content: '#main'
```

`BehatStepsExtension` registers its own factory behind `browserkit_http` with whichever Mink extension the suite registers. The factory builds every `browserkit_http` session exactly as Mink does, on Mink's own `HttpBrowser`, and records the session's options for the requests steps send from PHP, which go through 1 shared Symfony HttpClient transport. Drupal's `DrupalTestBrowser` and Guzzle are no longer used, so `guzzle_request_options` gives way to Mink's own `http_client_parameters`, which takes [Symfony HttpClient options](https://symfony.com/doc/current/http_client.html):

```yaml
# Before.
extensions:
  Drupal\MinkExtension:
    sessions:
      browserkit_http:
        browserkit_http:
          guzzle_request_options:
            verify: false

# After.
extensions:
  Behat\MinkExtension:
    sessions:
      browserkit_http:
        browserkit_http:
          http_client_parameters:
            verify_peer: false
            verify_host: false
```

The Guzzle options a suite most often sets map across like this:

| Guzzle | Symfony HttpClient |
| --- | --- |
| `verify: false` | `verify_peer: false` and `verify_host: false` |
| `verify: /path/to/ca.pem` | `cafile: /path/to/ca.pem` |
| `cert: /path/to/client.pem` | `local_cert: /path/to/client.pem` |
| `auth: [user, pass]` | `auth_basic: [user, pass]` |
| `timeout: 30` | `max_duration: 30` |
| `proxy` | `proxy` |
| `headers` | `headers` |

The page applies the options to every host, as it did with Guzzle. The requests steps send from PHP apply them to `base_url` only, and every `browserkit_http` session has to declare the same ones; [HTTP clients](docs/http-clients.md) explains both rules.

## Capability-based backend resolution

The Drupal Extension's `@api` tag no longer selects a driver, and its `default_driver`, `api_driver` and `drush_driver` settings are replaced by 1 `backends` list under `behat_steps`. That list names the backends a scenario may reach, in precedence order, and a step resolves the backend by the capability it needs.

| Before | After |
| --- | --- |
| `'default_driver' => 'blackbox'` | A configuration that declares no `backends` list gets every registered backend, in registration order |
| `'api_driver' => 'drupal'` | `'backends' => ['drupal', 'blackbox']` |
| `'drush_driver' => 'drush'` | Add `'drush'` to the `backends` list |
| `@api` on a scenario | Nothing. A step resolves the capability it needs, such as `ContentCapabilityInterface`, from the configured list |
| `@drush` on a scenario | Nothing. `DrushTrait` resolves `DrushCapabilityInterface` |

```php
// Before.
use Drupal\DrupalExtension\ServiceContainer\DrupalExtension;

$profile->withExtension(new Extension(DrupalExtension::class, [
  'default_driver' => 'blackbox',
  'api_driver' => 'drupal',
  'drush_driver' => 'drush',
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));

// After.
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'backends' => ['drupal', 'drush', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));
```

Every name in the list has to be a backend the extension registers, so a `backends` entry keeps company with the settings block that registers it: `drupal` needs `drupal:`, `drush` needs `drush:`, and `blackbox` is always registered. Naming one without its block fails the container build.

Then remove `@api` from every scenario and feature. It is not a tag of this package any more, and a configuration that lists a Drupal backend reaches Drupal without it.

To run one scenario against a different backend, tag it `@backend:NAME`, where `NAME` is a name the `backends` list holds. The tag moves that backend to the front of the scenario's order; it never adds a backend the list does not hold. A name outside the list fails at scenario start.

An entry may be keyed, so a profile can swap the implementation behind a name without editing any Gherkin: `'backends' => ['api' => 'drupal', 'blackbox']` in one profile and `'backends' => ['api' => 'acme-jsonapi', 'blackbox']` in another both answer `@backend:api`. Restricting a run to a narrower backend set is a profile's job - the package reads nothing from a Behat suite's settings.

```gherkin
@backend:drush
Scenario: The cache is cleared over the command line
  Given the cache is empty
```

3 consequences are worth checking in an existing project:

- **A step that used to fail for a missing `@api` now succeeds.** The tag no longer gates anything, so a scenario that reached a Drupal step without it used to throw and now runs. Where that gate was load-bearing, run those scenarios under a profile whose `backends` list excludes the Drupal backend.
- **Hooks that skipped a scenario without `@api` now run in it.** 3.14 ran the scenario hooks of `ConfigTrait`, `ConfigOverrideTrait`, `EmailTrait`, `FileTrait`, `ModuleTrait`, `StateTrait`, `TestmodeTrait`, `TimeTrait` and `WatchdogTrait` only in `@api` scenarios, and so did its entity cleanup and the Drupal Extension's static cache reset. They now run in every scenario of a context that composes their trait: the watchdog check, for one, reads the log after a scenario that never carried `@api`, and fails the scenario at its start when the profile lists no backend that can read the log.
- **`RawDrupalContext::getDriver()` is gone.** With no argument it returned the driver `@api` had selected. Its replacement, `WebRawContext::getBackend()`, takes a backend's name and has no default, so a call with no argument becomes `backendFor()` naming the capability the caller needs. A custom step that needs Drupal bootstrapped calls `$this->backendFor(CoreCapabilityInterface::class);`.

## DrupalExtension step text mapped to the v4 vocabulary

The Drupal Extension's contexts are gone. Their behavior lives in the step traits, re-expressed in the one grammar the docs linter enforces: tuple placeholders, no regex, no optional words, and a `Then` that starts with the subject rather than `I`.

Register Mink's own `Behat\MinkExtension\Context\MinkContext` where the suite registered the Drupal Extension's `MinkContext`, which extended it. It brings the base browser vocabulary, so `I am on`, `I go to`, `I should see`, `I fill in`, `I press`, `I follow`, `I check`, `I select`, `I attach the file`, `the response status code should be` and the other upstream Mink steps are unchanged. The table below covers only the steps the Drupal Extension added on top.

### Session and users

| Before | After |
| --- | --- |
| `Given I am an anonymous user` | `Given the user is anonymous` |
| `Given I am not logged in` | `Given the user is anonymous` |
| `When I log out` | `When I log out` |
| `Given I am logged in as a user with the :role role(s)` | `When I log in as a user with the role(s) :roles` |
| `Given I am logged in as a/an :role` | `When I log in as a user with the role(s) :roles` |
| `Given I am logged in as a user with the :role role(s) and I have the following fields:` | `When I log in as a user with the role(s) :roles and the following fields:` |
| `Given I am logged in as a user with the :permissions permission(s)` | `When I log in as a user with the permission(s) :permissions` |
| `Given I am logged in as :name` | `When I log in as the user :name` |
| `Given the following users:` | `Given the following users exist:` |

A context that called or overrode one of the Drupal Extension's `DrupalContext` methods behind these steps moves to the `Drupal\UserTrait` method behind the new step:

| Old `DrupalContext` method | New `Drupal\UserTrait` method |
| --- | --- |
| `createAndLoginUserWithRole()` (protected) | `userCreateAndLogin()` |
| `iAmLoggedInAs()` | `userLoginAs()` |
| `iAmLoggedInAsUserWithPermissions()` | `userLoginWithPermissions()` |
| `iAmLoggedInAsUserWithRole()`, `iAmLoggedInAsRole()` | `userLoginWithRoles()` |
| `iAmLoggedInAsUserWithRoleAndFields()` | `userLoginWithRolesAndFields()` |
| `iLogOut()` | `userLogout()` |
| `iAmAnonymous()`, `iAmNotLoggedIn()` | `userLogoutSession()` |
| `createUsers()` | `userCreateMultiple()` |

An override of one of the old methods isn't called in v4. Move its logic to an override of the new method.

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
| `Given a/an :vocabulary term with the name :name` | `Given the following :vocabulary terms exist:` then `When I visit the :vocabulary term page with the name :name` |
| `Given I am viewing a/an :vocabulary term with the name :name` | as above |
| `Then I should be able to edit the :type` | `Given the following :content_type content exist:`, `When I visit the :content_type content edit page with the title :title`, `Then the response status code should be 200` |
| `Then I should be able to edit the :type content` | as above |

The table steps' methods move the same way:

| Old method | New method |
| --- | --- |
| `DrupalContext::createNodes()` | `Drupal\ContentTrait::contentCreateMultiple()` |
| `DrupalContext::createTerms()`, which 3.x's `Drupal\TaxonomyTrait` overrode | `Drupal\TaxonomyTrait::taxonomyCreateMultiple()` |
| `DrupalContext::createEntities()` | `Drupal\EntityTrait::entityCreateMultiple()` |
| `DrupalContext::createLanguages()` | `Drupal\LanguageTrait::languageCreateMultiple()` |

### Cache, cron, batch and queues

| Before | After |
| --- | --- |
| `Given the cache has been cleared` | `Given the cache is empty` |
| `Given I run cron` | `When I run cron` |
| `Given I wait for the batch job to finish` | `When I wait for the batch job to finish` |
| `Given the following item is in the system queue:` | `Given the following item is in the queue :queue:` |

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

Every region step drops the optional `( region)` suffix, so one phrasing covers each action. An action names the region last, and an assertion names it with the subject it narrows, before `should`.

| Before | After |
| --- | --- |
| `When I follow/click :link in the :region( region)` | `When I click on the link :link in the region :region` |
| `Given I press :button in the :region( region)` | `When I press the button :button in the region :region` |
| `Given I fill in :field with :value in the :region( region)` | `When I fill in the field :field with the value :value in the region :region` |
| `Given I fill in :value for :field in the :region( region)` | `When I fill in the field :field with the value :value in the region :region` |
| `Given I check :locator in the :region( region)` | `When I check the checkbox :checkbox in the region :region` |
| `Given I uncheck :checkbox in the :region( region)` | `When I uncheck the checkbox :checkbox in the region :region` |
| `Then I should see( the text) :text in the :region( region)` | `Then the region :region should contain the value :value` |
| `Then I should not see( the text) :text in the :region( region)` | `Then the region :region should not contain the value :value` |
| `Then I should see the heading :heading in the :region( region)` | `Then the region :region should contain the heading :heading` |
| `Then I should see the :heading heading in the :region( region)` | `Then the region :region should contain the heading :heading` |
| `Then I should see the link :link in the :region( region)` | `Then the link :link in the region :region should exist` |
| `Then I should not see the link :link in the :region( region)` | `Then the link :link in the region :region should not exist` |
| `Then I should see the button :button in the :region( region)` | `Then the button :button in the region :region should exist` |
| `Then I should see the :button button in the :region( region)` | `Then the button :button in the region :region should exist` |
| `Then I should not see the button :button in the :region( region)` | `Then the button :button in the region :region should not exist` |
| `Then I should not see the :button button in the :region( region)` | `Then the button :button in the region :region should not exist` |
| `Then I should see the :tag element in the :region( region)` | `Then the element :selector in the region :region should exist` |
| `Then I should not see the :tag element in the :region( region)` | `Then the element :selector in the region :region should not exist` |
| `Then I should see :text in the :tag element in the :region( region)` | `Then the element :selector in the region :region should have the value :value` |
| `Then I should not see :text in the :tag element in the :region( region)` | `Then the element :selector in the region :region should not have the value :value` |
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
| `Given I click :link in the :rowText row` | `When I click on the link :link in the row containing :partial_text` |
| `Given I press :button in the :rowText row` | `When I press the button :button in the row containing :partial_text` |
| `Then I should see the text :text in the :rowText row` | `Then the row containing :partial_text should contain the value :value` |
| `Then I should not see the text :text in the :rowText row` | `Then the row containing :partial_text should not contain the value :value` |
| `Then I should see the :link in the :rowText row` | `Then the link :link in the row containing :partial_text should exist` |
| `Then I should not see the :link in the :rowText row` | `Then the link :link in the row containing :partial_text should not exist` |

### Mail

`Steps\Drupal\EmailTrait` carries the mail vocabulary. It collects mail through Drupal's test mail collector, so a scenario enables collection with the `@email` tag or `When I enable the test email system`, and the assertions read the collected messages.

The Drupal Extension's `new` mail family tracked messages sent since the previous assertion. Clear the queue explicitly instead: `When I clear the test email system queue` leaves only the messages a later action produces.

| Before | After |
| --- | --- |
| `When I send the following mail:` | dropped; trigger the site behavior that sends the mail |
| `When I send the following email:` | dropped; trigger the site behavior that sends the mail |
| `Then the following (e)mail(s) should have been sent:` | `Then the email field :field should contain:` |
| `Then the following (e)mail(s) should have been sent to :to:` | `Then an email should be sent to the address :address with the content:` |
| `Then the following (e)mail(s) should have been sent with the subject :subject:` | `Then the email field :field should be:` against `subject` |
| `Then the following (e)mail(s) should have been sent to :to with the subject :subject:` | the 2 steps above |
| `Then the following new (e)mail(s) should have been sent...` | clear the queue, then use the non-`new` step |
| `Then there should be a total of :count (e)mail(s) sent` | `Then the number of sent emails should be :count` |
| `Then there should be a total of :count (e)mail(s) sent to :to` | `Then the number of emails sent to the address :address should be :count` |
| `Then there should be a total of :count (e)mail(s) sent with the subject :subject` | `Then the number of emails sent with the subject :subject should be :count` |
| `Then there should be a total of :count (e)mail(s) sent to :to with the subject :subject` | the 2 count steps above |
| `Then there should be a total of :count new (e)mail(s) sent...` | clear the queue, then use the non-`new` step |
| `Then (a )(an )(e)mail(s) should have been sent with the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject` |
| `Then (a )(an )(e)mail(s) should have been sent to :to with the attachment(s) :attachments` | as above |
| `Then (a )(an )(e)mail(s) should have been sent with the subject :subject and the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject`, once per attachment |
| `Then (a )(an )(e)mail(s) should have been sent to :to with the subject :subject and the attachment(s) :attachments` | as above |
| `When I follow the link to :urlFragment from the email`, or `from the mail` | `When I follow the link with a URL containing :partial_url in the email` |
| `When I follow the link to :urlFragment from the email to :to`, or `from the mail to :to` | as above |
| `When I follow the link to :urlFragment from the email with the subject :subject`, or `from the mail with the subject :subject` | as above, or `When I follow the link with the index :index in the email with the subject :subject` to keep the subject |
| `When I follow the link to :urlFragment from the email to :to with the subject :subject`, or `from the mail to :to with the subject :subject` | as above |

Several Drupal Extension mail steps held 2 conditions to the same email, such as a recipient and a subject, or a subject and a link URL. No 4.x step does, so each row above keeps 1 of the conditions or checks each with its own step, and 2 steps can pass on 2 different emails. Clearing the queue right before the action that sends the mail usually leaves just that email, which closes the gap. Where it doesn't, a step of your own holds both conditions to 1 email through the public helpers in [HELPERS.md](HELPERS.md). `emailGetMessagesToAddress()` and `emailGetMessagesWithSubject()` key each message as collected, so the emails that match both are:

```php
$messages = array_intersect_key($this->emailGetMessagesToAddress($address), $this->emailGetMessagesWithSubject($subject));
```

For a link, `emailFindMessageBySubject()` finds the email and `emailExtractLinks()` lists the links in its body.

The Drupal Extension matched a subject in part and in any case. Here `with the subject :subject` compares the whole subject, so a scenario that relied on a partial match moves to `with a subject containing :partial_subject` and writes the subject's own case. [Email subject steps match the way they read](#email-subject-steps-match-the-way-they-read) has the details.

### Config

| Before | After |
| --- | --- |
| `Given I set the configuration item :name with key :key to :value` | `Given the config :name with the key :key has the value :value` |
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
| `When (I )break` | dropped; use a debugger or `Then print last response` (Mink) |

Random-value tokens (`[?name:type]`) and mapping tokens (`{{ Key }}`) are unchanged: `Steps\Web\RandomTrait` and `Steps\Web\MappingTrait` carry them, and a context composes the trait instead of registering `RandomContext` or `MappingContext`.

### `Drupal\OverrideTrait` is gone

3.x's `Drupal\OverrideTrait` adjusted 3 of the Drupal Extension's steps and bootstrapped Drupal ahead of every `@api` scenario. Each of those behaviors has a replacement:

- Its `createNodes()` and `createUsers()` deleted the nodes and users a table named before creating them. The v4 steps create without deleting, so a scenario that relied on it adds `Given the following :content_type content do not exist:` or `Given the following users do not exist:` first.
- Its `iAmLoggedInAsUserWithRole()` logged out for the `anonymous` and `anonymous user` roles instead of creating a user. Write `Given the user is anonymous` for those.
- Its `overrideBootstrapDrupal()` hook has no counterpart: a step bootstraps Drupal through the backend it resolves.

## Unified entity cleanup

3.14 tore a scenario's entities down in 2 hooks. The Drupal Extension's `RawDrupalContext::cleanEntities()` deleted the nodes, terms and languages its creation methods made, and this package's `entityCleanupAfterScenario()` deleted the entities its traits registered, leaving out the types in `ENTITY_CLEANUP_EXCLUDED_TYPES` so that the 2 never deleted the same one. Both are now 1 `entityLifecycleAfterScenario` hook on `Helper\Drupal\EntityLifecycleTrait`. Every entity a creation step or the backend creates, except a user or a role, is registered there and deleted in reverse creation order. There is no second registry and no exclusion list, so a node, a term and a media item created in 1 scenario come down in the order that respects the references between them. The users and roles `cleanUsers()` and `cleanRoles()` removed are `Helper\Drupal\AuthTrait`'s now.

The hook runs after every scenario, where `entityCleanupAfterScenario()` ran only after an `@api` one.

An entity a project saves through Drupal's API in its own step joins that teardown only when the step registers it, with `$this->entityLifecycleRegister($entity)` in place of `entityRegister()`. Without that call the entity survives the scenario.

| 3.14 | 4.0 |
| --- | --- |
| `@behat-steps-skip:entityCleanupAfterScenario` | `@behat-steps-skip:EntityLifecycleTrait` |
| `@behat-steps-entity-cleanup-skip:ENTITY_TYPE_ID` | Unchanged. It now keeps `node`, `taxonomy_term` and `language` entities too, and it's read on the `Feature:` line as well as on the scenario |
| `@behat-steps-skip:fileAfterScenario` | `@behat-steps-skip:FileTrait`, which also skips creating the private and temporary directories. `@behat-steps-entity-cleanup-skip:file` still keeps the managed files |
| `BEHAT_DRUPALEXTENSION_DISABLE_CLEANUP=1` | `BEHAT_STEPS_DISABLE_CLEANUP=1` |
| `@no-file-cleanup` | Nothing: 4.0 doesn't delete the files a scenario uploads with Mink's `I attach the file` step, so Drupal renames a later upload of the same name, as `image_0.png` |

`@behat-steps-skip:AuthTrait` keeps the users and roles a scenario created, which no 3.14 tag could do.

## Entity creation hooks take no argument

The entity creation hook attributes, such as `#[BeforeNodeCreate]` and `#[AfterEntityCreate]`, accepted an argument, and a hook declared with one never ran: it matched no entity at all. The attributes take no argument now, and a hook declared with one fails the run while Behat reads the context:

```
The "#[BeforeNodeCreate]" attribute on "FeatureContext::alterArticle()" takes no argument. The hook runs for every entity created in its scope, so read the entity from "$scope->getStub()" and return early for one it does not handle.
```

A hook meant for some entities only checks the stub itself:

```php
// Before.
#[BeforeNodeCreate('article')]
public function alterArticle(BeforeNodeCreateScope $scope): void {
  // ...
}

// After.
#[BeforeNodeCreate]
public function alterArticle(BeforeNodeCreateScope $scope): void {
  if ($scope->getStub()->getBundle() !== 'article') {
    return;
  }

  // ...
}
```

Once the argument is gone, the hook runs for the first time, so check that its body still does what it was written to do.

`FilterStringTrait` and `DrupalHookInterface::getFilterString()` are gone. `EntityHook` extends `RuntimeHook` rather than `RuntimeFilterableHook`, so a hook call carries no filter string, and each call under `Behat\Hook\Call` takes the callable as its first constructor argument:

| Before | After |
| --- | --- |
| `new BeforeNodeCreate(NULL, $callable)` | `new BeforeNodeCreate($callable)` |
| `new BeforeNodeCreate(NULL, $callable, $description)` | `new BeforeNodeCreate($callable, $description)` |

## Trait namespaces re-rooted under `Steps`

The step vocabulary now lives in one subtree, split by the context each trait needs. Generic traits moved from `DrevOps\BehatSteps\` to `DrevOps\BehatSteps\Steps\Web\`, and Drupal traits from `DrevOps\BehatSteps\Drupal\` to `DrevOps\BehatSteps\Steps\Drupal\`. The trait names themselves are unchanged, apart from the 2 `HelperTrait`s, which [became concern-named traits](#the-2-helpertraits-became-concern-named-traits), and `Drupal\OverrideTrait`, which [is gone](#drupaloverridetrait-is-gone). A consumer context updates its `use` statements, and [The context layer is one chain](#the-context-layer-is-one-chain) covers the class it extends:

```php
// Before.
use DrevOps\BehatSteps\CookieTrait;
use DrevOps\BehatSteps\Drupal\ContentTrait;

// After.
use DrevOps\BehatSteps\Steps\Web\CookieTrait;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
```

`DrevOps\BehatSteps\Exception\AssertionException` is new in v4 and sits outside `Steps`; see [Unified assertion exceptions](#unified-assertion-exceptions).

## The context layer is one chain

A 3.14 context extended the Drupal Extension's `DrupalContext` or `RawDrupalContext`, which carried the Drupal entity lifecycle, or Mink's `RawMinkContext` on a site that isn't Drupal. 4.0 ships a chain of its own instead: `WebRawContext` is the root, and each class below it adds 1 half of the vocabulary:

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
| `WebRawContext` | The project picks its own traits; each one brings the helpers it needs |

A 3.14 context that extended Mink's `RawMinkContext` or the Drupal Extension's `RawDrupalContext` to compose this package's traits extends `WebRawContext` instead. One that extended the Drupal Extension's `DrupalContext` can extend 4.0's `DrupalContext`, and drops the `use` lines for the traits it already composes:

```php
// Before.
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\JavascriptTrait;
use DrevOps\BehatSteps\WaitTrait;

class UiContext extends RawMinkContext {

  use JavascriptTrait;
  use WaitTrait;

}

// After.
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\JavascriptTrait;
use DrevOps\BehatSteps\Steps\Web\WaitTrait;

class UiContext extends WebRawContext {

  use JavascriptTrait;
  use WaitTrait;

}
```

### The Drupal lifecycle moved into concern-named helpers

What the Drupal Extension's `RawDrupalContext` and 3.14's `Drupal\HelperTrait` provided for creating and removing entities, users and roles moved under `DrevOps\BehatSteps\Helper\Drupal`, split by concern rather than gathered into 1 class. Every member carries its trait's prefix:

| Helper | Holds | Composed by |
| --- | --- | --- |
| `Helper\Drupal\EntityLifecycleTrait` | `entityLifecycleCreateNode()`, `entityLifecycleCreateTerm()`, `entityLifecycleCreate()`, `entityLifecycleCreateLanguage()`, `entityLifecycleRegister()`, `entityLifecycleParseFields()`, `entityLifecycleAfterScenario()`, `entityLifecycleBeforeNodeCreate()` | the 13 step traits that create entities, and `UserTrait` through `AuthTrait` |
| `Helper\Drupal\AuthTrait` | `authCreateUser()`, `authLogin()`, `authLogout()`, `authIsLoggedIn()`, `authGetUserRegistry()`, `authSetUserRegistry()`, `authGetAuthenticator()`, `authSetAuthenticator()`, `authAfterScenario()` | `Steps\Drupal\UserTrait` |
| `Helper\Drupal\StaticCacheTrait` | `staticCacheAfterScenario()` | `Steps\Drupal\CacheTrait` |
| `Helper\Drupal\FixtureFileTrait` | the 5 `fixtureFile*()` methods | `ContentTrait`, `MediaTrait` |
| `Helper\Drupal\QueryTrait` | `queryEntityIds()`, `queryFindNewestEntityId()`, `queryNodeIds()` | 11 step traits |

The web half of the library sits under `DrevOps\BehatSteps\Helper\Web` and names nothing Drupal:

| Helper | Holds | Composed by |
| --- | --- | --- |
| `Helper\Web\LastStepTrait` | `lastStepSetLine()`, `lastStepReached()` | `WebRawContext` and 3 step traits |
| `Helper\Web\RequestHeadersTrait` | `requestHeadersSet()`, `requestHeadersUnset()`, `requestHeadersAll()`, `requestHeadersReset()` | `WebRawContext` and 3 step traits |
| `Helper\Web\StringTrait` | `stringFixStepArgument()`, `stringNormalizeWhitespace()`, `stringSplitCommaSeparated()`, `stringSlug()` | `WebRawContext` and 20 step traits |
| `Helper\Web\TableTransposeTrait` | `tableTransposeVertical()`, `tableTransposeHorizontal()` | 5 step traits |

A call or an override in a consumer context is renamed:

| Old member | New member |
| --- | --- |
| `RawDrupalContext::nodeCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateNode()` |
| `RawDrupalContext::termCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateTerm()` |
| `RawDrupalContext::entityCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreate()` |
| `RawDrupalContext::languageCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateLanguage()` |
| `RawDrupalContext::parseEntityFields()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleParseFields()` |
| `RawDrupalContext::alterNodeParameters()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleBeforeNodeCreate()` |
| `RawDrupalContext::cleanEntities()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleAfterScenario()` |
| `Drupal\HelperTrait::entityCleanupAfterScenario()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleAfterScenario()` |
| `Drupal\HelperTrait::entityRegister()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleRegister()` |
| `Drupal\HelperTrait::entityRegisterId()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleRegister()`, passing the loaded entity rather than its type and id |
| `RawDrupalContext::$createdStubs`, `Drupal\HelperTrait::$entityRegistry` | `Helper\Drupal\EntityLifecycleTrait::$entityLifecycleCreatedStubs`, which holds entity stubs |
| `RawDrupalContext::userCreate()` | `Helper\Drupal\AuthTrait::authCreateUser()` |
| `RawDrupalContext::login()` | `Helper\Drupal\AuthTrait::authLogin()` |
| `RawDrupalContext::logout()` | `Helper\Drupal\AuthTrait::authLogout()` |
| `RawDrupalContext::loggedIn()` | `Helper\Drupal\AuthTrait::authIsLoggedIn()` |
| `RawDrupalContext::getUserManager()` | `Helper\Drupal\AuthTrait::authGetUserRegistry()` |
| `RawDrupalContext::setUserManager()` | `Helper\Drupal\AuthTrait::authSetUserRegistry()` |
| `RawDrupalContext::getAuthenticationManager()` | `Helper\Drupal\AuthTrait::authGetAuthenticator()` |
| `RawDrupalContext::setAuthenticationManager()` | `Helper\Drupal\AuthTrait::authSetAuthenticator()` |
| `RawDrupalContext::cleanUsers()` | `Helper\Drupal\AuthTrait::authCleanUsers()` |
| `RawDrupalContext::cleanRoles()` | `Helper\Drupal\AuthTrait::authCleanRoles()` |
| `RawDrupalContext::clearStaticCaches()` | `Helper\Drupal\StaticCacheTrait::staticCacheAfterScenario()` |

`cleanEntities()`, `cleanUsers()` and `cleanRoles()` were hooks, and in 3.14 they read no tag: only the `BEHAT_DRUPALEXTENSION_DISABLE_CLEANUP` environment variable switched them off. [Unified entity cleanup](#unified-entity-cleanup) lists what keeps a scenario's entities, users and roles now.

`authCleanUsers()` and `authCleanRoles()` take no parameters, aren't hooks, and are protected. `authAfterScenario()` is the hook. It runs them users first, and still runs the role cleanup when removing the users fails. When both fail, it throws 1 `\RuntimeException` that names both. An override of either drops its `@AfterScenario` annotation or `#[AfterScenario]` attribute, or it runs twice.

The roles a scenario creates are recorded in `AuthTrait::$authRoles`, which `authCleanRoles()` deletes. 3.x recorded them in the Drupal Extension's `RawDrupalContext::$roles`, so a context that read `$this->roles` reads `$this->authRoles`.

A step trait composes what its own body calls, so the teardown travels with the traits that create the thing being torn down. A context that composes no entity-creating trait runs no entity teardown, where `RawDrupalContext` ran its `clean*()` hooks for every context extending it. 4.0's `DrupalContext` composes every entity-creating trait, so a context extending it runs the whole teardown.

A context that wants one concern without the Drupal vocabulary composes that helper alone:

```php
class SpecContext extends WebRawContext {

  use EntityLifecycleTrait;

}
```

`UserAwareInterface` declares the four accessors `AuthTrait` implements: `authSetUserRegistry()`, `authGetUserRegistry()`, `authSetAuthenticator()` and `authGetAuthenticator()`. A context composing `AuthTrait` declares the interface so the context initializer injects both services; a context that creates no users declares nothing and neither is injected.

Basic authentication is a separate service, because applying credentials to a request needs Mink and a base URL and knows nothing about a Drupal session. `WebRawContext` carries `BasicAuthenticatorInterface` through `setBasicAuthenticator()` and `getBasicAuthenticator()`, and `Authenticator` takes it as a constructor argument to reapply the credentials after a fast logout.

### One context registers, not two

A 3.14 suite registered the Drupal Extension's contexts, such as `DrupalContext`, `MinkContext`, `MessageContext` and `DrushContext`, beside a context of its own that composed this package's traits. 4.0's `DrupalContext` extends `WebContext` and composes all 29 `Steps\Drupal` traits on top of its 28 web ones, so `$suite->addContext(DrupalContext::class)` alone gives a Drupal suite all 57 step traits, which carry the Drupal Extension's vocabulary as [DrupalExtension step text mapped to the v4 vocabulary](#drupalextension-step-text-mapped-to-the-v4-vocabulary) shows.

Registering `WebContext` beside `DrupalContext` is fatal, because the 28 web traits would register their steps twice. `WebContext::assertOneContext()` runs on `BeforeSuite` and names the real mistake rather than letting Behat report a `RedundantStepException` about an arbitrary step.

Registering either context beside a hand-composed context that already carries one of the same traits is a `RedundantStepException` too: two registered contexts cannot compose the same trait. Drop the trait from the hand-composed context, or register the shipped context instead of it.

There is no way to remove an inherited step, so a Drupal project cannot take the Drupal step traits without the 28 web ones. A project whose own step text collides with a shipped web step drops to `WebRawContext` and composes what it wants by hand.

Scoped configuration follows the chain. `WebContext` accepts the groups its web traits declare, such as `javascript`, `modal`, `wait`, `message`, `mapping` and `diagnostics`, and `DrupalContext` adds the groups of the Drupal traits, such as `watchdog`, `big_pipe`, `cache`, `queue` and `email`. A group no trait in the chain declares is an error at construction, naming what that context does accept.

`DrupalContext` composes `WatchdogTrait`, so a suite that registers it fails any scenario that logs a PHP error, even if your v3 context never composed the trait. The check reads the `watchdog` table, which only the core `dblog` module creates, and it reads it in the Behat process, so it needs a backend such as `drupal`. On a site without `dblog`, or under a profile that lists no such backend, such as `'backends' => ['drush', 'blackbox']`, every scenario fails at its start until you meet the prerequisite or switch the check off for the profile:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

Setting `fail_on_errors` to `FALSE` or tagging a scenario `@error` doesn't cover an unmet prerequisite, because both only apply to errors that were read.

## Traits declare the host they need

Every trait that reaches beyond its own methods states what it needs from its host. A web trait carries `@phpstan-require-extends`, naming `Behat\MinkExtension\Context\RawMinkContext` when a Mink session is all it touches and `DrevOps\BehatSteps\Behat\Context\WebRawContext` when it reads a backend or the extension configuration. A Drupal trait carries the same annotation and composes the helper traits its body calls, rather than requiring them of its host.

A 3.14 context that composed these traits onto Mink's `RawMinkContext` or the Drupal Extension's `RawDrupalContext` fails at run time once a trait calls a member only `WebRawContext` declares, such as the `skipTag()` its hooks open with, and a project running PHPStan sees the same mismatch reported up front. The fix is to extend the named class and declare the named interface.

## A trait declares its prerequisites

A trait states what it needs from the site in a `<prefix>Prerequisites()` method, named like its `<prefix>ConfigSchema()`, and each prerequisite goes through a backend capability rather than a query of its own. The module checks the step traits ran through `helperAssertModuleEnabled()` moved onto these declarations, and [STEPS.md](STEPS.md) lists each trait's prerequisites beside its options.

```php
protected function acmePrerequisites(): array {
  return [
    Prerequisite::capability(CoreCapabilityInterface::class),
    Prerequisite::check(
      static fn(ModuleCapabilityInterface $backend): bool
        => $backend->moduleIsEnabled('acme'),
      'the "acme" module from the "drupal/acme" package is enabled',
    ),
  ];
}
```

A step checks them with `$this->assertPrerequisites(__TRAIT__)`, a setup hook with `$this->assertPrerequisites(__TRAIT__, $scope)`, and a teardown asks `$this->prerequisitesMet(__TRAIT__)` instead, so it never replaces a failure the scenario already recorded. A prerequisite that doesn't hold fails with a message naming it. When a hook of a trait with an `enabled` option checks it, the message also names the option and the skip tag that switch the trait off; a step's message doesn't, because neither stops a step.

| Before | After |
| --- | --- |
| `$this->helperAssertModuleEnabled('acme', 'drupal/acme')` in a step | Declare the module in `<prefix>Prerequisites()` and call `$this->assertPrerequisites(__TRAIT__)` |
| `\Drupal::moduleHandler()->moduleExists('acme')` to adapt to an optional module | `$this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('acme')` |

The message for a missing module changes with it. `The "redirect" module is not enabled. Add "drupal/redirect" to the consumer project's composer.json and enable the module as part of the site setup.` becomes `RedirectTrait requires that the "redirect" module from the "drupal/redirect" package is enabled, which does not hold.`, and the core form, `The "menu_link_content" module is not enabled. Enable it as part of the site setup; it ships with Drupal core.`, becomes `MenuTrait requires that the core "menu_link_content" module is enabled, for the menu link steps, which does not hold.`, so a test asserting the old text needs the new one. `SearchApiTrait` and `DraggableviewsTrait` change the same way, and `WebformTrait`, which checked nothing in 3.14, now fails with the same shape of message.

`FileTrait`, `MediaTrait` and `TaxonomyTrait` declare the core module they build on, so on a site without it a step fails with `MediaTrait requires that the core "media" module is enabled, which does not hold.` rather than with whatever Drupal threw first. `FileTrait` checks `file` in its managed file steps only, so the unmanaged file steps keep working without it, and `TaxonomyTrait`'s term creation goes through the content capability and checks nothing.

`TestmodeTrait` checks the `testmode` module when a `@testmode` scenario starts, where 3.14 called the module's `Testmode` class without checking, and its teardown disables test mode only if the scenario enabled it.

## A trait's directory classifies it

A trait's directory is its classification: `src/Steps` registers Gherkin and `src/Helper` registers none. `scripts/lint-traits.php` fails a step trait composing another step trait, and a helper trait registering a step or a transform. A helper may register a hook, because the trait that owns a teardown carries the hook that runs it.

A consuming project keeps its own traits wherever it likes; the rule applies to this package's own tree, and it is what routes the reference documentation.

## Step traits no longer compose other step traits

Shared logic lives in step-free helper traits under `DrevOps\BehatSteps\Helper\Web` and `DrevOps\BehatSteps\Helper\Drupal`, each named for one concern, so that composing one trait cannot pull in another trait's steps.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\ContentTrait` | `contentLoadMultiple()` | `Helper\Drupal\QueryTrait::queryNodeIds()` |
| `RestTrait` | `$restHeaders` | `Helper\Web\RequestHeadersTrait::$requestHeaders`, read and written through `requestHeadersSet()`, `requestHeadersUnset()`, `requestHeadersAll()` and `requestHeadersReset()` |

`Drupal\SearchApiTrait` composed `ContentTrait` and so registered every content step alongside its own; it now composes `Helper\Drupal\QueryTrait` itself, finds the node with `queryFindNewestEntityId()`, and registers only the Search API steps. A context that relied on that indirect composition has to compose `ContentTrait` itself. `FieldTrait` composed `KeyboardTrait` the same way; [`FieldTrait` no longer re-exports the keyboard steps](#fieldtrait-no-longer-re-exports-the-keyboard-steps) covers it.

`Drupal\ConfigOverrideTrait` set its `X-Config-No-Override` signal on `RestTrait`'s property when it found one. It writes to the header bag instead. The bag is per context, so a suite that wants the signal on `RestTrait`'s own requests composes both traits into one context rather than registering the two shipped ones; the browser header, the `$_SERVER` entry and the environment variable reach the site either way.

A helper trait composed by a step trait and by the context under it holds one slot of state, so both reach the same bag.

### The 2 `HelperTrait`s became concern-named traits

3.14's `DrevOps\BehatSteps\HelperTrait` and `DrevOps\BehatSteps\Drupal\HelperTrait` are gone. Their members live under `DrevOps\BehatSteps\Helper\Web` and `DrevOps\BehatSteps\Helper\Drupal`, each trait named for the 1 concern it holds, and every method carries its own trait's prefix in place of the shared `helper` one. The entity registry members of `Drupal\HelperTrait` are in [The Drupal lifecycle moved into concern-named helpers](#the-drupal-lifecycle-moved-into-concern-named-helpers):

| Old member | New member |
| --- | --- |
| `helperSetLastStepLine()` | `Helper\Web\LastStepTrait::lastStepSetLine()` |
| `helperIsLastStep()` | `Helper\Web\LastStepTrait::lastStepReached()` |
| `$helperLastStepLine` | `Helper\Web\LastStepTrait::$lastStepLine` |
| `helperFixStepArgument()` | `Helper\Web\StringTrait::stringFixStepArgument()` |
| `helperNormalizeWhitespace()` | `Helper\Web\StringTrait::stringNormalizeWhitespace()` |
| `helperSplitCommaSeparated()` | `Helper\Web\StringTrait::stringSplitCommaSeparated()` |
| `helperSlug()` | `Helper\Web\StringTrait::stringSlug()` |
| `helperIsJavascriptSupported()` | `WebRawContext::browserDriverHas(JavascriptCapabilityInterface::class)` |
| `helperTransposeVerticalTable()` | `Helper\Web\TableTransposeTrait::tableTransposeVertical()` |
| `helperBuildHorizontalTable()` | `Helper\Web\TableTransposeTrait::tableTransposeHorizontal()` |
| `helperExpandEntityFieldsFixtures()` | `Helper\Drupal\FixtureFileTrait::fixtureFileExpandEntityFields()` |
| `helperLooksLikeCompoundCell()` | `Helper\Drupal\FixtureFileTrait::fixtureFileLooksLikeCompoundCell()` |
| `helperExpandCompoundCellFixtures()` | `Helper\Drupal\FixtureFileTrait::fixtureFileExpandCompoundCell()` |
| `helperResolveFixtureFile()` | `Helper\Drupal\FixtureFileTrait::fixtureFileResolve()` |
| `helperManagedFileExists()` | `Helper\Drupal\FixtureFileTrait::fixtureFileManagedExists()` |
| `helperAssertModuleEnabled()` | A `<prefix>Prerequisites()` declaration checked by `assertPrerequisites(__TRAIT__)`, as [A trait declares its prerequisites](#a-trait-declares-its-prerequisites) shows |

A context that composed a `HelperTrait` to reach one of these composes the trait holding it instead:

```php
// Before.
use DrevOps\BehatSteps\HelperTrait;

// After.
use DrevOps\BehatSteps\Helper\Web\StringTrait;
```

Extending `WebRawContext` needs no `use` statement for `LastStepTrait`, `RequestHeadersTrait` or `StringTrait`, which it composes, and composing a step trait needs none for the Drupal helpers: the step trait already composes what it calls.

`requestHeadersSet()`, the 2 `tableTranspose*()` methods, `fixtureFileExpandEntityFields()`, the `FixtureDirectoryTrait` and `HeadingTrait` members, and the entity, authentication and query members a step calls are `public` and published in [HELPERS.md](HELPERS.md). Every other helper method, hooks aside, stays `protected`.

## Trait methods prefixed with their trait name

Every method a trait contributes now begins with the trait's own name, so that traits mixed into one context cannot collide. Rename any call or override in a consumer context:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\DraggableviewsTrait` | `draggableViewsSaveBundleOrder()` | `draggableviewsSaveBundleOrder()` |
| `Drupal\DraggableviewsTrait` | `draggableViewsFindNode()` | `draggableviewsFindNode()` |
| `Drupal\MenuTrait` | `loadMenuByLabel()` | `menuFindByLabel()` |
| `Drupal\MenuTrait` | `loadMenuLinkByTitle()` | `menuFindLinkByTitle()` |
| `WaitTrait` | `waitWaitForSeconds()` | `waitSeconds()` |
| `WaitTrait` | `waitForAjaxToFinish()` | `waitForAjax()` |

Gherkin step text is unchanged, so feature files need no edit for the renames above. A skip tag names a trait rather than a method, so no rename changes one; [One skip tag per trait](#one-skip-tag-per-trait) maps every tag that named a hook method.

## Query parameter presence

`PathTrait` tested for a query parameter with `empty()`, which reads a parameter carrying `0` or an empty string as absent. Presence is now `array_key_exists()`, so `?page=0` and `?debug=` are parameters that are in the URL, and their value is compared separately.

| Step | `?filter=0` before | `?filter=0` after |
| --- | --- | --- |
| `Then the current URL should have the query parameter :name` | fails | passes |
| `Then the current URL should have the query parameter :name with the value :value` | fails | passes for the value `0` |
| `Then the current URL should not have the query parameter :name` | passes | fails |
| `Then the current URL should not have the query parameter :name with the value :value` | passes | fails for the value `0` |

A scenario that asserted a falsy parameter away with `Then current url should not have the "filter" parameter` now needs to name the value it excludes:

```gherkin
Then the current URL should not have the query parameter "filter" with the value "recent"
```

## A value of `0` is not empty

A few checks read a string with `empty()`, which treats the string `0` as absent. They compare against the empty string now, so `0` is a value like any other: `Given the password for the user :name is "0"` sets the password instead of failing with `Password must not be empty.`, an attribute whose value is `0` counts as present for the `the element :selector with the attribute :attribute ...` steps, a WYSIWYG field with the id `0` is filled through its id, and `fileCreateEntity()` honors a destination URI of `0`. The Drush backend likewise reads an alias or root path of `0` as configured, where the Drupal Driver's `DrushDriver` read it as missing.

## Email subject steps match the way they read

4 `Drupal\EmailTrait` steps pick an email by its subject. They matched it 2 different ways, and neither was what the step text says. `with the subject :subject` settled for the first email whose subject contained the text, after collapsing whitespace. `with the subject containing :subject`, now `with a subject containing :partial_subject`, ignored case.

Both now follow the grammar in [CONTRIBUTING.md](CONTRIBUTING.md#steps-format). `with the subject` names the whole subject, as `the number of emails sent with the subject :subject should be :count` does. `a subject containing` matches part of it, case-sensitively, like every other `containing` step.

| Step | Before | After |
| --- | --- | --- |
| `When I follow the link with the index :index in the email with the subject :subject` | The subject contains `:subject`, whitespace collapsed | The subject is exactly `:subject` |
| `Then the file :filename should be attached to the email with the subject :subject` | The subject contains `:subject`, whitespace collapsed | The subject is exactly `:subject` |
| `When I follow the link with the index :index in the email with a subject containing :partial_subject` | The subject contains `:partial_subject` in any case | The subject contains `:partial_subject` in the same case |
| `Then the file :filename should be attached to the email with a subject containing :partial_subject` | The subject contains `:partial_subject` in any case | The subject contains `:partial_subject` in the same case |

When several emails match, each step still uses the first one collected.

A scenario that named only part of a subject switches to the `containing` step, and one that leaned on case-insensitive matching writes the subject's own case:

```gherkin
# Before.
When I follow link number "1" in the email with the subject "Verification"
Then the file "report.xlsx" should be attached to the email with the subject containing "monthly report"

# After.
When I follow the link with the index "1" in the email with a subject containing "Verification"
Then the file "report.xlsx" should be attached to the email with a subject containing "Monthly Report"
```

The lookup behind all 4 steps is public, so your own step definitions can pick an email the same way. `emailFindMessageBySubject()` returns `NULL` when no email matches, and `emailGetMessageBySubject()` throws the same `ExpectationException` the steps fail with. Both take the subject and an `$is_partial` flag.

When the file is missing, the attachment steps now name it. The old message said the email had no attachments at all, which was wrong whenever it carried other files:

| Trait | Before | After |
| --- | --- | --- |
| Drupal\EmailTrait | No attachments were found in the email with subject .... | The file "..." is not attached to the email with subject "...". |
| Drupal\EmailTrait | No attachments were found in the email with subject containing "...". | The file "..." is not attached to the email with subject containing "...". |

## `@email:TYPE` collects email without `@email`

`Drupal\EmailTrait` read the handler types from `@email:TYPE` only when the scenario or its feature also carried a bare `@email`, so a scenario tagged `@email:TYPE` alone collected nothing. That tag now enables the test email system by itself, with the handler types it names. `@email @email:TYPE` behaves as before, so the bare tag beside a typed one can go.

A scenario tagged only `@email:TYPE` used to send its mail through the site's own mail system, and now captures it in the test collector. A scenario that relied on that mail leaving the site drops the tag.

## Page cache steps clear the paths they name

`Given the page cache for the path :path has been cleared` named 1 path but cleared every page. It invalidated the `http_response` cache tag, and Drupal puts that tag on every cacheable response, so the step emptied the whole internal page cache and the whole dynamic page cache. `Given the page cache for the paths matching :path_pattern has been cleared` matched its pattern anywhere in the cached URL, so `/news*` also cleared `/archive/news`, and a pattern with no `*` cleared every path that contained it.

Both steps now delete the internal page cache entries whose path matches. The match ignores the host, the query string and the request format, so clearing `/about` clears `/about?page=2` too. A pattern matches the whole path, and `*` matches any run of characters, `/` included.

That's on the database backend, which holds the page cache by default and is the only backend that can list its entries. A page cache bin on Redis, Memcache or any other backend is emptied whole instead, so the steps never leave a stale entry behind.

| Step | Before | After |
| --- | --- | --- |
| `Given the page cache for the path "/about" is empty` | Empties the internal and dynamic page caches for every path | Deletes the internal page cache entries for `/about` |
| `Given the page cache for the paths matching "/news*" is empty` | Deletes every entry whose URL contains `/news` | Deletes the entries whose path starts with `/news` |
| `Given the page cache for the paths matching "/news" is empty` | Deletes every entry whose URL contains `/news` | Deletes the entries for `/news` |

A scenario that leaned on the old reach, to refresh a path it didn't name or a page the dynamic page cache serves to a logged-in user, switches to a step that says what it clears:

```gherkin
# Before.
Given the page cache for the path "/about" has been cleared

# After: every internal page cache entry.
Given the page cache for the paths matching "/*" is empty

# After: every cache bin, the dynamic page cache included.
Given the cache is empty
```

The path is matched from its first character, so a site served under a base path includes it, as in `/subdir/news*`.

A path or a pattern carrying a query string or a fragment now fails with `The path "..." must not contain a query string or a fragment.`, or `The path pattern "..."` for a pattern. A cached path never holds either, so the argument could only match nothing. Drop the query string, since the step covers every query string of the path already.

The pattern step used to fail whenever the bin's table was missing. The database backend creates that table on its first write, so a missing table only means nothing is cached yet, and both steps now pass. They fail when the bin itself doesn't exist, which is the case when the page_cache module isn't enabled:

| Trait | Before | After |
| --- | --- | --- |
| Drupal\CacheTrait | The page cache table "..." does not exist. Ensure the "..." cache bin is configured. | The cache bin "..." does not exist. Enable the page_cache module or set the "cache.page_cache_bin" option. |

Both steps call `cacheDeletePagePath()`, which is public, so your own step definitions can clear a path the same way. It takes the path and an `$is_pattern` flag that reads `*` as a wildcard.

## Supported image fields reuse existing files

A `supported_image` field now resolves its value the way `file` and `image` fields do: a value naming a managed file that's already on the site references that file, and only a path to a file on disk is uploaded.

| Value | Before | After |
| --- | --- | --- |
| `public://hero.jpg`, the URI of a managed file | Uploads a copy as a new managed file | References the managed file |
| `hero.jpg`, the basename of a managed file in `public://` or `private://` | Fails with `Error reading file hero.jpg.` | References the managed file |

`SupportedImageHandler` extends `ImageHandler` now, and all 3 file handlers share the `doExpand()` in `FileHandler`. A handler of your own that extends `FileHandler` and only changes the properties stored beside the file id can override `getItemProperties()` instead of `doExpand()`, and it reuses existing files the same way.

## Unified assertion exceptions

Assertion steps used to throw whatever their trait happened to reach for: `ExpectationException` in most places, plain `\Exception` in 8 traits, `\RuntimeException` in `XmlTrait`'s format check, and `\InvalidArgumentException` in 2 select-option steps. The type is part of the contract - consumers catch on it - so it now follows one rule.

| Failure | Exception |
| --- | --- |
| An assertion fails and the step can reach the page | `Behat\Mink\Exception\ExpectationException` |
| An assertion fails and the step has no Mink session | `DrevOps\BehatSteps\Exception\AssertionException` |
| An element the step locates is missing, on the page or in an XML response: a field, link, button, select, table or row | `Behat\Mink\Exception\ElementNotFoundException` (a subclass of `ExpectationException`) |
| An attribute, a JSON path or a table column is missing. None of them is an element | `Behat\Mink\Exception\ExpectationException` |
| Anything that is not an assertion - an invalid step argument, an unmet prerequisite, an infrastructure error | `\RuntimeException` |
| A step needs a driver capability the current driver lacks | `Behat\Mink\Exception\UnsupportedDriverActionException` |
| No backend the scenario lists provides a capability the step needs | `DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException`, a `\RuntimeException` |

`ExpectationException` requires a browser driver as its second constructor argument, so traits that never touch the browser cannot construct it. Those traits throw `AssertionException` instead, which carries the same meaning without the dependency.

If your project catches an exception from one of these steps, update the type:

| Trait | Was | Now |
| --- | --- | --- |
| `CommandTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\ConfigTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\ModuleTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\StateTrait` (all `Then` steps) | `\Exception` | `AssertionException` |
| `Drupal\RedirectTrait` (`the following redirects should (not) exist:`) | `\Exception` | `AssertionException` |
| `Drupal\FileTrait` (`an unmanaged file at the URI ... should (not) contain the value ...`, when the file cannot be read) | `\Exception` | `\RuntimeException` |
| `Drupal\MediaTrait` (the steps that create media, when the bundle is missing or does not exist) | `\Exception` | `\RuntimeException` |
| `Drupal\QueueTrait` (all `Then` steps) | `ExpectationException` | `AssertionException` |
| `Drupal\WatchdogTrait` (the check for PHP errors logged during a scenario) | `ExpectationException` | `AssertionException` |
| `MetatagTrait` (all `Then` steps) | `\Exception` | `ExpectationException`; `ElementNotFoundException` when the meta tag itself is missing; `\RuntimeException` when an hreflang alternate page returns an HTTP error |
| `XmlTrait` (`the response should be in XML format`) | `\RuntimeException` | `ExpectationException` |
| `FieldTrait` (`the option ... within the select ... should (not) exist`) | `\InvalidArgumentException` | `ElementNotFoundException` for a missing select or a missing option, `ExpectationException` for an option that exists but should not |
| `Drupal\CacheTrait` (`the page cache for the path(s) ... is empty`) | `\InvalidArgumentException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...`) | `\InvalidArgumentException` | `\RuntimeException` |
| `TableTrait` (any table step, when the table or the row is missing) | `ExpectationException` | `ElementNotFoundException` |
| `ModalTrait` (`I close the modal`, `I click on the element ... in the modal`, `the modal should (not) contain ...`, when the close button, the content element or the target element is missing) | `ExpectationException` | `ElementNotFoundException` |
| `FieldTrait` (`I unselect the option ... from the select ...` and `the option ... within the select ... should not be selected`, when the option is missing; `I fill in the multi-value field ...`, when an input row is missing) | `ExpectationException` | `ElementNotFoundException` |
| `XmlTrait` (every `the XML element ...` and `the XML attribute ... on the element ...` step, when the element is missing) | `ExpectationException` | `ElementNotFoundException` |
| `JsonTrait` (an invalid JSONPath expression, an invalid regular expression, a count that is not an integer, a schema that is not JSON) | `ExpectationException` | `\RuntimeException` |
| `TableTrait` (`the table ... should be sorted by the column ... in ... order`, with a direction other than `ascending` or `descending`) | `ExpectationException` | `\RuntimeException` |
| `ElementTrait` (`... with the index ...`, with an index below 1; `... pinned to the top of the viewport within ... pixels`, with a negative tolerance) | `ExpectationException` | `\RuntimeException` |
| `Drupal\EmailTrait` (`I follow the link with the index ...`, with an index that is not a positive integer) | `ExpectationException` | `\RuntimeException` |
| `FieldTrait` (`I fill in the WYSIWYG field ...`, when the field has no `id` attribute) | `ExpectationException` | `\RuntimeException` |
| `XmlTrait` (`I print the last XML response`, when the document cannot be serialized) | `ExpectationException` | `\RuntimeException` |
| `XmlTrait` (`the response should match the following DTD:` and `the response should match the DTD in the file ...`, when the response has no root element or cannot be serialized) | `ExpectationException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...` without an element, when nothing has focus) | `ExpectationException` | `\RuntimeException` |
| `Drupal\BlockTrait` (the `Given the block :label ...` steps that change a block, when the block does not exist) | `ExpectationException` | `\RuntimeException` |
| `WaitTrait` (`I wait for ... second(s) for AJAX to finish`, without a JavaScript driver) | `\RuntimeException` | `UnsupportedDriverActionException` |
| `FieldTrait` (`I fill in the multi-value field ...`, without a JavaScript driver) | `\RuntimeException` | `UnsupportedDriverActionException` |

14 failure messages changed along with their type:

| Step | Was | Now |
| --- | --- | --- |
| `the response should be in XML format` | `Failed to load XML. Errors: ...` | `The response is not valid XML: ...` |
| `the option :option within the select :selector should exist` | `Element "..." is not found.` / `Option "..." is not found in select "...".` | `Select with id\|name\|label "..." not found.` / `Option in the select "..." with value\|text "..." not found.` |
| `the option :option within the select :selector should not exist` | `Element "..." is not found.` / `Option "..." is found in select "...", but should not.` | `Select with id\|name\|label "..." not found.` / `The option "..." was found in the select "..." on the page "...", but it should not exist.` |
| `I unselect the option :option from the select :selector` | `The option "..." was not found in the select "...".` | `Option in the select "..." with value\|text "..." not found.` |
| `the option :option within the select :selector should not be selected` | `The option "..." was not found in the select "..." on the page ....` | `Option in the select "..." with value\|text "..." not found.` |
| `I fill in the multi-value field :field with the following values:` | `Could not locate input row N for multi-value field "...".` | `Input row of the multi-value field "..." with index "N" not found.` |
| every `the table ...` step, when the table is missing | `Table with selector "..." not found.` | `Table matching css "..." not found.` |
| every `... the row ...` step, when the row is missing | `Table row containing text "..." not found.` | `Table row with text "..." not found.` |
| `I close the modal` | `The modal close button was not found.` | `Modal close button matching css "..." not found.` |
| `I click on the element :selector in the modal` | `The element "..." was not found in the modal.` | `Element in the modal with css\|id\|name\|title\|alt\|value\|text "..." not found.` |
| `the modal should (not) contain :text` | `The modal content element was not found.` | `Modal content element matching css "..." not found.` |
| every `the XML element ...` and `the XML attribute ... on the element ...` step, when the element is missing | `The XML element "..." was not found.` | `XML element matching xpath "..." not found.` |
| `the meta tag should exist with the following attributes:` | `Meta tag with specified attributes was not found: {...}.` | `Meta tag with attributes "{...}" not found.` |
| `the meta tag :name should not contain any HTML tags` | `Meta tag with name or property "..." not found.` | `Meta tag with name\|property "..." not found.` |

The same rule now covers the backend layer and the Behat services, which 4.0 carries in place of the Drupal Driver and the Drupal Extension. In 3.x those classes threw `\InvalidArgumentException` and plain `\Exception` for an invalid argument or an unmet prerequisite. If your project calls one of them directly and catches on the type, update it:

| Class | Was | Now |
| --- | --- | --- |
| `Drupal\Driver\Core\Core`, now `Backend\Core\Core` (an unknown entity type, bundle, vocabulary, language or handler class) | `\InvalidArgumentException` / `\Exception` | `\RuntimeException` |
| `Drupal\Driver\Core\Field\*Handler`, now `Backend\Core\Field\*Handler` (a malformed field value, an unreadable file, a missing referenced entity) | `\InvalidArgumentException` / `\Exception` | `\RuntimeException` |
| `Drupal\DrupalExtension\Manager\DriverManager::getDriver()` and `setDefaultDriverName()`, now `Behat\Registry\BackendRegistry::getBackend()` and `setScenarioBackends()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Drupal\DrupalExtension\Manager\DrupalUserManager::getUser()`, now `Behat\Registry\UserRegistry::getUser()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Drupal\DrupalExtension\Selector\RegionSelector::translateToXPath()`, now `Behat\Selector\RegionSelector::translateToXPath()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Drupal\Driver\Exception\CreationAliasResolutionException`, now `Backend\Exception\CreationAliasResolutionException` | extends `\InvalidArgumentException` | extends `Backend\Exception\Exception` |

`CreationAliasResolutionException` is no longer a `\LogicException`, so a `catch (\InvalidArgumentException)` or `catch (\LogicException)` no longer catches it; catch the class itself.

Behat reports every one of these as a failed step either way, so a scenario that simply runs to a failure behaves the same. Only code that catches a specific type, or asserts on the message text, needs changing.

## Failure messages read one way

A failure message names what it reports about the way its step does, with the article: `the element :selector` fails with `The element "..."` and `the config :name with the key :key` with `The config "..." with the key "..."`. It quotes the values it names in double quotes, the page URL included, puts the noun before the value it names (`the attribute "..."`, not `the "..." attribute`), ends with a period, and closes a broken expectation with `, but it should not` or `, but it should be`. The messages below changed wording only, so the exception a step throws is the same as the row above says; only a test asserting on the text needs the new one. Rows were checked against 3.14.4: a message introduced in 4.x is not listed.

| Trait | Before | After |
| --- | --- | --- |
| Drupal\BlockTrait | The block "..." exists but should not. | The block "..." exists, but it should not. |
| Drupal\BlockTrait | Block "..." is in region "..." but should not be. | The block "..." is in the region "...", but it should not be. |
| Drupal\ConfigTrait | The config "..." key "..." has the ... "...", which contains "..." but should not. | The config "..." with the key "..." has the ... "...", which contains "...", but it should not. |
| Drupal\ConfigTrait | The config "..." key "..." is not set, but it should have the ... "...". | The config "..." with the key "..." is not set, but it should have the ... "...". |
| Drupal\ConfigTrait | The config "..." key "..." has the ... "...", but it should have the ... "...". | The config "..." with the key "..." has the ... "...", but it should have the ... "...". |
| Drupal\ConfigTrait | The config "..." key "..." has the ... "...", but it should not have the ... "...". | The config "..." with the key "..." has the ... "...", but it should not have the ... "...". |
| Drupal\ConfigTrait | The config "..." key "..." is not set, but its ... should contain "...". | The config "..." with the key "..." is not set, but its ... should contain "...". |
| Drupal\ConfigTrait | The config "..." key "..." has the ... "...", which does not contain "...". | The config "..." with the key "..." has the ... "...", which does not contain "...". |
| Drupal\FileTrait | File contents "..." contains "...", but should not. | The file content "..." contains "...", but it should not. |
| LinkTrait | The link href "..." matches the specified href "..." but should not. | The link href "..." matches the specified href "...", but it should not. |
| LinkTrait | The link with the title "..." exists, but should not. | The link with the title "..." exists, but it should not. |
| ElementTrait | Element defined by "..." selector is visible on the page, but should not be. | The element "..." is visible on the page, but it should not be. |
| ElementTrait | Element(s) defined by "..." selector is displayed within a viewport with a top offset of N pixels, but should not be. | The element "..." is displayed within the viewport with a top offset of N pixels, but it should not be. |
| ElementTrait | Element(s) defined by "..." selector is displayed within a viewport, but should not be. | The element "..." is displayed within the viewport, but it should not be. |
| FieldTrait | The field "..." is empty, but should not be. | The field "..." is empty, but it should not be. |
| FieldTrait | The field "..." is marked as required, but should not be. | The field "..." is marked as required, but it should not be. |
| FieldTrait | The option "..." was selected in the select "..." on the page ..., but should not be. | The option "..." was selected in the select "..." on the page "...", but it should not be. |
| FieldTrait | The option "..." was not selected on the page .... | The option "..." was not selected on the page "...". |
| FieldTrait | The radio button "..." is selected, but should not be. | The radio button "..." is selected, but it should not be. |
| FileDownloadTrait | Found file partially named "..." in archive but should not. | Found file partially named "..." in archive, but it should not. |
| ResponseTrait | The response contains the header "...", but should not. | The response contains the header "...", but it should not. |
| PathTrait | The parameter "..." is in the URL but should not be. | The parameter "..." is in the URL, but it should not be. |
| PathTrait | The parameter "..." with value "..." is in the URL but should not be. | The parameter "..." with value "..." is in the URL, but it should not be. |
| MetatagTrait | The robots meta tag does not include the "..." directive. Found: .... | The robots meta tag does not contain the "..." directive. Found: .... |
| MetatagTrait | The robots meta tag includes the "..." directive, but it should not. | The robots meta tag contains the "..." directive, but it should not. |
| XmlTrait | Failed to serialise the response for DTD validation. | Failed to serialize the response for DTD validation. |
| JsonTrait | The JSON response must decode to an array or object, but got integer. (also `boolean`, `double`, `NULL`) | The JSON response must decode to an array or object, but got int. (also `bool`, `float`, `null`) |
| Drupal\ContentTrait | Content type "..." does not exist. | The content type "..." does not exist. |
| Drupal\ContentBlockTrait | Content block type "..." does not exist. | The content block type "..." does not exist. |
| Drupal\UserTrait | User with name "..." does not exist. | The user "..." does not exist. |
| Drupal\MediaTrait | Cannot create media because provided bundle '...' does not exist. | Cannot create media because provided bundle "..." does not exist. |
| ResponsiveTrait | Breakpoint '...' not found. Available breakpoints: ... | Breakpoint "..." not found. Available breakpoints: .... |
| ResponsiveTrait | Invalid breakpoint format for '...': '...'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080) | Invalid breakpoint format for "...": "...". Expected format: WIDTHxHEIGHT (e.g., 1920x1080). |
| ResponsiveTrait | Invalid breakpoint format: '...'. Expected format: WIDTHxHEIGHT (e.g., 1920x1080) | Invalid breakpoint format: "...". Expected format: WIDTHxHEIGHT (e.g., 1920x1080). |
| Drupal\FileTrait | The file "..." exists but it should not. | The file "..." exists, but it should not. |
| CookieTrait | The cookie with name "..." was set but it should not be. | The cookie with name "..." was set, but it should not be. |
| CookieTrait | The cookie with name containing "..." was set but it should not be. | The cookie with name containing "..." was set, but it should not be. |
| Drupal\BlockTrait | Block "..." is in region "..." but should be in "...". | The block "..." is in the region "...", but it should be in the region "...". |
| Drupal\EmailTrait | Invalid email field ... was specified for assertion. | Invalid email field "..." was specified for assertion. |
| MetatagTrait | Failed to fetch the hreflang alternate page "...". | Failed to fetch the hreflang alternate page "...": .... |
| FileDownloadTrait | Unable to download file from URL .... | Unable to download file from URL "...". |
| FileDownloadTrait | The URL ... returned HTTP status N. | The URL "..." returned HTTP status N. |
| FileDownloadTrait | Unable to save temp file from URL .... | Unable to save temp file from URL "...". |
| FileDownloadTrait | Unable to write downloaded content into file .... | Unable to write downloaded content into file "...". |
| ElementTrait | The "..." attribute does not exist on the element "...". | The attribute "..." does not exist on the element "...". |
| ElementTrait | The "..." attribute exists on the element "..." with a value "...", but it should not. | The attribute "..." exists on the element "..." with a value "...", but it should not. |
| ElementTrait | The "..." attribute exists on the element "..." with a value containing "...", but it should not. | The attribute "..." exists on the element "..." with a value containing "...", but it should not. |
| ElementTrait | The "..." attribute exists on the element "..." with a value "...", but it does not have a value "...". | The attribute "..." exists on the element "..." with a value "...", but it does not have a value "...". |
| ElementTrait | The "..." attribute exists on the element "..." with a value "...", but it does not contain a value "...". | The attribute "..." exists on the element "..." with a value "...", but it does not contain a value "...". |
| MetatagTrait | The "..." meta tag contains HTML tags: .... | The meta tag "..." contains HTML tags: .... |
| Drupal\EmailTrait | No emails should have been sent, but some were found: ... | An email was sent, but it should not have been: ... |
| ElementTrait | Element "..." appears before "...". | The element "..." appears before the element "...". |
| ElementTrait | Text was not found: "...". | The text "..." was not found. |
| ElementTrait | Text "..." appears before "...". | The text "..." appears before the text "...". |
| ElementTrait | Element with selector "..." is not at the top of the viewport. | The element "..." is not at the top of the viewport. |
| ElementTrait | Element with selector "..." is not centered in the viewport. | The element "..." is not centered in the viewport. |
| ElementTrait | None of the elements defined by "..." selector are visible on the page. | The element "..." is not visible on the page. |
| ElementTrait | Expected element "..." to ... (the stacking, pinned, keyboard focus and focus outline messages) | Expected the element "..." to ... |
| FieldTrait | The field "..." is not empty, but should be. | The field "..." is not empty, but it should be. |
| FieldTrait | The field "..." is not marked as required, but should be. | The field "..." is not marked as required, but it should be. |
| FieldTrait | The radio button "..." is not selected, but should be. | The radio button "..." is not selected, but it should be. |
| FieldTrait | A field "..." appears on this page, but it should not. | The field "..." appears on this page, but it should not. |
| FieldTrait | A field "..." should be disabled, but it is not. | The field "..." should be disabled, but it is not. |
| FieldTrait | A field "..." should not be disabled, but it is. | The field "..." should not be disabled, but it is. |
| FieldTrait | Color field "..." expected a value "..." but has a value "...". | The color field "..." expected a value "..." but has a value "...". |
| TableTrait | Expected table "..." to ... (the row count, column count, empty and not empty messages) | Expected the table "..." to ... |
| TableTrait | Expected table "..." to be sorted by "..." in ... order. Actual values: .... | Expected the table "..." to be sorted by the column "..." in ... order. Actual values: .... |
| TableTrait | Column "..." not found in table "...". Available columns: .... | The column "..." was not found in the table "...". Available columns: .... |
| TableTrait | Row N with values [...] not found in table "...". | The table "..." does not contain the row N with the values [...]. |
| TableTrait | Row containing "..." does not contain expected text "...". | The row containing "..." does not contain the text "...". |
| Drupal\UserTrait | User "..." does not have role(s) "...", but has roles "...". | The user "..." does not have role(s) "...", but has roles "...". |
| Drupal\UserTrait | User "..." should not have role(s) "...", but has "...". | The user "..." should not have role(s) "...", but has "...". |
| Drupal\UserTrait | User with email "..." is expected to exist, but they do not. | The user with the email "..." is expected to exist, but they do not. |
| Drupal\UserTrait | User with email "..." is expected to not exist, but they do. | The user with the email "..." is expected to not exist, but they do. |
| Drupal\UserTrait | User "..." is expected to be blocked, but they are not. | The user "..." is expected to be blocked, but they are not. |
| Drupal\UserTrait | User "..." is expected to not be blocked, but they are. | The user "..." is expected to not be blocked, but they are. |
| Drupal\QueueTrait | Expected queue "..." to have N items, but it has N. | Expected the queue "..." to have N items, but it has N. |
| Drupal\QueueTrait | Expected queue "..." to be empty, but it has N items. | Expected the queue "..." to be empty, but it has N items. |
| Drupal\FileTrait | File contents "..." does not contain "...". | The file content "..." does not contain "...". |
| FileDownloadTrait | Downloaded file "...", but expected "...". | The downloaded file name is "...", but expected "...". |
| MetatagTrait | Meta tag with specified attributes should not exist: .... | The meta tag with the attributes "..." exists, but it should not. |
| RestTrait | Expected response status code N, but got N. | Expected the REST response status code to be N, but got N. |
| PathTrait | Current path is "...", but expected is "...". | The current path is "...", but it should be "...". |
| PathTrait | Current path should not be "...". | The current path should not be "...", but it is. |
| Drupal\ContentTrait | "..." content with the title "..." should ... (the 3 existence and publishing messages) | The "..." content with the title "..." should ... |
| FileDownloadTrait | Downloaded file name "..." does not contain "...". | The downloaded file name "..." does not contain "...". |
| FileDownloadTrait | Downloaded file does not have correct headers set for ZIP. | The downloaded file does not have correct headers set for ZIP. |
| FileDownloadTrait | Downloaded file is not a valid ZIP file. | The downloaded file is not a valid ZIP file. |
| Drupal\EmailTrait | Unable to find email that should be sent to "..." retrieved from test email collector. | Unable to find an email that should be sent to "..." retrieved from test email collector. |
| Drupal\EmailTrait | Unable to find email with subject "..." retrieved from test email collector. (also `with subject containing`) | Unable to find an email with the subject "..." retrieved from test email collector. (also `with the subject containing`) |

## Tightened public surface

A handful of trait members exposed more than the surrounding code intended. Each one is reachable from a consuming context, so they're grouped here as breaking changes rather than fixed quietly. A `PublicSurfaceTest` now holds each of these conventions, so the surface stays deliberate from here on.

### The toolbox is now `public`

Visibility marks the API: a `public` method that Behat does not register is the toolbox, listed in [HELPERS.md](HELPERS.md) and covered by semantic versioning, and a `protected` one is an implementation detail. Helpers a project calls from its own step definitions were promoted to `public` for this, and the rest stayed `protected`.

PHP refuses to narrow an inherited method, so a context that overrides one of the promoted helpers as `protected` no longer loads:

```
Fatal error: Access level to FeatureContext::bigPipeGetWaitTimeout() must be public (as in class ...)
```

Change the `protected` keyword to `public` on any override of a method [HELPERS.md](HELPERS.md) lists. The body and signature are unchanged, and overriding still works exactly as before.

### Internal helpers are now `protected`

Neither method is a step or a hook, and both were only ever called from step methods in their own trait. Calling them from outside the context object no longer works. From inside it, `keyboardPressKeyOnElementSingle()` is unchanged and `fileDownloadAssertLinkPresent()` is now `fileDownloadGetLink()`, listed under [A lookup's verb says what a miss does](#a-lookups-verb-says-what-a-miss-does).

| Method | Was | Now |
| --- | --- | --- |
| `KeyboardTrait::keyboardPressKeyOnElementSingle()` | `public` | `protected` |
| `FileDownloadTrait::fileDownloadAssertLinkPresent()` | `public` | `protected` |

`DateTrait::dateRelativeProcessValue()` and `ResponsiveTrait::responsiveSetBreakpoints()` stay public and are now documented as API in their docblocks. `DateTrait` also stays static on purpose: `dateGetNow()` is the supported seam for pinning the clock. A 3.x override of `protected static function dateNow()` renames to `dateGetNow()`, as [Consumer override points are `Get`-prefixed](#consumer-override-points-are-get-prefixed) lists, and declares it `public static`.

### Constants carry their trait prefix

PHP treats two composed traits declaring the same constant name as a fatal error, so a generic name like `IMPACT_CRITICAL` is a collision waiting to happen in someone else's context.

| Constant | Replacement |
| --- | --- |
| `AccessibilityTrait::IMPACT_CRITICAL` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_CRITICAL` |
| `AccessibilityTrait::IMPACT_SERIOUS` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_SERIOUS` |
| `AccessibilityTrait::IMPACT_MODERATE` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_MODERATE` |
| `AccessibilityTrait::IMPACT_MINOR` | `AccessibilityTrait::ACCESSIBILITY_IMPACT_MINOR` |
| `Drupal\BigPipeTrait::DEFAULT_WAIT_TIMEOUT` | Removed; read the `big_pipe.wait_timeout` option, as [`BigPipeTrait` reads its timeout from configuration](#bigpipetrait-reads-its-timeout-from-configuration) shows |

### `FieldTrait` no longer re-exports the keyboard steps

`FieldTrait` composed `KeyboardTrait` without calling it, so a context composing only `FieldTrait` silently received every keyboard step. That composition is gone. If your context relies on those steps, compose the trait directly:

```php
use DrevOps\BehatSteps\Steps\Web\KeyboardTrait;

class FeatureContext extends WebRawContext {

  use FieldTrait;
  use KeyboardTrait;

}
```

### Hooks declare their scope parameter

Hook methods used to come in 3 shapes: taking and using the scope, taking and ignoring it, or declaring no parameter at all. They all declare it now, used or not, so there's one signature to match when you override one. If you override any of these in your `FeatureContext`, add the parameter:

| Hook | New signature |
| --- | --- |
| `CommandTrait::commandAfterScenario()` | `(AfterScenarioScope $scope)` |
| `CommandTrait::commandBeforeScenario()` | `(BeforeScenarioScope $scope)` |
| `JsonTrait::jsonAfterScenario()` | `(AfterScenarioScope $scope)` |
| `JsonTrait::jsonBeforeScenario()` | `(BeforeScenarioScope $scope)` |
| `XmlTrait::xmlAfterScenario()` | `(AfterScenarioScope $scope)` |
| `XmlTrait::xmlBeforeScenario()` | `(BeforeScenarioScope $scope)` |

2 hooks that changed name also gained the parameter: `bigPipeWaitBeforeStep()` and `accessibilityAggregateRender()`. [A hook is named for its event](#a-hook-is-named-for-its-event) gives their new names and signatures.

2 protected helpers changed signature too, so an override of either takes the new one:

| Method | 3.x | v4 |
| --- | --- | --- |
| `AccessibilityTrait::accessibilityResolveTags()` | `(array $tags)` | `(BeforeScenarioScope $scope)` |
| `Drupal\EmailTrait::emailSetMailSystemDefault()` | `static (string $type, mixed $value)` | `(string $type, mixed $value)`, no longer `static` |

### Properties declare native types

5 properties relied on a `@var` docblock with no native type. They're typed now, which narrows what a subclass may assign to them.

| Property | Type |
| --- | --- |
| `Drupal\FileTrait::$filesUnmanagedUris`, renamed `$fileUnmanagedUris` | `array` |
| `Drupal\RedirectTrait::$redirectAllowedStatusCodes` | `array` |
| `Drupal\WatchdogTrait::$watchdogMessageTypes` | `array` |
| `Drupal\WatchdogTrait::$watchdogScenarioStartTime` | `?int` |
| `FileDownloadTrait::$fileDownloadDownloadedFileInfo` | `array` |

## Relative-date transform placeholder renamed

`DateTrait` registers its relative-date transform against the placeholder names a step argument can carry. One of those names was camel case, which no other placeholder in the library uses and which the snake case placeholder rule forbids.

| Before | After |
| --- | --- |
| `#[Transform(':expectedValue')]` | `#[Transform(':expected_value')]` |

No step shipped by this library declares `:expectedValue`, so the shipped vocabulary is unaffected. A project whose own step declares an `:expectedValue` argument and relies on `[relative:...]` tokens being expanded in it renames that argument to `:expected_value`. The `:datetime` and `:value` placeholders are unchanged.

## One shape per naming idea

Method names carried 6 shapes for "assert the negative", 2 spellings of "normalize" and 2 of "log in", 2 shapes for a consumer override point, 3 lookup verbs that didn't say what a lookup does when nothing matches, 2 word orders for a method that creates an entity, 3 shapes for a method acting on several entities, and assertions that put a qualifier ahead of their predicate, used `Has`, `Includes` or `Present` where the rules say `Equals`, `Contains` or `Exists`, or weren't named as assertions at all. They are members a consumer calls, overrides or implements, so each is renamed rather than aliased. These renames leave Gherkin step text alone, apart from the 2 meta robots steps, whose rows are under [One wording per step idea](#one-wording-per-step-idea). Where a renamed method's step text, placeholders or behavior changed between 3.x and v4 for another reason, another section of this guide lists it.

`CONTRIBUTING.md` states the settled conventions, and `tests/phpunit/src/TraitMethodNamingTest.php` and `tests/phpunit/src/CapabilityMethodNamingTest.php` enforce them.

### Negation is spelled `Not`, in one slot

`Not` sits immediately after `Assert<Subject>`, directly before the predicate it negates, so a negative name is its positive counterpart with `Not` inserted and nothing else changed. The determiner `No`, the copula `Is`, and antonyms standing in for a negation are gone.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\EmailTrait` | `emailAssertNoMessagesSent()` | `emailAssertMessageNotSent()` |
| `Drupal\EmailTrait` | `emailAssertNoMessagesSentToAddress()` | `emailAssertMessageNotSentToAddress()` |
| `Drupal\FileTrait` | `fileAssertUnmanagedHasNoContent()` | `fileAssertUnmanagedNotContains()` |
| `Drupal\UserTrait` | `userAssertHasNoRoles()` | `userAssertNotHasRoles()` |
| `Drupal\UserTrait` | `userAssertIsBlocked()` | `userAssertBlocked()` |
| `Drupal\UserTrait` | `userAssertIsNotBlocked()` | `userAssertNotBlocked()` |
| `Drupal\WatchdogTrait` | `watchdogAssertNoErrors()` | `watchdogAssertErrorsNotExist()` |
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
| `JavascriptTrait` | `javascriptAssertNoErrors()` | `javascriptAssertErrorsNotExist()` |
| `JsonTrait` | `jsonAssertResponseIsJson()` | `jsonAssertResponseJson()` |
| `JsonTrait` | `jsonAssertResponseIsNotJson()` | `jsonAssertResponseNotJson()` |
| `LinkTrait` | `linkAssertLinkIsAbsolute()` | `linkAssertAbsolute()` |
| `LinkTrait` | `linkAssertLinkIsNotAbsolute()` | `linkAssertNotAbsolute()` |
| `MetatagTrait` | `metatagAssertNoHtml()` | `metatagAssertNotContainsHtml()` |
| `PathTrait` | `pathAssertUrlHasNoParameter()` | `pathAssertUrlParameterNotExists()` |
| `PathTrait` | `pathAssertUrlHasNoParameterWithValue()` | `pathAssertUrlParameterNotEquals()` |
| `XmlTrait` | `xmlAssertResponseIsXml()` | `xmlAssertResponseXml()` |
| `XmlTrait` | `xmlAssertResponseIsNotXml()` | `xmlAssertResponseNotXml()` |

`ElementTrait::elementAssertPinnedToTop()` appears on both sides of that table. The public step took the name once its copula was dropped, and the protected helper that backs all 3 pinned-to-top steps moved to `elementAssertPinnedToTopWithin()`, after the tolerance it takes. A 3.x call to the helper doesn't fail: it reaches the step, and PHP drops the 2 extra arguments, so it asserts that the element is pinned within 2 pixels, whatever tolerance and direction it passed. Move each call to `elementAssertPinnedToTopWithin()`.

The file, watchdog, JavaScript and path rows point straight at the names [`Has` names something the subject holds](#has-names-something-the-subject-holds) settles on.

3 `Drupal\EmailTrait` methods asserted an exact match under names that gave no way to derive one from the other. They now carry the `Equals` predicate the rest of the library uses.

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
| `Drupal\StateTrait` | `stateNormaliseValue()` | `Helper\Web\StringTrait::stringNormalizeValue()`, as [Step logic moved into shared helpers](#step-logic-moved-into-shared-helpers) describes |
| `ElementTrait` | `elementNormaliseCssProperty()` | `elementNormalizeCssProperty()` |
| `Drupal\Driver\Core\Field\AbstractHandler`, now `Backend\Core\Field\AbstractHandler` | `normalise()` | `normalize()` |

A field handler of your own that overrides `normalise()` isn't called any more, and nothing reports it, until the override is renamed `normalize()`.

### `Login` and `Logout`, not `LogIn` and `LogOut`

Logging in and out is spelled as 1 word in every name, as the Drupal Extension's `RawDrupalContext::login()`, `FastLogoutInterface` and `login_url` key already spelled it. Step text keeps the 2-word verb, as in `When I log in as the user :name`.

| Where | Old | New |
| --- | --- | --- |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManagerInterface`, now `Behat\Auth\AuthenticatorInterface` | `logIn()` | `login()` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManagerInterface`, now `Behat\Auth\AuthenticatorInterface` | `logOut()` | `logout()` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManagerInterface`, now `Behat\Auth\AuthenticatorInterface` | `loggedIn()` | `isLoggedIn()` |
| `Drupal\DrupalExtension` configuration, now `BehatStepsExtension` | `text: log_in:` | `text: login:` |
| `Drupal\DrupalExtension` configuration, now `BehatStepsExtension` | `text: log_out:` | `text: logout:` |

PHP matches method names without regard to case, so the `logIn()` and `logOut()` rows need no edit of their own: only the interface they're declared on is renamed. A custom authentication manager that declares `loggedIn()` fails to load until it declares `isLoggedIn()`, and a `log_in` or `log_out` key under `text` fails the container build with a message naming its replacement. The methods behind the Drupal Extension's session steps are listed under [Session and users](#session-and-users).

### Consumer override points are `Get`-prefixed

A documented override point that supplies a value now reads `<trait>Get<Noun>()`, booleans included. The ones that already did are unchanged.

| Trait | Old | New |
| --- | --- | --- |
| `CommandTrait` | `commandTimeout()` | `commandGetTimeout()` |
| `DateTrait` | `dateNow()` | `dateGetNow()` |
| `DiagnosticsTrait` | `diagnosticsHeader()` | `diagnosticsGetHeader()` |
| `DiagnosticsTrait` | `diagnosticsRerunBinary()` | `diagnosticsGetRerunBinary()` |
| `DiagnosticsTrait` | `diagnosticsShowDriver()` | `diagnosticsGetShowBrowserDriver()` |
| `DiagnosticsTrait` | `diagnosticsShowJsErrors()` | `diagnosticsGetShowJsErrors()` |
| `DiagnosticsTrait` | `diagnosticsShowRerun()` | `diagnosticsGetShowRerun()` |
| `DiagnosticsTrait` | `diagnosticsShowStatusCode()` | `diagnosticsGetShowStatusCode()` |
| `DiagnosticsTrait` | `diagnosticsShowUrl()` | `diagnosticsGetShowUrl()` |
| `ElementTrait` | `elementScrollIntoViewCenter()` | `elementGetScrollIntoViewCenter()` |
| `MetatagTrait` | `metatagOpenGraphRequired()` | `metatagGetRequiredOpenGraphTags()` |
| `MetatagTrait` | `metatagTwitterCardRequired()` | `metatagGetRequiredTwitterCardTags()` |

The 2 `MetatagTrait` override points also name what they return, the required tags, since `OpenGraphRequired` on its own isn't a noun.

An override is a method of your own that the trait calls, so one left under its 3.x name isn't called any more, and nothing reports it: a `dateNow()` that pinned the clock stops pinning it. Rename each override, and declare it `public`, as [The toolbox is now `public`](#the-toolbox-is-now-public) describes.

### A lookup's verb says what a miss does

`Find`, `Load` and `Get` each named some lookups that return `NULL` when nothing matches and others that throw, sometimes in the same trait: `TableTrait` had a `tableFind()` that threw beside a `tableFindRowByText()` that returned `NULL`. The verb now carries the contract. A `Find` returns `NULL`, a `Get` throws and never returns `NULL`, and a `Load` loads a set, so no lookup for 1 item is named `Load`.

The last column is each method's v4 contract. Besides the name, `userGetByName()` returns `UserInterface` where `userLoadByName()` declared `?UserInterface`, `metatagFindMetaContent()` names its parameter `$meta_name` where it took `$name`, and `fileDownloadGetLink()` is protected where `fileDownloadAssertLinkPresent()` was public. 2 of them throw differently from 3.x: `tableGet()` throws `ElementNotFoundException` where `tableFind()` threw `ExpectationException`, and `elementGetNth()` throws `\RuntimeException` for an index below 1 where `elementFindNthOrFail()` threw `ExpectationException`. The lookups by title or label pick the newest match, as [A lookup by title acts on the newest match](#a-lookup-by-title-acts-on-the-newest-match) describes, and some of their messages changed, as [Failure messages read one way](#failure-messages-read-one-way) lists.

| Trait | Old | New | When nothing matches |
| --- | --- | --- | --- |
| `Drupal\BlockTrait` | `blockLoadByLabel()` | `blockFindByLabel()` | returns `NULL` |
| `Drupal\ContentTrait` | `contentLoadNodeByTitle()` | `contentGetNodeByTitle()` | throws `\RuntimeException` |
| `Drupal\ContentTrait` | `contentResolveNidByTitle()` | `contentGetNidByTitle()` | throws `\RuntimeException` |
| `Drupal\EmailTrait` | `emailGetMailSystemDefault()` (protected) | `emailFindMailSystemDefault()` | returns `NULL` |
| `Drupal\EmailTrait` | `emailGetMailSystemOriginal()` (protected) | `emailFindMailSystemOriginal()` | returns `NULL` |
| `Drupal\UserTrait` | `userLoadByName()` | `userGetByName()` | throws `\RuntimeException` |
| `Drupal\WebformTrait` | `webformTemplates()` | `webformLoadTemplateMultiple()` | returns an empty array |
| `CookieTrait` | `cookieGetByName()` | `cookieFindByName()` | returns `NULL` |
| `DiagnosticsTrait` | `diagnosticsGetDriverName()` | `diagnosticsFindBrowserDriverName()` | returns `NULL` |
| `DiagnosticsTrait` | `diagnosticsGetRerunCommand()` | `diagnosticsFindRerunCommand()` | returns `NULL` |
| `DiagnosticsTrait` | `diagnosticsGetStatusCode()` | `diagnosticsFindStatusCode()` | returns `NULL` |
| `DiagnosticsTrait` | `diagnosticsGetUrl()` | `diagnosticsFindUrl()` | returns `NULL` |
| `ElementTrait` | `elementFindNthOrFail()` | `elementGetNth()` | throws `ElementNotFoundException`, or `ExpectationException` past the last match |
| `FileDownloadTrait` | `fileDownloadAssertLinkPresent()` | `fileDownloadGetLink()` (protected) | throws `ElementNotFoundException` |
| `JsonTrait` | `jsonResolveSingle()` (protected) | `jsonGetValue()` | throws `ExpectationException` |
| `JsonTrait` | `jsonResolveScalar()` (protected) | `jsonGetScalar()` | throws `ExpectationException` |
| `MetatagTrait` | `metatagGetCanonicalHref()` | `metatagFindCanonicalHref()` | returns `NULL` |
| `MetatagTrait` | `metatagGetMetaContent()` | `metatagFindMetaContent()` | returns `NULL` |
| `ModalTrait` | `modalFindVisible()` | `modalGetVisible()` | throws `ExpectationException` |
| `TableTrait` | `tableFind()` | `tableGet()` | throws `ElementNotFoundException` |

The 2 `MenuTrait` lookups go straight to their `Find` names, listed under [Trait methods prefixed with their trait name](#trait-methods-prefixed-with-their-trait-name). A lookup that already matched its contract keeps its name, such as `tableFindRowByText()`, `modalFind()`, `metatagFindMeta()` and `emailFindMessage()`.

`webformTemplates()` carried no verb at all, and `fileDownloadAssertLinkPresent()` was named as an assertion although it returns the link it finds, so both take the lookup verb for what they do.

`Resolve` derives a value from its input, as `restResolveUrl()` does, so the 2 JSON path lookups take `Get` and drop `Single`. A JSON `null` is a value the path matches, so `jsonGetValue()` returns `NULL` for it and throws only when the path matches nothing or more than 1 value.

### A qualifier follows the predicate

An assertion that narrows its subject with a qualifier, such as a cookie's name or an attribute's value, put that qualifier ahead of the predicate in some traits and after it in others: `cookieAssertWithNameExists()` sat beside `mediaAssertExistsWithName()`. Ahead of the predicate, the qualifier takes the slot `Not` belongs in, so the negative read `cookieAssertWithNameNotExists()`. The qualifier now follows the predicate everywhere, and each negative is its positive with `Not` inserted.

| Trait | Old | New |
| --- | --- | --- |
| `CookieTrait` | `cookieAssertWithNameExists()` | `cookieAssertExistsWithName()` |
| `CookieTrait` | `cookieAssertWithNameValueExists()` | `cookieAssertExistsWithNameValue()` |
| `CookieTrait` | `cookieAssertWithNamePartialValueExists()` | `cookieAssertExistsWithNamePartialValue()` |
| `CookieTrait` | `cookieAssertWithPartialNameExists()` | `cookieAssertExistsWithPartialName()` |
| `CookieTrait` | `cookieAssertWithPartialNameValueExists()` | `cookieAssertExistsWithPartialNameValue()` |
| `CookieTrait` | `cookieAssertWithPartialNamePartialValueExists()` | `cookieAssertExistsWithPartialNamePartialValue()` |
| `CookieTrait` | `cookieAssertWithNameNotExists()` | `cookieAssertNotExistsWithName()` |
| `CookieTrait` | `cookieAssertWithNameValueNotExists()` | `cookieAssertNotExistsWithNameValue()` |
| `CookieTrait` | `cookieAssertWithNamePartialValueNotExists()` | `cookieAssertNotExistsWithNamePartialValue()` |
| `CookieTrait` | `cookieAssertWithPartialNameNotExists()` | `cookieAssertNotExistsWithPartialName()` |
| `CookieTrait` | `cookieAssertWithPartialNameValueNotExists()` | `cookieAssertNotExistsWithPartialNameValue()` |
| `CookieTrait` | `cookieAssertWithPartialNamePartialValueNotExists()` | `cookieAssertNotExistsWithPartialNamePartialValue()` |
| `ElementTrait` | `elementAssertAttributeWithValueExists()` | `elementAssertExistsWithAttributeValue()` |
| `ElementTrait` | `elementAssertAttributeContainingValueExists()` | `elementAssertExistsWithAttributeContainingValue()` |
| `ElementTrait` | `elementAssertAttributeWithValueNotExists()` | `elementAssertNotExistsWithAttributeValue()` |
| `ElementTrait` | `elementAssertAttributeContainingValueNotExists()` | `elementAssertNotExistsWithAttributeContainingValue()` |
| `LinkTrait` | `linkAssertTextWithHrefExists()` | `linkAssertExistsWithHref()` |
| `LinkTrait` | `linkAssertTextWithHrefWithinElementExists()` | `linkAssertExistsWithHrefWithinElement()` |
| `LinkTrait` | `linkAssertTextWithHrefNotExists()` | `linkAssertNotExistsWithHref()` |
| `LinkTrait` | `linkAssertTextWithHrefWithinElementNotExists()` | `linkAssertNotExistsWithHrefWithinElement()` |
| `LinkTrait` | `linkAssertWithTitleExists()` | `linkAssertExistsWithTitle()` |
| `LinkTrait` | `linkAssertWithTitleNotExists()` | `linkAssertNotExistsWithTitle()` |
| `MetatagTrait` | `metatagAssertWithAttributesExists()` | `metatagAssertExistsWithAttributes()` |
| `MetatagTrait` | `metatagAssertWithAttributesNotExists()` | `metatagAssertNotExistsWithAttributes()` |
| `TableTrait` | `tableAssertMultipleTextsInRow()` | `tableAssertRowContainsMultiple()` |

The subject is what the step asserts about. `ElementTrait`'s attribute steps assert that an element exists, so the attribute and its value join the qualifier, and `LinkTrait` drops `Text`, which named how the step finds the link: `the link :link with the href :href should exist` is `linkAssertExistsWithHref()`. `the row containing :partial_text should contain the following:` asserts about the row, so `TableTrait` names it first.

`Drupal\EmailTrait::emailAssertMessageSentToAddressWithContentNotContaining()` negates its content check rather than the send, so `Not` can't move into the predicate slot without changing what it asserts, and `emailAssertMessageNotSentToAddressWithContentContaining()` already asserts the other thing. The message sent to the address becomes the subject instead:

| Old | New |
| --- | --- |
| `emailAssertMessageSentToAddressWithContentNotContaining()` | `emailAssertMessageSentToAddressNotContains()` |

It still asserts that an email went to the address and that no collected email's body contains the text.

### A qualifier on an action is `With`

`By` is the lookup spelling: `Find`, `Get` and `Exists` methods name the key they search by, as `blockFindByLabel()` and `userExistsByMail()` do. An assertion or an action that narrows its target reads `With`, as its step text does, so the 8 names below join `mediaAssertExistsWithName()` and `contentVisitEditPageWithTitle()`. Step text is unchanged.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\UserTrait` | `userAssertExistsByMail()` | `userAssertExistsWithMail()` |
| `Drupal\UserTrait` | `userAssertNotExistsByMail()` | `userAssertNotExistsWithMail()` |
| `Drupal\TaxonomyTrait` | `taxonomyAssertTermExistsByName()` | `taxonomyAssertTermExistsWithName()` |
| `Drupal\TaxonomyTrait` | `taxonomyAssertTermNotExistsByName()` | `taxonomyAssertTermNotExistsWithName()` |
| `Drupal\ContentTrait` | `contentRebuildAccessGrantsByTitle()` | `contentRebuildAccessGrantsWithTitle()` |
| `ElementTrait` | `elementClickByIndex()` | `elementClickWithIndex()` |
| `ElementTrait` | `elementFollowLinkByIndex()` | `elementFollowLinkWithIndex()` |
| `ElementTrait` | `elementPressButtonByIndex()` | `elementPressButtonWithIndex()` |

2 `Drupal\EmailTrait` assertions named their target unlike their siblings: the attachment check named the file instead of the subject that picks the email, and the address check dropped the `Address` its 6 siblings carry.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\EmailTrait` | `emailAssertMessageContainsAttachmentWithName()` | `emailAssertMessageContainsAttachmentWithSubject()` |
| `Drupal\EmailTrait` | `emailAssertMessageSentTo()` | `emailAssertMessageSentToAddress()` |

### `Has` names something the subject holds

`Has` named something a subject holds, such as a user's roles, and also stood in for a comparison: `stateAssertHasValue()` checks that a state value equals the expected one. A compared value now reads `Equals` or `Contains`, and `Has` stays for what a subject holds, as in `userAssertHasRoles()` and `elementAssertHasKeyboardFocus()`.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\FileTrait` | `fileAssertUnmanagedHasContent()` | `fileAssertUnmanagedContains()` |
| `Drupal\StateTrait` | `stateAssertHasValue()` | `stateAssertValueEquals()` |
| `ElementTrait` | `elementAssertHasCssPropertyWithValue()` | `elementAssertCssPropertyEquals()` |
| `ElementTrait` | `elementAssertHasCssPropertyContainingValue()` | `elementAssertCssPropertyContains()` |
| `ElementTrait` | `elementAssertNotHasCssPropertyWithValue()` | `elementAssertCssPropertyNotEquals()` |
| `ElementTrait` | `elementAssertNotHasCssPropertyContainingValue()` | `elementAssertCssPropertyNotContains()` |
| `FieldTrait` | `fieldAssertColorFieldHasValue()` | `fieldAssertColorFieldEquals()` |
| `PathTrait` | `pathAssertUrlHasParameter()` | `pathAssertUrlParameterExists()` |
| `PathTrait` | `pathAssertUrlHasParameterWithValue()` | `pathAssertUrlParameterEquals()` |

`PathTrait`'s existence pair follows its value pair, as `ResponseTrait`'s header assertions do, so all 4 name `UrlParameter` before the predicate. The watchdog and JavaScript error checks read like `messageAssertErrorsNotExist()`, because errors are entries that must be absent rather than something the log holds. Those and the negative file and path assertions shipped under older names, so their rows in [Negation is spelled `Not`, in one slot](#negation-is-spelled-not-in-one-slot) point straight at the new names.

### `Contains` and `Exists`, not `Includes` and `Present`

| Trait | Old | New |
| --- | --- | --- |
| `MetatagTrait` | `metatagAssertRobotsIncludes()` | `metatagAssertRobotsContains()` |
| `MetatagTrait` | `metatagAssertRobotsNotIncludes()` | `metatagAssertRobotsNotContains()` |
| `MetatagTrait` | `metatagAssertMetaSetPresent()` | `metatagAssertMetaSetExists()` |

The step text follows the method: `the meta robots should include :directive` is `the meta robots should contain :directive`, and `should not include` is `should not contain`.

### An assertion names its predicate

An assertion says what it asserts after its subject: a compared value reads `Equals`, a set that must be present reads `Exist`, validity reads `Valid` after the subject, and a check the subject must pass reads `Passes`, as `commandAssertOutputEquals()`, `metatagAssertHreflangValid()` and `accessibilityAssertCurrentPagePasses()` do. 9 assertions named no predicate or put `Valid` ahead of the subject. The renames change no step text. The step behind `accessibilityAssertCurrentPagePassesForTags()` changed for another reason, as [Step text follows the documented grammar](#step-text-follows-the-documented-grammar) lists.

| Trait | Old | New |
| --- | --- | --- |
| `AccessibilityTrait` | `accessibilityAssertCurrentPage()` | `accessibilityAssertCurrentPagePasses()` |
| `AccessibilityTrait` | `accessibilityAssertCurrentPageForTags()` | `accessibilityAssertCurrentPagePassesForTags()` |
| `CommandTrait` | `commandAssertExitCode()` | `commandAssertExitCodeEquals()` |
| `FileDownloadTrait` | `fileDownloadAssertFileName()` | `fileDownloadAssertFileNameEquals()` |
| `MetatagTrait` | `metatagAssertOpenGraphTags()` | `metatagAssertOpenGraphTagsExist()` |
| `MetatagTrait` | `metatagAssertTwitterCardTags()` | `metatagAssertTwitterCardTagsExist()` |
| `RestTrait` | `restAssertResponseStatusCode()` | `restAssertResponseStatusCodeEquals()` |
| `XmlTrait` | `xmlAssertValidRssFeed()` | `xmlAssertRssFeedValid()` |
| `XmlTrait` | `xmlAssertValidAtomFeed()` | `xmlAssertAtomFeedValid()` |

### Only an assertion is named `Assert`

A method that only checks something and fails with an assertion exception is named as an assertion, whether or not it registers a step. A method that only rejects a bad step argument or a missing precondition throws `\RuntimeException` instead, so it isn't an assertion and is named for what it does.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\ConfigTrait` | `configCompareContains()` (protected) | `configAssertContains()` |
| `Drupal\ConfigTrait` | `configCompareEquals()` (protected) | `configAssertEquals()` |
| `CommandTrait` | `commandAssertHasRun()` (protected) | `commandRequireRun()` |
| `CookieTrait` | `cookieExists()` | `cookieAssertExists()` |
| `CookieTrait` | `cookieNotExists()` | `cookieAssertNotExists()` |
| `JsonTrait` | `jsonValidateSchema()` (protected) | `jsonAssertResponseMatchesSchema()` |
| `XmlTrait` | `xmlValidateXsd()` (protected) | `xmlAssertResponseMatchesXsd()` |
| `XmlTrait` | `xmlValidateRelaxNg()` (protected) | `xmlAssertResponseMatchesRelaxNg()` |
| `XmlTrait` | `xmlValidateDtd()` (protected) | `xmlAssertResponseMatchesDtd()` |
| `XmlTrait` | `xmlValidateRssFeed()` (protected) | folded into `xmlAssertRssFeedValid()` |
| `XmlTrait` | `xmlValidateAtomFeed()` (protected) | folded into `xmlAssertAtomFeedValid()` |

The schema steps take a PyString or a file name, so the 4 schema checks stay helpers that take the schema source, and name the response they check. The 2 feed checks take no argument, exactly like the steps that called them, so a context calls or overrides the step method instead.

The protected `Drupal\EmailTrait::emailAssertLinkNumber()`, `CommandTrait::commandAssertInteger()` and `CommandTrait::commandAssertNumeric()` are gone rather than renamed. A step parses its number with `StringTrait::stringParseInteger()` or `StringTrait::stringParseNumber()` instead, as [A step method takes only what its step binds](#a-step-method-takes-only-what-its-step-binds) describes.

### A hook is named for its event

A hook method reads `<prefix><Event>`, so `configBeforeScenario()` and `contentBeforeNodeCreate()` already told the reader when they run. The hooks that were named for what they do take the same shape. A skip tag names a trait now, not a hook, so a 3.x tag that named one of these hooks moves to its trait's tag, as [One skip tag per trait](#one-skip-tag-per-trait) lists.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\TimeTrait` | `timeCleanup()` | `timeAfterScenario()` |
| `Drupal\WatchdogTrait` | `watchdogSetScenario()` | `watchdogBeforeScenario()` |
| `Drupal\BigPipeTrait` | `bigPipeWaitBeforeStep()` | `bigPipeBeforeStep(BeforeStepScope $scope)` |
| `AccessibilityTrait` | `accessibilitySetupScenario()` | `accessibilityBeforeScenario()` |
| `AccessibilityTrait` | `accessibilityAutoAssess()` | `accessibilityAfterStep()` |
| `AccessibilityTrait` | `accessibilityFinalizeScenario()` | `accessibilityAfterScenario()` |
| `AccessibilityTrait` | `accessibilityAggregateRender()` | `accessibilityAfterSuite(AfterSuiteScope $scope)` |

`AccessibilityTrait` registered 2 `BeforeSuite` hooks, so neither could take the event's name. `accessibilityBeforeSuite()` is the hook now, and it runs `accessibilityCaptureBaseDir()` and then `accessibilityAggregateReset()`. Both keep their 3.x names and their parameterless signatures, but they aren't hooks anymore and they're protected. An override drops the `#[BeforeSuite]` attribute it copied from 3.x, or it runs twice.

### A bundle parameter is named after its entity type

A helper that takes a bundle names the parameter after the entity type, as the step placeholders do. 4 helpers named it otherwise; a call that passes the argument by name renames it.

| Method | Before | After |
| --- | --- | --- |
| `ContentBlockTrait::contentBlockCreateSingle()`, now `contentBlockCreate()` | `string $type, array $values` | `string $content_block_type, array $values` |
| `ContentBlockTrait::contentBlockLoadMultiple()` | `string $type, array $conditions = []` | `string $content_block_type, array $conditions = []` |
| `MediaTrait::mediaLoadMultiple()` | `string $type, array $conditions = []` | `string $media_type, array $conditions = []` |
| `TaxonomyTrait::taxonomyLoadMultiple()` | `string $vocabulary_machine_name, array $conditions = []` | `string $vocabulary, array $conditions = []` |

`contentBlockCreateSingle()` is `contentBlockCreate()` in v4, and the 3.x `contentBlockCreate()` step is `contentBlockCreateMultiple()`, both renamed under [A method acting on several entities ends in `Multiple`](#a-method-acting-on-several-entities-ends-in-multiple). `taxonomyVisitActionPageWithName()` renamed its bundle parameter as well, as the `TaxonomyTrait` row under [Method names](#method-names) lists.

### A create or delete method names the verb first

A method that created an entity put the verb and the noun in either order. The step traits read verb-first, as `contentCreateWithFields()` did, while the Drupal Extension's `RawDrupalContext`, `EckTrait` and every Drupal Driver capability read noun-first, as `RawDrupalContext::nodeCreate()` and `ContentCapabilityInterface::nodeCreate()` did. The verb now comes first everywhere: a trait method puts it right after its prefix, and a capability method opens with it.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\EckTrait` | `eckEntitiesCreate()` | `eckCreateMultiple()` |
| `Drupal\MenuTrait` | `menuLinksCreate()` | `menuCreateLinkMultiple()` |
| `Drupal\MenuTrait` | `menuLinksDelete()` | `menuDeleteLinkMultiple()` |

The 3 steps in the table also act on several entities, so they take the `Multiple` the next section describes. The Drupal Extension's `RawDrupalContext::nodeCreate()`, `termCreate()`, `languageCreate()`, `entityCreate()` and `userCreate()` read noun-first too; their verb-first replacements, such as `entityLifecycleCreateNode()` and `authCreateUser()`, are listed under [The Drupal lifecycle moved into concern-named helpers](#the-drupal-lifecycle-moved-into-concern-named-helpers).

The verb that deletes is `Delete`. 3 `does not exist` steps said `Remove` instead, and `ContentTrait` repeated the noun its prefix already carries:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\BlockTrait` | `blockRemove()` | `blockDelete()` |
| `Drupal\ContentTrait` | `contentRemoveContentType()` | `contentDeleteType()` |
| `Drupal\MediaTrait` | `mediaRemoveType()` | `mediaDeleteType()` |

A capability interface that creates an entity now reads one way, so its delete, place and role methods move with its create methods. A Drupal Driver driver of your own that implements one of these interfaces, a backend in v4, renames the methods it implements; the shipped `DrupalBackend`, `DrushBackend` and `Core` already use the new names.

| Interface | Old | New |
| --- | --- | --- |
| `ContentCapabilityInterface` | `nodeCreate()` | `createNode()` |
| `ContentCapabilityInterface` | `nodeDelete()` | `deleteNode()` |
| `ContentCapabilityInterface` | `termCreate()` | `createTerm()` |
| `ContentCapabilityInterface` | `termDelete()` | `deleteTerm()` |
| `ContentCapabilityInterface` | `entityCreate()` | `createEntity()` |
| `ContentCapabilityInterface` | `entityDelete()` | `deleteEntity()` |
| `BlockCapabilityInterface` | `blockPlace()` | `placeBlock()` |
| `BlockCapabilityInterface` | `blockDelete()` | `deleteBlock()` |
| `BlockCapabilityInterface` | `blockContentCreate()` | `createBlockContent()` |
| `BlockCapabilityInterface` | `blockContentDelete()` | `deleteBlockContent()` |
| `LanguageCapabilityInterface` | `languageCreate()` | `createLanguage()` |
| `LanguageCapabilityInterface` | `languageDelete()` | `deleteLanguage()` |
| `UserCapabilityInterface` | `userCreate()` | `createUser()` |
| `UserCapabilityInterface` | `userDelete()` | `deleteUser()` |
| `UserCapabilityInterface` | `userAddRole()` | `addUserRole()` |
| `RoleCapabilityInterface` | `roleCreate()` | `createRole()` |
| `RoleCapabilityInterface` | `roleDelete()` | `deleteRole()` |

The authentication, config, module, mail, cache and cron capabilities keep their method names. So do the entity-create hooks, because a hook is named for its event: `BeforeNodeCreate`, `AfterTermCreate` and the rest are unchanged. `StateCapabilityInterface` is new in v4 and names its methods the way the config capability does.

### An action method names its step's verb first

A method behind an action step puts the verb its step reads right after its prefix, as `fieldFillColor()` and `fieldClearSelect()` do. 3 `FieldTrait` methods put the noun first, 1 of them with a verb its step doesn't use, and `SearchApiTrait` named its indexing step `Do`, which names no action. Step text is unchanged.

| Trait | Old | New |
| --- | --- | --- |
| `FieldTrait` | `fieldCheckboxCheck()` | `fieldCheckCheckbox()` |
| `FieldTrait` | `fieldCheckboxUncheck()` | `fieldUncheckCheckbox()` |
| `FieldTrait` | `fieldRadioSelect()` | `fieldChooseRadioButton()` |
| `Drupal\SearchApiTrait` | `searchApiDoIndex()` | `searchApiRunIndexing()` |

### A method acting on several entities ends in `Multiple`

A method that created, deleted or loaded several entities at once took one of 3 shapes: a plural noun, as `userCreateRoles()` did, a `Multiple` suffix, as `userLoadMultiple()` does, or a bare verb beside a `Single` sibling, as `mediaCreate()` and `mediaCreateSingle()` did. Each now ends in `Multiple`, the way Drupal's own `loadMultiple()` does, and the method for 1 entity is the same name without it. A `With` qualifier still comes last.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\ContentTrait` | `contentCreateWithFields()` | `contentCreateMultipleWithFields()` |
| `Drupal\ContentTrait` | `contentDelete()` | `contentDeleteMultiple()` |
| `Drupal\ContentBlockTrait` | `contentBlockCreate()` | `contentBlockCreateMultiple()` |
| `Drupal\ContentBlockTrait` | `contentBlockCreateWithFields()` | `contentBlockCreateMultipleWithFields()` |
| `Drupal\ContentBlockTrait` | `contentBlockDelete()` | `contentBlockDeleteMultiple()` |
| `Drupal\ContentBlockTrait` | `contentBlockCreateSingle()` | `contentBlockCreate()` |
| `Drupal\EckTrait` | `eckDeleteEntities()` | `eckDeleteMultiple()` |
| `Drupal\EckTrait` | `eckCreateEntity()` | `eckCreate()` |
| `Drupal\EckTrait` | `eckCreateEntities()` (protected) | folded into `eckCreateMultiple()` |
| `Drupal\FileTrait` | `fileCreateManaged()` | `fileCreateManagedMultiple()` |
| `Drupal\FileTrait` | `fileDeleteManagedFiles()` | `fileDeleteManagedMultiple()` |
| `Drupal\FileTrait` | `fileCreateManagedSingle()` | `fileCreateManaged()` |
| `Drupal\MediaTrait` | `mediaCreate()` | `mediaCreateMultiple()` |
| `Drupal\MediaTrait` | `mediaCreateWithFields()` | `mediaCreateMultipleWithFields()` |
| `Drupal\MediaTrait` | `mediaDelete()` | `mediaDeleteMultiple()` |
| `Drupal\MediaTrait` | `mediaCreateSingle()` | `mediaCreate()` |
| `Drupal\MenuTrait` | `menuCreate()` | `menuCreateMultiple()` |
| `Drupal\MenuTrait` | `menuDeleteSingle()` | `menuDelete()` |
| `Drupal\RedirectTrait` | `redirectCreate()` | `redirectCreateMultiple()` |
| `Drupal\RedirectTrait` | `redirectDelete()` | `redirectDeleteMultiple()` |
| `Drupal\TaxonomyTrait` | `taxonomyCreateWithFields()` | `taxonomyCreateMultipleWithFields()` |
| `Drupal\TaxonomyTrait` | `taxonomyDeleteTerms()` | `taxonomyDeleteMultiple()` |
| `Drupal\UserTrait` | `userCreateWithFields()` | `userCreateMultipleWithFields()` |
| `Drupal\UserTrait` | `userCreateRoles()` | `userCreateRoleMultiple()` |
| `Drupal\UserTrait` | `userDelete()` | `userDeleteMultiple()` |
| `Drupal\WebformTrait` | `webformLoadAll()` | `webformLoadMultiple()` |

10 of the 3.x names in that table now belong to a different method. Each 3.x step over a table took the `Multiple` name, and the name it freed went to a helper that acts on 1 entity: the renamed `Single` helper for `mediaCreate()`, `contentBlockCreate()` and `fileCreateManaged()`, and a helper new in v4 for the other 7. A call left on a 3.x name reaches that helper:

| 3.x method | v4 method of the same name | A call left on the 3.x name |
| --- | --- | --- |
| `Drupal\ContentTrait::contentDelete(string $content_type, TableNode $table)` | `contentDelete(string $content_type, array $conditions)` | fails with a `TypeError` |
| `Drupal\ContentBlockTrait::contentBlockCreate(string $type, TableNode $content_block_table)` | `contentBlockCreate(string $content_block_type, array $values)` | fails with a `TypeError` |
| `Drupal\ContentBlockTrait::contentBlockDelete(string $type, TableNode $content_block_table)` | `contentBlockDelete(string $content_block_type, array $conditions)` | fails with a `TypeError` |
| `Drupal\FileTrait::fileCreateManaged(TableNode $table)` | `fileCreateManaged(string $path, EntityStubInterface $stub, ?string $uri = NULL)` | fails with an `ArgumentCountError` |
| `Drupal\MediaTrait::mediaCreate(string $media_type, TableNode $table)` | `mediaCreate(EntityStubInterface $stub)` | fails with a `TypeError` |
| `Drupal\MediaTrait::mediaDelete(string $media_type, TableNode $table)` | `mediaDelete(string $media_type, array $conditions)` | fails with a `TypeError` |
| `Drupal\MenuTrait::menuCreate(TableNode $table)` | `menuCreate(array $values)` | fails with a `TypeError` |
| `Drupal\RedirectTrait::redirectCreate(TableNode $table)` | `redirectCreate(string $from, string $to, int $status_code = 301)` | fails with an `ArgumentCountError` |
| `Drupal\RedirectTrait::redirectDelete(TableNode $table)` | `redirectDelete(string $from)` | runs without an error and deletes nothing |
| `Drupal\UserTrait::userDelete(TableNode $table)` | `userDelete(array $values)` | fails with a `TypeError` |

`TableNode` implements `Stringable`, so where the v4 method takes a `string` first, PHP's default coercive mode turns the table into its text instead of rejecting it. `fileCreateManaged()` and `redirectCreate()` then fail on the argument that's missing, and `redirectDelete()` looks for a redirect whose source is the table's text, finds none, and returns. In a file that declares `strict_types=1`, all 3 fail with a `TypeError` instead. 3.x's `Drupal\OverrideTrait` called `contentDelete()` and `userDelete()` with a table, so a context that copied its overrides carries 2 of these calls.

2 more 3.x names reach a different method in v4. `ElementTrait::elementAssertPinnedToTop()` went the other way, from a protected helper to a public step, and a 3.x call to it runs without an error, as [Negation is spelled `Not`, in one slot](#negation-is-spelled-not-in-one-slot) explains. The Drupal Extension's `RawDrupalContext::userCreate(EntityStubInterface $stub)` is `authCreateUser()` now, and on the shipped `DrupalContext` the old name reaches `Drupal\UserTrait::userCreate(array $values)`, so a leftover `$this->userCreate($stub)` fails with a `TypeError`.

An override that keeps a 3.x signature fails too. On a context that extends a shipped one, PHP rejects the declaration as incompatible with the v4 method when the class loads. On a context that composes the trait itself, an override of one of the 10 names above replaces the v4 helper, so the `Multiple` step that calls the helper fails with a `TypeError`. Search a project for each name above before upgrading.

`WebformTrait::webformTemplates()` is `webformLoadTemplateMultiple()` as well; its row is under [A lookup's verb says what a miss does](#a-lookups-verb-says-what-a-miss-does).

`ContentTrait`, `TaxonomyTrait` and `UserTrait` carry their own 1-entity helpers, `contentCreate()`, `taxonomyCreate()` and `userCreate()`; a single language or other entity goes through `entityLifecycleCreateLanguage()` or `entityLifecycleCreate()`. `userCreate()` takes field values, so it isn't the Drupal Extension's `RawDrupalContext::userCreate()`, which took a stub and is `authCreateUser()` now. `contentCreateMultiple()`, `taxonomyCreateMultiple()`, `userCreateMultiple()`, `languageCreateMultiple()` and `entityCreateMultiple()` replace the Drupal Extension's `DrupalContext` creation methods, as the [DrupalExtension mapping](#drupalextension-step-text-mapped-to-the-v4-vocabulary) lists.

A table step that sets several values takes the suffix too, as `configSetMultiple()` and `stateSetMultiple()` do. `ResponsiveTrait`'s breakpoint table read `FromTable` with a plural noun instead:

| Trait | Old | New |
| --- | --- | --- |
| `ResponsiveTrait` | `responsiveSetBreakpointsFromTable()` | `responsiveSetBreakpointMultiple()` |

### A boolean parameter reads as a question

A single-word boolean parameter takes an `is_` prefix, as `$is_partial` and `$is_inverted` already did. `Drupal\EmailTrait::emailFindMessage()` named its flag bare. Step text is unchanged, so this only matters to a call that passes the argument by name.

| Method | Before | After |
| --- | --- | --- |
| `emailFindMessage()` | `bool $exact = FALSE` | `bool $is_exact = FALSE` |

`emailAssertMessageHeaderContains()`, `emailAssertMessageFieldContains()`, `emailAssertMessageFieldNotContains()` and `emailClearTestQueue()` lost their flag instead, as [A step method takes only what its step binds](#a-step-method-takes-only-what-its-step-binds) lists.

`Helper\Drupal\AuthTrait::authLogout()`, which replaces the Drupal Extension's `RawDrupalContext::logout()`, takes `$is_fast` where `logout()` took `$fast`.

### A method is named for what it does, not `Helper`

The protected `FieldTrait::fieldFillDatetimeHelper()` is `fieldFillDatetimeInput()`, after the 1 input of a datetime field it fills. A context that overrides it renames the override.

### `ConfigTrait` names its helpers as its siblings do

`ConfigTrait` had 2 protected helpers that its siblings name differently: 1 records what a step is about to change so the teardown can restore it, as `stateStoreOriginalValue()` and `moduleStoreOriginalState()` do, and 1 turns step text into a typed value, which `ConfigTrait` and `StateTrait` now share as `Helper\Web\StringTrait::stringNormalizeValue()`. A context that overrides one of them renames the override.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\ConfigTrait` | `configSnapshot()` | `configStoreOriginalData()` |
| `Drupal\ConfigTrait` | `configCastValue()` | `Helper\Web\StringTrait::stringNormalizeValue()`, as [Step logic moved into shared helpers](#step-logic-moved-into-shared-helpers) describes |

### `XmlTrait` names its content steps as `JsonTrait` does

`XmlTrait` and `JsonTrait` register the same 2 `Given` steps, 1 loading the response body from a fixture file and 1 taking it from a PyString. `JsonTrait` names them `jsonSetContentFromFile()` and `jsonSetContent()`, while `XmlTrait` added `Response`, which every step in the trait acts on, and `Direct`, which names nothing in its step. Their step text changed as well, as the `XmlTrait` table under [Unified step text](#unified-step-text) lists.

| Trait | Old | New |
| --- | --- | --- |
| `XmlTrait` | `xmlSetResponseContentFromFile()` | `xmlSetContentFromFile()` |
| `XmlTrait` | `xmlSetResponseContentDirect()` | `xmlSetContent()` |

## A class is named for the role it plays

The Drupal Extension kept 4 classes in `Drupal\DrupalExtension\Manager` that shared a `Manager` suffix while playing 3 different roles, so nothing in a name told a lookup table apart from a service that acts. Their replacements follow a 2-part rule: a `*Registry` holds things and looks them up, and anything that performs an action takes an agent noun. The registries live in `DrevOps\BehatSteps\Behat\Registry`, and the authenticators and `FastLogoutInterface` in `DrevOps\BehatSteps\Behat\Auth`.

| Drupal Extension 6.1 | Role | 4.x |
| --- | --- | --- |
| `Drupal\DrupalExtension\Manager\DriverManager` | registers backends, resolves one by capability, tracks the scenario's order | `Behat\Registry\BackendRegistry` |
| `Drupal\DrupalExtension\Manager\DrupalUserManager` | stores the users a scenario created, tracks the current one | `Behat\Registry\UserRegistry` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManager` | logs a user in and out, holds a Drupal session | `Behat\Auth\Authenticator` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManager`, its basic authentication | derives credentials from `base_url`, applies them to Mink | `Behat\Auth\BasicAuthenticator` |

`DrupalAuthenticationManager` implemented `DrupalAuthenticationManagerInterface`, `FastLogoutInterface` and `BasicAuthInterface` at once. 4.x splits it in 2: `Authenticator` implements `AuthenticatorInterface` and `FastLogoutInterface`, and `BasicAuthenticator` implements `BasicAuthenticatorInterface`. The 4th class, `DrupalMailManager`, has no replacement, as [`DrupalMailManager` is gone](#drupalmailmanager-is-gone) explains.

Each interface travels with its class:

| Drupal Extension 6.1 | 4.x |
| --- | --- |
| `Drupal\DrupalExtension\Manager\DriverManagerInterface` | `Behat\Registry\BackendRegistryInterface` |
| `Drupal\DrupalExtension\Manager\DrupalUserManagerInterface` | `Behat\Registry\UserRegistryInterface` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManagerInterface` | `Behat\Auth\AuthenticatorInterface` |
| `Drupal\DrupalExtension\Manager\BasicAuthInterface` | `Behat\Auth\BasicAuthenticatorInterface` |
| `Drupal\DrupalExtension\Manager\FastLogoutInterface` | `Behat\Auth\FastLogoutInterface` |

```php
// Before.
use Drupal\DrupalExtension\Manager\DrupalUserManagerInterface;

// After.
use DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface;
```

`FastLogoutInterface` keeps its short name. It describes a capability rather than a role. [`Login` and `Logout`, not `LogIn` and `LogOut`](#login-and-logout-not-login-and-logout) lists the methods `AuthenticatorInterface` renames, and [Methods and constants](#methods-and-constants) the ones `BackendRegistryInterface` renames. `BasicAuthenticatorInterface` adds `findCredentials()`, as [Steps send their own requests through 3 HTTP clients](#steps-send-their-own-requests-through-3-http-clients) describes.

`DrupalAwareInterface`, which `RawDrupalContext` implemented, declared the accessors a context used to reach these services. 4.x splits it in 2: `BackendAwareInterface`, which `WebRawContext` implements, and `UserAwareInterface`, which a context composing `AuthTrait` declares. A context that extends a shipped context inherits the new names, and one that calls the old accessors from its own step definitions renames the calls. One that implemented `DrupalAwareInterface` by hand implements the 2 new interfaces instead, with the new types in its accessor signatures, or PHP refuses to load it; `BackendAwareInterface` also declares the accessors for the HTTP client factory, option resolution and basic authentication, which had no 3.x counterpart.

| Drupal Extension 6.1 | 4.x |
| --- | --- |
| `DrupalAwareInterface::setDrupal()` | `BackendAwareInterface::setBackendRegistry()` |
| `DrupalAwareInterface::getDrupal()` | `BackendAwareInterface::getBackendRegistry()` |
| `DrupalAwareInterface::setDispatcher()` | `BackendAwareInterface::setHookDispatcher()` |
| `DrupalAwareInterface::setAuthenticationManager()` | `UserAwareInterface::authSetAuthenticator()` |
| `DrupalAwareInterface::getAuthenticationManager()` | `UserAwareInterface::authGetAuthenticator()` |

`getUserManager()` and `setUserManager()` are listed under [The Drupal lifecycle moved into concern-named helpers](#the-drupal-lifecycle-moved-into-concern-named-helpers), which also describes the `setBasicAuthenticator()` and `getBasicAuthenticator()` pair basic authentication gets on `BackendAwareInterface`.

The service ids and the `*.class` parameters that let a suite swap an implementation follow the classes:

| Drupal Extension 6.1 | 4.x |
| --- | --- |
| `drupal.drupal` | `behat_steps.backend_registry` |
| `drupal.user_manager` | `behat_steps.user_registry` |
| `drupal.authentication_manager` | `behat_steps.authenticator` |

`drupal.drupal.class` becomes `behat_steps.backend_registry.class`, and so on for the other 2. Basic authentication is a service of its own, `behat_steps.basic_authenticator`, so a suite that swapped `drupal.authentication_manager.class` for a class that also applied basic auth now registers 2 classes, through `behat_steps.authenticator.class` and `behat_steps.basic_authenticator.class`. Each implements the interface, because the shipped classes are `final`, as [Classes are final unless a project extends them](#classes-are-final-unless-a-project-extends-them) shows.

`grep -rnE 'DrupalExtension\\+Manager|DrupalAwareInterface|drupal\.(drupal|user_manager|authentication_manager)' <your project>` lists every import, docblock type and container name that still names the Drupal Extension's.

### `DrupalMailManager` is gone

The Drupal Extension's `DrupalMailManager` forwarded to `MailCapabilityInterface` and added nothing: `startCollectingMail()` called `mailStartCollecting()` and then `mailClear()`, `stopCollectingMail()` called `mailStopCollecting()`, `getMail()` called `mailGet()`, `clearMail()` called `mailClear()`, and `disableMail()` and `enableMail()` called `startCollectingMail()` and `stopCollectingMail()`. Only `RawMailContext::getMailManager()` built it, for the Drupal Extension's mail steps. Their replacement, `EmailTrait`, resolves `CoreCapabilityInterface` and swaps the mail system itself, as [Mail](#mail) describes, so nothing in 4.x needs the class.

That is the general rule, not a one-off: a class that only wraps a capability interface is not written, because the capability interface already is the abstraction. A trait reaches a capability through `backendFor(SomeCapabilityInterface::class)` on `WebRawContext`.

A project that built a `DrupalMailManager` itself, or called `getMailManager()`, calls the capability instead:

```php
// Before.
$mail = new DrupalMailManager($this->getDriver());
$mail->startCollectingMail();
$messages = $mail->getMail();

// After.
$backend = $this->backendFor(MailCapabilityInterface::class);
$backend->mailStartCollecting();
$backend->mailClear();
$messages = $backend->mailGet();
```

`DrupalMailManagerInterface` is removed with the class.

## Capabilities of the browser driver

The Drupal half of the vocabulary resolves its backends by capability. The browser half now does the same: a step names the capability it needs and never a browser driver, so a project registering its own browser driver gets the shipped steps working as soon as it registers an adapter declaring that capability.

`DrevOps\BehatSteps\Behat\Mink\Capability` holds the 5 interfaces, and `DrevOps\BehatSteps\Behat\Mink\Adapter` holds 1 adapter per shipped browser driver family. A browser driver comes from another package, so an adapter declares the capabilities on its behalf rather than the browser driver implementing them.

| Capability | BrowserKit | Selenium2 | Chrome (CDP) |
| --- | --- | --- | --- |
| `CookieCapabilityInterface` | yes | yes | yes |
| `HttpClientCapabilityInterface` | yes | no | no |
| `JavascriptCapabilityInterface` | no | yes | yes |
| `KeyboardCapabilityInterface` | no | yes | yes |
| `RequestHeaderCapabilityInterface` | yes | no | yes |

`WebRawContext` exposes the pair the Drupal side already has: `browserDriverFor()` returns the adapter or raises `UnsupportedDriverActionException` naming the capability, and `browserDriverHas()` answers the same question as a boolean for a step that degrades gracefully instead of failing.

### `helperIsJavascriptSupported()` asks the capability

3.x's `helperIsJavascriptSupported()` probed the browser driver by evaluating `true` in the browser, so every call paid a round trip to answer a question that cannot change during a scenario. A custom step that called it asks the capability instead:

```php
// Before.
if (!$this->helperIsJavascriptSupported()) {
  return;
}

// After.
if (!$this->browserDriverHas(JavascriptCapabilityInterface::class)) {
  return;
}
```

Where the step could not proceed without JavaScript, resolve the capability and let it raise:

```php
$this->browserDriverFor(JavascriptCapabilityInterface::class);
```

### Four traits now need the library's context

`CookieTrait`, `DropzoneTrait`, `IframeTrait` and `KeyboardTrait` resolve a browser capability, so they need `WebRawContext` and no longer compose onto Mink's own `RawMinkContext`. A context composing one of them extends `DrevOps\BehatSteps\Behat\Context\WebRawContext`. `JsonTrait`, `LinkTrait`, `PathTrait`, `ResponseTrait`, `ResponsiveTrait` and `XmlTrait` still run on the bare Mink context, and so does `RegionTrait`, which is new in 4.x; every other web trait needs `WebRawContext`. `MetatagTrait` needs it too, for its HTTP client; see [Steps send their own requests through 3 HTTP clients](#steps-send-their-own-requests-through-3-http-clients).

### Three steps now fail naming the capability

`I wait for the modal to appear`, `I drop the following files on the dropzone :selector:` and `I switch to the iframe :selector` performed work only a browser can do without checking for one first. Each now raises `UnsupportedDriverActionException` naming the capability. The modal step is the visible improvement: it used to spend its whole timeout, 3 seconds by default, and then report that the modal had not appeared.

`I press the key ...` still raises `UnsupportedDriverActionException` on a browser driver that cannot serve it, naming the capability where it named "Selenium2 or Chrome". `I wait for :seconds second(s) for AJAX to finish` now raises `UnsupportedDriverActionException` naming the capability too, where it threw `\RuntimeException` naming the browser driver's class, so a test that caught `\RuntimeException` there catches the new type instead.

### Registering an adapter for another browser driver

```php
$this->getBrowserCapabilityResolver()
  ->registerAdapter(AcmeDriverAdapter::class);
```

An adapter extends `BrowserAdapterBase`, implements the capability interfaces its browser driver can honor, and answers `supports()` for the browser driver it speaks for. A registered adapter is offered each browser driver ahead of the shipped ones.

## Steps send their own requests through 3 HTTP clients

Every request a step sends from PHP goes through 1 of 3 clients on `WebRawContext`, so the choice shows where it's made. `httpPageClient()` sends a request whose response becomes the page. `httpDetachedClient()` sends one as the scenario's visitor, with its cookies, headers and credentials, and leaves the page alone. `httpBareClient()` sends one that carries nothing of the scenario. All 3 return BrowserKit's `AbstractBrowser`, and [HTTP clients](docs/http-clients.md) covers them in full.

`FileDownloadTrait` downloads through the detached client instead of cURL, so `fileDownloadProcess()` takes Symfony HttpClient options in place of `CURLOPT_*` constants:

```php
// Before.
$this->fileDownloadProcess($url, [CURLOPT_USERAGENT => 'acme']);

// After.
$this->fileDownloadProcess($url, ['headers' => ['User-Agent' => 'acme']]);
```

The download timeout moves from a hardcoded 120 seconds to the `file_download.timeout` option. The download no longer passes the session's cookies itself, because the detached client carries them, along with the headers steps set and the basic-auth credentials. So a download from a route behind basic auth now works after `the basic authentication has the username :username and the password :password`.

`MetatagTrait` fetches the hreflang alternates through the detached client, so the return-link check works on a site behind basic auth or a login. That makes it need `WebRawContext`, like the traits in [Four traits now need the library's context](#four-traits-now-need-the-librarys-context). `AccessibilityTrait` fetches its engine through the bare client, which takes the site's connection settings only for requests to `base_url`.

`BasicAuthenticatorInterface`, which replaces the Drupal Extension's `BasicAuthInterface`, adds `findCredentials()`, which returns the credentials the `base_url` carries. It takes over from the protected `resolveBasicAuth()` in `DrupalAuthenticationManager`, so a class implementing the interface adds it.

This package now requires `symfony/browser-kit`, `symfony/http-client` and `symfony/mime`. `webflo/drupal-finder` came in with `drupal/drupal-extension`, so a project that drops the Drupal Extension and calls drupal-finder directly requires it itself.

## Drupal capabilities cover the config, module and state steps

In 3.x the config, module and state steps called `\Drupal::configFactory()`, `\Drupal::moduleHandler()` and `\Drupal::state()` directly, so they ran only where the Drupal API driver had bootstrapped Drupal in the Behat process. They now name the capability they need and run on any backend providing it, including Drush.

A project with its own backend implementing these capabilities adds the methods below, and declares `configGet()` and `configGetOriginal()` with `?string $key = NULL` where the Drupal Driver's `ConfigCapabilityInterface` took `string $key = ''`. A project using only the shipped backends needs no change.

| Interface | Added |
| --- | --- |
| `ConfigCapabilityInterface` | `configExists()`, `configGetData()`, `configSetData()`, `configDelete()` |
| `ModuleCapabilityInterface` | `moduleIsEnabled()`, `moduleIsPresent()` |
| `StateCapabilityInterface` | New: `stateGet()`, `stateSet()`, `stateDelete()`, `stateExists()` |

### The Drush backend stores config values correctly

The Drupal Driver's `DrushDriver::configSet()` asked Drush for `--input-format=json`, which `drush config:set` does not parse - only `yaml` does. Every value it wrote was stored as its own JSON encoding, so a string landed with its quotes around it and an array landed as a JSON string rather than an array. `DrushBackend::configSet()` passes `--input-format=yaml`, so a project that seeded config through the Drush driver and worked around the mangled values can drop the workaround.

2 smaller corrections come with it. A keyed `configGet()` returned Drush's `{"<name>:<key>": value}` envelope instead of the value. And `configGetOriginal()` was the same call as `configGet()`, so the stored and effective reads the config steps distinguish collapsed into one; the effective read now passes `--include-overridden` and the stored read does not.

## The Drush backend needs Drush 13

The Drush backend supports Drush 13, the first release that runs Drupal 11, and newer. It runs every command by its canonical colon-separated name rather than by a legacy alias, and each of those names exists from Drush 13:

| Method | Before | After |
| --- | --- | --- |
| `cronRun()` | `cron` | `core:cron` |
| `moduleInstall()` | `pm-enable` | `pm:install` |
| `moduleUninstall()` | `pm-uninstall` | `pm:uninstall` |
| `createUser()` | `user-create` | `user:create` |
| `deleteUser()` | `user-cancel` | `user:cancel` |
| `addUserRole()` | `user-add-role` | `user:role:add` |

The method names are the ones [A create or delete method names the verb first](#a-create-or-delete-method-names-the-verb-first) settles on; the Drupal Driver named the last 3 `userCreate()`, `userDelete()` and `userAddRole()`.

`cacheClear()` no longer runs `cache-clear drush` ahead of `cache:rebuild`. Drush 13 keeps no cache of its own, so that command cleared nothing, and Drush 14 rejects the `drush` cache type, so every cache clear over Drush failed there. `cacheClear()` now runs `cache:rebuild` alone.

A project that extended the Drupal Driver's `DrushDriver` and matched the command names it issues, in an override of `drush()` or `drushResult()`, extends `DrushBackend` and matches the names in the right-hand column.

## The Drush backend reads `NULL` as an unset alias or root

`NULL` is the only value meaning the Drush backend wasn't given an alias or a root path. The Drupal Extension passed `FALSE` for an unset `alias` or `root`, PHP turned it into an empty string, and the Drupal Driver's `DrushDriver` read an empty string as missing too. `BehatStepsExtension` passes `NULL`, so the `behat_steps.backend.drush.alias` and `behat_steps.backend.drush.root` container parameters, the Drupal Extension's `drupal.driver.drush.alias` and `drupal.driver.drush.root`, hold `NULL` when unset, and the `DrushBackend` constructor throws a `BootstrapException` for an empty alias or root path:

```php
// Before.
$driver = new DrushDriver('', 'web');

// After.
$backend = new DrushBackend(NULL, 'web');
```

An empty `alias` or `root` under `behat_steps: drush:` fails the container build and names the setting, where it used to be read as missing. Leave the setting out, or set it to `NULL`.

## Capability creates return the stub, deletes tolerate a miss

The capability interfaces disagreed about what a create returns and what a delete does when its target is already gone. Every create now returns a stub, and every delete returns `void` and does nothing for a target that doesn't exist, so teardown code can delete whatever a scenario created without checking first.

| Method | Before | After |
| --- | --- | --- |
| `UserCapabilityInterface::createUser()` | `void` | Returns the stub |
| `LanguageCapabilityInterface::createLanguage()` | Returned `FALSE` for a language that already exists | Returns the stub, left unsaved for a language that already exists |
| `RoleCapabilityInterface::createRole()` | The role's machine name | A `user_role` stub carrying `id` and `label` |
| `ContentCapabilityInterface::deleteTerm()` | `bool` | `void` |
| `LanguageCapabilityInterface::deleteLanguage()` | Threw for a language that doesn't exist | Does nothing |

The table and the text below use the names [A create or delete method names the verb first](#a-create-or-delete-method-names-the-verb-first) settles on, and the code shows a call written before both changes.

A project with its own backend updates those signatures, and every delete it implements does nothing for a missing target rather than throwing. A caller of `createRole()` reads the machine name from the stub:

```php
// Before.
$role = $backend->roleCreate(['access content']);

// After.
$role = $backend->createRole(['access content'])->getValue('id');
```

`Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateLanguage()` follows the backend: it returns the stub in both cases instead of `FALSE`, and only a stub the backend saved joins the teardown.

A create flags the stub as saved only when the backend holds the created entity. The Drush backend runs outside the test process, so its `createUser()` writes the new `uid` onto the stub and its `createRole()` stub carries the role's `id`, but neither stub is saved. Read the id from the stub's values rather than from `getSavedEntity()` when the backend may be Drush.

The shipped backends keep the delete contract throughout. The Drush backend's `deleteRole()` and `deleteUser()` no longer fail for a role or user that's already gone, and the in-process `deleteUser()` no longer reports "The user account ... does not exist." for one.

A stub that names no entity at all is a malformed argument rather than a miss, so a delete throws `\RuntimeException` for it. The in-process `deleteNode()`, `deleteTerm()` and `deleteUser()` now do this for a stub that's neither saved nor carries a `nid`, `tid` or `uid` value, where the Drupal Driver's `nodeDelete()` and `termDelete()` did nothing and its `userDelete()` passed an empty id to `user_cancel()`. `deleteBlock()` throws `\RuntimeException` for a stub without an `id`, where `blockDelete()` threw `\InvalidArgumentException`. A stub whose id names nothing is still a no-op.

## Every `LoadMultiple()` returns loaded entities

6 of the 7 `<trait>LoadMultiple()` helpers returned entity IDs, while `userLoadMultiple()` returned loaded users. You couldn't tell from 1 signature what the next would hand back. `contentLoadMultiple()` became `Helper\Drupal\QueryTrait::queryNodeIds()`, as [Step traits no longer compose other step traits](#step-traits-no-longer-compose-other-step-traits) lists, and the other 5 now return the loaded entities keyed by entity ID, or an empty array when nothing matches. `userLoadMultiple()` already worked this way, so it's unchanged.

`webformLoadMultiple()` and `webformLoadTemplateMultiple()` joined the family when 3.x's `webformLoadAll()` and `webformTemplates()` were renamed, as [A method acting on several entities ends in `Multiple`](#a-method-acting-on-several-entities-ends-in-multiple) and [A lookup's verb says what a miss does](#a-lookups-verb-says-what-a-miss-does) list. They always returned loaded webforms keyed by ID, so only their names changed.

| Trait | Method | Returned | Returns now |
| --- | --- | --- | --- |
| `Drupal\ContentBlockTrait` | `contentBlockLoadMultiple()` | block content IDs | `BlockContentInterface` entities |
| `Drupal\EckTrait` | `eckLoadMultiple()` | entity IDs | `EntityInterface` entities |
| `Drupal\FileTrait` | `fileLoadMultiple()` | file IDs | `FileInterface` entities |
| `Drupal\MediaTrait` | `mediaLoadMultiple()` | media IDs | `MediaInterface` entities |
| `Drupal\TaxonomyTrait` | `taxonomyLoadMultiple()` | term IDs | `TermInterface` entities |

The native return type is still `array`, so the call itself doesn't fail. Code that treats a value as an ID fails where it uses it instead, for example with `Object of class ... could not be converted to string` when it builds a path. If your code loaded the IDs itself, drop that step. If it needs only the IDs, call `Helper\Drupal\QueryTrait::queryEntityIds()`: all 5 traits compose it, and it returns exactly what the old helpers did.

```php
// Before.
$ids = $this->mediaLoadMultiple('image', ['name' => 'Logo']);
$media = \Drupal::entityTypeManager()->getStorage('media')->loadMultiple($ids);

// After.
$media = $this->mediaLoadMultiple('image', ['name' => 'Logo']);

// After, when only the IDs are needed.
$ids = $this->queryEntityIds('media', ['name' => 'Logo'], 'image');
```

The keys changed as well. An entity query keys a revisionable entity type by revision ID, so media, terms and content blocks came back keyed by revision ID. They're keyed by entity ID now, like the other 3.

This affects the steps that look an entity up by name and, when several share that name, visit the one with the highest key. With duplicates, they now visit the most recently created match instead of the most recently revised one:

- The 4 media steps `I visit the :media_type media page with the name :name`, `I visit the :media_type media edit page with the name :name`, `I visit the :media_type media delete page with the name :name` and `I visit the :media_type media revisions page with the name :name`, and the `mediaVisitActionPageWithName()` helper behind them.
- The 3 term steps `I visit the :vocabulary term page with the name :name`, `I visit the :vocabulary term edit page with the name :name` and `I visit the :vocabulary term delete page with the name :name`, and the `taxonomyVisitActionPageWithName()` helper behind them.
- `I visit the :content_block_type content block edit page with the description :description`.

## One skip tag per trait

A skip tag names the trait whose hooks it switches off, and switches off every hook that trait registers. `@behat-steps-skip:<TraitName>` is the only form: the hook-method form is gone, and with it the choice between the 2. The tag is the same switch the trait's `enabled` option sets for a whole profile or context.

A skip tag carrying anything but a trait name fails the run at scenario start, naming the tag, so a feature file still carrying a hook-method tag cannot quietly lose its effect:

```
The "@behat-steps-skip:emailAfterScenario" tag does not name a trait. A skip tag takes the name of the trait whose hooks it switches off, as in "@behat-steps-skip:JavascriptTrait".
```

A skip tag naming a trait that no context of the suite composes fails the same way, so a misspelled trait name can't quietly leave the trait's hooks running:

```
The "@behat-steps-skip:EmialTrait" tag names no trait a context of the "default" suite composes, so it would switch nothing off. Check the trait name for a typo, or remove the tag.
```

A trait counts as composed when a context uses it directly, through a parent class or through another trait, so `@behat-steps-skip:EntityLifecycleTrait` works in a suite that registers `DrupalContext`. The name has to match the trait's own, case included. A feature that 2 suites share, carrying a skip tag for a trait only 1 suite's contexts compose, fails in the other suite. Drop the tag and switch the trait off with its `enabled` option instead, in the `config` argument of the context that composes it.

Replace each hook-method tag with its trait's:

| Tag | Replacement |
| --- | --- |
| `@behat-steps-skip:configAfterScenario` | `@behat-steps-skip:ConfigTrait` |
| `@behat-steps-skip:configOverrideBeforeScenario` | `@behat-steps-skip:ConfigOverrideTrait` |
| `@behat-steps-skip:configOverrideBeforeStep` | `@behat-steps-skip:ConfigOverrideTrait` |
| `@behat-steps-skip:emailAfterScenario` | `@behat-steps-skip:EmailTrait` |
| `@behat-steps-skip:emailBeforeScenario` | `@behat-steps-skip:EmailTrait` |
| `@behat-steps-skip:entityCleanupAfterScenario` | `@behat-steps-skip:EntityLifecycleTrait` |
| `@behat-steps-skip:fileAfterScenario` | `@behat-steps-skip:FileTrait` |
| `@behat-steps-skip:fileBeforeScenario` | `@behat-steps-skip:FileTrait` |
| `@behat-steps-skip:fileDownloadAfterScenario` | `@behat-steps-skip:FileDownloadTrait` |
| `@behat-steps-skip:fileDownloadBeforeScenario` | `@behat-steps-skip:FileDownloadTrait` |
| `@behat-steps-skip:moduleAfterScenario` | `@behat-steps-skip:ModuleTrait` |
| `@behat-steps-skip:moduleBeforeScenario` | `@behat-steps-skip:ModuleTrait` |
| `@behat-steps-skip:overrideBootstrapDrupal` | Remove it. `OverrideTrait` is gone, and a step bootstraps Drupal through the backend it resolves. |
| `@behat-steps-skip:queueAfterScenario` | `@behat-steps-skip:QueueTrait` |
| `@behat-steps-skip:restBeforeScenario` | `@behat-steps-skip:RestTrait` |
| `@behat-steps-skip:stateAfterScenario` | `@behat-steps-skip:StateTrait` |
| `@behat-steps-skip:testmodeAfterScenario` | `@behat-steps-skip:TestmodeTrait` |
| `@behat-steps-skip:testmodeBeforeScenario` | `@behat-steps-skip:TestmodeTrait` |
| `@behat-steps-skip:timeCleanup` | `@behat-steps-skip:TimeTrait` |
| `@behat-steps-skip:watchdogAfterStep` | `@behat-steps-skip:WatchdogTrait` |
| `@behat-steps-skip:watchdogSetScenario` | `@behat-steps-skip:WatchdogTrait` |

The Drupal Extension's user, role and entity cleanup read no skip tag: only the `BEHAT_DRUPALEXTENSION_DISABLE_CLEANUP` environment variable switched it off, for the whole run. That variable is `BEHAT_STEPS_DISABLE_CLEANUP` now, and `@behat-steps-skip:AuthTrait` and `@behat-steps-skip:EntityLifecycleTrait` switch the same cleanup off for 1 scenario or feature.

A per-type cleanup tag left over from before 3.12, such as `@behat-steps-skip:mediaAfterScenario`, which 3.14 already ignored, fails the same way; `@behat-steps-entity-cleanup-skip:media` is its replacement.

### A tag now reaches every hook of its trait

For most traits the replacement does exactly what the old tag did, because the trait has one hook to switch off or both of its tags had the same effect: `ConfigTrait`, `ConfigOverrideTrait`, `QueueTrait`, `RestTrait`, `StateTrait`, `TimeTrait` and `WatchdogTrait`. The rest reach further than a single hook tag did. A tag that skipped a trait's teardown alone now skips its setup as well: `@behat-steps-skip:EmailTrait` keeps an `@email` scenario from enabling the test email system, not only from disabling it afterwards. A scenario that wants the collector without the teardown enables it itself with `When I enable the test email system`. `FileTrait` (the private and temporary directories), `FileDownloadTrait` (the download directory), `ModuleTrait` (the `@module:` tags) and `TestmodeTrait` (the `@testmode` tag) work the same way. `@behat-steps-skip:EntityLifecycleTrait` also keeps the nodes, terms and languages a scenario created, which `@behat-steps-skip:entityCleanupAfterScenario` left to the Drupal Extension's cleanup.

### `I enable the test email system` works without `@email`

The step enabled nothing in a scenario without an `@email` tag, because only the hook reading that tag named a handler. It now falls back to the `default` handler, as a bare `@email` does, and the collector it enables is disabled once the scenario finishes, unless `@behat-steps-skip:EmailTrait` switches the hooks off.

### A guard of your own names its trait

A trait of your own that guarded a hook on a skip tag, as the 3.x traits did, calls `skipTag()` with `__TRAIT__`, which resolves to the trait the code is written in. `skipTag()` is on `WebRawContext`, so the context composing the trait extends it.

```php
// Before.
if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
  return;
}

// After.
if ($this->skipTag(__TRAIT__, $scope)) {
  return;
}
```

## Tags on the `Feature:` line apply to every scenario

7 traits read their tags from the scenario alone, so a tag on the `Feature:` line switched some traits on and did nothing for others. `@javascript` there turned on `JavascriptTrait`'s error collection, because Behat's own hook filter reads both lines, while `@email` there left `EmailTrait`'s collector off. Every hook now reads the scenario's tags together with its feature's. So does every `@behat-steps-skip:` tag: most 3.x traits read theirs from the scenario alone too, so a skip tag on the `Feature:` line now switches its trait off for every scenario below it.

| Trait | Tags now read on the `Feature:` line |
| --- | --- |
| `Drupal\EmailTrait` | `@email`, `@email:TYPE`, `@debug` |
| `Drupal\ModuleTrait` | `@module:NAME`, `@module:!NAME` |
| `Drupal\TestmodeTrait` | `@testmode` |
| `Drupal\WatchdogTrait` | `@watchdog:TYPE` |
| `FieldTrait` | `@disable-form-validation` |
| `FileDownloadTrait` | `@download` |
| `ResponsiveTrait` | `@breakpoint:NAME`, and the `@javascript` it requires |

Where both lines carry the same kind of tag:

- A flag such as `@email` or `@download` switches the behavior on from either line.
- `@email:TYPE` and `@watchdog:TYPE` add up, so the scenario uses every handler type and tracks every message type named on either line.
- `@module:` and `@breakpoint:` take the scenario's value over the feature's. A feature tagged `@module:help` holding a scenario tagged `@module:!help` leaves `help` disabled for that scenario, and doesn't install it first.
- Each line takes 1 `@breakpoint:` tag at most. 2 on the `Feature:` line fail every scenario below it with `Only one @breakpoint tag is allowed per feature`.
- A parametrized tag with nothing after the colon, such as `@module:`, `@email:` or `@breakpoint:`, names nothing and is ignored. It used to reach some traits as an empty module name, handler type or breakpoint.

### What to check

A tag on the `Feature:` line that used to do nothing now acts on every scenario in the feature. `grep -rn -B3 'Feature:' <your features directory>` prints the lines above each `Feature:` keyword, which is where those tags sit. Look for:

- `@email`: every scenario captures mail in the test collector, so a scenario that relied on mail actually leaving the site no longer sends it.
- `@testmode`: every scenario runs in test mode, and fails on a site without the Testmode module enabled.
- `@module:NAME`: the module is installed or uninstalled around every scenario, which is slow. If every scenario needs it, enable it in the fixture site instead.
- `@watchdog:TYPE`: every scenario that logs a warning or worse of that type fails.
- `@breakpoint:NAME`: every scenario is resized, and a scenario with `@javascript` on neither line fails.
- `@disable-form-validation`: every JavaScript scenario submits forms the browser would otherwise block.
- `@download` and `@debug`: the download directory is prepared around every scenario, and every `@email` scenario prints the messages it reads.

A tag that was only meant for some of the scenarios in a feature moves down onto those scenarios.

## Drupal, Drush and Blackbox are backends, not drivers

Mink owns the word "driver" across the Behat ecosystem, and the Drupal Extension and the Drupal Driver used it for a second thing: the Drupal, Drush and Blackbox backends a step reaches the site through. So `$this->getDriver('drupal')` and `$this->getSession()->getDriver()` returned 2 unrelated objects, and only a naming rule told them apart. This package carries the Drupal Driver's classes under its own `Backend` namespace, the backends carry their own name, and "driver" in this package only ever means Mink's browser driver.

Most of it is a rename, and no step text changes. PHP that names a renamed class or method fails on the missing name. The service container is the exception: a parameter, service tag or service id under its old name can go unread without an error, so [Service ids and parameters](#service-ids-and-parameters) lists what to check. Where behavior changed too, the sections above say so, such as [The Drush backend needs Drush 13](#the-drush-backend-needs-drush-13) and [Capability creates return the stub, deletes tolerate a miss](#capability-creates-return-the-stub-deletes-tolerate-a-miss).

### Namespaces, classes and interfaces

Everything the Drupal Driver kept under `Drupal\Driver` moved to `DrevOps\BehatSteps\Backend`, so `drupal/drupal-driver` is no longer needed. The capability interfaces, `Core`, the field handlers, `EntityStub`, the creation aliases and the other exceptions keep their own names, so for them the namespace is the whole change:

```php
// Before.
use Drupal\Driver\Capability\ConfigCapabilityInterface;

// After.
use DrevOps\BehatSteps\Backend\Capability\ConfigCapabilityInterface;
```

Besides `SubDriverFinderInterface`, below, 5 types have no counterpart: `WatchdogCapabilityInterface`, whose `watchdogFetch()` neither this package nor the Drupal Extension called, and the `ColorFieldTypeHandler`, `TextHandler`, `TextLongHandler` and `TextWithSummaryHandler` field handlers, whose field types fall back to `DefaultHandler`. `DrupalDriverInterface::getCore()` and `getDrupalVersion()` moved to `CoreCapabilityInterface`, so `DrupalBackendInterface` declares only `setCore()`. The capability methods that create or delete an entity are renamed, as [A create or delete method names the verb first](#a-create-or-delete-method-names-the-verb-first) lists.

The names that said "driver" change as well, and so do the Drupal Extension classes that registered and selected the drivers:

| Before | After |
| --- | --- |
| `Drupal\Driver\DriverInterface` | `Backend\BackendInterface` |
| `Drupal\Driver\DrupalDriver` | `Backend\DrupalBackend` |
| `Drupal\Driver\DrupalDriverInterface` | `Backend\DrupalBackendInterface` |
| `Drupal\Driver\DrushDriver` | `Backend\DrushBackend` |
| `Drupal\Driver\DrushDriverInterface` | `Backend\DrushBackendInterface` |
| `Drupal\Driver\BlackboxDriver` | `Backend\BlackboxBackend` |
| `Drupal\Driver\BlackboxDriverInterface` | `Backend\BlackboxBackendInterface` |
| `Drupal\Driver\Exception\UnsupportedDriverActionException` | `Backend\Exception\UnsupportedBackendActionException` |
| `Drupal\DrupalExtension\Context\DrupalAwareInterface` | `Behat\Context\BackendAwareInterface`, with the user accessors on `Behat\Context\UserAwareInterface` |
| `Drupal\DrupalExtension\Context\Initializer\DrupalAwareInitializer` | `Behat\Context\Initializer\BackendAwareInitializer` |
| `Drupal\DrupalExtension\Listener\DriverListener` | `Behat\Listener\BackendListener` |
| `Drupal\DrupalExtension\Compiler\DriverPass` | `Behat\ServiceContainer\BackendPass` |

`DriverManager` and its interface are in [A class is named for the role it plays](#a-class-is-named-for-the-role-it-plays).

`BackendInterface` and `UnsupportedBackendActionException` no longer share a short name with Mink's `DriverInterface` and `UnsupportedDriverActionException`, so a file that imported Mink's under an alias to tell a pair apart can drop the alias.

A Drupal core of your own for one Drupal major is looked up as `DrevOps\BehatSteps\Backend\Core{N}\Core`, where the Drupal Driver looked it up as `Drupal\Driver\Core{N}\Core`, so a class of your own in that namespace moves with the rest.

`Drupal\Driver\SubDriverFinderInterface` and `DrupalDriver::getSubDriverPaths()` are gone. They served the Drupal Extension's subcontext discovery, which this package doesn't do. Code that read the extension paths asks the in-process backend's core:

```php
// Before.
$paths = $this->getDriver('drupal')->getSubDriverPaths();

// After.
$paths = $this->backendFor(CoreCapabilityInterface::class)
  ->getCore()
  ->getExtensionPathList();
```

### Methods and constants

| Class | Before | After |
| --- | --- | --- |
| `DriverManagerInterface`, now `BackendRegistryInterface` | `registerDriver()` | `registerBackend()` |
| `DriverManagerInterface`, now `BackendRegistryInterface` | `getDrivers()` | `getBackends()` |
| `DriverManagerInterface`, now `BackendRegistryInterface` | `getDriver()` | `getBackend()`, which takes a name; `getBackendFor()` resolves one by capability |
| `DriverManagerInterface`, now `BackendRegistryInterface` | `setDefaultDriverName()` | `setScenarioBackends()`, which takes the scenario's ordered list |
| `Drupal\Driver\Exception\Exception`, now `Backend\Exception\Exception` | `getDriver()` | `getBackend()` |
| `DriverListener`, now `BackendListener` | `prepareDefaultDrupalDriver()` | `prepareScenarioBackends()` |
| `RawDrupalContext`, now `Helper\Drupal\EntityLifecycleTrait` | `getContentDriver()` (protected) | `entityLifecycleGetContentBackend()` |

`RawDrupalContext::getDriver()` is in [Capability-based backend resolution](#capability-based-backend-resolution), and `getDrupal()` and `setDrupal()` are in [A class is named for the role it plays](#a-class-is-named-for-the-role-it-plays).

A parameter named `$driver` that held a driver is now `$backend`, and `DriverManager`'s `$drivers` is `BackendRegistry`'s `$backends`. That only matters to a call that passes it by name, such as `new UnsupportedBackendActionException($message, backend: $backend)`, which the Drupal Driver's `UnsupportedDriverActionException` took as `driver:`.

### Service ids and parameters

| Drupal Extension 6.1 | 4.x |
| --- | --- |
| `drupal.driver.drupal` | `behat_steps.backend.drupal` |
| `drupal.driver.drush` | `behat_steps.backend.drush` |
| `drupal.driver.blackbox` | `behat_steps.backend.blackbox` |
| `drupal.driver.core` | `behat_steps.backend.core` |
| `drupal.driver.random` | `behat_steps.backend.random` |
| `drupal.listener.driver` | `behat_steps.listener.backend` |
| `drupal.context.initializer` | `behat_steps.context.initializer` |
| `drupal.context.loader.attribute` | `behat_steps.context.attribute_reader` |
| `drupal.region_selector` | `behat_steps.region_selector` |
| The `drupal.parameters` parameter | The `behat_steps.parameters` parameter |
| The `drupal.regions` parameter | The `behat_steps.regions` parameter |
| The `drupal.driver` service tag | The `behat_steps.backend` service tag |
| The `drupal.core` service tag | The `behat_steps.core` service tag |

`drupal.drupal`, `drupal.user_manager` and `drupal.authentication_manager` are in [A class is named for the role it plays](#a-class-is-named-for-the-role-it-plays). `drupal.random` and `drupal.context.environment.reader` have no counterpart: in the Drupal Extension 6.1 nothing used the first, and the second added no contexts.

The parameters follow their service: `drupal.driver.drupal.class` becomes `behat_steps.backend.drupal.class`, `drupal.driver.drush.binary` becomes `behat_steps.backend.drush.binary`, and so on for every `.class`, `drupal_root`, `alias`, `binary` and `root` parameter. 4 don't follow the pattern:

| Drupal Extension 6.1 | 4.x |
| --- | --- |
| `drupal.listener.drivers.class` | `behat_steps.listener.backend.class` |
| `drupal.random.class` | `behat_steps.random.class` |
| `drupal.context.attribute.reader.class` | `behat_steps.context.attribute_reader.class` |
| `drupal.context.region_selector.class` | `behat_steps.region_selector.class` |

A parameter under its old name isn't rejected - it's just never read again - so a suite that swaps an implementation through one should check it renamed it.

An old service id is only loud where something requires it. A `@drupal.driver.drush` argument or a `getDefinition()` call fails the container build on the missing service, though Symfony's message doesn't mention the rename. A `hasDefinition()` check or a service defined under an old id is as quiet as a parameter.

A backend of your own registers by tagging its service `behat_steps.backend`, with the name it answers to as the alias:

```yaml
# Before.
tags:
  - { name: drupal.driver, alias: acme-jsonapi }

# After.
tags:
  - { name: behat_steps.backend, alias: acme-jsonapi }
```

A service still tagged `drupal.driver` isn't registered at all. When the `backends` list names it, the container build fails with `The "backends" list under "behat_steps" names the backend "acme-jsonapi", which is not registered. Registered backends: ...`. Without a `backends` list it's left out of the scenario's order, so each step resolves the first remaining backend that provides its capability, or fails with `No backend provides "..."` when none does.

`grep -rnE 'drupal\.(drupal|driver|core|listener|context|region_selector|parameters|regions|user_manager|authentication_manager|random)' <your extension directory>` lists every Drupal Extension container name your extension still uses: parameters, service ids and tags.

### Messages

A failure that names the concept now says "backend", so a test that asserts one of these messages needs the new text:

| Before | After |
| --- | --- |
| `The active Drupal driver "..." does not support ...` | `No backend provides "...". Backends available to this scenario, in order: ...` |
| `Driver "..." is not registered` | `Backend "..." is not registered. Registered backends: ...` |

The second is a `\RuntimeException` where `DriverManager` threw `\InvalidArgumentException`.

## A step method takes only what its step binds

Behat binds every placeholder as a string, and a Turnip pattern can't make a placeholder optional. Step methods declared other types anyway: native `int`, `string|int`, `mixed` and nullable parameters, plus trailing parameters with a default that no step text ever set. Each step method now declares exactly what its step binds - a required `string` per placeholder, and a trailing `TableNode` or `PyStringNode` for a step that ends with a colon - and a step that needs a number parses it.

No step text changes. A `.feature` file only needs an edit where it asserts on one of the messages below, or where it passed a malformed number that used to read as `0`.

### A malformed number fails with `\RuntimeException`

A step that takes a number parses it with `StringTrait::stringParseInteger()` or `StringTrait::stringParseNumber()`, which throw `\RuntimeException` naming the argument:

```
The count must be an integer, but "abc" was given.
The count must be 0 or greater, but "-1" was given.
```

| Steps | `abc` before | `abc` now |
| --- | --- | --- |
| Every step whose method took an `int`: the queue, table and element counts, the element index, tolerance and offset steps, and `the REST response status code should be :code` | Behat's `Type error: ... must be of type int, string given` | `\RuntimeException` |
| `I wait for :seconds second(s)`, `I wait for :seconds second(s) for AJAX to finish` and `I run search indexing for :count item(s)` | read as `0` | `\RuntimeException` |
| `the number of sent emails should be :count` and the 2 other email count steps, which replace the Drupal Extension's `there should be a total of :count (e)mail(s) sent` family | read as `0`, and `no`, `a` and `an` read as `0`, `1` and `1` | `\RuntimeException`; write the number |
| `the XML element :element should have :count element(s)` and `I set the system time to the value :value` | read as `0` | `\RuntimeException` |
| `I set the viewport width to :width`, `I set the viewport height to :height` and `I set the viewport to :width by :height` | read as `0`, and the resize failed without a word | `\RuntimeException` |

PHP's coercion was looser than the `int` type suggested: `1e3` read as 1000, and `3.5` read as 3 with a deprecation notice, which only failed the step where PHP reports deprecations. Both now fail with `\RuntimeException`.

A value below what a step accepts fails the same way: a count below 0, a link index below 1, a viewport width or height below 1, a wait below 0 seconds and a command duration below 0. An element index below 1 and a negative tolerance keep their messages, and throw `\RuntimeException` where 3.x threw `ExpectationException`, as [Unified assertion exceptions](#unified-assertion-exceptions) lists.

### Messages

A test that asserts one of these messages needs the new text:

| Step | Before | After |
| --- | --- | --- |
| `the command exit code should be :code` | `The expected exit code must be an integer, but got "...".` | `The exit code must be an integer, but "..." was given.` |
| `the command should complete in less than :seconds second(s)` and `... more than :seconds second(s)` | `The expected duration must be numeric, but got "...".` | `The duration must be a number, but "..." was given.` |
| `I follow link number :link_number in the email ...`, both forms, now `I follow the link with the index :index in the email ...` | `The link number must be a positive integer, but "..." was provided.` | `The link index must be an integer, but "..." was given.`, or `The link index must be 1 or greater, but "..." was given.` for an integer below 1 |
| `the JSON path :path should have :count element(s)` | `The expected element count "..." is not a valid non-negative integer.` | `The count must be an integer, but "..." was given.`, or `The count must be 0 or greater, but "..." was given.` for an integer below 0 |

### Signatures

Each method is listed under its 3.14 name, followed by its 4.x name where they differ; [Trait methods prefixed with their trait name](#trait-methods-prefixed-with-their-trait-name) and [One shape per naming idea](#one-shape-per-naming-idea) list the renames. PHP that calls one of these methods passes a string where it passed an `int`, or calls the method in the last column.

| Method | Before | After |
| --- | --- | --- |
| `Drupal\QueueTrait::queueProcessItems()`, `queueAssertItemCount()` | `int $count` | `string $count` |
| `Drupal\SearchApiTrait::searchApiDoIndex()`, now `searchApiRunIndexing()` | `string\|int $limit` | `string $count` |
| `TableTrait::tableAssertRowCount()`, `tableAssertColumnCount()` | `int $count` | `string $count` |
| `RestTrait::restAssertResponseStatusCode()`, now `restAssertResponseStatusCodeEquals()` | `int $code` | `string $code` |
| `ElementTrait::elementClickByIndex()`, `elementFollowLinkByIndex()`, `elementPressButtonByIndex()`, now `elementClickWithIndex()`, `elementFollowLinkWithIndex()`, `elementPressButtonWithIndex()` | `int $index` | `string $index` |
| `ElementTrait::elementAssertIsPinnedToTopWithTolerance()`, now `elementAssertPinnedToTopWithTolerance()` | `int $tolerance` | `string $tolerance` |
| `ElementTrait::elementAssertIsVisuallyVisibleWithOffset()`, `elementAssertIsNotVisuallyVisibleWithOffset()`, now `elementAssertVisuallyVisibleWithOffset()`, `elementAssertNotVisuallyVisibleWithOffset()` | `int $number` | `string $offset` |
| `ElementTrait::elementAssertChildElementCount()` | `int $count` | `string $count` |
| `ElementTrait::elementAssertAttributeWithValueExists()` and the 3 other attribute value steps, now `elementAssertExistsWithAttributeValue()` and its 3 siblings | `mixed $value` | `string $value` or `string $partial_value` |
| `ElementTrait::elementAssertIsVisuallyHidden()`, now `elementAssertNotVisuallyVisible()` | `int $offset = 0` | no `$offset`; call `elementAssertNotVisuallyVisibleWithOffset()` |
| `WaitTrait::waitWaitForSeconds()`, now `waitSeconds()` | `string\|int $seconds` | `string $seconds` |
| `WaitTrait::waitForAjaxToFinish()`, now `waitForAjax()` | `string\|int $seconds` | `string $seconds`; PHP holding an `int` calls the new `waitForAjaxWithin(int $seconds)` |
| `FieldTrait::fieldFillColor()` | `?string $value = NULL` | `string $value` |
| `KeyboardTrait::keyboardPressKeyOnElement()`, `keyboardPressKeysOnElement()` | `?string $selector` | `string $selector`; for the focused element, call `keyboardPressKey()` or `keyboardPressKeys()` |
| `LinkTrait::linkAssertTextWithHrefWithinElementExists()`, `linkAssertTextWithHrefWithinElementNotExists()`, now `linkAssertExistsWithHrefWithinElement()`, `linkAssertNotExistsWithHrefWithinElement()` | `?string $selector` | `string $selector`; for the whole page, call `linkAssertExistsWithHref()` or `linkAssertNotExistsWithHref()` |
| `Drupal\EmailTrait::emailClearTestQueue()` | `bool $force = FALSE` | no `$force`; `emailClearCollectedMessages()` clears the collected messages without the check |
| `Drupal\EmailTrait::emailAssertMessageHeaderContains()` | `bool $exact = FALSE` | no `$exact`; for `TRUE`, call `emailAssertMessageHeaderEquals()` |
| `Drupal\EmailTrait::emailAssertMessageFieldContains()` | `bool $exact = FALSE` | no `$exact`; for `TRUE`, call `emailAssertMessageFieldEquals()` |
| `Drupal\EmailTrait::emailAssertMessageFieldNotContains()` | `bool $exact = FALSE` | no `$exact`; for `TRUE`, call `emailAssertMessageFieldNotEquals()` |
| `Drupal\FileTrait::fileCreateUnmanaged()` | `string $content = 'test'` | no `$content`; call `fileCreateUnmanagedWithContent()` |

An override of one of these methods in your `FeatureContext` takes the 4.x name and the new signature, or PHP reports it as incompatible with the trait's.

A call that still passes a removed argument doesn't fail, because PHP drops an extra argument without a word: `fileCreateUnmanaged($uri, 'Hello')` writes `test`, `emailAssertMessageFieldContains($field, $string, TRUE)` compares with whitespace collapsed, and `elementAssertNotVisuallyVisible($selector, 10)`, the 4.x name of `elementAssertIsVisuallyHidden()`, checks an offset of 0. Move each one to the method in the last column. PHPStan reports every such call as a method invoked with more parameters than it takes.

### Optional string parameters default to `NULL`

A string parameter left out for "not given" defaults to `NULL` with a nullable type, never to an empty string. 4 helpers that open an entity's action page took their subpath as `string $action_subpath = ''`:

| Method | Before | After |
| --- | --- | --- |
| `Drupal\ContentTrait::contentVisitActionPageWithTitle()` | `string $action_subpath = ''` | `?string $action_subpath = NULL` |
| `Drupal\MediaTrait::mediaVisitActionPageWithName()` | `string $action_subpath = ''` | `?string $action_subpath = NULL` |
| `Drupal\TaxonomyTrait::taxonomyVisitActionPageWithName()` | `string $action_subpath = ''` | `?string $action_subpath = NULL` |
| `Drupal\UserTrait::userVisitActionPage()` | `string $action_subpath = ''` | `?string $action_subpath = NULL` |

A call that leaves the subpath out, or passes `''`, opens the same page as before. An override in your `FeatureContext` takes the new signature.

## Classes are final unless a project extends them

A class a project isn't meant to extend is `final` now, so the classes left open are the ones built for it: the 3 contexts, the backends, `Core`, the field handlers, the browser adapters, `HttpClientFactory` and `DocumentElement`. [Final classes](CONTRIBUTING.md#final-classes) says why each of those stays open.

A class of your own that extended one of the Drupal Extension 6.1 or Drupal Driver 3.3 classes below can't follow it into 4.x by changing its parent, because the class that replaces it is `final`:

```
PHP Fatal error:  Class Acme\AcmeAuthenticator cannot extend final class DrevOps\BehatSteps\Behat\Auth\Authenticator
```

| 3.x class | 4.x class, now `final` |
| --- | --- |
| `Drupal\Driver\Alias\RolesAlias` | `Backend\Alias\RolesAlias` |
| `Drupal\Driver\Core\Alias\AuthorAlias`, `ParentTermAlias`, `VocabularyMachineNameAlias` | `Backend\Core\Alias\AuthorAlias`, `ParentTermAlias`, `VocabularyMachineNameAlias` |
| `Drupal\Driver\Core\Field\FieldClassifier`, `FieldShapeClassifier` | `Backend\Core\Field\FieldClassifier`, `FieldShapeClassifier` |
| `Drupal\DrupalExtension\Parser\Exception\MultipleParseException` | `Backend\Core\Field\Parser\Exception\MultipleParseException` |
| `Drupal\Driver\Exception\BootstrapException`, `CreationAliasResolutionException`, `UnsupportedDriverActionException` | `Backend\Exception\BootstrapException`, `CreationAliasResolutionException`, `UnsupportedBackendActionException` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManager` | `Behat\Auth\Authenticator`, and `Behat\Auth\BasicAuthenticator` for its basic authentication |
| `Drupal\DrupalExtension\Context\Attribute\HookAttributeReader` | `Behat\Context\Attribute\HookAttributeReader` |
| `Drupal\DrupalExtension\Context\Initializer\DrupalAwareInitializer` | `Behat\Context\Initializer\BackendAwareInitializer` |
| `Drupal\DrupalExtension\Generator\ClassGenerator` | `Behat\Generator\ClassGenerator` |
| `Drupal\DrupalExtension\Hook\Call\AfterEntityCreate` and the 7 other `Before*Create` and `After*Create` calls | `Behat\Hook\Call\AfterEntityCreate` and the same 7 |
| `Drupal\DrupalExtension\Listener\DriverListener` | `Behat\Listener\BackendListener` |
| `Drupal\MinkExtension\ServiceContainer\Driver\BrowserKitFactory` | `Behat\Mink\ServiceContainer\Driver\BrowserKitFactory` |
| `Drupal\DrupalExtension\Manager\DriverManager` | `Behat\Registry\BackendRegistry` |
| `Drupal\DrupalExtension\Manager\DrupalUserManager` | `Behat\Registry\UserRegistry` |
| `Drupal\DrupalExtension\Selector\RegionSelector` | `Behat\Selector\RegionSelector` |
| `Drupal\DrupalExtension\Compiler\DriverPass` | `Behat\ServiceContainer\BackendPass` |
| `Drupal\DrupalExtension\ServiceContainer\DrupalExtension` | `Behat\ServiceContainer\BehatStepsExtension` |

The classes 4.x adds are `final` from the start: `Behat\Config\ConfigSchemaReader`, `TagOverrideResolver`, `TraitOptionResolver` and `TraitOptionResolverFactory`, `Behat\Http\HttpIdentity`, `Behat\Listener\SkipTagListener`, `Behat\Mink\BrowserCapabilityResolver`, `Behat\Prerequisite\PrerequisiteReader`, `Behat\Registry\ScenarioTagRegistry` and `Exception\AssertionException`. `Backend\Core\Field\Parser\EntityFieldParser` stays `final`, as the Drupal Extension's `EntityFieldParser` was.

Where one of these classes has an interface, implement the interface instead. A suite that swaps in its own registry or authenticator through a `*.class` parameter implements `BackendRegistryInterface`, `ScenarioTagRegistryInterface`, `UserRegistryInterface`, `AuthenticatorInterface` with `FastLogoutInterface`, or `BasicAuthenticatorInterface`. A creation alias of your own implements `PreCreateAliasInterface` or `PostCreateAliasInterface`, and option resolution is replaced through `TraitOptionResolverFactoryInterface`, as [Trait options](docs/configuration.md#trait-options) describes.

PHPUnit can't double a final class either, so a test that built a mock of one of these fails with `Class "..." is declared "final" and cannot be doubled`. Double its interface instead.

1 constructor parameter is named after its type now, which only matters to a call that passes it by name:

| Constructor | Before | After |
| --- | --- | --- |
| `Drupal\Driver\Alias\RolesAlias::__construct()`, now `Backend\Alias\RolesAlias::__construct()` | `$driver` | `$userCapability` |

### Constants declare native types

Every constant under `src/` declares its type, as `public const string CONFIG_KEY = 'behat_steps';` does. None of the 6 constants 3.14 declared carries over under its name: [Constants carry their trait prefix](#constants-carry-their-trait-prefix) lists the 4 `IMPACT_*` renames and the removed `DEFAULT_WAIT_TIMEOUT`, and `Drupal\HelperTrait::ENTITY_CLEANUP_EXCLUDED_TYPES` has no successor. PHP holds a redeclared constant to the type it inherits, so a constant of your own that shares its name with one your context now inherits, such as a `BATCH_WAIT_TIMEOUT` in a `FeatureContext` that extends `DrupalContext`, declares the same type:

```
PHP Fatal error:  Type of FeatureContext::BATCH_WAIT_TIMEOUT must be compatible with DrupalContext::BATCH_WAIT_TIMEOUT of type int
```

## Queues are deleted after every scenario that used them

`QueueTrait` deleted the queues a scenario used only when the scenario was tagged `@queue`, a tag nothing documented, so an untagged scenario left its queue items behind for the next one. The teardown now runs after every scenario, and `@queue` does nothing: remove it from your feature files.

A queue counts as used once any queue step names it, the assertions included, so a scenario that only checks a queue the site filled deletes it as well. To keep the items for a later scenario, tag the scenario that leaves them `@behat-steps-skip:QueueTrait`, or set `queue.enabled` to `FALSE` for the suite.

## Step logic moved into shared helpers

Every step is now a thin wrapper over named helpers, so whatever a scenario can do, a project's own step definitions can do too, by calling the same helpers from PHP. No step calls another step any more: logic 2 steps share lives in a helper both call. The new public helpers are listed in [HELPERS.md](HELPERS.md).

No step text changes. A project needs an edit only where it calls or overrides one of the members below, or asserts one of the messages.

### Members that moved to a helper trait

4 web helper traits are new. `Helper\Web\FixtureDirectoryTrait` resolves and reads fixture files under the Mink `files_path` parameter, `Helper\Web\HeadingTrait` finds a heading in a container, `Helper\Web\JavascriptErrorTrait` holds the JavaScript error registry that `JavascriptTrait` and `DiagnosticsTrait` share, and `Helper\Web\TokenTrait` replaces the tokens in a table for `MappingTrait`, `DateTrait` and `RandomTrait`. Members that did one of these jobs inside a step trait moved to the helper:

| Trait | Old | New |
| --- | --- | --- |
| `JavascriptTrait` | `javascriptClearRegistry()` | `Helper\Web\JavascriptErrorTrait::javascriptErrorClear()` |
| `JavascriptTrait` | `$javascriptErrorRegistry` | `Helper\Web\JavascriptErrorTrait::$javascriptErrorRegistry` |
| `XmlTrait` | `xmlReadFile()` | `Helper\Web\FixtureDirectoryTrait::fixtureDirectoryReadFile()` |
| `JsonTrait` | `jsonReadFile()` | `Helper\Web\FixtureDirectoryTrait::fixtureDirectoryReadFile()` |
| `DropzoneTrait` | `dropzoneResolvePath()` | `Helper\Web\FixtureDirectoryTrait::fixtureDirectoryGetFile()` |
| `Drupal\ConfigTrait` | `configCastValue()` | `Helper\Web\StringTrait::stringNormalizeValue()` |
| `Drupal\ConfigTrait` | `configStringifyValue()` | `Helper\Web\StringTrait::stringFormatValue()` |
| `Drupal\StateTrait` | `stateNormaliseValue()` | `Helper\Web\StringTrait::stringNormalizeValue()` |
| `Drupal\StateTrait` | `stateStringifyValue()` | `Helper\Web\StringTrait::stringFormatValue()` |

Each of these was `protected`, so only a context that called or overrode one is affected. `Drupal\HelperTrait::helperResolveFixtureFile()` and `helperExpandCompoundCellFixtures()`, now `Helper\Drupal\FixtureFileTrait::fixtureFileResolve()` and `fixtureFileExpandCompoundCell()`, no longer take a `$fixture_path` argument either: they read the directory through `FixtureDirectoryTrait` themselves.

### `fieldAssertExists()` returns nothing

`FieldTrait::fieldAssertExists()` returned the field it found, which made the step double as a lookup. It's a plain assertion now, and `fieldGet()` is the lookup: it returns the field, or throws `ElementNotFoundException` when the page has none.

```php
// Before.
$field = $this->fieldAssertExists('Title');

// After.
$field = $this->fieldGet('Title');
```

### Behavior

- **A state value holding a JSON object is stored as an array.** `StateTrait` decoded JSON to a `stdClass` while `ConfigTrait` decoded it to an array. Both now share `stringNormalizeValue()`, which decodes to an array, so `the state :name has the value :value` with `{"a":1}` stores `['a' => 1]`.
- **Draggable Views resolves every title before it writes a weight.** `I save the draggable views items ... in the following order:` used to write each row's weight as it went, so a missing title left the order half-saved. A missing title now fails before anything is written.
- **A new block instance is configured directly.** `the instance of the block :admin_label exists with the following configuration:` used to look the block up again by its label after creating it, so an existing block carrying the same label could receive the configuration instead. It now configures the block it created, and registers it for removal before configuring it.
- **A managed file path that leaves the fixture directory is used as given.** A `path` in `the following managed files exist:` that climbs out of `files_path` with `..` no longer resolves against the fixture directory, so it's read relative to the working directory instead.

### Messages

A test that asserts one of these messages needs the new text:

| Step | Before | After |
| --- | --- | --- |
| A downloaded-file assertion run before any download | `Downloaded file content has no data.`, `Downloaded file name content has no data.` or `Downloaded file path data is not available.` | `No file has been downloaded. Download a file before asserting on it.` |
| `the response XML is loaded from the file :filename`, `the response JSON is loaded from the file :filename` and the 4 `the response should match the ... in the file :filename` steps, for a missing file | `The file "..." does not exist.` | `The fixture file "..." does not exist.` |
| The same steps with no `files_path` configured | `The file "..." does not exist.` | `The Mink "files_path" parameter is not configured.` |
| The same steps with a path that leaves `files_path` | the file outside it was read | `The fixture file "..." is outside the configured "files_path".` |

## Public surface and placement settled

Whether a project could call a helper, override a method or import a type used to depend on where the code happened to sit. Each of these now follows 1 rule, written down in `CONTRIBUTING.md`, and the changes below are what applying them took. No step text changes, so no `.feature` file needs an edit.

### Helpers doing the same job share a visibility

2 members change visibility. `JsonTrait::jsonResolveContent()` was `protected`, and it's now `public` like the new `XmlTrait::xmlGetContent()`, which does the same job for XML. Both take a `Get` name, since they take no input for `Resolve` to derive a value from. The Drupal Extension published its authentication manager's `getLogoutElement()`, which only served the manager's own logout check, and `Behat\Auth\Authenticator` keeps it `protected`.

| Member | Before | After |
| --- | --- | --- |
| `JsonTrait::jsonResolveContent()` | `protected` | `public`, renamed `jsonGetContent()` |
| `Drupal\DrupalExtension\Manager\DrupalAuthenticationManager::getLogoutElement()` | `public` | `protected`, on `Behat\Auth\Authenticator` |

An override of `jsonResolveContent()` is renamed to `jsonGetContent()`, since nothing calls the old name. It's declared `public` as well, because PHP refuses to narrow an inherited method, as [The toolbox is now `public`](#the-toolbox-is-now-public) describes. Code that called `getLogoutElement()` on the authentication manager asks `isLoggedIn()` instead, the 4.x name of its `loggedIn()`, which is the question the method served.

### Documented override points are public

7 methods carried a docblock asking a project to override them, yet they were protected, so they sat outside the API that semantic versioning covers. They're public now and listed in [HELPERS.md](HELPERS.md). 3 of them take a new name too: `accessibilityBlankUrls()` and `elementScrollIntoViewCenter()` supply a value, so they read `Get` like every other override point that does, and the Drupal Extension's `getFieldParser()` moved to the helper trait that builds entities and took its prefix.

| Member | Before | After |
| --- | --- | --- |
| `AccessibilityTrait::accessibilityFormatUrl()` | `protected` | `public` |
| `AccessibilityTrait::accessibilityBlankUrls()` | `protected static` | `public static`, renamed `accessibilityGetBlankUrls()` |
| `AccessibilityTrait::accessibilityRenderHtmlPage()` | `protected` | `public` |
| `AccessibilityTrait::accessibilityRenderHtmlSections()` | `protected` | `public` |
| `AccessibilityTrait::accessibilityRenderAggregate()` | `protected static` | `public static` |
| `ElementTrait::elementScrollIntoViewCenter()` | `protected` | `public`, renamed `elementGetScrollIntoViewCenter()` |
| `Drupal\DrupalExtension\Context\RawDrupalContext::getFieldParser()` | `protected` | `public`, renamed `Helper\Drupal\EntityLifecycleTrait::entityLifecycleGetFieldParser()` |

An override declared `protected` no longer loads:

```
Fatal error: Access level to FeatureContext::elementGetScrollIntoViewCenter() must be public (as in class ...)
```

Change `protected` to `public` on the override and leave its body as it is. An override of `accessibilityBlankUrls()`, `elementScrollIntoViewCenter()` or `getFieldParser()` takes the new name as well. Under its old name nothing calls it and nothing reports it, so a `FeatureContext` that switched centered scrolling off the way the 3.x `elementScrollIntoViewCenter()` docblock showed scrolls to the center again until the override is renamed. `accessibilityGetBlankUrls()` stays `static`, because the suite report calls it from a static hook.

### Lookups are named for what a miss does

4 helpers used `Read`, a verb that says nothing about a miss, and between them they handled one 3 different ways. Each takes the verb for what it does now. The 3 that 3.x kept `protected` are published as well, so an override of one is renamed and declared `public`. Otherwise only the name changes, apart from `stateReadValue()`, which returned 2 answers in 1 array and is 2 helpers now.

| Trait | Old | New | When nothing matches |
| --- | --- | --- | --- |
| `Drupal\ConfigTrait` | `configReadStored()` (protected) | `configFindStoredValue()` | returns `NULL` |
| `Drupal\ConfigTrait` | `configReadEffective()` (protected) | `configFindEffectiveValue()` | returns `NULL` |
| `Drupal\DrupalExtension\Context\DrushContext` | `readDrushOutput()` | `Drupal\DrushTrait::drushGetOutput()` | throws `\RuntimeException` |
| `Drupal\StateTrait` | `stateReadValue()` (protected) | `stateExists()` and `stateFindValue()` | `stateExists()` returns `FALSE`, `stateFindValue()` returns `NULL` |

`stateReadValue()` returned `['exists' => ..., 'value' => ...]`, so read each half from its own helper:

```php
// Before.
$state = $this->stateReadValue('my_module.launched');
if (!$state['exists']) {
  return;
}
$value = $state['value'];

// After.
if (!$this->stateExists('my_module.launched')) {
  return;
}
$value = $this->stateFindValue('my_module.launched');
```

`WatchdogTrait::watchdogClearErrors()` is new: it returns the errors logged since the scenario started and deletes them, which 3.x did only inside `watchdogAssertNoErrors()`.

The snapshots behind the 2 reverting traits share their keys now too. `ConfigTrait::$configOriginalData` stores `exists` and `value` in place of `existed` and `data`, matching `StateTrait::$stateOriginalValues`, so a context reading the property directly renames the keys.

### 3 Drupal Extension types moved into `Behat`

The Drupal Extension kept `ParametersAwareInterface`, `ParametersTrait` and `MinkAwareTrait` at the root of `Drupal\DrupalExtension`. Their 4.x versions sit in the sub-namespace of their concern:

| Before | After |
| --- | --- |
| `Drupal\DrupalExtension\ParametersAwareInterface` | `DrevOps\BehatSteps\Behat\Context\ParametersAwareInterface` |
| `Drupal\DrupalExtension\ParametersTrait` | `DrevOps\BehatSteps\Behat\Config\ParametersTrait` |
| `Drupal\DrupalExtension\MinkAwareTrait` | `DrevOps\BehatSteps\Behat\Mink\MinkAwareTrait` |

A context that extends a shipped context picks up the new names for free. A context or service of your own that implements `ParametersAwareInterface` or composes one of the 2 traits updates its imports. `ParametersTrait` no longer carries `getMapping()`, as [`getMapping()` became `mappingGetValue()`](#getmapping-became-mappinggetvalue) describes, and `MinkAwareTrait` no longer carries `saveScreenshot()`; a context still inherits it from Mink's `RawMinkContext`. The other members are unchanged.

`grep -rnE 'DrupalExtension\\+(ParametersAwareInterface|ParametersTrait|MinkAwareTrait)' <your project>` lists every import and docblock type that still names an old location.

## A lookup by title acts on the newest match

When several entities share the title, label, name or description a step names, the step now acts on the newest of them. The traits used to pick 4 different ways, so a scenario changes only where it has duplicates:

| Trait | Before | After |
| --- | --- | --- |
| `Drupal\ContentTrait`, `Drupal\SearchApiTrait`, `Drupal\MediaTrait`, `Drupal\TaxonomyTrait`, `Drupal\ContentBlockTrait` | the entity saved most recently, by revision ID | the entity created last, by ID |
| `Drupal\MenuTrait` (a menu by label, a link by title), `Drupal\DraggableviewsTrait`, `Drupal\EckTrait` | the first match the database returned | the newest match |
| `Drupal\ParagraphsTrait` (the parent entity) | the last match the database returned | the newest match |
| `Drupal\BlockTrait` | the last machine name in string order, so `block_9` won over `block_10` | the block the scenario placed last, and otherwise the last machine name in natural order |

An entity saved again after a newer one was created no longer wins: content entities are compared by ID, which only grows when an entity is created. Blocks and menus have machine names instead of numeric IDs, so the one the scenario created last wins over any the site already held.

`the menu :menu_name does not exist` and `the following menu links do not exist in the menu :menu_name:` now remove every menu and every link that matches, as every other `... does not exist` step already did. They used to remove 1.

4 public helpers are new: `Helper\Drupal\QueryTrait::queryFindNewestEntityId()` and `Helper\Drupal\EntityLifecycleTrait::entityLifecycleFindNewest()` pick the newest match, and `Drupal\MenuTrait::menuLoadMultiple()` and `Drupal\MenuTrait::menuDeleteLink()` load and delete every match.

## Viewport steps need a JavaScript browser driver

`ResponsiveTrait` swallowed every browser driver exception. On a browser driver that can't resize the window, its 4 viewport steps passed without doing anything, and `responsiveGetCurrentDimensions()` reported 1280 by 800 whatever the browser held. The steps now fail with Mink's `UnsupportedDriverActionException`, which names the browser driver, and `responsiveGetCurrentDimensions()` throws `\RuntimeException` when the browser doesn't report a positive integer. Tag a scenario that sets the viewport `@javascript`, as `@breakpoint:NAME` already required.

## The top offset leaves space above the element

`the element :selector should be displayed within the viewport with a top offset of :offset pixels` and its negative scrolled `:offset` pixels past the top of the element. They now scroll so the top of the element is `:offset` pixels below the top of the viewport, the space a fixed header of that height covers, and a negative offset scrolls the top of the element above the viewport. To keep what a scenario checked, negate its offset:

```gherkin
# Before.
Then the element ".sticky-header" should be displayed within a viewport with a top offset of 200 pixels

# After.
Then the element ".sticky-header" should be displayed within the viewport with a top offset of -200 pixels
```

The step reads `the viewport` now, as [I-prefixed step text and placeholder types](#i-prefixed-step-text-and-placeholder-types) lists.

The scroll also targets the element's position in the document now. It used to read `offsetTop`, which is measured from the nearest positioned ancestor, so an element inside a `position: relative` wrapper further down the page was scrolled to the wrong place. `the element :selector should be displayed within the viewport` and its negative, which scroll the same way with no offset, now find such an element where it is.

## Steps and backends do what their text and contracts say

6 steps and 3 capability methods did something other than what their step text or docblock said. Each one does what it says now. A scenario changes only where it leaned on the old behavior, and each part below says what to look for. Each heading gives the step's 4.x text; [Unified step text](#unified-step-text) and the 3 sections after it map the 3.14 wording.

### `the element :selector should be at the top of the viewport`

The step passed for any element whose top edge sat somewhere in the viewport, so an element halfway down the screen counted as being at the top. It now passes only when the element's top edge is within 2 pixels of the viewport top, the same tolerance `the element :selector should be pinned to the top of the viewport` uses, and an element that isn't rendered fails it. A selector that matches nothing fails with `ElementNotFoundException`, like every other element assertion, where it used to fail with a JavaScript error from the browser driver.

`I scroll to the element :selector` centers the element by default, so a scenario that scrolled and then checked "at the top" only passed because the check was loose. Assert the position the scroll actually leaves:

```gherkin
# Before.
When I scroll to the element "#main-content"
Then the element "#main-content" should be at the top of the viewport

# After: the default scroll centers the element.
When I scroll to the element "#main-content"
Then the element "#main-content" should be centered in the viewport
```

A scenario that only needs the element on screen asserts `the element :selector should be displayed within the viewport` instead.

### `the element :selector should not be displayed within the viewport`

A selector that matched nothing read as displayed, so the negative step and its `with a top offset of :offset pixels` form failed on it. Nothing matching now means nothing is displayed, and both pass. `ElementTrait::elementIsVisuallyVisible()` returns a falsy value for such a selector. The positive steps fail on it with `ElementNotFoundException`, as they did before.

### `the block :label has the condition :condition removed`

The step reset the condition to its defaults instead of removing it, and added the condition to a block that didn't carry it. Drupal drops a condition left at its defaults when the block saves, so for the conditions core ships, the saved block came out the same. A condition whose configuration doesn't return to its defaults stayed on the block, though.

The step now removes the condition and leaves a block without it unchanged, through the new public helper `Drupal\BlockTrait::blockUnsetVisibilityCondition()`. A condition ID that names no condition plugin fails with `\RuntimeException` (`The condition "..." does not exist.`), where Drupal's `PluginNotFoundException` used to surface.

### `I add the :content_type content with the title :title to the search index`

The step tracked the node, then indexed whichever item came next in each index, which was the named node only when nothing else was waiting. It now indexes the named node's own items on every enabled index that includes the node, and leaves everything else queued. It fails with `\RuntimeException` when no enabled index includes the node (`No active search index includes the "..." content with the title "...".`), where it used to fail only when no index was enabled at all.

A scenario that relied on the step to push some other queued item into the index runs `I run search indexing for :count item(s)` as well. A PHP call to `Drupal\SearchApiTrait::searchApiIndexContent()` changes the same way, and the new public helper `searchApiIndexNode()` indexes a node object it's given. An index that fails to index the node now throws Search API's `SearchApiException` rather than logging it.

### `the webform :title exists from the template :template`

When several templates matched, the step cloned the first one sorted by title, then by machine name. It now clones the newest, as every lookup for 1 entity does: the template the scenario created last, and otherwise the one with the last machine name in natural order. The new public helper `Drupal\WebformTrait::webformFindTemplateByTitle()` does the lookup. A scenario with 1 matching template sees no change.

### Capability contracts

3 capability methods that the Drupal Driver declared promised more than a shipped backend delivered. Each contract now matches what every shipped backend does.

| Method | Before | After |
| --- | --- | --- |
| `CacheCapabilityInterface::cacheClear()` | Took a cache bin to clear, which no backend honored | Takes no argument and clears every cache |
| `UserCapabilityInterface::userAddRole()`, now `addUserRole()` | A role label worked on the in-process backend only | The Drush backend resolves a label as well, through `drush role:list` |
| `RoleCapabilityInterface::roleCreate()`, now `createRole()` | Promised permission labels, which `drush role:perm:add` rejects | Takes permission machine names. The in-process backend still converts a label |

A call that passed a type to `cacheClear()` drops it:

```php
// Before.
$backend->cacheClear('all');

// After.
$backend->cacheClear();
```

The same goes for `cacheClear('drush')`, which made the Drupal Driver's `DrushDriver` clear only Drush's own cache: it now rebuilds every cache, like any other call.

A backend of your own that implements `cacheClear(?string $type = NULL)` keeps loading, because an extra optional parameter is compatible with the interface, so drop the parameter whenever it suits. A project that called `roleCreate()` with permission labels on any backend but the in-process one passes machine names to `createRole()` instead.
