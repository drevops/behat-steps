---
title: "Drupal\\UserTrait steps"
sidebar_label: "UserTrait"
sidebar_position: 27
description: "Manage Drupal users with role and permission assignments."
custom_edit_url: null
---

# Drupal\UserTrait steps

[Source](../../../../src/Steps/Drupal/UserTrait.php), [Example](../../../../tests/behat/features/drupal_user.feature), [Helpers](../../helpers/drupal/user.md)

>  Manage Drupal users with role and permission assignments.
>  - Create user accounts
>  - Create user roles
>  - Visit user profile pages for editing and deletion.
>  - Assert user roles.
>  - Assert user account status (active/inactive).

## Prerequisites

| Prerequisite | Capability |
| --- | --- |
| A backend in the scenario's list provides "UserCapabilityInterface" | `UserCapabilityInterface` |

## Steps

<details>
  <summary><code>@Given the following users do not exist:</code></summary>

<br/>
Remove users specified in a table
<br/><br/>

```gherkin
Given the following users do not exist:
  | name |
  | John |
  | Jane |
 Given the following users do not exist:
   | mail             |
   | john@example.com |
   | jane@example.com |
```

</details>

<details>
  <summary><code>@Given the following users with fields exist:</code></summary>

<br/>
Create users with vertical field format
<br/><br/>

```gherkin
Given the following users with fields exist:
  | name  | [TEST] user1         | [TEST] user2         |
  | mail  | user1@example.com    | user2@example.com    |
  | roles | editor               | author               |
```

</details>

<details>
  <summary><code>@Given the following users exist:</code></summary>

<br/>
Create users from a table of field values
<br/><br/>

```gherkin
Given the following users exist:
  | name         | mail              | roles  |
  | [TEST] user1 | user1@example.com | editor |
```

</details>

<details>
  <summary><code>@Given I am an anonymous user</code></summary>

<br/>
Log the current user out so the session is anonymous
<br/><br/>

```gherkin
Given I am an anonymous user
```

</details>

<details>
  <summary><code>@Given the password for the user :name is :password</code></summary>

<br/>
Set a password for a user
<br/><br/>

```gherkin
Given the password for the user "John" is "password"
```

</details>

<details>
  <summary><code>@Given the last access time for the user :name is :datetime</code></summary>

<br/>
Set last access time for a user
<br/><br/>

```gherkin
Given the last access time for the user "John" is "Friday, 22 November 2024 13:46:14"
Given the last access time for the user "John" is "1732319174"
```

</details>

<details>
  <summary><code>@Given the last login time for the user :name is :datetime</code></summary>

<br/>
Set last login time for a user
<br/><br/>

```gherkin
Given the last login time for the user "John" is "Friday, 22 November 2024 13:46:14"
Given the last login time for the user "John" is "1732319174"
```

</details>

<details>
  <summary><code>@Given the role :role has the permission(s) :permissions</code></summary>

<br/>
Create a single role with specified permissions
<br/><br/>

```gherkin
Given the role "Content Manager" has the permissions "access content, create article content, edit any article content"
```

</details>

<details>
  <summary><code>@Given the following roles exist:</code></summary>

<br/>
Create multiple roles from the specified table
<br/><br/>

```gherkin
Given the following roles exist:
  | name              | permissions                              |
  | Content Editor    | access content, create article content   |
  | Content Approver  | access content, edit any article content |
```

</details>

<details>
  <summary><code>@Given I am logged in as a user with the role(s) :roles</code></summary>

<br/>
Create a user with the roles and log in as them
<br/><br/>

```gherkin
Given I am logged in as a user with the role "editor"
Given I am logged in as a user with the roles "editor, admin"
```

</details>

<details>
  <summary><code>@Given I am logged in as a user with the role(s) :roles and I have the following fields:</code></summary>

<br/>
Create a user with the roles and fields, and log in as them
<br/><br/>

```gherkin
Given I am logged in as a user with the role "editor" and I have the following fields:
  | field_user_name    | John  |
  | field_user_surname | Smith |
```

</details>

<details>
  <summary><code>@Given I am logged in as a user with the permission(s) :permissions</code></summary>

<br/>
Create a role carrying the permissions, then log in as a user with it
<br/><br/>

```gherkin
Given I am logged in as a user with the permission "administer nodes"
Given I am logged in as a user with the permissions "administer nodes, access content"
```

</details>

<details>
  <summary><code>@Given I am logged in as the user :name</code></summary>

<br/>
Log in as an existing user created earlier in the scenario
<br/><br/>

```gherkin
Given I am logged in as the user "[TEST] user1"
```

</details>

<details>
  <summary><code>@When I log out</code></summary>

<br/>
Log the current user out
<br/><br/>

```gherkin
When I log out
```

</details>

<details>
  <summary><code>@When I visit the profile page of the user :name</code></summary>

<br/>
Visit the profile page of the specified user
<br/><br/>

```gherkin
When I visit the profile page of the user "John"
```

</details>

<details>
  <summary><code>@When I visit my own profile page</code></summary>

<br/>
Visit the profile page of the current user
<br/><br/>

```gherkin
When I visit my own profile page
```

</details>

<details>
  <summary><code>@When I visit the profile edit page of the user :name</code></summary>

<br/>
Visit the profile edit page of the specified user
<br/><br/>

```gherkin
When I visit the profile edit page of the user "John"
```

</details>

<details>
  <summary><code>@When I visit my own profile edit page</code></summary>

<br/>
Visit the profile edit page of the current user
<br/><br/>

```gherkin
When I visit my own profile edit page
```

</details>

<details>
  <summary><code>@When I visit the profile delete page of the user :name</code></summary>

<br/>
Visit the profile delete page of the specified user
<br/><br/>

```gherkin
When I visit the profile delete page of the user "John"
```

</details>

<details>
  <summary><code>@When I visit my own profile delete page</code></summary>

<br/>
Visit the profile delete page of the current user
<br/><br/>

```gherkin
When I visit my own profile delete page
```

</details>

<details>
  <summary><code>@When I visit the password reset link for the user :name</code></summary>

<br/>
Visit the password reset link for a user
<br/><br/>

```gherkin
When I visit the password reset link for the user "admin"
When I visit the password reset link for the user "test_user"
```

</details>

<details>
  <summary><code>@When I visit my own password reset link</code></summary>

<br/>
Visit the password reset link for the currently logged-in user
<br/><br/>

```gherkin
When I visit my own password reset link
```

</details>

<details>
  <summary><code>@Then the user :name should have the role(s) :roles assigned</code></summary>

<br/>
Assert that a user has roles assigned
<br/><br/>

```gherkin
Then the user "John" should have the roles "administrator, editor" assigned
```

</details>

<details>
  <summary><code>@Then the user :name should not have the role(s) :roles assigned</code></summary>

<br/>
Assert that a user does not have roles assigned
<br/><br/>

```gherkin
Then the user "John" should not have the roles "administrator, editor" assigned
```

</details>

<details>
  <summary><code>@Then the user with the email :address should exist</code></summary>

<br/>
Assert that a user with an email address exists
<br/><br/>

```gherkin
Then the user with the email "alice@example.com" should exist
```

</details>

<details>
  <summary><code>@Then the user with the email :address should not exist</code></summary>

<br/>
Assert that a user with an email address does not exist
<br/><br/>

```gherkin
Then the user with the email "alice@example.com" should not exist
```

</details>

<details>
  <summary><code>@Then the user :name should be blocked</code></summary>

<br/>
Assert that a user is blocked
<br/><br/>

```gherkin
Then the user "John" should be blocked
```

</details>

<details>
  <summary><code>@Then the user :name should not be blocked</code></summary>

<br/>
Assert that a user is not blocked
<br/><br/>

```gherkin
Then the user "John" should not be blocked
```

</details>
