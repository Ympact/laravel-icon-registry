---
name: code-review
description: Conventions this repository has already settled, so review effort goes to defects rather than to re-litigating them.
---

# Reviewing ympact/laravel-icon-registry

[One or two sentences on what the package does, and where its bugs would hurt most.]

Full conventions live in [`agents.md`](../../../agents.md). This file records the decisions that
reviews keep raising, and what tests already enforce, so neither needs raising again.

## Settled decisions: please do not re-raise

**`@param` / `@return` tags are required only where the native type is not the whole answer.**
An array or iterable shape, a generic, a union, or a nullable whose `null` needs explaining. They
are not wanted where they would restate the signature: `bool`, `int`, `float`, `string`, an enum, a
concrete class, `self`, `static`. `void` and `never` take no `@return`; `@throws` always.

**Pest tests use `it('...')` with a behavioural sentence.** Names describe what is guaranteed, not
what is called, and may be long.

<!-- Add each convention here the second time a review argues it, with the reason and the test
that enforces it, if any. -->

## Where review effort pays

- **Claims that outrun the diff.** Changelog entries, docblocks and guides that promise more than
  the code does.
- **Test coverage of the branch that matters.** Does a new guard have a test that fails without it?

## Repository facts

| | |
| --- | --- |
| PHP | 8.3+ · Laravel 12–13 |
| Tests | Pest, `vendor/bin/pest` (Browser suite: `composer test:browser`) |
| Static analysis | PHPStan/Larastan **level 8, empty baseline**: `vendor/bin/phpstan analyse` |
| Style | Laravel Pint: `vendor/bin/pint --test` |
