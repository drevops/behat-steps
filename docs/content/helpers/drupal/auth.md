---
title: "Drupal\\AuthTrait helpers"
sidebar_label: "AuthTrait"
sidebar_position: 25
description: "Creates users and roles, logs them in, and removes them afterwards."
custom_edit_url: null
---

# Drupal\AuthTrait helpers

[Source](../../../../src/Steps/Helper/Drupal/AuthTrait.php)

> Creates users and roles, logs them in, and removes them afterwards.

<details>
  <summary><code>public function authCreateUser(EntityStubInterface $stub): EntityStubInterface</code></summary>

<br/>
Creates a user
<br/><br/>

</details>

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
  <summary><code>public function authLogout(bool $is_fast = FALSE): void</code></summary>

<br/>
Logs the current user out
<br/><br/>

</details>
