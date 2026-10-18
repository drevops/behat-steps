---
title: "WebRawContext helpers"
sidebar_label: "WebRawContext"
sidebar_position: 1
description: "Root context carrying the plumbing every suite needs."
custom_edit_url: null
---

# WebRawContext helpers

[Source](../../../../src/WebRawContext.php)

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
  <summary><code>public function getBrowserCapabilityResolver(): BrowserCapabilityResolver</code></summary>

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
  <summary><code>public function getRandom(): RandomGeneratorInterface</code></summary>

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
