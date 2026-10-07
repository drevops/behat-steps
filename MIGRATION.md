# Migration guide

## Per-trait configuration

Every configurable trait now declares its options in a `<prefix>ConfigSchema()` method returning `Option` objects, and the extension carries their profile-wide defaults under a new `steps` section. Each group is named after the trait that declares it, in snake case: `JavascriptTrait` reads `javascript`, `BigPipeTrait` reads `big_pipe`, `FileDownloadTrait` reads `file_download`. [STEPS.md](STEPS.md) lists the options of each trait beside its steps.

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

### A trait declares its options as `Option` objects

A project with its own configurable trait returns a list of `DrevOps\BehatSteps\Behat\Config\Option` from its `<prefix>ConfigSchema()` method instead of a map of array shapes. The constructor validates the declaration, so a missing description or a tag list written as a list rather than a map fails when the context is built, with a message naming the declaring method.

```php
// Before.
protected function acmeConfigSchema(): array {
  return [
    'enabled' => [
      'default' => TRUE,
      'description' => 'Whether the Acme hook runs.',
    ],
    'wait_timeout' => [
      'default' => 5000,
      'description' => 'How long to wait, in milliseconds.',
      'tags' => ['slow' => 30000],
    ],
  ];
}

// After.
protected function acmeConfigSchema(): array {
  return [
    new Option('enabled', default: TRUE, description: 'Whether the Acme hook runs.'),
    new Option('wait_timeout', default: 5000, description: 'How long to wait, in milliseconds.', tags: ['slow' => 30000]),
  ];
}
```

### An option is read at the type it was declared with

`getOption()` no longer returns `mixed` for a declared type, and no longer takes a `ScenarioScope`. A read names the type the declaration defaults to, so a caller neither casts nor guards what it gets back, and a read naming the wrong type fails with a message naming both.

| Before | After |
| --- | --- |
| `(bool) $this->getOption('acme', 'enabled')` | `$this->getOptionBool('acme', 'enabled')` |
| `(int) $this->getOption('acme', 'wait_timeout')` | `$this->getOptionInt('acme', 'wait_timeout')` |
| `(float) $this->getOption('acme', 'ratio')` | `$this->getOptionFloat('acme', 'ratio')` |
| `(string) $this->getOption('acme', 'label')` | `$this->getOptionString('acme', 'label')` |
| `(array) $this->getOption('acme', 'selectors')` | `$this->getOptionArray('acme', 'selectors')` |
| `$this->getOption('acme', 'enabled', $scope)` | `$this->getOptionBool('acme', 'enabled')` |

`getOption()` stays for an option whose declaration defaults to `NULL` and so names no type.

Dropping the scope also removes an asymmetry. The two tag layers used to be read only when a scope was passed, which a hook has and a step does not, so a tag that set an option worked in a hook and silently did nothing in a step. The tags of the running scenario are now published once when the scenario starts, and every read sees them. A trait that resolved a tag-bound option in a `BeforeScenario` hook and cached it on a property for a step hook to read can drop the property and read the option where it is used.

### Option resolution is a service a project can replace

`WebRawContext` delegates to a `TraitOptionResolverInterface` built by the `behat_steps.config.resolver_factory` service. Registering another `TraitOptionResolverFactoryInterface` under that id replaces resolution for every context, in place of overriding the protected methods the base class used to carry. A context implementing `BackendAwareInterface` directly rather than extending `WebRawContext` adds `setOptionResolverFactory()` and `getOptionResolver()`.

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

`@error` and `@js-errors` are also read on the `Feature:` line now, so either one there covers every scenario in that feature. [Tags on the `Feature:` line apply to every scenario](#tags-on-the-feature-line-apply-to-every-scenario) covers the rest of the tags.

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

Placeholder names, articles and `Given` verbs drifted as traits were added, so the same idea ended up written several different ways: an XML attribute was `:attribute` in 4 steps and `:attribute_name` in 2, a taxonomy vocabulary answered to 3 different names, and a handful of `Given` steps had no verb at all. 89 steps now follow one set of conventions.

- A step that names its target (`:element`, `:path`, `:key`, `:field`) compares against `:value`. `:text` is now reserved for steps that assert on a whole body with no named target, such as `the modal should contain :text`.
- A bundle placeholder is named after its entity type - `:content_type`, `:media_type`, `:content_block_type`, `:vocabulary`. Steps that are deliberately entity-agnostic keep `:bundle` (`EckTrait`, and the parent lookup in `ParagraphsTrait`).
- A bundle placeholder that qualifies an entity noun comes before it, as in `the :media_type media`. One that is itself the subject follows its noun, as in `the media type :media_type`.
- Every noun takes an article, `URL` is uppercase, and a named value reads `the value :value` rather than `the :value value`.
- Placeholder names are `snake_case`.
- A `Given` states a fact in the present tense. Verbless steps gained `exist`, bare noun phrases gained a verb, and the `has been cleared` family became `is empty`. Steps that already read `is empty`, `is enabled` or `is disabled` were left alone: they mirror the `should be ...` assertion they pair with, and forcing them into an `exists` form would say something different.

Placeholder names are part of the contract even when the surrounding words are identical. Behat binds a step argument to the method parameter of the same name, so a rename reaches any context that overrides the step method or calls it directly.

Three steps were relying on Behat's positional fallback because their parameter never matched their placeholder. Their step text is unchanged, but the method signatures are not: `MediaTrait::mediaDeleteType()` now takes `$media_type`, and `SearchApiTrait::searchApiIndexContent()` and `searchApiRunIndexing()` now take `$content_type` and `$count`.

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
| `Then no emails should have been sent to the :address` | `Then no emails should have been sent to the address :address` |
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
| `Then the :rowText row should contain the following:` | `Then the row :row_text should contain the following:` |

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
| `Then the element :selector should be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should be displayed within the viewport with a top offset of :offset pixels` |
| `Then the element :selector should not be displayed within a viewport with a top offset of :number pixels` | `Then the element :selector should not be displayed within the viewport with a top offset of :offset pixels` |

This also renames the `$number` argument of `ElementTrait::elementAssertVisuallyVisibleWithOffset()` and `ElementTrait::elementAssertNotVisuallyVisibleWithOffset()` to `$offset`, which matters only if you call either method with named arguments.

## Step text follows the documented grammar

The passes above still left steps that broke the step-text rules in [CONTRIBUTING.md](CONTRIBUTING.md#steps-format). They follow those rules now. Only the wording and the placeholder names changed. The one behavior tied to a placeholder name, `[relative:...]` token expansion, is described below the rules.

- A placeholder that names a thing follows its noun: `the queue :queue`, `the module :module`, `the dropzone :selector`. A bundle still comes before the entity noun it qualifies (`the :media_type media`), and a count before its unit (`:count item(s)`).
- Every noun takes an article: `on the element :element`, `to the URL :url`, `the system time`, `the last XML response`.
- A value reads `the value :value`, so `should be equal to :value` became `should be equal to the value :value`.
- A step that names its target compares against `:value`, so the region, row and command output assertions take `:value` where they took `:text`. `the modal should contain :text` keeps `:text`, because it asserts on a whole body with no named target.
- A partial match reads `a <thing> containing :partial_<thing>`, as the cookie steps already did.
- `:param` became `:name`, the placeholder every other named thing uses, and an email link's position became `:index`, as it is in `I follow the link :link with the index :index`.

Where a pass above already renamed a step, its row there now carries the final text instead of being repeated here, so each v3 step maps straight to its v4 form. That covers the query parameter, meta tag and table row steps, and the XML comparisons. Steps that are new in v4 changed only in the [DrupalExtension mapping](#drupalextension-step-text-mapped-to-the-v4-vocabulary).

A renamed placeholder renames the method parameter behind it, because Behat binds a step argument to the parameter of the same name. That matters only to a context that overrides one of these methods or calls it with named arguments. 2 steps changed a placeholder name and nothing else, so their feature files need no edit:

| Method | Before | After |
| --- | --- | --- |
| `ElementTrait::elementFollowLinkWithIndex()` | `$text` | `$link` |
| `ElementTrait::elementPressButtonWithIndex()` | `$label` | `$button` |

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
| `Then the block :label should exist in the :region region` | `Then the block :label should exist in the region :region` |
| `Then the block :label should not exist in the :region region` | `Then the block :label should not exist in the region :region` |

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

A handful of ideas still read 2 ways after the passes above. Most navigation steps said `I visit the ... page`, while 2 dropped `page` and 3 said `I edit the ...` although they only open the edit form. A click was `I click on` in some traits and `I click` in others, the viewport was both `the viewport` and `a viewport`, a `<select>` was both `the select` and `the select element`, and an email address was `:address` in one trait and `:mail` in another. Each idea now reads 1 way:

- A step that opens a page reads `I visit the ... page` and names the page it opens.
- A click reads `I click on the ...`.
- The viewport is `the viewport`, and a `<select>` is `the select :selector`.
- An email address is `:address` everywhere, and an email link's position reads `WithIndex` in the method names, as it already did in the step text.

`ahoy lint-docs` and `TraitMethodNamingTest` reject the replaced forms, so they don't come back.

The media, ECK and content block navigation steps and the 2 viewport offset steps were already renamed by a pass above, so their rows there carry the final text. `I click on the link :link in the region :region` and `I click on the link :link in the row :row_text` are new in v4, so only their [DrupalExtension mapping](#drupalextension-step-text-mapped-to-the-v4-vocabulary) rows change. The steps below changed in this pass alone.

### ElementTrait

| Before | After |
| --- | --- |
| `Then the element :selector should be displayed within a viewport` | `Then the element :selector should be displayed within the viewport` |
| `Then the element :selector should not be displayed within a viewport` | `Then the element :selector should not be displayed within the viewport` |

### FieldTrait

| Before | After |
| --- | --- |
| `Then the option :option should exist within the select element :selector` | `Then the option :option should exist within the select :selector` |
| `Then the option :option should not exist within the select element :selector` | `Then the option :option should not exist within the select :selector` |
| `Then the option :option should be selected within the select element :selector` | `Then the option :option should be selected within the select :selector` |
| `Then the option :option should not be selected within the select element :selector` | `Then the option :option should not be selected within the select :selector` |

### Method names

A method behind a navigation step opens with `Visit` and names the page the way its step does, and `Drupal\EmailTrait` names a link's position `WithIndex`, as `ElementTrait` already did. Rename any call or override in a consumer context:

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

`the user with the email :address should exist` and its negative took `:mail`. Their step text is unchanged, so no feature file needs an edit, but the parameter behind the placeholder is renamed. That matters only to a context that overrides either method or calls it with named arguments:

| Method | Before | After |
| --- | --- | --- |
| `UserTrait::userAssertExistsWithMail()` | `$mail` | `$address` |
| `UserTrait::userAssertNotExistsWithMail()` | `$mail` | `$address` |

A taxonomy term's name, a role and a file name each had 2 placeholder names: `:term_name` where every other named entity reads `:name`, `:role_name` beside its own `:roles`, and `:file_name` or `:path` where the XML and JSON steps read `:filename`. Each now has 1. A placeholder name never appears in a feature file, so no `.feature` file changes, but the parameter behind each placeholder is renamed:

| Method | Before | After |
| --- | --- | --- |
| `TaxonomyTrait::taxonomyVisitTermPageWithName()`, `taxonomyVisitTermEditPageWithName()`, `taxonomyVisitTermDeletePageWithName()`, `taxonomyVisitActionPageWithName()` | `$term_name` | `$name` |
| `TaxonomyTrait::taxonomyAssertTermExistsWithName()`, `taxonomyAssertTermNotExistsWithName()` | `$term_name` | `$name` |
| `UserTrait::userCreateRole()` | `$role_name` | `$role` |
| `EmailTrait::emailAssertMessageContainsAttachmentWithSubject()`, `emailAssertMessageContainsAttachmentWithSubjectContaining()` | `$file_name` | `$filename` |
| `DropzoneTrait::dropzoneDropFile()` | `$path` | `$filename` |

`FileTrait::fileAssertUnmanagedContains()` and `fileAssertUnmanagedNotContains()` take `$value` where they took `$content`, because their steps now read `the value :value`, as the `FileTrait` table under [Unified step text](#unified-step-text) shows.

### Failure messages

| Trait | Before | After |
| --- | --- | --- |
| ElementTrait | Element(s) defined by "..." selector is not displayed within a viewport. | Element(s) defined by "..." selector is not displayed within the viewport. |
| ElementTrait | Element(s) defined by "..." selector is not displayed within a viewport with a top offset of N pixels. | Element(s) defined by "..." selector is not displayed within the viewport with a top offset of N pixels. |
| Drupal\EmailTrait | The link number must be a positive integer, but "..." was provided. | The link index must be a positive integer, but "..." was provided. |
| Drupal\EmailTrait | The link with number N was not found among N links. | The link with the index N was not found among N links. |

The 2 viewport messages that end in `, but it should not be.` also changed under [Failure messages read one way](#failure-messages-read-one-way), and their rows there carry the final text.

## Optional dependencies moved to `require-dev` and `suggest`

Trait-specific packages are no longer hard `require` dependencies. They now live in `require-dev` (so this library's own test suite still runs) and `suggest`, matching the existing treatment of `justinrainbow/json-schema`. Projects that relied on transitive installation must add the packages they use to their own `composer.json`.

| Package | Add it to your `require-dev` when you use |
| --- | --- |
| `drupal/drupal-extension` | any Drupal trait (`DrevOps\BehatSteps\Steps\Drupal\*`) |
| `softcreatr/jsonpath` | `JsonTrait` JSON path steps (`the JSON path ... should ...`) |

`@javascript` scenarios need a JavaScript-capable browser driver. The steps work with either of these, so install **one** of them - both run the full `@javascript` suite and both are exercised by this library's CI:

- `lullabot/mink-selenium2-driver` - drives a Selenium/WebDriver server.
- `dmore/behat-chrome-extension` - drives headless Chrome directly over the Chrome DevTools Protocol, with no Selenium server.

`behat/behat` and `behat/mink` remain hard `require` dependencies. For example, a project that uses the Drupal traits and runs JavaScript scenarios with headless Chrome adds:

```bash
composer require --dev drupal/drupal-extension dmore/behat-chrome-extension
```

## Behat extensions registered in the Behat configuration

`drupal/drupal-extension` is no longer a dependency. Its Mink extension is replaced by Mink's own, and its Drupal extension by the one this package supplies:

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
  ->withExtension(new Extension(BehatStepsExtension::class, ['drupal' => ['drupal_root' => 'web']]));
```

Every option under them - `base_url`, `files_path`, `javascript_session`, `selenium2`, `browserkit_http`, `drupal_root` - is set exactly as before. `guzzle_request_options`, `ajax_timeout`, the 3 `*_driver` keys and the `log_in` and `log_out` text keys are the exceptions; see below, [Capability-based backend resolution](#capability-based-backend-resolution) and [`Login` and `Logout`, not `LogIn` and `LogOut`](#login-and-logout-not-login-and-logout).

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

`ajax_timeout` moves from the `mink` key, where `Drupal\MinkExtension` accepted it, to the `wait` group under `steps` (see [Per-trait configuration](#per-trait-configuration)):

```yaml
# Before.
extensions:
  Drupal\MinkExtension:
    ajax_timeout: 10

# After.
extensions:
  DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
    steps:
      wait:
        ajax_timeout: 10
```

Mink's own extension declares no `ajax_timeout`, so one left under the `mink` key fails the container build with an `Unrecognized option "ajax_timeout"` error.

## Capability-based backend resolution

`@api` no longer selects a backend, and the `default_driver`, `api_driver` and `drush_driver` options are replaced by one `backends` list under `behat_steps`. That list names the backends a scenario may reach, in precedence order, and a step resolves the backend by the capability it needs.

| Before | After |
| --- | --- |
| `'default_driver' => 'blackbox'` | A configuration that declares no `backends` list gets every registered backend, in registration order |
| `'api_driver' => 'drupal'` | `'backends' => ['drupal', 'blackbox']` |
| `'drush_driver' => 'drush'` | Add `'drush'` to the `backends` list |
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

Two consequences are worth checking in an existing project:

- **A step that used to fail for a missing `@api` now succeeds.** The tag no longer gates anything, so a scenario that reached a Drupal step without it used to throw and now runs. Where that gate was load-bearing, run those scenarios under a profile whose `backends` list excludes the Drupal backend.
- **`RawContext::assertDrupal()` is gone.** A custom step that called it calls `$this->backendFor(CoreCapabilityInterface::class);` instead. `RawContext::getDriver()` becomes `WebRawContext::getBackend()`, which takes a name and no longer defaults to "the current driver"; a call with no argument becomes `backendFor()` naming the capability the caller needs.

## DrupalExtension step text mapped to the v4 vocabulary

The Drupal Extension's contexts are gone. Their behavior lives in the step traits, re-expressed in the one grammar the docs linter enforces: tuple placeholders, no regex, no optional words, and a `Then` that starts with the subject rather than `I`.

The suite registers `Behat\MinkExtension\Context\MinkContext` for the base browser vocabulary, so `I am on`, `I go to`, `I should see`, `I fill in`, `I press`, `I follow`, `I check`, `I select`, `I attach the file`, `the response status code should be` and the other upstream Mink steps are unchanged. The table below covers only the steps the Drupal Extension added on top.

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

Every region step drops the optional `( region)` suffix and names the region last, so one phrasing covers each action.

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
| `Then I should see the link :link in the :region( region)` | `Then the link :link should exist in the region :region` |
| `Then I should not see the link :link in the :region( region)` | `Then the link :link should not exist in the region :region` |
| `Then I should see the button :button in the :region( region)` | `Then the button :button should exist in the region :region` |
| `Then I should see the :button button in the :region( region)` | `Then the button :button should exist in the region :region` |
| `Then I should not see the button :button in the :region( region)` | `Then the button :button should not exist in the region :region` |
| `Then I should not see the :button button in the :region( region)` | `Then the button :button should not exist in the region :region` |
| `Then I should see the :tag element in the :region( region)` | `Then the element :selector should exist in the region :region` |
| `Then I should not see the :tag element in the :region( region)` | `Then the element :selector should not exist in the region :region` |
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
| `Given I click :link in the :rowText row` | `When I click on the link :link in the row :row_text` |
| `Given I press :button in the :rowText row` | `When I press the button :button in the row :row_text` |
| `Then I should see the text :text in the :rowText row` | `Then the row :row_text should contain the value :value` |
| `Then I should not see the text :text in the :rowText row` | `Then the row :row_text should not contain the value :value` |
| `Then I should see the :link in the :rowText row` | `Then the link :link should exist in the row :row_text` |
| `Then I should not see the :link in the :rowText row` | `Then the link :link should not exist in the row :row_text` |

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
| `Then the following (e)mail(s) should have been sent to :to with the subject :subject:` | the two steps above, combined |
| `Then the following new (e)mail(s) should have been sent...` | clear the queue, then use the non-`new` step |
| `Then there should be a total of :count (e)mail(s) sent` | `Then the number of sent emails should be :count` |
| `Then there should be a total of :count (e)mail(s) sent to :to` | `Then the number of emails sent to the address :address should be :count` |
| `Then there should be a total of :count (e)mail(s) sent with the subject :subject` | `Then the number of emails sent with the subject :subject should be :count` |
| `Then there should be a total of :count new (e)mail(s) sent...` | clear the queue, then use the non-`new` step |
| `Then (a )(an )(e)mail(s) should have been sent with the attachment(s) :attachments` | `Then the file :filename should be attached to the email with the subject :subject` |
| `Then (a )(an )(e)mail(s) should have been sent to :to with the attachment(s) :attachments` | as above |
| `When I follow the link to :urlFragment from the (e)mail` | `When I follow the link with a URL containing :partial_url in the email` |
| `When I follow the link to :urlFragment from the (e)mail to :to` | as above |
| `When I follow the link to :urlFragment from the (e)mail with the subject :subject` | `When I follow the link with the index :index in the email with the subject :subject` |

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
| `When (I )break` | dropped; use a debugger or `When I print last response` (Mink) |

### Metatag

| Before | After |
| --- | --- |
| Then the meta robots should include :directive | Then the meta robots should contain :directive |
| Then the meta robots should not include :directive | Then the meta robots should not contain :directive |

Random-value tokens (`[?name:type]`) and mapping tokens (`{{ Key }}`) are unchanged: `Steps\Web\RandomTrait` and `Steps\Web\MappingTrait` carry them, and a context composes the trait instead of registering `RandomContext` or `MappingContext`.

## Unified entity cleanup

Every entity a creation step or the backend creates is registered on `Helper\Drupal\EntityLifecycleTrait` and deleted in reverse creation order by one `entityLifecycleAfterScenario` hook. Every trait that creates an entity composes that helper, and trait flattening is idempotent, so however many of them a context carries there is still one registry and one hook. There is no second registry and no exclusion list, so a node, a term and a media item created in one scenario come down in the order that respects the references between them.

An entity a project saves through Drupal's API in its own step joins that teardown only when the step registers it, which it does with `$this->entityLifecycleRegister($entity)`. Without that call the entity survives the scenario.

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

To skip cleanup of every registered entity at once, use `@behat-steps-skip:EntityLifecycleTrait`, and `@behat-steps-skip:AuthTrait` to keep the users and roles a scenario created.

`@behat-steps-skip:FileTrait` now keeps only the unmanaged files `FileTrait` created; managed file entities it creates are cleaned up by the shared registry and can be kept with `@behat-steps-entity-cleanup-skip:file`.

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
| `WebRawContext` | The project picks its own traits; each one brings the helpers it needs |

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

### The Drupal lifecycle moved into concern-named helpers

Everything `RawContext` declared about Drupal moved under `DrevOps\BehatSteps\Helper\Drupal`, split by concern rather than gathered into one class. Every member carries its trait's prefix:

| Helper | Holds | Composed by |
| --- | --- | --- |
| `Helper\Drupal\EntityLifecycleTrait` | `entityLifecycleCreateNode()`, `entityLifecycleCreateTerm()`, `entityLifecycleCreate()`, `entityLifecycleCreateLanguage()`, `entityLifecycleRegister()`, `entityLifecycleParseFields()`, `entityLifecycleAfterScenario()`, `entityLifecycleBeforeNodeCreate()` | the 13 step traits that create entities, and `UserTrait` through `AuthTrait` |
| `Helper\Drupal\AuthTrait` | `authCreateUser()`, `authLogin()`, `authLogout()`, `authIsLoggedIn()`, `authGetUserRegistry()`, `authSetUserRegistry()`, `authGetAuthenticator()`, `authSetAuthenticator()`, `authAfterScenario()` | `Steps\Drupal\UserTrait` |
| `Helper\Drupal\StaticCacheTrait` | `staticCacheAfterScenario()` | `Steps\Drupal\CacheTrait` |
| `Helper\Drupal\FixtureFileTrait` | the 5 `fixtureFile*()` methods | `ContentTrait`, `MediaTrait` |
| `Helper\Drupal\QueryTrait` | `queryEntityIds()`, `queryNodeIds()` | 9 step traits |

The web half of the library sits under `DrevOps\BehatSteps\Helper\Web` and names nothing Drupal:

| Helper | Holds | Composed by |
| --- | --- | --- |
| `Helper\Web\LastStepTrait` | `lastStepSetLine()`, `lastStepReached()` | `WebRawContext` and 3 step traits |
| `Helper\Web\RequestHeadersTrait` | `requestHeadersSet()`, `requestHeadersUnset()`, `requestHeadersAll()`, `requestHeadersReset()` | `WebRawContext` and 2 step traits |
| `Helper\Web\StringTrait` | `stringFixStepArgument()`, `stringNormalizeWhitespace()`, `stringSplitCommaSeparated()`, `stringSlug()` | `WebRawContext` and 6 step traits |
| `Helper\Web\TableTransposeTrait` | `tableTransposeVertical()`, `tableTransposeHorizontal()` | 5 step traits |

A call or an override in a consumer context is renamed:

| Old `RawContext` member | New member |
| --- | --- |
| `nodeCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateNode()` |
| `termCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateTerm()` |
| `entityCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreate()` |
| `languageCreate()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleCreateLanguage()` |
| `entityRegister()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleRegister()` |
| `parseEntityFields()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleParseFields()` |
| `cleanEntities()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleAfterScenario()` |
| `alterNodeParameters()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleBeforeNodeCreate()` |
| `userCreate()` | `Helper\Drupal\AuthTrait::authCreateUser()` |
| `login()` | `Helper\Drupal\AuthTrait::authLogin()` |
| `logout()` | `Helper\Drupal\AuthTrait::authLogout()` |
| `loggedIn()` | `Helper\Drupal\AuthTrait::authIsLoggedIn()` |
| `getUserManager()` | `Helper\Drupal\AuthTrait::authGetUserRegistry()` |
| `setUserManager()` | `Helper\Drupal\AuthTrait::authSetUserRegistry()` |
| `cleanUsers()` | `Helper\Drupal\AuthTrait::authCleanUsers()` |
| `cleanRoles()` | `Helper\Drupal\AuthTrait::authCleanRoles()` |
| `clearStaticCaches()` | `Helper\Drupal\StaticCacheTrait::staticCacheAfterScenario()` |

Three of those names were also skip tags. A skip tag names a trait rather than a method, so `@behat-steps-skip:cleanEntities` becomes `@behat-steps-skip:EntityLifecycleTrait`, and `@behat-steps-skip:cleanUsers` and `@behat-steps-skip:cleanRoles` both become `@behat-steps-skip:AuthTrait`.

`authCleanUsers()` and `authCleanRoles()` take no parameters, aren't hooks, and are protected. `authAfterScenario()` is the hook. It runs them users first, and still runs the role cleanup when removing the users fails. When both fail, it throws 1 `\RuntimeException` that names both. An override of either drops its `@AfterScenario` annotation or `#[AfterScenario]` attribute, or it runs twice.

A step trait composes what its own body calls, so the teardown travels with the traits that create the thing being torn down. A context that composes no entity-creating trait runs no entity teardown, where the old `RawContext` ran it for every suite. A context extending `DrupalContext` needs no change.

A context that wants one concern without the Drupal vocabulary composes that helper alone:

```php
class SpecContext extends WebRawContext {

  use EntityLifecycleTrait;

}
```

`UserAwareInterface` declares the four accessors `AuthTrait` implements: `authSetUserRegistry()`, `authGetUserRegistry()`, `authSetAuthenticator()` and `authGetAuthenticator()`. A context composing `AuthTrait` declares the interface so the context initializer injects both services; a context that creates no users declares nothing and neither is built.

Basic authentication is a separate service, because applying credentials to a request needs Mink and a base URL and knows nothing about a Drupal session. `WebRawContext` carries `BasicAuthenticatorInterface` through `setBasicAuthenticator()` and `getBasicAuthenticator()`, and `Authenticator` takes it as a constructor argument to reapply the credentials after a fast logout.

### One context registers, not two

`DrupalContext` used to ship 7 of the Drupal traits and 10 of the web ones. It now extends `WebContext` and composes all 29 `Steps\Drupal` traits on top of its 28 web ones, so `$suite->addContext(DrupalContext::class)` alone gives a Drupal suite all 57 step traits.

Registering `WebContext` beside `DrupalContext` is fatal, because the 28 web traits would register their steps twice. `WebContext::assertOneContext()` runs on `BeforeSuite` and names the real mistake rather than letting Behat report a `RedundantStepException` about an arbitrary step.

Registering either context beside a hand-composed context that already carries one of the same traits is a `RedundantStepException` too: two registered contexts cannot compose the same trait. Drop the trait from the hand-composed context, or register the shipped context instead of it.

There is no way to remove an inherited step, so a Drupal project cannot take the Drupal step traits without the 28 web ones. A project whose own step text collides with a shipped web step drops to `WebRawContext` and composes what it wants by hand.

Scoped configuration follows the chain. `WebContext` accepts the `javascript`, `modal`, `wait`, `message`, `mapping` and `diagnostics` groups, and `DrupalContext` accepts those plus `watchdog`, `big_pipe`, `cache`, `queue` and `email`. A group no trait in the chain declares is an error at construction, naming what that context does accept.

`DrupalContext` composes `WatchdogTrait`, so a suite that registers it fails any scenario that logs a PHP error, even if your v3 context never composed the trait. The check reads the `watchdog` table, which only the core `dblog` module creates, and it reads it in the Behat process, so it needs a backend such as `drupal`. On a site without `dblog`, or under a profile that lists no such backend, such as `'backends' => ['drush', 'blackbox']`, every scenario fails at its start until you meet the prerequisite or switch the check off for the profile:

```php
'steps' => ['watchdog' => ['enabled' => FALSE]],
```

Setting `fail_on_errors` to `FALSE` or tagging a scenario `@error` doesn't cover an unmet prerequisite, because both only apply to errors that were read.

## Traits declare the host they need

Every trait that reaches beyond its own methods states what it needs from its host. A web trait carries `@phpstan-require-extends`, naming `Behat\MinkExtension\Context\RawMinkContext` when a Mink session is all it touches and `DrevOps\BehatSteps\Behat\Context\WebRawContext` when it reads a backend or the extension configuration. A Drupal trait carries the same annotation and composes the helper traits its body calls, rather than requiring them of its host.

Composition is unchanged at run time, but a project running PHPStan gets an error when a context uses a trait without extending the class or declaring the interface that trait needs. The fix is to extend the named class and declare the named interface, which is what the trait already assumed.

## A trait declares its prerequisites

A trait states what it needs from the site in a `<prefix>Prerequisites()` method, named like its `<prefix>ConfigSchema()`, and each prerequisite goes through a backend capability rather than a query of its own. The module checks the step traits ran through `queryAssertModuleEnabled()` moved onto these declarations, and [STEPS.md](STEPS.md) lists each trait's prerequisites beside its options.

```php
protected function acmePrerequisites(): array {
  return [
    Prerequisite::capability(CoreCapabilityInterface::class),
    Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('acme'), 'the "acme" module from the "drupal/acme" package is enabled'),
  ];
}
```

A step or a setup hook checks them with `$this->assertPrerequisites(__TRAIT__)`, and a teardown asks `$this->prerequisitesMet(__TRAIT__)` instead, so it never replaces a failure the scenario already recorded. A prerequisite that doesn't hold fails with a message naming it and, for a trait with an `enabled` option, the option and the skip tag that switch the trait off.

| Before | After |
| --- | --- |
| `$this->queryAssertModuleEnabled('acme', 'drupal/acme')` in a step | Declare the module in `<prefix>Prerequisites()` and call `$this->assertPrerequisites(__TRAIT__)` |
| `\Drupal::moduleHandler()->moduleExists('acme')` to adapt to an optional module | `$this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('acme')` |

The message for a missing module changes with it. `The "webform" module is not enabled. Add "drupal/webform" to the consumer project's composer.json and enable the module as part of the site setup.` becomes `WebformTrait requires that the "webform" module from the "drupal/webform" package is enabled, which does not hold.`, so a test asserting the old text needs the new one.

`TestmodeTrait` still checks the `testmode` module when a `@testmode` scenario starts, but it no longer checks it again when the scenario ends: the teardown disables test mode only if the scenario enabled it.

## A trait's directory classifies it

A trait's directory is its classification: `src/Steps` registers Gherkin and `src/Helper` registers none. `scripts/lint-traits.php` fails a step trait composing another step trait, and a helper trait registering a step or a transform. A helper may register a hook, because the trait that owns a teardown carries the hook that runs it.

A consuming project keeps its own traits wherever it likes; the rule applies to this package's own tree, and it is what routes the reference documentation.

## Step traits no longer compose other step traits

Shared logic lives in step-free helper traits under `DrevOps\BehatSteps\Helper\Web` and `DrevOps\BehatSteps\Helper\Drupal`, each named for one concern, so that composing one trait cannot pull in another trait's steps.

| Trait | Old | New |
| --- | --- | --- |
| `Steps\Drupal\ContentTrait` | `contentLoadMultiple()` | `Helper\Drupal\QueryTrait::queryNodeIds()` |
| `Steps\Web\RestTrait` | `$restHeaders` | `Helper\Web\RequestHeadersTrait::$requestHeaders`, read and written through `requestHeadersSet()`, `requestHeadersUnset()`, `requestHeadersAll()` and `requestHeadersReset()` |

`Steps\Drupal\SearchApiTrait` composed `ContentTrait` and so registered every content step alongside its own; it now reads `queryNodeIds()` off its host and registers only the Search API steps. A context that relied on that indirect composition has to compose `ContentTrait` itself.

`Steps\Drupal\ConfigOverrideTrait` set its `X-Config-No-Override` signal on `RestTrait`'s property when it found one. It writes to the header bag instead. The bag is per context, so a suite that wants the signal on `RestTrait`'s own requests composes both traits into one context rather than registering the two shipped ones; the browser header, the `$_SERVER` entry and the environment variable reach the site either way.

A helper trait composed by a step trait and by the context under it holds one slot of state, so both reach the same bag.

### The two `HelperTrait`s became 6 concern-named traits

`Steps\Web\HelperTrait` and `Steps\Drupal\HelperTrait` are gone. Their members live under `DrevOps\BehatSteps\Helper\Web` and `DrevOps\BehatSteps\Helper\Drupal`, each trait named for the one concern it holds, and every method carries its own trait's prefix in place of the shared `helper` one:

| Old member | New member |
| --- | --- |
| `helperSetLastStepLine()` | `Helper\Web\LastStepTrait::lastStepSetLine()` |
| `helperIsLastStep()` | `Helper\Web\LastStepTrait::lastStepReached()` |
| `$helperLastStepLine` | `Helper\Web\LastStepTrait::$lastStepLine` |
| `helperSetRequestHeader()` | `Helper\Web\RequestHeadersTrait::requestHeadersSet()` |
| `helperUnsetRequestHeader()` | `Helper\Web\RequestHeadersTrait::requestHeadersUnset()` |
| `helperGetRequestHeaders()` | `Helper\Web\RequestHeadersTrait::requestHeadersAll()` |
| `helperResetRequestHeaders()` | `Helper\Web\RequestHeadersTrait::requestHeadersReset()` |
| `$helperRequestHeaders` | `Helper\Web\RequestHeadersTrait::$requestHeaders` |
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
| `helperLoadNodeIds()` | `Helper\Drupal\QueryTrait::queryNodeIds()` |
| `helperAssertModuleEnabled()` | A `<prefix>Prerequisites()` declaration checked by `assertPrerequisites(__TRAIT__)`, as [A trait declares its prerequisites](#a-trait-declares-its-prerequisites) shows |

A context that composed a `HelperTrait` to reach one of these composes the trait holding it instead:

```php
// Before.
use DrevOps\BehatSteps\Steps\Web\HelperTrait;

// After.
use DrevOps\BehatSteps\Helper\Web\StringTrait;
```

Extending `WebRawContext` needs no `use` statement for `LastStepTrait`, `RequestHeadersTrait` or `StringTrait`, which it composes, and composing a step trait needs none for the Drupal helpers: the step trait already composes what it calls.

`requestHeadersSet()`, the two `tableTranspose*()` methods and the entity, authentication and query members a step calls are `public` and published in [HELPERS.md](HELPERS.md). Every other helper stays `protected`.

## Trait methods prefixed with their trait name

Every method a trait contributes now begins with the trait's own name, so that traits mixed into one context cannot collide. Rename any call or override in a consumer context:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\DraggableviewsTrait` | `draggableViewsSaveBundleOrder()` | `draggableviewsSaveBundleOrder()` |
| `Drupal\DraggableviewsTrait` | `draggableViewsFindNode()` | `draggableviewsFindNode()` |
| `Drupal\HelperTrait` | `entityRegister()` | `Helper\Drupal\EntityLifecycleTrait::entityLifecycleRegister()` |
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

A scenario that asserted a falsy parameter away with `Then the current URL should not have the query parameter "filter"` now needs to name the value it excludes:

```gherkin
Then the current URL should not have the query parameter "filter" with the value "recent"
```

## A value of `0` is not empty

A few checks read a string with `empty()`, which treats the string `0` as absent. They compare against the empty string now, so `0` is a value like any other: `Given the password for the user :name is "0"` sets the password instead of failing with `Password must not be empty.`, an attribute whose value is `0` counts as present for the `the element :selector with the attribute :attribute ...` steps, an iframe named `0` is switched to by name, a WYSIWYG field with the id `0` is filled through its id, and `fileCreateEntity()` honors a destination URI of `0`. A `drush` backend configured with an alias or root path of `0` is likewise read as configured.

## Email subject steps match the way they read

4 `Drupal\EmailTrait` steps pick an email by its subject. They matched it 2 different ways, and neither was what the step text says. `with the subject :subject` settled for the first email whose subject contained the text, after collapsing whitespace. `with a subject containing :partial_subject` ignored case.

Both now follow the grammar in [CONTRIBUTING.md](CONTRIBUTING.md#steps-format). `with the subject` names the whole subject, which is how `the number of emails sent with the subject :subject should be :count` already compared it. `a subject containing` matches part of it, case-sensitively, like every other `containing` step.

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
When I follow the link with the index "1" in the email with the subject "Verification"
Then the file "report.xlsx" should be attached to the email with a subject containing "monthly report"

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

## Page cache steps clear the paths they name

`Given the page cache for the path :path is empty` named 1 path but cleared every page. It invalidated the `http_response` cache tag, and Drupal puts that tag on every cacheable response, so the step emptied the whole internal page cache and the whole dynamic page cache. `Given the page cache for the paths matching :path_pattern is empty` matched its pattern anywhere in the cached URL, so `/news*` also cleared `/archive/news`, and a pattern with no `*` cleared every path that contained it.

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
Given the page cache for the path "/about" is empty

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
| An expected element, field, link or selector is missing | `Behat\Mink\Exception\ElementNotFoundException` (a subclass of `ExpectationException`) |
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
| `MetatagTrait` (all `Then` steps) | `\Exception` | `ExpectationException`; `ElementNotFoundException` when the meta tag itself is missing; `\RuntimeException` when an hreflang alternate page returns an HTTP error |
| `XmlTrait` (`the response should be in XML format`) | `\RuntimeException` | `ExpectationException` |
| `FieldTrait` (`the option ... should (not) exist within the select ...`) | `\InvalidArgumentException` | `ElementNotFoundException` for a missing select or a missing option, `ExpectationException` for an option that exists but should not |
| `Drupal\CacheTrait` (`the page cache for the path(s) ... is empty`) | `\InvalidArgumentException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...`) | `\InvalidArgumentException` | `\RuntimeException` |
| `TableTrait` (any table step, when the table or the row is missing) | `ExpectationException` | `ElementNotFoundException` |
| `ModalTrait` (`I close the modal`, `I click on the element ... in the modal`, `the modal should (not) contain ...`, when the close button, the content element or the target element is missing) | `ExpectationException` | `ElementNotFoundException` |
| `FieldTrait` (`I unselect the option ... from the select ...` and `the option ... should not be selected within the select ...`, when the option is missing; `I fill in the multi-value field ...`, when an input row is missing) | `ExpectationException` | `ElementNotFoundException` |
| `XmlTrait` (every `the XML element ...` and `the XML attribute ... on the element ...` step, when the element is missing) | `ExpectationException` | `ElementNotFoundException` |
| `JsonTrait` (an invalid JSONPath expression, an invalid regular expression, a count that is not an integer, a schema that is not JSON) | `ExpectationException` | `\RuntimeException` |
| `TableTrait` (`the table ... should be sorted by the column ... in ... order`, with a direction other than `ascending` or `descending`) | `ExpectationException` | `\RuntimeException` |
| `ElementTrait` (`... with the index ...`, with an index below 1; `... pinned to the top of the viewport within ... pixels`, with a negative tolerance) | `ExpectationException` | `\RuntimeException` |
| `Drupal\EmailTrait` (`I follow the link with the index ...`, with an index that is not a positive integer) | `ExpectationException` | `\RuntimeException` |
| `FieldTrait` (`I fill in the WYSIWYG field ...`, when the field has no `id` attribute) | `ExpectationException` | `\RuntimeException` |
| `XmlTrait` (`I print last XML response`, when the document cannot be serialized) | `ExpectationException` | `\RuntimeException` |
| `KeyboardTrait` (`I press the key(s) ...` without an element, when nothing has focus) | `ExpectationException` | `\RuntimeException` |
| `Drupal\BlockTrait` (every `Given the block ...` step, when the block does not exist) | `ExpectationException` | `\RuntimeException` |
| `WaitTrait` (`I wait for AJAX to finish` and `I wait for ... second(s) for AJAX to finish`, without a JavaScript driver) | `\RuntimeException` | `UnsupportedDriverActionException` |
| `FieldTrait` (`I fill in the multi-value field ...`, without a JavaScript driver) | `\RuntimeException` | `UnsupportedDriverActionException` |

14 failure messages changed along with their type:

| Step | Was | Now |
| --- | --- | --- |
| `the response should be in XML format` | `Failed to load XML. Errors: ...` | `The response is not valid XML: ...` |
| `the option :option should exist within the select :selector` | `Element "..." is not found.` / `Option "..." is not found in select "...".` | `Select with id\|name\|label "..." not found.` / `Option in the select "..." with value\|text "..." not found.` |
| `the option :option should not exist within the select :selector` | `Element "..." is not found.` / `Option "..." is found in select "...", but should not.` | `Select with id\|name\|label "..." not found.` / `The option "..." was found in the select "..." on the page ..., but it should not exist.` |
| `I unselect the option :option from the select :selector` | `The option "..." was not found in the select "...".` | `Option in the select "..." with value\|text "..." not found.` |
| `the option :option should not be selected within the select :selector` | `The option "..." was not found in the select "..." on the page ....` | `Option in the select "..." with value\|text "..." not found.` |
| `I fill in the multi-value field :field with the following values:` | `Could not locate input row N for multi-value field "...".` | `Input row of the multi-value field "..." with index "N" not found.` |
| every `the table ...` step, when the table is missing | `Table with selector "..." not found.` | `Table matching css "..." not found.` |
| every `... the row ...` step, when the row is missing | `Table row containing text "..." not found.` | `Table row with text "..." not found.` |
| `I close the modal` | `The modal close button was not found.` | `Modal close button matching css "..." not found.` |
| `I click on the element :selector in the modal` | `The element "..." was not found in the modal.` | `Element in the modal with css\|id\|name\|title\|alt\|value\|text "..." not found.` |
| `the modal should (not) contain :text` | `The modal content element was not found.` | `Modal content element matching css "..." not found.` |
| every `the XML element ...` and `the XML attribute ... on the element ...` step, when the element is missing | `The XML element "..." was not found.` | `XML element matching xpath "..." not found.` |
| `the meta tag should exist with the following attributes:` | `Meta tag with specified attributes was not found: {...}.` | `Meta tag with attributes "{...}" not found.` |
| `the meta tag :name should not contain any HTML tags` | `Meta tag with name or property "..." not found.` | `Meta tag with name\|property "..." not found.` |

The same rule now covers the backend layer and the Behat services under `src/Behat`, which used to throw `\InvalidArgumentException` and plain `\Exception` for an invalid argument or an unmet prerequisite. If your project calls a backend or one of those services directly and catches on the type, update it:

| Class | Was | Now |
| --- | --- | --- |
| `Backend\Core\Core` (an unknown entity type, bundle, vocabulary, user, language, severity or handler class) | `\InvalidArgumentException` / `\Exception` | `\RuntimeException` |
| `Backend\Core\Field\*Handler` (a malformed field value, an unreadable file, a missing referenced entity) | `\InvalidArgumentException` / `\Exception` | `\RuntimeException` |
| `Behat\Registry\BackendRegistry::getBackend()` and `setScenarioBackends()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Behat\Registry\UserRegistry::getUser()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Behat\Selector\RegionSelector::translateToXPath()` | `\InvalidArgumentException` | `\RuntimeException` |
| `Backend\Exception\CreationAliasResolutionException` | extends `\InvalidArgumentException` | extends `Backend\Exception\Exception` |

`CreationAliasResolutionException` is no longer a `\LogicException`, so a `catch (\InvalidArgumentException)` or `catch (\LogicException)` no longer catches it; catch the class itself.

Behat reports every one of these as a failed step either way, so a scenario that simply runs to a failure behaves the same. Only code that catches a specific type, or asserts on the message text, needs changing.

## Failure messages read one way

A failure message quotes the values it names in double quotes, ends with a period, and reports something present that must be absent with `, but it should not`. The messages below changed wording only, so the exception a step throws is the same as the row above says; only a test asserting on the text needs the new one. Rows were checked against 3.14.4: a message introduced in 4.x is not listed.

| Trait | Before | After |
| --- | --- | --- |
| Drupal\BlockTrait | The block "..." exists but should not. | The block "..." exists, but it should not. |
| Drupal\BlockTrait | Block "..." is in region "..." but should not be. | Block "..." is in region "...", but it should not be. |
| Drupal\ConfigTrait | The config "..." key "..." has the ... "...", which contains "..." but should not. | The config "..." key "..." has the ... "...", which contains "...", but it should not. |
| Drupal\FileTrait | File contents "..." contains "...", but should not. | File contents "..." contains "...", but it should not. |
| LinkTrait | The link href "..." matches the specified href "..." but should not. | The link href "..." matches the specified href "...", but it should not. |
| LinkTrait | The link with the title "..." exists, but should not. | The link with the title "..." exists, but it should not. |
| ElementTrait | Element defined by "..." selector is visible on the page, but should not be. | Element defined by "..." selector is visible on the page, but it should not be. |
| ElementTrait | Element(s) defined by "..." selector is displayed within a viewport with a top offset of N pixels, but should not be. | Element(s) defined by "..." selector is displayed within the viewport with a top offset of N pixels, but it should not be. |
| ElementTrait | Element(s) defined by "..." selector is displayed within a viewport, but should not be. | Element(s) defined by "..." selector is displayed within the viewport, but it should not be. |
| FieldTrait | The field "..." is empty, but should not be. | The field "..." is empty, but it should not be. |
| FieldTrait | The field "..." is marked as required, but should not be. | The field "..." is marked as required, but it should not be. |
| FieldTrait | The option "..." was selected in the select "..." on the page ..., but should not be. | The option "..." was selected in the select "..." on the page ..., but it should not be. |
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

Neither method is a step or a hook, and both were only ever called from step methods in their own trait. Calling them from outside the context object no longer works. From inside it, `keyboardPressKeyOnElementSingle()` is unchanged and `fileDownloadAssertLinkPresent()` is now `fileDownloadGetLink()`, listed under [A lookup's verb says what a miss does](#a-lookups-verb-says-what-a-miss-does).

| Method | Was | Now |
| --- | --- | --- |
| `KeyboardTrait::keyboardPressKeyOnElementSingle()` | `public` | `protected` |
| `FileDownloadTrait::fileDownloadAssertLinkPresent()` | `public` | `protected` |

`DateTrait::dateRelativeProcessValue()` and `ResponsiveTrait::responsiveSetBreakpoints()` stay public and are now documented as API in their docblocks. `DateTrait` also stays static on purpose: `dateGetNow()` is the supported seam for pinning the clock, and overriding it in your `FeatureContext` works as before under that name, which [Consumer override points are `Get`-prefixed](#consumer-override-points-are-get-prefixed) lists against the old `dateNow()`.

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
| `AccessibilityTrait::accessibilityAfterSuite()` | `(AfterSuiteScope $scope)` |
| `AccessibilityTrait::accessibilityBeforeSuite()` | `(BeforeSuiteScope $scope)` |
| `CommandTrait::commandAfterScenario()` | `(AfterScenarioScope $scope)` |
| `CommandTrait::commandBeforeScenario()` | `(BeforeScenarioScope $scope)` |
| `Drupal\BigPipeTrait::bigPipeBeforeStep()` | `(BeforeStepScope $scope)` |
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

## Relative-date transform placeholder renamed

`DateTrait` registers its relative-date transform against the placeholder names a step argument can carry. One of those names was camel case, which no other placeholder in the library uses and which the snake case placeholder rule forbids.

| Before | After |
| --- | --- |
| `#[Transform(':expectedValue')]` | `#[Transform(':expected_value')]` |

No step shipped by this library declares `:expectedValue`, so the shipped vocabulary is unaffected. A project whose own step declares an `:expectedValue` argument and relies on `[relative:...]` tokens being expanded in it renames that argument to `:expected_value`. The `:datetime` and `:value` placeholders are unchanged.

## One shape per naming idea

Method names carried 6 shapes for "assert the negative", 2 spellings of "normalize" and 2 of "log in", 2 shapes for a consumer override point, 3 lookup verbs that didn't say what a lookup does when nothing matches, 2 word orders for a method that creates an entity, 3 shapes for a method acting on several entities, and assertions that put a qualifier ahead of their predicate, used `Has`, `Includes` or `Present` where the rules say `Equals`, `Contains` or `Exists`, or weren't named as assertions at all. They are members a consumer calls, overrides or implements, so each is renamed rather than aliased. Gherkin step text, step parameter names and method bodies are unchanged, so no `.feature` file needs an edit.

`CONTRIBUTING.md` states the settled conventions, and `tests/phpunit/src/TraitMethodNamingTest.php` and `tests/phpunit/src/CapabilityMethodNamingTest.php` enforce them.

### Negation is spelled `Not`, in one slot

`Not` sits immediately after `Assert<Subject>`, directly before the predicate it negates, so a negative name is its positive counterpart with `Not` inserted and nothing else changed. The determiner `No`, the copula `Is`, and antonyms standing in for a negation are gone.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\EmailTrait` | `emailAssertNoMessagesSent()` | `emailAssertMessagesNotSent()` |
| `Drupal\EmailTrait` | `emailAssertNoMessagesSentToAddress()` | `emailAssertMessagesNotSentToAddress()` |
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

`ElementTrait::elementAssertPinnedToTop()` appears on both sides of that table. The public step took the name once its copula was dropped, and the protected helper that backs all three pinned-to-top steps moved to `elementAssertPinnedToTopWithin()`, after the tolerance it takes.

The file, watchdog, JavaScript and path rows point straight at the names [`Has` names something the subject holds](#has-names-something-the-subject-holds) settles on.

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

### `Login` and `Logout`, not `LogIn` and `LogOut`

Logging in and out is spelled as 1 word in every name, as `authLogin()`, `FastLogoutInterface` and the `login_url` key already spelled it. Step text keeps the verb, so `When I log in as the user :name` is unchanged.

| Where | Old | New |
| --- | --- | --- |
| `Behat\Auth\AuthenticatorInterface` | `logIn()` | `login()` |
| `Behat\Auth\AuthenticatorInterface` | `logOut()` | `logout()` |
| `Behat\Auth\AuthenticatorInterface` | `loggedIn()` | `isLoggedIn()` |
| `Drupal\UserTrait` | `userCreateAndLogIn()` | `userCreateAndLogin()` |
| `Drupal\UserTrait` | `userLogInAs()` | `userLoginAs()` |
| `Drupal\UserTrait` | `userLogInWithPermissions()` | `userLoginWithPermissions()` |
| `Drupal\UserTrait` | `userLogInWithRoles()` | `userLoginWithRoles()` |
| `Drupal\UserTrait` | `userLogInWithRolesAndFields()` | `userLoginWithRolesAndFields()` |
| `Drupal\UserTrait` | `userLogOut()` | `userLogout()` |
| `Drupal\UserTrait` | `userLogOutSession()` | `userLogoutSession()` |
| `BehatStepsExtension` | `text: log_in:` | `text: login:` |
| `BehatStepsExtension` | `text: log_out:` | `text: logout:` |

PHP matches method names without regard to case, so only 3 rows need an edit. A custom authenticator that declares `loggedIn()` fails to load until it declares `isLoggedIn()`, and a `log_in` or `log_out` key under `text` fails the container build with a message naming its replacement. The other rows change case only, so existing calls and overrides keep working.

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

### A lookup's verb says what a miss does

`Find`, `Load` and `Get` each named some lookups that return `NULL` when nothing matches and others that throw, sometimes in the same trait: `TableTrait` had a `tableFind()` that threw beside a `tableFindRowByText()` that returned `NULL`. The verb now carries the contract. A `Find` returns `NULL`, a `Get` throws and never returns `NULL`, and a `Load` loads a set, so no lookup for 1 item is named `Load`.

Only the name changes. Each method keeps its body, its parameters, its return type and the exceptions it throws.

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
| `FileDownloadTrait` | `fileDownloadAssertLinkPresent()` (protected) | `fileDownloadGetLink()` | throws `ElementNotFoundException` |
| `MetatagTrait` | `metatagGetCanonicalHref()` | `metatagFindCanonicalHref()` | returns `NULL` |
| `MetatagTrait` | `metatagGetMetaContent()` | `metatagFindMetaContent()` | returns `NULL` |
| `ModalTrait` | `modalFindVisible()` | `modalGetVisible()` | throws `ExpectationException` |
| `TableTrait` | `tableFind()` | `tableGet()` | throws `ElementNotFoundException` |

The 2 `MenuTrait` lookups go straight to their `Find` names, listed under [Trait methods prefixed with their trait name](#trait-methods-prefixed-with-their-trait-name). A lookup that already matched its contract keeps its name, such as `tableFindRowByText()`, `modalFind()`, `metatagFindMeta()` and `emailFindMessage()`.

`webformTemplates()` carried no verb at all, and `fileDownloadAssertLinkPresent()` was named as an assertion although it returns the link it finds, so both take the lookup verb for what they do.

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

The subject is what the step asserts about. `ElementTrait`'s attribute steps assert that an element exists, so the attribute and its value join the qualifier, and `LinkTrait` drops `Text`, which named how the step finds the link: `the link :link with the href :href should exist` is `linkAssertExistsWithHref()`. `the row :row_text should contain the following:` asserts about the row, so `TableTrait` names it first.

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

An assertion says what it asserts after its subject: a compared value reads `Equals`, a set that must be present reads `Exist`, and validity reads `Valid` after the subject, as `commandAssertOutputEquals()` and `metatagAssertHreflangValid()` do. 7 assertions named no predicate or put `Valid` ahead of the subject. Step text is unchanged.

| Trait | Old | New |
| --- | --- | --- |
| `CommandTrait` | `commandAssertExitCode()` | `commandAssertExitCodeEquals()` |
| `FileDownloadTrait` | `fileDownloadAssertFileName()` | `fileDownloadAssertFileNameEquals()` |
| `MetatagTrait` | `metatagAssertOpenGraphTags()` | `metatagAssertOpenGraphTagsExist()` |
| `MetatagTrait` | `metatagAssertTwitterCardTags()` | `metatagAssertTwitterCardTagsExist()` |
| `RestTrait` | `restAssertResponseStatusCode()` | `restAssertResponseStatusCodeEquals()` |
| `XmlTrait` | `xmlAssertValidRssFeed()` | `xmlAssertRssFeedValid()` |
| `XmlTrait` | `xmlAssertValidAtomFeed()` | `xmlAssertAtomFeedValid()` |

### Only an assertion is named `Assert`

A method that fails with an assertion exception is named as an assertion, whether or not it registers a step. A method that only rejects a bad step argument or a missing precondition throws `\RuntimeException` instead, so it isn't an assertion and is named for what it does.

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\ConfigTrait` | `configCompareContains()` (protected) | `configAssertContains()` |
| `Drupal\ConfigTrait` | `configCompareEquals()` (protected) | `configAssertEquals()` |
| `CommandTrait` | `commandAssertHasRun()` (protected) | `commandRequireRun()` |
| `CookieTrait` | `cookieExists()` | `cookieAssertExists()` |
| `CookieTrait` | `cookieNotExists()` | `cookieAssertNotExists()` |

The protected `Drupal\EmailTrait::emailAssertLinkNumber()`, `CommandTrait::commandAssertInteger()` and `CommandTrait::commandAssertNumeric()` are gone rather than renamed. A step parses its number with `StringTrait::stringParseInteger()` or `StringTrait::stringParseNumber()` instead, as [A step method takes only what its step binds](#a-step-method-takes-only-what-its-step-binds) describes.

### A hook is named for its event

A hook method reads `<prefix><Event>`, so `configBeforeScenario()` and `contentBeforeNodeCreate()` already told the reader when they run. The hooks that were named for what they do take the same shape. A skip tag names a trait, not a hook, so no tag changes.

| Trait | Old | New |
| --- | --- | --- |
| Drupal\TimeTrait | timeCleanup() | timeAfterScenario() |
| Drupal\WatchdogTrait | watchdogSetScenario() | watchdogBeforeScenario() |
| Drupal\BigPipeTrait | bigPipeWaitBeforeStep() | bigPipeBeforeStep() |
| AccessibilityTrait | accessibilitySetupScenario() | accessibilityBeforeScenario() |
| AccessibilityTrait | accessibilityAutoAssess() | accessibilityAfterStep() |
| AccessibilityTrait | accessibilityFinalizeScenario() | accessibilityAfterScenario() |
| AccessibilityTrait | accessibilityAggregateRender() | accessibilityAfterSuite() |

`AccessibilityTrait` registered 2 `BeforeSuite` hooks, so neither could take the event's name. `accessibilityBeforeSuite()` is the hook now, and it runs `accessibilityCaptureBaseDir()` and then `accessibilityAggregateReset()`. Both keep their 3.x names and their parameterless signatures, but they aren't hooks anymore and they're protected. An override drops the `#[BeforeSuite]` attribute it copied from 3.x, or it runs twice.

### A bundle parameter is named after its entity type

A helper that takes a bundle names the parameter after the entity type, as the step placeholders do. 2 `Drupal\ContentBlockTrait` helpers took `$type`; a call that passes the argument by name renames it.

| Method | Before | After |
| --- | --- | --- |
| `contentBlockCreate()` | `string $type, array $values` | `string $content_block_type, array $values` |
| `contentBlockLoadMultiple()` | `string $type, array $conditions = []` | `string $content_block_type, array $conditions = []` |

`contentBlockCreate()` is the 1-entity helper that was `contentBlockCreateSingle()`, renamed under [A method acting on several entities ends in `Multiple`](#a-method-acting-on-several-entities-ends-in-multiple).

### A create or delete method names the verb first

A method that created an entity put the verb and the noun in either order. The step traits read verb-first, as `contentCreateWithFields()` did, while the entity lifecycle helper, `EckTrait` and every backend capability read noun-first, as `entityLifecycleNodeCreate()` and `nodeCreate()` did. The verb now comes first everywhere: a trait method puts it right after its prefix, and a capability method opens with it.

| Trait | Old | New |
| --- | --- | --- |
| `Helper\Drupal\EntityLifecycleTrait` | `entityLifecycleNodeCreate()` | `entityLifecycleCreateNode()` |
| `Helper\Drupal\EntityLifecycleTrait` | `entityLifecycleTermCreate()` | `entityLifecycleCreateTerm()` |
| `Helper\Drupal\EntityLifecycleTrait` | `entityLifecycleLanguageCreate()` | `entityLifecycleCreateLanguage()` |
| `Helper\Drupal\AuthTrait` | `authUserCreate()` | `authCreateUser()` |
| `Drupal\EckTrait` | `eckEntitiesCreate()` | `eckCreateMultiple()` |
| `Drupal\MenuTrait` | `menuLinksCreate()` | `menuCreateLinkMultiple()` |
| `Drupal\MenuTrait` | `menuLinksDelete()` | `menuDeleteLinkMultiple()` |

`entityLifecycleCreate()` already read verb-first and is unchanged. The 3 steps at the bottom also act on several entities, so they take the `Multiple` the next section describes. The `RawContext` rows in [The Drupal lifecycle moved into concern-named helpers](#the-drupal-lifecycle-moved-into-concern-named-helpers) point straight at the new names.

The verb that deletes is `Delete`. 3 `does not exist` steps said `Remove` instead, and `ContentTrait` repeated the noun its prefix already carries:

| Trait | Old | New |
| --- | --- | --- |
| `Drupal\BlockTrait` | `blockRemove()` | `blockDelete()` |
| `Drupal\ContentTrait` | `contentRemoveContentType()` | `contentDeleteType()` |
| `Drupal\MediaTrait` | `mediaRemoveType()` | `mediaDeleteType()` |

A capability interface that creates an entity now reads one way, so its delete, place and role methods move with its create methods. A backend of your own renames the methods it implements; `DrupalBackend`, `DrushBackend` and `Core` already have.

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

The config, state, module, mail, cache and cron capabilities keep their names. So do the entity-create hooks, because a hook is named for its event: `BeforeNodeCreate`, `AfterTermCreate` and the rest are unchanged.

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
| `Drupal\ContentTrait` | `contentCreate()` | `contentCreateMultiple()` |
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
| `Drupal\TaxonomyTrait` | `taxonomyCreate()` | `taxonomyCreateMultiple()` |
| `Drupal\TaxonomyTrait` | `taxonomyCreateWithFields()` | `taxonomyCreateMultipleWithFields()` |
| `Drupal\TaxonomyTrait` | `taxonomyDeleteTerms()` | `taxonomyDeleteMultiple()` |
| `Drupal\UserTrait` | `userCreateWithFields()` | `userCreateMultipleWithFields()` |
| `Drupal\UserTrait` | `userCreateRoles()` | `userCreateRoleMultiple()` |
| `Drupal\UserTrait` | `userDelete()` | `userDeleteMultiple()` |
| `Drupal\WebformTrait` | `webformLoadAll()` | `webformLoadMultiple()` |
| `Drupal\WebformTrait` | `webformLoadTemplates()` | `webformLoadTemplateMultiple()` |

`mediaCreate()`, `contentBlockCreate()` and `fileCreateManaged()` appear on both sides of that table. The step over a table took the `Multiple` name, and the 1-entity helper beside it took the name the step freed. Their parameters differ, so a call left on the old name fails with a `TypeError` rather than reaching the wrong method quietly.

`webformLoadTemplates()` shipped in v3 as `webformTemplates()`, so its row under [A lookup's verb says what a miss does](#a-lookups-verb-says-what-a-miss-does) maps that name straight to `webformLoadTemplateMultiple()`.

`ContentTrait`, `TaxonomyTrait`, `UserTrait`, `LanguageTrait` and `EntityTrait` had no 1-entity helper to rename: a single node, term, user, language or other entity goes through `entityLifecycleCreateNode()`, `entityLifecycleCreateTerm()`, `authCreateUser()`, `entityLifecycleCreateLanguage()` or `entityLifecycleCreate()`. `userCreateMultiple()`, `languageCreateMultiple()` and `entityCreateMultiple()` already carried the suffix and are unchanged.

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

`Helper\Drupal\AuthTrait::authLogout()`, which replaces `RawContext::logout()`, takes `$is_fast` where `logout()` took `$fast`.

### A method is named for what it does, not `Helper`

The protected `FieldTrait::fieldFillDatetimeHelper()` is `fieldFillDatetimeInput()`, after the 1 input of a datetime field it fills. A context that overrides it renames the override.

### `XmlTrait` names its content steps as `JsonTrait` does

`XmlTrait` and `JsonTrait` register the same 2 `Given` steps, 1 loading the response body from a fixture file and 1 taking it from a PyString. `JsonTrait` names them `jsonSetContentFromFile()` and `jsonSetContent()`, while `XmlTrait` added `Response`, which every step in the trait acts on, and `Direct`, which names nothing in its step. Step text is unchanged.

| Trait | Old | New |
| --- | --- | --- |
| `XmlTrait` | `xmlSetResponseContentFromFile()` | `xmlSetContentFromFile()` |
| `XmlTrait` | `xmlSetResponseContentDirect()` | `xmlSetContent()` |

## A class is named for the role it plays

5 classes under `Behat\Manager` shared a `Manager` suffix while playing 3 different roles, so nothing in a name told a lookup table apart from a service that acts. The suffix is replaced by a 2-part rule: a `*Registry` holds things and looks them up, and anything that performs an action takes an agent noun.

| Old | Role | New |
| --- | --- | --- |
| `Behat\Manager\DriverManager` | registers backends, resolves one by capability, tracks the scenario's order | `Behat\Registry\BackendRegistry` |
| `Behat\Manager\UserManager` | stores the users a scenario created, tracks the current one | `Behat\Registry\UserRegistry` |
| `Behat\Manager\AuthenticationManager` | logs a user in and out, holds a Drupal session | `Behat\Auth\Authenticator` |
| `Behat\Manager\BasicAuthManager` | derives credentials from `base_url`, applies them to Mink | `Behat\Auth\BasicAuthenticator` |

Each interface travels with its class:

| Old | New |
| --- | --- |
| `DriverManagerInterface` | `BackendRegistryInterface` |
| `UserManagerInterface` | `UserRegistryInterface` |
| `AuthenticationManagerInterface` | `AuthenticatorInterface` |
| `BasicAuthInterface` | `BasicAuthenticatorInterface` |

`FastLogoutInterface` keeps its name. It describes a capability rather than a role.

A context implements `BackendAwareInterface` or `UserAwareInterface`, so the 8 accessors those interfaces declare are renamed too. A project that composes `AuthTrait` or extends any shipped context inherits the new names for free; one that calls them from its own step definitions renames the calls.

| Interface | Old | New |
| --- | --- | --- |
| `BackendAwareInterface` | `setDriverManager()` | `setBackendRegistry()` |
| `BackendAwareInterface` | `getDriverManager()` | `getBackendRegistry()` |
| `BackendAwareInterface` | `setBasicAuthManager()` | `setBasicAuthenticator()` |
| `BackendAwareInterface` | `getBasicAuthManager()` | `getBasicAuthenticator()` |
| `UserAwareInterface` | `authSetUserManager()` | `authSetUserRegistry()` |
| `UserAwareInterface` | `authGetUserManager()` | `authGetUserRegistry()` |
| `UserAwareInterface` | `authSetManager()` | `authSetAuthenticator()` |
| `UserAwareInterface` | `authGetManager()` | `authGetAuthenticator()` |

The service ids and the `*.class` parameters that let a suite swap an implementation follow the classes.

| Old | New |
| --- | --- |
| `behat_steps.driver_manager` | `behat_steps.backend_registry` |
| `behat_steps.user_manager` | `behat_steps.user_registry` |
| `behat_steps.authentication_manager` | `behat_steps.authenticator` |
| `behat_steps.basic_auth_manager` | `behat_steps.basic_authenticator` |

### `Behat\Manager` split into `Behat\Registry` and `Behat\Auth`

The class renames left `Manager` in 1 place: the namespace the classes shared, which named no role any of them plays. The 3 registries now live in `Behat\Registry`, and the 2 authenticators and `FastLogoutInterface` in `Behat\Auth`. Every class keeps its own name, so the namespace is the whole change:

```php
// Before.
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;

// After.
use DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface;
```

| Old | New |
| --- | --- |
| `Behat\Manager\BackendRegistry` | `Behat\Registry\BackendRegistry` |
| `Behat\Manager\BackendRegistryInterface` | `Behat\Registry\BackendRegistryInterface` |
| `Behat\Manager\UserRegistry` | `Behat\Registry\UserRegistry` |
| `Behat\Manager\UserRegistryInterface` | `Behat\Registry\UserRegistryInterface` |
| `Behat\Manager\ScenarioTagRegistry` | `Behat\Registry\ScenarioTagRegistry` |
| `Behat\Manager\ScenarioTagRegistryInterface` | `Behat\Registry\ScenarioTagRegistryInterface` |
| `Behat\Manager\Authenticator` | `Behat\Auth\Authenticator` |
| `Behat\Manager\AuthenticatorInterface` | `Behat\Auth\AuthenticatorInterface` |
| `Behat\Manager\BasicAuthenticator` | `Behat\Auth\BasicAuthenticator` |
| `Behat\Manager\BasicAuthenticatorInterface` | `Behat\Auth\BasicAuthenticatorInterface` |
| `Behat\Manager\FastLogoutInterface` | `Behat\Auth\FastLogoutInterface` |

A context that composes `AuthTrait` or extends a shipped context picks up the new types for free. One that implements `BackendAwareInterface` or `UserAwareInterface` by hand names these types in its accessor signatures, and PHP refuses to load it until they match the interface again.

The service ids and their `*.class` parameters keep their names; only the default class each parameter holds moves. A suite that swaps in its own registry or authenticator through one of those parameters keeps the parameter as it is and updates the imports in its own class.

`grep -rnE 'BehatSteps\\+Behat\\+Manager' <your project>` lists every import, docblock type and container parameter that still names the old namespace.

### `MailManager` is gone

`MailManager` forwarded to `MailCapabilityInterface` and added nothing: `stopCollectingMail()` called `mailStopCollecting()`, `getMail()` called `mailGet()`, `clearMail()` called `mailClear()`, and `startCollectingMail()` called `mailStartCollecting()` and then cleared. Nothing registered it as a service, and `EmailTrait` resolves `CoreCapabilityInterface` directly rather than asking for the mail capability at all, which is why the class was never wired.

That is the general rule, not a one-off: a class that only wraps a capability interface is not written, because the capability interface already is the abstraction. A trait reaches a capability through `backendFor(SomeCapabilityInterface::class)` on `WebRawContext`.

A project that constructed `MailManager` itself calls the capability instead:

```php
// Before.
$mail = new MailManager($backend);
$mail->startCollectingMail();
$messages = $mail->getMail();

// After.
$backend = $this->backendFor(MailCapabilityInterface::class);
$backend->mailStartCollecting();
$backend->mailClear();
$messages = $backend->mailGet();
```

`MailManagerInterface` is removed with the class.

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

### `JavascriptSupportTrait` is gone

`javascriptSupportAvailable()` probed the browser driver by evaluating `true` in the browser, so every call paid a round trip to answer a question that cannot change during a scenario. A custom step that called it asks the capability instead:

```php
// Before.
if (!$this->javascriptSupportAvailable()) {
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

`CookieTrait`, `DropzoneTrait`, `IframeTrait` and `KeyboardTrait` resolve a browser capability, so they need `WebRawContext` and no longer compose onto Mink's own `RawMinkContext`. A context composing one of them extends `DrevOps\BehatSteps\Behat\Context\WebRawContext`. `JsonTrait`, `LinkTrait`, `PathTrait`, `RegionTrait`, `ResponseTrait`, `ResponsiveTrait` and `XmlTrait` still run on the bare Mink context. `MetatagTrait` needs `WebRawContext` too, for its HTTP client; see [Steps send their own requests through 3 HTTP clients](#steps-send-their-own-requests-through-3-http-clients).

### Three steps now fail naming the capability

`I wait for the modal to appear`, `I drop the following files on the dropzone :selector:` and `I switch to the iframe :selector` performed work only a browser can do without checking for one first. Each now raises `UnsupportedDriverActionException` naming the capability. The modal step is the visible improvement: it used to spend its whole `wait_timeout` and then report that the modal had not appeared.

`I press the key ...` and `I wait for :seconds second(s) for AJAX to finish` still raise on a browser driver that cannot serve them, with the capability named in place of a hardcoded list of browser drivers.

### Registering an adapter for another browser driver

```php
$this->getBrowserCapabilityResolver()->registerAdapter(AcmeDriverAdapter::class);
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

3 signatures change for code that implements or calls them directly:

- `HttpClientCapabilityInterface::httpClient()` declares `AbstractBrowser` in place of `object`, so an adapter implementing it narrows its return type to match.
- `RestTrait::restGetClient()` returns the page client, the same browser as before.
- `BasicAuthenticatorInterface` gains `findCredentials()`, which returns the credentials the `base_url` carries. It takes over from the protected `resolveBasicAuth()` in `BasicAuthenticator`, and a class implementing the interface adds it.

`guzzlehttp/guzzle` and `webflo/drupal-finder` are no longer dependencies, while `symfony/browser-kit`, `symfony/http-client` and `symfony/mime` are. A project that calls Guzzle directly requires it itself.

## Drupal capabilities cover the config, module and state steps

The config, module and state steps resolved `CoreCapabilityInterface` - "bootstrap Drupal in this process" - and then reached into the container, so they ran only on the in-process backend. They now name the capability they need and run on any backend providing it, including Drush.

A project with its own backend implementing these capabilities adds the methods below. A project using only the shipped backends needs no change.

| Interface | Added |
| --- | --- |
| `ConfigCapabilityInterface` | `configExists()`, `configGetData()`, `configSetData()`, `configDelete()` |
| `ModuleCapabilityInterface` | `moduleIsEnabled()`, `moduleIsPresent()` |
| `StateCapabilityInterface` | New: `stateGet()`, `stateSet()`, `stateDelete()`, `stateExists()` |

### The Drush backend stores config values correctly

`DrushBackend::configSet()` asked Drush for `--input-format=json`, which `drush config:set` does not parse - only `yaml` does. Every value the backend wrote was stored as its own JSON encoding, so a string landed with its quotes around it and an array landed as a JSON string rather than an array. A project that seeded config through the Drush backend and worked around the mangled values can drop the workaround.

Two smaller corrections come with it. A keyed `configGet()` returned Drush's `{"<name>:<key>": value}` envelope instead of the value. And `configGetOriginal()` was the same call as `configGet()`, so the stored and effective reads the config steps distinguish collapsed into one; the effective read now passes `--include-overridden` and the stored read does not.

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

The shipped backends keep the delete contract throughout. The Drush backend's `deleteRole()` and `deleteUser()` no longer fail for a role or user that's already gone, and the in-process `deleteUser()` no longer reports "The user account ... does not exist." for one.

## Every `LoadMultiple()` returns loaded entities

5 of the 6 `<trait>LoadMultiple()` helpers returned entity IDs, while `userLoadMultiple()` returned loaded users. You couldn't tell from 1 signature what the next would hand back. All 6 now return the loaded entities keyed by entity ID, or an empty array when nothing matches. `userLoadMultiple()` already worked this way, so it's unchanged.

`webformLoadMultiple()` and `webformLoadTemplateMultiple()` joined the family when [A method acting on several entities ends in `Multiple`](#a-method-acting-on-several-entities-ends-in-multiple) renamed them. They always returned loaded webforms keyed by ID, so only their names changed.

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

Replace each hook-method tag with its trait's:

| Tag | Replacement |
| --- | --- |
| `@behat-steps-skip:authCleanRoles` | `@behat-steps-skip:AuthTrait` |
| `@behat-steps-skip:authCleanUsers` | `@behat-steps-skip:AuthTrait` |
| `@behat-steps-skip:basicAuthBeforeScenario` | `@behat-steps-skip:BasicAuthTrait` |
| `@behat-steps-skip:cleanEntities` | `@behat-steps-skip:EntityLifecycleTrait` |
| `@behat-steps-skip:cleanRoles` | `@behat-steps-skip:AuthTrait` |
| `@behat-steps-skip:cleanUsers` | `@behat-steps-skip:AuthTrait` |
| `@behat-steps-skip:configAfterScenario` | `@behat-steps-skip:ConfigTrait` |
| `@behat-steps-skip:configOverrideBeforeScenario` | `@behat-steps-skip:ConfigOverrideTrait` |
| `@behat-steps-skip:configOverrideBeforeStep` | `@behat-steps-skip:ConfigOverrideTrait` |
| `@behat-steps-skip:emailAfterScenario` | `@behat-steps-skip:EmailTrait` |
| `@behat-steps-skip:emailBeforeScenario` | `@behat-steps-skip:EmailTrait` |
| `@behat-steps-skip:entityCleanupAfterScenario` | `@behat-steps-skip:EntityLifecycleTrait` |
| `@behat-steps-skip:entityLifecycleCleanAll` | `@behat-steps-skip:EntityLifecycleTrait` |
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

The per-type cleanup tags listed under [Unified entity cleanup](#unified-entity-cleanup), such as `@behat-steps-skip:mediaAfterScenario`, fail the same way. Their replacements are unchanged.

### A tag now reaches every hook of its trait

For most traits the replacement does exactly what the old tag did, because the trait has one hook to switch off or both of its tags had the same effect: `ConfigTrait`, `ConfigOverrideTrait`, `QueueTrait`, `RestTrait`, `StateTrait`, `TimeTrait` and `WatchdogTrait`. The rest reach further than a single hook tag did:

- A tag that skipped a trait's teardown alone now skips its setup as well. `@behat-steps-skip:EmailTrait` keeps an `@email` scenario from enabling the test email system, not only from disabling it afterwards. A scenario that wants the collector without the teardown enables it itself with `When I enable the test email system`. `FileTrait` (the private and temporary directories), `FileDownloadTrait` (the download directory), `ModuleTrait` (the `@module:` tags) and `TestmodeTrait` (the `@testmode` tag) work the same way.
- `@behat-steps-skip:AuthTrait` keeps both the users and the roles a scenario created, where the 2 were separate tags.

### `I enable the test email system` works without `@email`

The step enabled nothing in a scenario without an `@email` tag, because only the hook reading that tag named a handler. It now falls back to the `default` handler, as a bare `@email` does, and the collector it enables is disabled once the scenario finishes, unless `@behat-steps-skip:EmailTrait` switches the hooks off.

### A guard of your own names its trait

A trait of your own that guards a hook with `skipTag()` passes `__TRAIT__`, which resolves to the trait the code is written in:

```php
// Before.
if ($this->skipTag(__FUNCTION__, $scope)) {
  return;
}

// After.
if ($this->skipTag(__TRAIT__, $scope)) {
  return;
}
```

`TraitOptionResolverInterface::groupFor()` resolves a trait name only, so a resolver of your own drops any matching of hook-method names against a group's prefix.

## Tags on the `Feature:` line apply to every scenario

7 traits read their tags from the scenario alone, so a tag on the `Feature:` line switched some traits on and did nothing for others. `@javascript` there turned on `WaitTrait`'s waits, while `@email` there left `EmailTrait`'s collector off. Every hook now reads the scenario's tags together with its feature's, the way Behat's own tag filters, the `@behat-steps-skip:` tags and the option tags already did.

| Trait | Tags now read on the `Feature:` line |
| --- | --- |
| `Drupal\EmailTrait` | `@email`, `@email:TYPE`, `@debug` |
| `Drupal\ModuleTrait` | `@module:NAME`, `@module:!NAME` |
| `Drupal\TestmodeTrait` | `@testmode` |
| `Drupal\WatchdogTrait` | `@watchdog:TYPE` |
| `Web\FieldTrait` | `@disable-form-validation` |
| `Web\FileDownloadTrait` | `@download` |
| `Web\ResponsiveTrait` | `@breakpoint:NAME`, and the `@javascript` it requires |

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

Mink owns the word "driver" across the Behat ecosystem, and this package used it for a second thing: the Drupal, Drush and Blackbox backends a step resolves a capability from. So `$this->getDriver('drupal')` and `$this->getSession()->getDriver()` returned 2 unrelated objects, and only a naming rule told them apart. The backends now carry their own name, and "driver" in this package only ever means Mink's browser driver.

Apart from 1 removed interface, it's a rename: behavior stays the same, and no step text changes. Configuration and feature files fail until they're renamed, and PHP that calls a renamed class or method fails on the missing name. The service container is the exception: a parameter, service tag or service id under its old name can go unread without an error, so [Service ids and parameters](#service-ids-and-parameters) lists what to check.

### Configuration and tags

The `drivers` list is now the `backends` list, and the `@driver:` tag is now the `@backend:` tag. The entries and the names they carry stay the same.

```php
// Before.
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drivers' => ['api' => 'drupal', 'drush', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));

// After.
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'backends' => ['api' => 'drupal', 'drush', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'drush' => ['root' => 'web'],
]));
```

In `behat.yml`:

```yaml
# Before.
default:
  extensions:
    DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
      drivers: [drupal, drush, blackbox]

# After.
default:
  extensions:
    DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
      backends: [drupal, drush, blackbox]
```

In feature files:

```gherkin
# Before.
@driver:drush
Scenario: The cache is cleared over the command line

# After.
@backend:drush
Scenario: The cache is cleared over the command line
```

Neither old name is quietly ignored. A `drivers` key fails the container build:

```
The "drivers" setting under "behat_steps" moved to "backends". Rename the key; its entries are unchanged.
```

A `@driver:` tag on a scenario or on its feature fails the scenario at its start:

```
The "@driver:drush" tag moved to "@backend:drush". Rename the tag; the name it carries is unchanged.
```

`grep -rn '@driver:' <your features directory>` lists every tag that needs the rename.

### Namespaces, classes and interfaces

Everything under `DrevOps\BehatSteps\Driver` moved to `DrevOps\BehatSteps\Backend`. The capability interfaces, `Core`, the field handlers, `EntityStub` and the creation aliases keep their own names, so for them the namespace is the whole change:

```php
// Before.
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;

// After.
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
```

The names that said "driver" change as well:

| Before | After |
| --- | --- |
| `Driver\DriverInterface` | `Backend\BackendInterface` |
| `Driver\DrupalDriver` | `Backend\DrupalBackend` |
| `Driver\DrupalDriverInterface` | `Backend\DrupalBackendInterface` |
| `Driver\DrushDriver` | `Backend\DrushBackend` |
| `Driver\DrushDriverInterface` | `Backend\DrushBackendInterface` |
| `Driver\BlackboxDriver` | `Backend\BlackboxBackend` |
| `Driver\BlackboxDriverInterface` | `Backend\BlackboxBackendInterface` |
| `Driver\Exception\UnsupportedDriverActionException` | `Backend\Exception\UnsupportedBackendActionException` |
| `Behat\Manager\DriverRegistry` | `Behat\Registry\BackendRegistry` |
| `Behat\Manager\DriverRegistryInterface` | `Behat\Registry\BackendRegistryInterface` |
| `Behat\Context\DriverAwareInterface` | `Behat\Context\BackendAwareInterface` |
| `Behat\Context\Initializer\DriverAwareInitializer` | `Behat\Context\Initializer\BackendAwareInitializer` |
| `Behat\Listener\DriverListener` | `Behat\Listener\BackendListener` |
| `Behat\ServiceContainer\DriverPass` | `Behat\ServiceContainer\BackendPass` |

`BackendInterface` and `UnsupportedBackendActionException` no longer share a short name with Mink's `DriverInterface` and `UnsupportedDriverActionException`, so a file that imported Mink's under an alias to tell a pair apart can drop the alias.

A Drupal core of your own for one Drupal major is looked up as `DrevOps\BehatSteps\Backend\Core{N}\Core`, where `{N}` is the major version, so a class in the old namespace moves with the rest.

`Driver\SubDriverFinderInterface` and `DrupalDriver::getSubDriverPaths()` are gone. They served the Drupal Extension's subcontext discovery, which this package doesn't do. Code that read the extension paths asks the in-process backend's core:

```php
// Before.
$paths = $this->getDriver('drupal')->getSubDriverPaths();

// After.
$paths = $this->backendFor(CoreCapabilityInterface::class)->getCore()->getExtensionPathList();
```

### Methods and constants

| Class | Before | After |
| --- | --- | --- |
| `WebRawContext` | `driverFor()` | `backendFor()` |
| `WebRawContext` | `anyDriverFor()` (protected) | `anyBackendFor()` |
| `WebRawContext` | `getDriver()` | `getBackend()` |
| `WebRawContext`, `BackendAwareInterface` | `getDriverRegistry()` | `getBackendRegistry()` |
| `WebRawContext`, `BackendAwareInterface` | `setDriverRegistry()` | `setBackendRegistry()` |
| `BackendRegistryInterface` | `registerDriver()` | `registerBackend()` |
| `BackendRegistryInterface` | `getDrivers()` | `getBackends()` |
| `BackendRegistryInterface` | `getDriver()` | `getBackend()` |
| `BackendRegistryInterface` | `getDriverFor()` | `getBackendFor()` |
| `BackendRegistryInterface` | `getResolvedDriverFor()` | `getResolvedBackendFor()` |
| `BackendRegistryInterface` | `setScenarioDrivers()` | `setScenarioBackends()` |
| `BackendRegistryInterface` | `getScenarioDrivers()` | `getScenarioBackends()` |
| `Backend\Exception\Exception` | `getDriver()` | `getBackend()` |
| `BackendListener` | `prepareScenarioDrivers()` | `prepareScenarioBackends()` |
| `BackendListener` | `configuredDrivers()` (protected) | `configuredBackends()` |
| `BackendListener` | `promotedDrivers()` (protected) | `promotedBackends()` |
| `BackendListener` | `DRIVER_TAG_PREFIX` | `BACKEND_TAG_PREFIX` |
| `BackendPass` | `DRIVER_TAG` | `BACKEND_TAG` |
| `BehatStepsExtension` | `DRIVERS_PARAMETER` | `BACKENDS_PARAMETER` |
| `BehatStepsExtension` | `processDriverPass()` (protected) | `processBackendPass()` |
| `BehatStepsExtension` | `processDrivers()` (protected) | `processBackends()` |
| `BehatStepsExtension` | `validateDriverEntry()` (protected) | `validateBackendEntry()` |
| `Steps\Drupal\DrushTrait` | `drushGetDriver()` | `drushGetBackend()` |
| `Helper\Drupal\EntityLifecycleTrait` | `entityLifecycleGetContentDriver()` (protected) | `entityLifecycleGetContentBackend()` |

A parameter named `$driver` that held a backend is now `$backend`. That only matters to a call that passes it by name, such as `userAssignRoles(driver: ...)`.

### Service ids and parameters

| Before | After |
| --- | --- |
| `behat_steps.driver_registry` | `behat_steps.backend_registry` |
| `behat_steps.driver.drupal` | `behat_steps.backend.drupal` |
| `behat_steps.driver.drush` | `behat_steps.backend.drush` |
| `behat_steps.driver.blackbox` | `behat_steps.backend.blackbox` |
| `behat_steps.driver.core` | `behat_steps.backend.core` |
| `behat_steps.driver.random` | `behat_steps.backend.random` |
| `behat_steps.listener.driver` | `behat_steps.listener.backend` |
| The `behat_steps.driver` service tag | The `behat_steps.backend` service tag |
| `behat_steps.drivers` | `behat_steps.backends` |

The parameters follow their service: `behat_steps.driver_registry.class` becomes `behat_steps.backend_registry.class`, `behat_steps.driver.drush.binary` becomes `behat_steps.backend.drush.binary`, and so on for every `.class`, `drupal_root`, `alias`, `binary` and `root` parameter. Unlike the configuration key, a parameter under its old name isn't rejected - it's just never read again - so a suite that swaps an implementation through one should check it renamed it.

An old service id is only loud where something requires it. An `@behat_steps.driver_registry` argument or a `getDefinition()` call fails the container build on the missing service, though Symfony's message doesn't mention the rename. A `hasDefinition()` check or a service defined under an old id is as quiet as a parameter.

A backend of your own registers by tagging its service `behat_steps.backend`, with the name it answers to as the alias:

```yaml
# Before.
tags:
  - { name: behat_steps.driver, alias: acme-jsonapi }

# After.
tags:
  - { name: behat_steps.backend, alias: acme-jsonapi }
```

A service still tagged `behat_steps.driver` isn't registered at all. When the `backends` list names it, the container build fails with `The "backends" list under "behat_steps" names the backend "acme-jsonapi", which is not registered.` Without a `backends` list it's left out of the scenario's order, so each step resolves the first remaining backend that provides its capability, or fails with `No backend provides "..."` when none does.

`grep -rnE 'behat_steps\.(listener\.)?driver' <your extension directory>` lists every container name that needs the rename: parameters, service ids and tags.

### Messages

A failure that names the concept now says "backend", so a test that asserts one of these messages needs the new text:

| Before | After |
| --- | --- |
| `No driver provides "...". Drivers available to this scenario, in order: ...` | `No backend provides "...". Backends available to this scenario, in order: ...` |
| `... requires that a driver in the scenario's list provides "...", which does not hold.` | `... requires that a backend in the scenario's list provides "...", which does not hold.` |
| `The "@driver:..." tag names a driver that the configured driver list does not hold.` | `The "@backend:..." tag names a backend that the configured backend list does not hold.` |
| `Driver "..." is not registered. Registered drivers: ...` | `Backend "..." is not registered. Registered backends: ...` |

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
| Every step whose method took an `int`: the queue, email, table and element counts, the element index, tolerance and offset steps, and `the REST response status code should be :code` | Behat's `Type error: ... must be of type int, string given` | `\RuntimeException` |
| `I wait for :seconds second(s)`, `I wait for :seconds second(s) for AJAX to finish` and `I run search indexing for :count item(s)` | read as `0` | `\RuntimeException` |
| `I set the viewport width to :width`, `I set the viewport height to :height` and `I set the viewport to :width by :height` | read as `0`, and the resize failed without a word | `\RuntimeException` |

PHP's coercion was looser than the `int` type suggested: `1e3` read as 1000, and `3.5` read as 3 with a deprecation notice, which only failed the step where PHP reports deprecations. Both now fail with `\RuntimeException`.

A value below what a step accepts fails the same way: a count below 0, a link index below 1, a viewport width or height below 1, a wait below 0 seconds and a command duration below 0. An element index below 1 and a negative tolerance already failed this way and still do.

### Messages

A test that asserts one of these messages needs the new text:

| Step | Before | After |
| --- | --- | --- |
| `the command exit code should be :code` | `The expected exit code must be an integer, but got "...".` | `The exit code must be an integer, but "..." was given.` |
| `the command should complete in less than :seconds second(s)` and `... more than :seconds second(s)` | `The expected duration must be numeric, but got "...".` | `The duration must be a number, but "..." was given.` |
| `I follow the link with the index :index ...`, both forms | `The link number must be a positive integer, but "..." was provided.` | `The link index must be an integer, but "..." was given.`, or `The link index must be 1 or greater, but "..." was given.` for an integer below 1 |

### Signatures

The methods are listed under their 4.x names; [One shape per naming idea](#one-shape-per-naming-idea) lists the renames. PHP that calls one of these methods passes a string where it passed an `int`, or calls the method in the last column.

| Method | Before | After |
| --- | --- | --- |
| `Drupal\QueueTrait::queueProcessItems()`, `queueAssertItemCount()` | `int $count` | `string $count` |
| `Drupal\EmailTrait::emailAssertMessageCount()`, `emailAssertMessageCountToAddress()`, `emailAssertMessageCountWithSubject()` | `int $count` | `string $count` |
| `Drupal\SearchApiTrait::searchApiRunIndexing()` | `string\|int $limit` | `string $count` |
| `TableTrait::tableAssertRowCount()`, `tableAssertColumnCount()` | `int $count` | `string $count` |
| `RestTrait::restAssertResponseStatusCodeEquals()` | `int $code` | `string $code` |
| `ElementTrait::elementClickWithIndex()`, `elementFollowLinkWithIndex()`, `elementPressButtonWithIndex()` | `int $index` | `string $index` |
| `ElementTrait::elementAssertPinnedToTopWithTolerance()` | `int $tolerance` | `string $tolerance` |
| `ElementTrait::elementAssertVisuallyVisibleWithOffset()`, `elementAssertNotVisuallyVisibleWithOffset()` | `int $number` | `string $offset` |
| `ElementTrait::elementAssertChildElementCount()` | `int $count` | `string $count` |
| `ElementTrait::elementAssertExistsWithAttributeValue()` and the 3 other attribute value steps | `mixed $value` | `string $value` or `string $partial_value` |
| `ElementTrait::elementAssertNotVisuallyVisible()` | `int $offset = 0` | no `$offset`; call `elementAssertNotVisuallyVisibleWithOffset()` |
| `WaitTrait::waitSeconds()` | `string\|int $seconds` | `string $seconds` |
| `WaitTrait::waitForAjax()` | `string\|int $seconds` | `string $seconds`; PHP holding an `int` calls the new `waitForAjaxWithin(int $seconds)` |
| `FieldTrait::fieldFillColor()` | `?string $value = NULL` | `string $value` |
| `KeyboardTrait::keyboardPressKeyOnElement()`, `keyboardPressKeysOnElement()` | `?string $selector` | `string $selector`; for the focused element, call `keyboardPressKey()` or `keyboardPressKeys()` |
| `LinkTrait::linkAssertExistsWithHrefWithinElement()`, `linkAssertNotExistsWithHrefWithinElement()` | `?string $selector` | `string $selector`; for the whole page, call `linkAssertExistsWithHref()` or `linkAssertNotExistsWithHref()` |
| `Drupal\EmailTrait::emailClearTestQueue()` | `bool $force = FALSE` | no `$force`; `emailClearCollectedMessages()` clears the collected messages without the check |
| `Drupal\EmailTrait::emailAssertMessageHeaderContains()` | `bool $exact = FALSE` | no `$exact`; for `TRUE`, call `emailAssertMessageHeaderEquals()` |
| `Drupal\EmailTrait::emailAssertMessageFieldContains()` | `bool $exact = FALSE` | no `$exact`; for `TRUE`, call `emailAssertMessageFieldEquals()` |
| `Drupal\EmailTrait::emailAssertMessageFieldNotContains()` | `bool $exact = FALSE` | no `$exact`; for `TRUE`, call `emailAssertMessageFieldNotEquals()` |
| `Drupal\FileTrait::fileCreateUnmanaged()` | `string $content = 'test'` | no `$content`; call `fileCreateUnmanagedWithContent()` |

An override of one of these methods in your `FeatureContext` takes the new signature, or PHP reports it as incompatible with the trait's.

A call that still passes a removed argument doesn't fail, because PHP drops an extra argument without a word: `fileCreateUnmanaged($uri, 'Hello')` writes `test`, `emailAssertMessageFieldContains($field, $string, TRUE)` compares with whitespace collapsed, and `elementAssertNotVisuallyVisible($selector, 10)` checks an offset of 0. Move each one to the method in the last column. PHPStan reports every such call as a method invoked with more parameters than it takes.

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

A class of your own that extends one of the classes below no longer loads:

```
PHP Fatal error:  Class Acme\AcmeAuthenticator cannot extend final class DrevOps\BehatSteps\Behat\Auth\Authenticator
```

| Namespace | Now `final` |
| --- | --- |
| `Backend\Alias` | `RolesAlias` |
| `Backend\Core\Alias` | `AuthorAlias`, `ParentTermAlias`, `VocabularyMachineNameAlias` |
| `Backend\Core\Field` | `FieldClassifier`, `FieldShapeClassifier` |
| `Backend\Core\Field\Parser` | `EntityFieldParser` |
| `Backend\Core\Field\Parser\Exception` | `MultipleParseException` |
| `Backend\Exception` | `BootstrapException`, `CreationAliasResolutionException`, `UnsupportedBackendActionException` |
| `Behat\Auth` | `Authenticator`, `BasicAuthenticator` |
| `Behat\Config` | `ConfigSchemaReader`, `TagOverrides`, `TraitOptionResolver`, `TraitOptionResolverFactory` |
| `Behat\Context\Attribute` | `HookAttributeReader` |
| `Behat\Context\Initializer` | `BackendAwareInitializer` |
| `Behat\Generator` | `ClassGenerator` |
| `Behat\Hook\Call` | `AfterEntityCreate`, `AfterNodeCreate`, `AfterTermCreate`, `AfterUserCreate`, `BeforeEntityCreate`, `BeforeNodeCreate`, `BeforeTermCreate`, `BeforeUserCreate` |
| `Behat\Http` | `HttpIdentity` |
| `Behat\Listener` | `BackendListener`, `SkipTagListener` |
| `Behat\Mink` | `BrowserCapabilityResolver` |
| `Behat\Mink\ServiceContainer\Driver` | `BrowserKitFactory` |
| `Behat\Prerequisite` | `PrerequisiteReader` |
| `Behat\Registry` | `BackendRegistry`, `ScenarioTagRegistry`, `UserRegistry` |
| `Behat\Selector` | `RegionSelector` |
| `Behat\ServiceContainer` | `BackendPass`, `BehatStepsExtension` |
| `Exception` | `AssertionException` |

Where one of these classes has an interface, implement the interface instead. A suite that swaps in its own registry or authenticator through a `*.class` parameter implements `BackendRegistryInterface`, `ScenarioTagRegistryInterface`, `UserRegistryInterface`, `AuthenticatorInterface` with `FastLogoutInterface`, or `BasicAuthenticatorInterface`. A creation alias of your own implements `PreCreateAliasInterface` or `PostCreateAliasInterface`, and option resolution is replaced through `TraitOptionResolverFactoryInterface`, as [Option resolution is a service a project can replace](#option-resolution-is-a-service-a-project-can-replace) describes.

PHPUnit can't double a final class either, so a test that built a mock of one of these fails with `Class "..." is declared "final" and cannot be doubled`. Double its interface instead.

2 constructor parameters are named after their types now, which only matters to a call that passes them by name:

| Constructor | Before | After |
| --- | --- | --- |
| `Backend\Alias\RolesAlias::__construct()` | `$backend` | `$userCapability` |
| `Behat\Context\Initializer\BackendAwareInitializer::__construct()` | `$optionResolverFactory` | `$traitOptionResolverFactory` |

### Constants declare native types

Every constant under `src/` declares its type now, as `public const string CONFIG_KEY = 'behat_steps';` does, and every value is unchanged. PHP holds a redeclared constant to the type it inherits, so a class of your own that redeclares one of them, such as a `FeatureContext` redeclaring a constant of a trait its parent composes, declares the same type:

```
PHP Fatal error:  Type of FeatureContext::BATCH_WAIT_TIMEOUT must be compatible with DrupalContext::BATCH_WAIT_TIMEOUT of type int
```

## Queues are deleted after every scenario that used them

`QueueTrait` deleted the queues a scenario used only when the scenario was tagged `@queue`, a tag nothing documented, so an untagged scenario left its queue items behind for the next one. The teardown now runs after every scenario, and `@queue` does nothing: remove it from your feature files.

A queue counts as used once any queue step names it, the assertions included, so a scenario that only checks a queue the site filled deletes it as well. To keep the items for a later scenario, tag the scenario that leaves them `@behat-steps-skip:QueueTrait`, or set `queue.enabled` to `FALSE` for the suite.
