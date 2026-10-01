# Configuration

Everything this package reads arrives through one of 4 channels. Each channel answers a different question, and none of them is a substitute for another.

| Channel | Answers | Scope |
| --- | --- | --- |
| [Suites and context arguments](#1-suites-and-context-arguments) | Which scenarios run against which surface, with which vocabulary | Per suite |
| [The `behat_steps` extension key](#2-the-behat_steps-extension-key) | How the package talks to the site | Per profile |
| [Tags](#3-tags) | What a single scenario opts into | Per scenario or feature |
| [Environment variables](#4-environment-variables) | What differs between one machine and another | Per run |

The tables in sections 2 and 3 are generated from the source by [docs.php](../docs.php). Run `ahoy update-docs` after changing an option or a tag.

## 1. Suites and context arguments

### Suite per surface

A suite is the unit that binds a set of feature files to a set of contexts. The default layout is one suite per surface under test, because each surface needs a different vocabulary and a different backend.

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;

// The browser surface: what an editor or a visitor can do in a page.
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class);

// The API surface: responses, headers and payloads, with no browser.
$api = (new Suite('api'))
  ->withPaths('%paths.base%/tests/behat/features/api')
  ->addContext(ApiContext::class);

// The specification surface: the few domain-language scenarios a stakeholder reads.
$spec = (new Suite('spec'))
  ->withPaths('%paths.base%/tests/behat/features/spec')
  ->addContext(SpecContext::class);

$profile = (new Profile('default'))
  ->withSuite($ui)
  ->withSuite($api)
  ->withSuite($spec)
  ->withExtension(new Extension(BehatStepsExtension::class, ['drupal' => ['drupal_root' => 'web']]));

return (new Config())->withProfile($profile);
```

Run one surface with `vendor/bin/behat --suite=api`, or all of them with a bare `vendor/bin/behat`.

Three properties follow from the layout:

- **Each suite composes only the vocabulary its surface needs.** `ApiContext` mixes in `RestTrait`, `JsonTrait` and `ResponseTrait`; it never loads a trait that expects a rendered page. A step that cannot work on a surface is not defined on it, so a mistake is a "step not found" at parse time instead of a confusing failure mid-run.
- **The spec suite and the regression suites do different jobs.** The spec suite holds domain-language scenarios written over your own step definitions; the regression suites hold broad scenarios written in the shipped vocabulary. See [Scenario styles](scenario-styles.md) for why both exist.
- **A slow surface can be excluded without touching the others.** Accessibility, visual and `@javascript` scenarios sit behind their own suite or a tag filter, so the fast suites stay fast.

### Context constructor arguments

The extension key in section 2 is read once per profile. A value that has to differ between suites travels as a context constructor argument instead:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class, ['fixtures_path' => '%paths.base%/tests/behat/fixtures']);
```

```php
class UiContext extends WebRawContext {

  public function __construct(protected string $fixtures_path) {
  }

}
```

Behat matches the arguments to the constructor by name, so the array keys are the parameter names.

`WebRawContext` declares one argument of its own, `config`, and every shipped and consuming context inherits it without redeclaring a constructor. It carries the [trait options](#trait-options) that differ between two contexts of the same profile:

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(WebContext::class, [
    'config' => ['wait' => ['ajax_timeout' => 10]],
  ]);
```

A group names the trait that declares it, so a context accepts only the groups its own traits bring. `WaitTrait` and `JavascriptTrait` are web traits, so `WebContext` takes `wait` and `javascript`; `DrupalContext` extends it and adds `watchdog`, `big_pipe`, `cache`, `queue` and `email` on top. Setting a group no trait in the chain declares is an error at construction, naming what that context does accept.

A context that adds arguments of its own forwards `config` to the parent, and the suite passes both:

```php
class UiContext extends WebRawContext {

  use JavascriptTrait;

  public function __construct(protected string $fixtures_path, array $config = []) {
    parent::__construct($config);
  }

}
```

```php
$ui = (new Suite('ui'))
  ->withPaths('%paths.base%/tests/behat/features/ui')
  ->addContext(UiContext::class, [
    'fixtures_path' => '%paths.base%/tests/behat/fixtures',
    'config' => ['javascript' => ['fail_on_errors' => FALSE]],
  ]);
```

Behat 4 reads PHP configuration only, from `behat.php` or, when there is no `behat.php`, from `behat.dist.php`. Behat 3 reads the same settings from `behat.yml`. Write `behat.php` first: it is the format both majors accept, and the only format Behat 4 accepts.

While a project is still on Behat 3, the YAML form of the suite above is:

```yaml
default:
  suites:
    ui:
      paths: ['%paths.base%/tests/behat/features/ui']
      contexts:
        - UiContext:
            fixtures_path: '%paths.base%/tests/behat/fixtures'
    api:
      paths: ['%paths.base%/tests/behat/features/api']
      contexts: [ApiContext]
  extensions:
    DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension:
      drupal:
        drupal_root: web
```

[behat.dist.php](../behat.dist.php) in this repository sets every option the package accepts, as a reference.

## 2. The `behat_steps` extension key

Settings under this key configure how the package reaches the site: which backends a scenario may resolve and in what order, how each of them connects, what the login form looks like, which CSS selector each named region resolves to. They are read once per profile.

HTTP connection settings are the exception. Certificate checks, a proxy or DNS pinning live on Mink's `browserkit_http` session as `http_client_parameters`, and the requests steps send from PHP, such as a file download, share them with the page. [HTTP clients](http-clients.md) covers how.

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'backends' => ['drupal', 'blackbox'],
  'drupal' => ['drupal_root' => 'web'],
  'regions' => ['content' => '#content'],
]));
```

A nested option is written as a section in the configuration and reads as a dotted path below.

[//]: # (START_EXTENSION_OPTIONS)

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `backends` | map | `[]` | Ordered list of the backends a scenario may resolve, most preferred first. It is both the allow-list and the precedence order: a step names the capability it needs and the first backend here providing it answers. A bare entry names a registered backend; a "tag: backend" entry gives it a name of its own, so the same feature file runs against a different backend in another profile. Omit it to get every registered backend, in registration order.<br>- drupal<br>- api: acme-jsonapi<br>- blackbox |
| `login_field` | string | `'name'` | User entity property submitted as the login value. Defaults to "name". Set to "mail" for sites that authenticate by email, or any other user property. |
| `regions` | map | `[]` | Map of named regions to CSS selectors. Region steps such as 'I press the button :button in the region :region' resolve against this map.<br>My region: "#css-selector"<br>Content: "#main .region-content"<br>Right sidebar: "#sidebar-second" |
| `text` | section | - | Text strings, such as Log out or the Username field can be altered in the Behat configuration if they vary from the default values.<br>login_url: "/user"<br>logout_url: "/user/logout"<br>logout_confirm_url: "/user/logout/confirm"<br>log_out: "Sign out"<br>log_in: "Sign in"<br>password_field: "Enter your password"<br>username_field: "Nickname" |
| `text.login_url` | string | `'/user'` | Path the login steps submit the login form on. |
| `text.logout_url` | string | `'/user/logout'` | Path the logout steps request. |
| `text.logout_confirm_url` | string | `'/user/logout/confirm'` | Path of the logout confirmation form, submitted when the site asks to confirm. |
| `text.log_in` | string | `'Log in'` | Text of the login submit button. |
| `text.log_out` | string | `'Log out'` | Text of the logout link. |
| `text.password_field` | string | `'Password'` | Label of the password field on the login form. |
| `text.username_field` | string | `'Username'` | Label of the username field on the login form. |
| `login_wait` | integer | `0` | Maximum seconds to wait for post-login DOM signals (URL change, body render, logged-in selector, logout link). Set to 0 to disable waiting. |
| `steps` | map | `[]` | Default values of the options the step traits declare, keyed by trait group and then by option name. Each group is named after the trait that declares it, so "JavascriptTrait" reads "javascript" and "BigPipeTrait" reads "big_pipe". A group naming a trait none of the registered contexts composes is ignored, so one profile can carry the defaults of every suite.<br>javascript:<br>enabled: true<br>fail_on_errors: false<br>wait:<br>ajax_timeout: 10 |
| `selectors` | section | - | CSS selectors the steps resolve page structures against. |
| `selectors.login_form_selector` | string | `'form#user-login,form#user-login-form'` | Selector of the login form, used to tell a login page from a page that merely holds a login block. |
| `selectors.logged_in_selector` | string | `'body.logged-in,body.user-logged-in'` | Selector present only while a user is authenticated, used to confirm a login took effect. |
| `blackbox` | section | - | Settings of the backend that drives the site through the browser only. It has no options, and it performs no backend operation, so it provides no capability a step can resolve. |
| `drupal` | section | - | Settings of the backend that bootstraps Drupal in-process. |
| `drupal.drupal_root` | string | required | Path to the Drupal root the in-process backend bootstraps. |
| `drush` | section | - | Settings of the backend that reaches the site by running Drush. |
| `drush.alias` | string | - | Drush site alias to run every command against. |
| `drush.binary` | string | `'vendor/bin/drush'` | Path to the Drush binary. |
| `drush.root` | string | - | Drupal root passed to Drush, for a site Drush cannot locate on its own. |
| `drush.global_options` | string | - | Options appended to every Drush command, such as "--uri=http://example.com". |

[//]: # (END_EXTENSION_OPTIONS)

### Backend resolution

Five authorities decide which backend runs a step, and each decides exactly one thing.

| Authority | Decides | Where |
| --- | --- | --- |
| Extension | Which backends exist at all, and in what order | `behat_steps: { backends: [...] }` |
| Profile | Which backends exist per environment | `behat -p remote` |
| Scenario tag | Preference among the backends that exist | `@backend:NAME` |
| Step | Which capability it needs | `backendFor(X::class)` |
| Capability interface | Which backends are even eligible | `instanceof` during the walk |

The `backends` list is both the allow-list and the precedence order. Each entry names a backend the extension registers; a keyed entry gives the backend a name of its own, so the same feature file can run against a different backend in another profile.

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'blackbox' => NULL,
  'drupal' => ['drupal_root' => 'web'],
  'backends' => [
    'drupal',
    'api' => 'acme-jsonapi',
    'blackbox',
  ],
]));
```

That aliasing is what lets a profile swap the implementation behind a name without touching any Gherkin:

```php
// Default profile: "api" is the in-process backend.
'backends' => ['api' => 'drupal', 'blackbox'],

// "behat -p staging": "api" is the real HTTP API, and Drupal is unreachable.
'backends' => ['api' => 'acme-jsonapi', 'blackbox'],
```

A name holds only letters, digits, `_` and `-`, so that `@backend:NAME` is a valid tag, and it is unique. A name the extension does not register fails the container build. A configuration that declares no list gets every registered backend, in registration order.

Scoping a run to a restricted backend set is a profile's job, not a suite's: the package reads nothing from a Behat suite's settings.

At step time the resolution is:

1. Start from the configured order.
2. Move every name a `@backend:` tag promotes to the front: the names on the scenario line first, then those on the feature line. Each group keeps the configured order, so the order the tags are written in never changes the result.
3. Walk that order and return the first backend implementing the capability the step asked for, bootstrapping only that one.

```gherkin
@backend:api
Scenario: Content created over the public API is immediately visible
  Given the following "article" content exist:
    | title      |
    | Lab report |
  When I visit "/articles"
  Then the page should contain "Lab report"
```

The order becomes `acme-jsonapi, drupal, blackbox`. The content step resolves to `acme-jsonapi`. A cache step in the same scenario still resolves to `drupal`, because `acme-jsonapi` implements no cache capability - promotion only affects the capabilities the promoted backend actually provides.

Repeated tags promote their names in the configured order: `@backend:blackbox @backend:api` and `@backend:api @backend:blackbox` both give `acme-jsonapi, blackbox, drupal`. A `Scenario Outline` row counts the outline's tags and its `Examples:` table's tags as 1 scenario line.

`@backend:NAME` reorders the configured list; it never adds to it. A name outside the list is an error at scenario start, so a typo cannot quietly run the wrong backend, and a `smoke` profile listing only `blackbox` cannot be handed a Drupal backend by any tag. When no backend in the order provides the capability a step asked for, the step fails with an `UnsupportedBackendActionException` naming the capability and the resolved order.

### Trait options

Every configurable trait declares its own options, and the `steps` section holds their profile-wide defaults. A group is named after the trait that declares it, in snake case: `JavascriptTrait` reads `javascript`, `BigPipeTrait` reads `big_pipe`, `FileDownloadTrait` reads `file_download`. [STEPS.md](../STEPS.md) lists the options of each trait beside its steps.

```php
$profile->withExtension(new Extension(BehatStepsExtension::class, [
  'drupal' => ['drupal_root' => 'web'],

  'steps' => [
    'javascript' => ['enabled' => TRUE, 'fail_on_errors' => TRUE],
    'watchdog' => ['enabled' => TRUE, 'fail_on_errors' => TRUE],
    'big_pipe' => ['wait_timeout' => 5000],
    'wait' => ['ajax_timeout' => 10],
    'modal' => ['selectors' => ['.ui-dialog', '[role="dialog"]']],
  ],
]));
```

An option resolves through five layers, each overriding the one above it:

```
declaration default
  -> behat_steps.steps        (profile-wide)
    -> context config          (per context)
      -> feature tag
        -> scenario tag
```

The two levels differ in how strictly they are read, because they know different things:

- **`steps` is permissive.** A group there may name a trait only one of the registered contexts composes, so a group or an option a context cannot serve is ignored. One profile can carry the defaults of every suite.
- **A context's `config` is strict.** The context knows which traits it composes, so an unknown group, an unknown option, or a value of the wrong type is an error naming what the context accepts.

Two options recur, and they are deliberately separate switches:

| Option | Effect |
| --- | --- |
| `enabled` | The trait's hooks do nothing at all. Equivalent to `@behat-steps-skip:<TraitName>` on every scenario. |
| `fail_on_errors` | The trait still collects, and does not fail the scenario on what it found. |

`fail_on_errors` only covers what a trait collected. A trait whose prerequisites don't hold, like `WatchdogTrait` on a site without the core `dblog` module, fails the scenario at its start whatever `fail_on_errors` says, so `enabled` is the switch there. [Switch a trait off](usage.md#switch-a-trait-off) has the details.

Overriding the trait's `<trait>Get<Noun>()` method in the composing context sits outside the chain and replaces the resolution entirely, which remains the escape hatch for anything the configuration cannot express.

A project writing its own configurable trait declares each option as a `DrevOps\BehatSteps\Behat\Config\Option` returned from a `<prefix>ConfigSchema()` method, where `<prefix>` is the prefix the trait's other members carry:

```php
protected function acmeConfigSchema(): array {
  return [
    new Option('enabled', default: TRUE, description: 'Whether the Acme hook runs.'),
    new Option('wait_timeout', default: 5000, description: 'How long to wait, in milliseconds.', tags: ['slow' => 30000]),
  ];
}
```

The declared default carries the option's type, and the composing context reads it at that type with `getOptionBool()`, `getOptionInt()`, `getOptionFloat()`, `getOptionString()` or `getOptionArray()`. A default of `NULL` names no type, and `getOption()` reads it. Resolution itself is the `behat_steps.config.resolver_factory` service, so a project that needs a chain of its own registers another `TraitOptionResolverFactoryInterface` under that id.

## 3. Tags

A tag configures one scenario or one feature. A parametrized tag takes its value after a colon, never a hyphen: `@module:redirect`, not `@module-redirect`. A tag with nothing after the colon names nothing and is ignored, except `@behat-steps-skip:` and `@backend:`, which fail at scenario start like any other value they cannot resolve. A flag tag stands alone. `@module:` also switches its value off with a `!`: `@module:!redirect`.

```gherkin
@module:redirect @behat-steps-skip:WatchdogTrait
Scenario: Editor publishes a page
```

A tag on the `Feature:` line applies to every scenario in that feature. Where a scenario and its feature carry the same kind of tag, a flag takes effect from either line, `@email:VALUE`, `@watchdog:VALUE`, `@disable-config-override:VALUE` and `@behat-steps-entity-cleanup-skip:VALUE` add up across both lines, and `@module:VALUE` for the same module and `@breakpoint:VALUE` take the scenario's value over the feature's. `@backend:VALUE` promotes from both lines, the scenario's names ahead of the feature's, as [backend resolution](#backend-resolution) describes.

[//]: # (START_TAGS)

| Tag | Description |
| --- | --- |
| `@behat-steps-skip:VALUE` | Switch off every hook of the named trait, such as `EmailTrait`. On a trait that declares an `enabled` option, the tag sets it to FALSE. A value that is not a trait name fails the run at scenario start. |
| `@behat-steps-entity-cleanup-skip:VALUE` | Keep entities of the named entity type after the scenario. Repeat the tag to keep several types. |
| `@backend:VALUE` | Move the named backend to the front of the configured backend list for the scenario. Repeat the tag to promote several: they keep the configured order among themselves, so the order the tags are written in does not matter. The tag reorders the list; it never adds to it. |
| `@module:VALUE` | Enable the named module for the scenario, or disable it when the name is prefixed with `!`. The original state is restored afterwards. |
| `@breakpoint:VALUE` | Resize the viewport to the named breakpoint before the first step. A scenario and its feature take 1 tag each, and the scenario's replaces the feature's. The scenario or its feature has to be `@javascript`. |
| `@email:VALUE` | Collect email for the scenario with the named handler type. A bare `@email` uses the `default` handler. |
| `@watchdog:VALUE` | Track the named Watchdog message type in addition to `php`, which is always tracked. |
| `@disable-config-override:VALUE` | Disable `settings.php` overrides for the named configuration object for the duration of the scenario. |
| `@accessibility:VALUE` | Assess every page the scenario visits. The value sets the impact threshold that fails the scenario: `critical`, `serious`, `moderate`, `minor`, `any`, `warning` or `strict`. |
| `@bigpipe` | Render BigPipe placeholders server-side, for a browser driver without JavaScript. |
| `@disable-form-validation` | Strip HTML5 validation from every form on the page so a scenario can submit values the browser would block. |
| `@js-errors` | Allow JavaScript errors, which otherwise fail the scenario. They are still collected. Sets the `javascript.fail_on_errors` option to FALSE. |
| `@download` | Prepare the download directory for the scenario and clean it up afterwards. |
| `@testmode` | Enable the Testmode module for the scenario. |
| `@debug` | Print detailed diagnostics while the scenario runs. |
| `@error` | Expect the scenario to log an error. The errors are still read and cleared, and the scenario is not failed. Sets the `watchdog.fail_on_errors` option to FALSE. |

[//]: # (END_TAGS)

`@javascript` is a Mink tag rather than a tag of this package: it selects the browser session. Which backend a step runs against is settled by [backend resolution](#backend-resolution) and the `@backend:NAME` tag above.

## 4. Environment variables

These vary a run without changing any committed configuration. Nothing else in the package reads the environment.

| Variable | Read by | Effect |
| --- | --- | --- |
| `BEHAT_STEPS_DISABLE_CLEANUP` | `EntityLifecycleTrait` | Set to `1`, `true`, `yes` or `on` to keep the entities, users and roles a scenario created, instead of deleting them in the teardown. For inspecting the state a failing scenario left behind, not for CI. |
| `BEHAT_ACCESSIBILITY_PRINT` | `AccessibilityTrait` | Set to any value other than `0` to print a one-line accessibility summary per page to the console. |
| `COMPOSER_BIN_DIR` | `DrushBackend` | Names the directory the Drush binary is resolved from, before the backend falls back to `vendor/bin/drush` under the working directory. Composer sets it inside its own scripts. |

`BEHAT_PARAMS` is Behat's own override channel and applies here as it does to any extension: it carries a JSON object merged over the loaded configuration, which is the usual way to point `base_url` or a browser driver's `api_url` somewhere else for one run.
