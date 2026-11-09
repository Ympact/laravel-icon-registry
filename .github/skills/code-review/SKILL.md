---
name: code-review
description: Conventions this repository has already settled, so review effort goes to defects rather than to re-litigating them.
---

# Reviewing ympact/laravel-icon-registry

A catalogue of the icons a Laravel application can draw, with search, a validation rule and a
cache. Bugs hurt most where a stored key stops resolving (a page throws on render) and where the
metadata rewrite loses or corrupts an application's file.

Full conventions live in [`agents.md`](../../../agents.md). This file records the decisions that
reviews keep raising, and what tests already enforce, so neither needs raising again.

## Settled decisions: please do not re-raise

**`@param` / `@return` tags are required only where the native type is not the whole answer.**
An array or iterable shape, a generic, a union, or a nullable whose `null` needs explaining. They
are not wanted where they would restate the signature: `bool`, `int`, `float`, `string`, an enum, a
concrete class, `self`, `static`. `void` and `never` take no `@return`; `@throws` always.

**Pest tests use `it('...')` with a behavioural sentence.** Names describe what is guaranteed, not
what is called, and may be long.

**Flux and Blade Icons are `suggest`, not `require`.** Providers check `isAvailable()` and are
skipped without their package; the `without-optional-packages` CI job proves the package boots
with neither. Do not propose making either a hard dependency.

**Blade Icons sets are read with `Factory::all()`, which Blade Icons marks `@internal`.** There is
no public alternative; the provider scans the set paths itself rather than read Blade Icons'
manifest, which is memoised before late-registered sets exist.

**The Blade tag defaults to `registry-icon`, not `icon`.** Blade Icons registers `<x-icon>` by
default, so the shorter name would collide. The tag is configurable.

**Documentation drift is enforced by tests.** `ConfigurationDocsTest` fails on a config key missing
from the configuration page, `CommandsDocsTest` on a command, argument or option missing from the
commands reference, `DocsIndexTest` on a page not linked from the index, and `FacadeDocsTest` on a public registry
method without a `@method` tag on the facade.

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
