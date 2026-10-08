---
name: package-generate-skill
description: "Use this skill when creating or updating the shipped agent skill (resources/boost/skills/laravel-biometric-auth/SKILL.md and its identical copy skills/laravel-biometric-auth/SKILL.md) from the package implementation. Trigger after public APIs, commands, config, routes, views, publish tags, or examples change."
license: MIT
metadata:
  author: laravel
---

# Package Generate Skill

## Primary Goal

Keep the package's shipped agent skill accurate, concise, and focused on how an application uses the package. The skill lives at `resources/boost/skills/laravel-biometric-auth/SKILL.md` (Laravel Boost) and an identical copy at `skills/laravel-biometric-auth/SKILL.md` (`npx skills`); `tests/Unit/AgentSkillTest.php` fails when the two differ.

## Workflow

1. Inspect the package implementation before editing the Boost skill: service provider, facades, public classes, commands, config, routes, migrations, events, views, publish tags, and tests.
2. Inspect the user-facing behavior described in the changelog and on the documentation website (laranex.vercel.app/laravel-biometric-auth); the README only links there.
3. Identify the public integration surface only. Include install, configure, publish, command, route, facade, helper, middleware, event, and testing guidance only when the package actually exposes it.
4. Update `resources/boost/skills/laravel-biometric-auth/SKILL.md`, then copy it unchanged to `skills/laravel-biometric-auth/SKILL.md`.
5. Preserve the front matter (`name: laravel-biometric-auth`, description, license, metadata) and the usage-only structure: When to use, Install, Configure, Use (one subsection per feature), Test your app, Avoid. No sections about maintaining, releasing or regenerating the package or the skill.
6. Validate that the Boost skill does not describe internals as public API and does not document features that are not implemented.

## Writing Rules

- Write for consumers installing the package in a Laravel application, not for maintainers changing package internals.
- Prefer short, task-oriented steps over broad explanations.
- Point to concrete files only when they help the consumer understand package adoption.
- Keep examples runnable and aligned with documented package names, config keys, commands, and publish tags.
- Keep the skill small enough for an agent to load and apply quickly.

## References

- `resources/boost/skills/laravel-biometric-auth/SKILL.md`
- `skills/laravel-biometric-auth/SKILL.md`
- `src/*ServiceProvider.php`
- `src/Facades/`
- `src/Console/Commands/`
- `config/*.php`
- `routes/*.php`
- `database/migrations/`
- `tests/Feature/` and `tests/Unit/`

## Examples

- After adding a new Artisan command, update the Boost skill with when to run it, required options, expected output, and any related config.
- After adding config and publish tags, update the Boost skill with publish commands and the minimum config keys users need to set.
- After adding a facade or public class, update the Boost skill with one practical integration example and the test assertion a consuming app should use.

## Anti-Patterns

- Regenerating the Boost skill from documentation alone without checking implementation.
- Documenting private classes, test helpers, workbench-only routes, or implementation details as consumer API.
- Adding speculative examples for features the package does not provide.
- Removing package metadata that consuming agents need to identify and apply the skill.
