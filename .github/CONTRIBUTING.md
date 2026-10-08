# Contribution Guide

Thank you for considering contributing to Laravel Biometric Auth! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run all tests:

```bash
composer test
```

## Releasing

Pushing a version tag releases the package: `.github/workflows/release.yml` runs the test suite, then creates the GitHub release, and Packagist picks the tag up.

1. Add the release's section to `CHANGELOG.md` (`## v4.1.0 - 2026-10-08`). Its body becomes the GitHub release notes; when no section matches the tag, the notes are generated from the merged pull requests instead.
2. Merge into `master`.
3. Tag the merged commit and push the tag:

   ```bash
   git tag v4.1.0
   git push origin v4.1.0
   ```

That's it. Tags must be semantic versions (`vMAJOR.MINOR.PATCH`); a pre-release suffix such as `v4.1.0-rc.1` marks the GitHub release as a pre-release. Don't add a `version` to `composer.json`: Packagist reads versions from the tags. `CHANGELOG.md` is written by hand before tagging; nothing commits it back after the release.

Optional repository secrets: set `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` (your API token from packagist.org, Profile → Show API Token) to have the workflow ping Packagist so the version appears immediately. Without them the step is skipped and Packagist updates through its GitHub hook.
