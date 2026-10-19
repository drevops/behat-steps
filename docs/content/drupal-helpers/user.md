---
title: "Users - Drupal helpers"
sidebar_label: "Users"
sidebar_position: 26
description: "Manage Drupal users with role and permission assignments."
custom_edit_url: null
---

# Users

`DrevOps\BehatSteps\Steps\Drupal\UserTrait` - [Source](../../../src/Steps/Drupal/UserTrait.php), [Steps](../drupal-steps/user.md)

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
  <summary><code>public function userCreate(array $values): EntityStubInterface</code></summary>

<br/>
Create a user
<br/><br/>

</details>

<details>
  <summary><code>public function userCreateAndLogin(string $roles, array $extra_fields = []): void</code></summary>

<br/>
Create a user carrying the roles and extra fields, and log in as them
<br/><br/>

</details>

<details>
  <summary><code>public function userCreateRoleWithPermissions(string $role, array $permissions): EntityStubInterface</code></summary>

<br/>
Create a role with permissions, replacing a role with the same ID
<br/><br/>

</details>

<details>
  <summary><code>public function userCreateWithPermissions(array $permissions): EntityStubInterface</code></summary>

<br/>
Create a user with a new role carrying permissions
<br/><br/>

</details>

<details>
  <summary><code>public function userDelete(array $values): void</code></summary>

<br/>
Delete the users with an email address or, without one, a name
<br/><br/>

</details>

<details>
  <summary><code>public function userExistsByMail(string $mail): bool</code></summary>

<br/>
Check whether a user with the given email address exists
<br/><br/>

</details>

<details>
  <summary><code>public function userGetByName(string $name): EntityStubInterface</code></summary>

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
  <summary><code>public function userVisitActionPage(string $name, string $rel = 'canonical'): void</code></summary>

<br/>
Visit a page of a user
<br/><br/>

</details>

<details>
  <summary><code>public function userVisitPasswordResetLinkForUser(EntityStubInterface $user): void</code></summary>

<br/>
Visit the password reset link for a user
<br/><br/>

</details>
