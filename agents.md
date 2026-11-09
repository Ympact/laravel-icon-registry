---
applyTo: "**/*"
---
# Icon Registry

This file is for coding agents working in this repo. Follow it literally.

## Project context

- **ympact/laravel-icon-registry is a Laravel package**: a searchable catalogue of the icons an application can draw, across Flux, Blade Icons and custom providers.
- **Operate like a cofounder.** Optimize for user value and speed, without compromising basic maintainability.

## Non‑negotiables

- **Do not overwrite user edits.** The user may change code between messages. If something changed, understand *why* and build on it.
- **Keep changes simple.** Implement the smallest change that solves the problem (unless you’re writing tests).
- **Fix root causes.** When debugging, gather enough info to understand the failure and fix it at the source (not via band-aids).

## Developer friendly

- Well documented, use proper PHPDoc blocks
- Make classes extendable (either by allowing overriding through config and/or macroable).
- When providing migrations, make sure a configurable prefix can be added to avoid conflicts with existing tables.
- Create builder classes for fluent usage of the API, and support conditionable/tappable traits.
- Clear API interfaces.
- Prefer DTOs over raw array data inputs and exchange.
- Use Action classes to concentrate business logic and for easy implementation of Livewire components, jobs, Artisan commands, API calls, etc.

## Architecture & structure (Laravel)

- **Prefer small, verb-named Actions.** Avoid generic “Service/Manager/Handler” classes.
- **Controllers stay thin.** Single-action controllers are preferred.
- **Avoid events unless necessary.** Keep code flow obvious without jumping between files.
- **Jobs are thin + idempotent.** Delegate business logic to Actions.
- **If you create a model, also create a factory + seeder.**

## Code style (PHP)

- **Follow PSR-12 coding standards**
- **Document intent** for non-obvious code (explain *why*, not *what*). Inline comments explain *why* in one sentence; a complex algorithm may have a short *what* orientation comment (max 2 lines total). Never comment a single line whose code already expresses the intent.
- **Class docblocks are required.** Every class/trait/interface/enum under `src/` must have a top-level PHPDoc block: one concise paragraph (max 2 sentences) stating what the class does and any non-obvious contract. No `Purpose:`, `Why extracted:`, or `Contract:` label prefixes.
- **Method docblocks:** required on all public methods and properties. The test for a `@param`/`@return` tag is whether the native type is the whole answer:
  - **Tag it when the type is not the whole answer**: an array or iterable shape, a generic (`Collection<int, User>`, `Builder<Post>`), a union, or a nullable whose `null` needs explaining. PHPStan runs at level 8 with an empty baseline, so most of these are load-bearing rather than decorative.
  - **Leave it off when the tag would restate the signature**: `bool`, `int`, `float`, `string`, an enum, a concrete class, `self`, `static`. `@return bool Whether it is expired.` above `isExpired(): bool` adds nothing and goes stale the moment the type changes. Put what the method is *for* in the description instead.
  - **The test is the type, not the keyword.** That list is where a tag *usually* restates; it is not a ban. A `string` whose domain is constrained (`@param string $mode One of self::MODES.`) or a `bool` whose two values need telling apart (`@return bool False when the request has already been settled.`) both say something the signature cannot, so both earn their tag. Ask what the reader still would not know.
  - **`void` and `never` take no `@return`.**
  - **Always `@throws`** when an exception can escape, saying when it is thrown rather than restating the class name.
  - Non-public methods: tag only for mixed/union types, non-obvious constraints, or ambiguous parameter names.
  - Do not narrate the method body step by step.
- **Claim only what the diff makes true.** A changelog headline, a docblock or a guide states the behaviour that exists after the change, not the one that was aimed at. Where a change *narrows* a problem rather than removing it, say which: "the outcomes no longer disagree" is true; "the mistake is now impossible" is not, if the mistake is still reachable. The same applies to guarantees: "nothing restricted is pruned" needs the race that remains stated, or it reads as a promise the code does not keep.
- **Import namespaces.** Don’t rely on implicit/global imports.
- **Avoid ambiguous names.** No one-letter variables unless extremely local and obvious.
- **Use guard clauses** over deep nesting.
- **No debugging helpers** in committed code (`dd()`, `dump()`, etc.).
- **Do not use `final`** on behavioural classes. Readonly DTOs and value objects are fine to be final.
- **Never use `@`** (PHP error suppression). If you truly must, document why and prefer explicit alternatives.
- **Guard `glob()` with `?: []`.** It returns `false` on failure, and `foreach` over `false` warns instead of failing where you can see it.
- **Default to `protected`** for non-public methods/properties unless there’s a strong reason.
- **Use self explanatory code.**
- **Dealing with errors/exceptions first in methods** or other things that could go wrong before tackling the happy path.

## Laravel conventions & dependency boundaries

- **Do things the Laravel way.** Use helpers/Collections/Facades/attributes.
- **Prefer Contracts + Dependency injection** over facades or `app()`. Provide a facade as a convenience layer for end-users of the package, while keeping internal code DI-based.
- **Do not call `env()`** outside config files.
- **Prefer named routes** + `route()` over hardcoded URLs (including in app code).
- **Prefer helpers over Facades** when available (e.g. `session()` over `Session::get()`).
- **Avoid raw queries.** If unavoidable, parameterize and document why.

## Data & migrations

Only after v1.0.0 is tagged:

- **Migrations should be reversible** when possible.
- **Never edit old migrations** after they’ve been merged. Create a new migration.

## Frontend (Blade + Tailwind + Alpine)

- **HTML must be tidy, valid, semantic, and accessible.**
- **Close inline tags** (`<meta />`, `<img />`, `<br />`, …).
- **Prefer landmarks** (`header`, `nav`, `main`, `footer`) over generic wrappers.
- **Keep focus outlines.** Focus states should be visible and intentional.
- **Every input needs a `<label>`** (via `for` + `id`) unless there’s a strong reason.

### Styling (Tailwind v4)

- Prefer Tailwind utilities over custom CSS.
- If custom CSS is necessary, keep it minimal and document why.
- Extract repeated UI patterns into Blade components (don’t copy/paste huge class strings).

### Interactivity (Alpine.js in Blade)

- Alpine code belongs in the Blade component.
- Use `x-cloak` to avoid flashes during init.
- Keep state small and local (avoid hidden global state).

### Accessibility

- **Accessibility is important**. Make sure that the application uses at least WCAG 2.
- Try to conform to EN 301 549 / European Accessibility Act (EAA).
- Add tests for testing accessiblity (preferably using Pest v4 Browser testing).
- In case of doubts or incompatibility with EN 301 549 / WCAG 2, document this in `/accessibility`.
- Keep ARIA attributes in sync with state (e.g. `aria-expanded`).
- Decorative icons get `aria-hidden="true"`; informative icons need an accessible name.

### Readability conventions (Blade)

- When an element has many attributes, format them one per line.
- Top-of-file Blade comment blocks use:
  - `{{--` on its own line,
  - A capitalized sentence ending in a period,
  - `--}}` on its own line

## Documentation

- Package documentation is in `./docs`.
- Use the [document stubs](./docs/stubs) as a starting point.
- Package should be documented for developers in mind in the following areas:
  - **Getting started**: installation, configuration, basic usage.
  - **Common usage**: description and usage of common features for typical use cases.
  - **Advanced usage**: advanced usage such as extending the package, implementing custom providers, etc.
  - **Examples**: in-depth guides/examples for common use cases and features.
  - **Reference**: detailed reference for public classes/methods/properties.

## Development workflow

- Developing new features:
  - Start with creating a branch named `feature/feature-name`.
  - Keep a `log-{feature-name}.md` in the root with the aim, requirements and plan, and update it as you go. It is for local work only: `.gitignore` keeps `/log-*.md` out of the repository.
- Bug fixing:
  - Work on a `fix/bug-name` branch and open a pull request to `main`.
- Pull request titles follow Conventional Commits (`type(scope): summary`).
- Before committing: always check [Definition of Done](#definition-of-done) and [Review behavior](#review-behavior) sections below.
- After finishing a feature or fixing a bug we PR into `main`.

## Release process

- Run CI on `main` and make sure all checks pass.
- The version is the git tag; `composer.json` has no `version` field. Decide the number according to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
- make sure CHANGELOG is up to date. CHANGELOG does not need to be exhaustive nor include a list of future improvements/roadmap items.
- Make sure the [roadmap](./docs/roadmap.md) is current: remove what was released, add what came up.
- Update `llms.txt`, the agent reference for applications that use this package.
- Discuss what the version number should be with the team, and make sure it follows Semantic Versioning.
- Commit, sync with remote, tag and push the new version.
- Draft a new release on GitHub. The release notes should be concise. Link the changelog at the bottom for full description. Do not publish the release yourself.
- remove branches that we're pr'ed into `main` within this release.

## Testing (Pest v4)

- Test files mirror `./src` structure 1:1 into `/tests/Feature` for all non-livewire php classes
  - Same goes for Unit and Browser tests: mirror those into `/tests/Unit` and `/tests/Browser` respectively.
  - If there is no matching `src/` file, only then place tests at the root (e.g. `./tests/Feature`) with a clear justification.
- Security tests go in `/tests/Security` and follow the same mirroring principle.
- Avoid hardcoded hosts/URLs; prefer `route()` / `url()`.
- Prefer strict fakes over permissive mocks.
- Tests must be parallel-safe: avoid shared fixed file paths and clean up created files.
- Import Pest global functions (e.g. `use function Pest\Laravel\actingAs;`).
- Avoid `$this` in Pest tests; prefer the equivalent global functions.
- Use Real-Time Facades if you need to mock something resolved from the container.

### Database-specific tests

Tests that depend on the database driver are tagged `database` (`pest()->group('database')`). CI runs that group against MySQL and PostgreSQL when the database job is enabled; SQLite hides whole classes of failure (foreign keys, identifier limits, JSON ordering), so run the group against a real driver before pushing anything that touches SQL or a migration.

## Definition of done

1. **PHPdocs:** all public methods/properties have docblocks, tagged per the `@param`/`@return`/`@throws` rule in **Code style (PHP)**: tags where the native type is not the whole answer, not where they would restate it. Non-public methods get docblocks when the logic is non-obvious or the param/return types are mixed, union, or otherwise ambiguous. Every class/trait/interface/enum under `src/` has a concise 1–2 sentence class-level docblock (no `Purpose:` / `Why extracted:` / `Contract:` labels). Inline comments explain *why* in one sentence; complex algorithms may add up to 2 lines of *what* orientation.
2. **Sanity:** no debug helpers left behind; migrations reversible; UI remains accessible; minimal change set.
3. **Security**: no new security issues introduced (e.g. injection, mass assignment, policy enforcement, idempotency, webhook signature verification). The security test suite is green
4. **Format:** `./vendor/bin/pint --parallel`
5. **Tests:** `./vendor/bin/pest --parallel` (use `--filter` when iterating)
6. **Static analysis:** `./vendor/bin/phpstan analyse`. Uses larastan/larastan at level 8. Do not add new errors to the baseline or suppress them.
7. **Documentation:** Update the docs. Use [doc stubs](/docs/stubs/) for outline and authoring notes (new and existing doc pages).
8. **Changelog:** Update `CHANGELOG.md` with implemented features and future improvements. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Review behavior

Whenever you touch code, ask yourself:

- **Developer friendliness** is a core value. “Is this easy to understand, implement in apps and extend?”
- **Documentation check**: make sure the code is properly documented with concise PHPDoc blocks, and that any new features or public APIs are reflected in the documentation (either in existing pages or new ones).
- **Every page that describes what you changed**, not just the nearest one. A public signature appears in `docs/reference/`; a config key in the configuration page *and* in the guide for its feature; a command's output in its own page. `tests/Feature/Docs/DocsIndexTest.php` checks that every page is reachable from `docs/index.md`; the prose is yours. Grep for the old behaviour before assuming nothing else mentions it.
- **Existence check**: for every `src/` file you create or edit, confirm it earns its existence. If it’s redundant/unused/over-abstracted, prefer deleting/merging/moving it (and updating routes/usages/tests).
- **Logic check**: inside kept files, remove or simplify any code that isn’t justified (dead branches, unused options, placeholder copy, unnecessary indirection).
- **Test alignment**: keep tests mirrored to the `src/` structure 1:1 under `/tests/{TestSuite}/`. Update or delete tests alongside code changes.
- **A new guard is not done until it has been seen to fail.** Break what it guards, watch it go red, put it back.
