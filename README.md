# Laravel Biometric Auth

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)
[![Tests](https://github.com/laranex/laravel-biometric-auth/actions/workflows/tests.yml/badge.svg)](https://github.com/laranex/laravel-biometric-auth/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/laranex/laravel-biometric-auth.svg?style=flat-square)](https://packagist.org/packages/laranex/laravel-biometric-auth)
[![License](https://img.shields.io/packagist/l/laranex/laravel-biometric-auth.svg?style=flat-square)](LICENSE.md)

Biometric authentication for Laravel: register device public keys, issue single-use challenges and verify signatures. Built for humans and AI agents.

Signing in with Face ID or a fingerprint should not mean storing a password or a long-lived secret on the phone. With Laravel Biometric Auth the device keeps its private key, your API stores only the public key, and every sign-in is the device signing a fresh, one-time challenge.

## Why Laravel Biometric Auth

- **The private key never leaves the device.** Your database holds public keys only, so a leaked table cannot be used to sign in.
- **Single-use, expiring challenges.** Each challenge is random, expires after 5 minutes by default and is cleared once verified, so a captured signature cannot be replayed, even when two requests race.
- **Guessing is capped.** A challenge is cleared after 5 failed signatures by default, and each user can enroll at most 10 devices.
- **Only strong keys.** EC P-256 and P-384, Ed25519 and 2048 to 4096-bit RSA, checked when a device enrolls and again at every verification.
- **One trait on your model.** `createBiometric()`, `revokeBiometric()` and `revokeAllBiometrics()` on any Eloquent model, with morph map support.
- **API-ready errors.** Unknown devices, missing challenges and rejected keys become JSON errors with a 404 or 422 status.
- **Your tokens, your choice.** Once `verifyBiometric()` returns `true`, issue whatever your API already uses, such as Sanctum or [Laravel Refresh Token](https://laranex.vercel.app/laravel-refresh-token/introduction).

## Installation

Requires PHP 8.1+, Laravel 10, 11, 12 or 13, and phpseclib 3 (3.0.57 or later) or 4.

```bash
composer require laranex/laravel-biometric-auth
```

## Documentation

The full documentation lives at **[laranex.vercel.app/laravel-biometric-auth](https://laranex.vercel.app/laravel-biometric-auth)**:

- [Laravel Biometric Auth introduction](https://laranex.vercel.app/laravel-biometric-auth/introduction): why use it and what it does
- [Installing Laravel Biometric Auth](https://laranex.vercel.app/laravel-biometric-auth/installation): Composer, the migration and the publish tags
- [Laravel Biometric Auth AI agent skill](https://laranex.vercel.app/laravel-biometric-auth/ai-agent-skill): the skill for Claude Code, Codex, Cursor and other coding agents
- [Laravel Biometric Auth configuration](https://laranex.vercel.app/laravel-biometric-auth/configuration): the table, the device limit, challenge expiry and attempts, RSA settings and accepted keys
- [Upgrading Laravel Biometric Auth](https://laranex.vercel.app/laravel-biometric-auth/upgrading): from v3 to v4
- [Laravel Biometric Auth usage](https://laranex.vercel.app/laravel-biometric-auth/usage): register a device, issue and verify challenges, revoke devices and handle exceptions

## License

MIT. See [LICENSE.md](LICENSE.md).
