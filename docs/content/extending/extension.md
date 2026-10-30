---
title: Extending the extension layer
sidebar_label: Extension layer
sidebar_position: 2
description: Replace the HTTP client factory, a registry, an authenticator or option resolution for every context at once from a Behat extension of your own, and give another browser driver a page client.
---

# Extending the extension layer

Follow this page when a change has to reach every context at once. The extension layer is `src/Extension`. It holds `BehatStepsExtension` and the services that extension builds into Behat's container, and the context initializer injects those services into the contexts. You change them from a Behat extension of your own, in 3 ways:

- Replace the HTTP client factory.
- Swap a registry or an authenticator through its `*.class` parameter.
- Replace option resolution.

This page also covers registering a browser adapter from a context.

The container sits outside the 5 surfaces semantic versioning covers, so check [What semantic versioning covers](README.md#what-semantic-versioning-covers) before you build on these changes.

## From a Behat extension of your own

Make each container change on this page in the `process()` method of a Behat extension of your own. Behat calls the `load()` method of every extension before it calls any `process()`. Behat Steps defines its services in `load()`, so they exist when your `process()` runs, whatever order the profile lists the extensions in.

To add the extension:

1. Write an extension class such as this:

   ```php
   <?php

   declare(strict_types=1);

   namespace Acme\Behat;

   use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
   use Behat\Testwork\ServiceContainer\ExtensionManager;
   use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
   use Symfony\Component\DependencyInjection\ContainerBuilder;

   final class AcmeExtension implements ExtensionInterface {

     public function getConfigKey(): string {
       return 'acme';
     }

     public function initialize(ExtensionManager $extension_manager): void {}

     public function configure(ArrayNodeDefinition $builder): void {}

     public function load(ContainerBuilder $container, array $config): void {}

     public function process(ContainerBuilder $container): void {
       // Replace a Behat Steps service here.
     }

   }
   ```

2. Register it on the profile beside `BehatStepsExtension`:

   ```php
   $profile->withExtension(new Extension(AcmeExtension::class));
   ```

## The HTTP client factory and browser adapters

The 2 extension points in this section are meant for package authors. To decorate the transport for 1 context, use [a context override](steps.md#a-context-override-a-flaky-staging-server).

To give every context another HTTP client, such as a tracing client for debugging or a mock client for offline tests, replace the `behat_steps.http_client_factory` service. The container builds your class with the shared transport and the base URL. Your class implements `HttpClientFactoryInterface`, and extending the shipped `HttpClientFactory` keeps the rules that [HTTP clients](../guides/http-clients.md#the-rules-the-clients-follow) describes:

```php
public function process(ContainerBuilder $container): void {
  $container->getDefinition('behat_steps.http_client_factory')
    ->setClass(AcmeHttpClientFactory::class);
}
```

To give another browser driver a page client, register an adapter that implements `HttpClientCapabilityInterface` from your context, such as in its constructor:

```php
$this->getBrowserCapabilityResolver()
  ->registerAdapter(AcmeDriverAdapter::class);
```

Each context keeps its own resolver, so register the adapter in every context that needs it. The resolver tries your adapter ahead of the shipped ones. [Capabilities of the browser driver](../../../MIGRATION.md#capabilities-of-the-browser-driver) covers writing the adapter itself.

## Swap a registry or an authenticator

The container builds the 3 registries and 2 authenticators from the classes that `*.class` container parameters name. To put a class of your own in place of 1 of them, set its parameter from your extension. The shipped classes are final, so your class implements the interface. The registry interfaces sit in `DrevOps\BehatSteps\Extension\Registry`, and the authenticator interfaces in `DrevOps\BehatSteps\Extension\Auth`. The following table lists each parameter:

| Parameter | Shipped class | Your class implements | The container passes |
| --- | --- | --- | --- |
| `behat_steps.backend_registry.class` | `BackendRegistry` | `BackendRegistryInterface` | An empty array, followed by 1 `registerBackend()` call for each backend the extension registers |
| `behat_steps.scenario_tag_registry.class` | `ScenarioTagRegistry` | `ScenarioTagRegistryInterface` | Nothing |
| `behat_steps.user_registry.class` | `UserRegistry` | `UserRegistryInterface` | Nothing |
| `behat_steps.authenticator.class` | `Authenticator` | `AuthenticatorInterface`, and optionally `FastLogoutInterface` | `@mink`, `@behat_steps.user_registry`, `@behat_steps.backend_registry`, `@behat_steps.basic_authenticator`, `%mink.parameters%` and `%behat_steps.parameters%` |
| `behat_steps.basic_authenticator.class` | `BasicAuthenticator` | `BasicAuthenticatorInterface` | `@mink` and `%mink.parameters%` |

Set the parameter in `process()`:

```php
public function process(ContainerBuilder $container): void {
  $container->setParameter(
    'behat_steps.user_registry.class',
    AcmeUserRegistry::class,
  );
}
```

Only the class changes. The container builds yours with the arguments and calls the table lists, in that order. Every service and context that receives 1 of these services takes it by its interface, so nothing else needs replacing.

`FastLogoutInterface` changes how `AuthTrait` logs out in the teardown after each scenario:

- With it, `AuthTrait` calls `fastLogout()`. The shipped `Authenticator::fastLogout()` resets the browser session, so the site runs no logout hook. The method also logs out of the first backend in the scenario's order that provides authentication, when the scenario already reached that backend.
- Without it, `AuthTrait` calls `logout()` only while a user is still logged in.

## Replace option resolution

The `behat_steps.config.resolver_factory` service builds the resolver that every option read goes through. [Trait options](../guides/configuration.md#trait-options) lists the layers a trait option resolves through. To resolve options your own way, register a `TraitOptionResolverFactoryInterface` implementation under that ID:

```php
public function process(ContainerBuilder $container): void {
  $definition = new Definition(AcmeOptionResolverFactory::class);
  $container->setDefinition('behat_steps.config.resolver_factory', $definition);
}
```

The factory's `create()` takes the context class, the context's `config` argument and the extension's `steps` section. It returns the `TraitOptionResolverInterface` that `getOption()`, `getOptionBool()` and the other typed readers go through. The shipped `TraitOptionResolverFactory` is final, so a factory of your own implements the interface. To keep the container's arguments, set the `behat_steps.config.resolver_factory.class` parameter instead of calling `setDefinition()`. The container then passes `@behat_steps.config.schema_reader`, `@behat_steps.scenario_tag_registry` and `@behat_steps.config.tag_override_resolver`.

Behat builds a context before the initializer injects the service, so the context's constructor still checks its `config` argument through the shipped factory. A `config` value the shipped resolution rejects fails there, whatever factory the service holds.

## Where to next

- [Extending the step vocabulary](steps.md) covers what a single context of yours overrides without a Behat extension.
- [Data flow](../architecture/data-flow.md) shows when the services the extension builds run and how they reach a context.
