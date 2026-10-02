# Usage Guide

## Package Fit

`maatify/php-return-target` is a framework-agnostic library for turning an accepted internal return target into an opaque, signed, expiring transport token and recovering it through a public verification API. It does not perform a redirect.

## Requirements and Non-Goals

The package requires PHP `^8.4`, `ext-hash`, `ext-json`, `maatify/crypto ^1.0`, `maatify/exceptions ^1.0`, and `maatify/shared-common ^1.0`. The Host must provide a public `KeyProviderInterface` implementation and a `ClockInterface` implementation.

The canonical `HmacReturnTargetService` requires exactly one ACTIVE key with a
non-empty active key ID. It consumes Host key material through the public
`KeyProviderInterface`. Its token is signed, not encrypted; confidential information
and secrets must not be placed in the return target or token.

The package does not own HTTP, routing, sessions, authentication, authorization, persistence, databases, token consumption, key storage, or redirect execution.

## Public Construction Boundary

Construct `HmacReturnTargetService` with `ReturnTargetConfig`, a Host-owned `KeyProviderInterface`, a Host-owned `ClockInterface`, and optionally a `ReturnTargetRestrictionPolicyInterface`. The optional policy is restrict-only: it can reject a canonically valid inspection target but cannot widen canonical safety validation.

## Inputs, Outputs, and Rejection Semantics

- `accepts(string $target): bool` reports current acceptance.
- `issue(string $target): ?string` returns an opaque token or `null` for normal target rejection.
- `verify(string $token): ?VerifiedReturnTargetDTO` returns the exact target and authoritative `expiresAt`, or `null` for normal malformed, untrusted, expired, or currently rejected tokens.
- Canonical configuration and classified crypto failures remain exceptions. Unknown provider and Host-policy throwables propagate; they are not silently converted to normal rejection.

## Capability Map

| Capability | Walkthrough | Maintained example |
| --- | --- | --- |
| Public service construction and token issue/verify | [Basic workflow](#basic-workflow) | [`examples/basic_usage.php`](../../examples/basic_usage.php) |
| Exact target and deterministic expiry | [Basic workflow](#basic-workflow) | [`examples/basic_usage.php`](../../examples/basic_usage.php) |
| Restrict-only policy and current-policy rejection | [Policy workflow](#policy-workflow) | [`examples/basic_usage.php`](../../examples/basic_usage.php) |
| Unsafe target and expiry rejection | [Rejection workflow](#rejection-workflow) | [`examples/basic_usage.php`](../../examples/basic_usage.php) |

## Basic Workflow

Input → Public Call → Result → Boundary:

1. **Input:** the Host receives an internal target and owns a key provider and clock.
2. **Public Call:** construct `HmacReturnTargetService`, call `issue($target)`, then call `verify($token)`.
3. **Result:** the public `VerifiedReturnTargetDTO` contains the exact original target and `expiresAt` from the injected clock.
4. **Boundary:** the Host decides whether and how to execute its redirect.

The complete runnable construction is in [`examples/basic_usage.php`](../../examples/basic_usage.php). Its key material is explicitly example-only and must not be used in production.

## Policy Workflow

Input → Public Call → Result → Boundary:

1. **Input:** the Host supplies an optional `ReturnTargetRestrictionPolicyInterface`.
2. **Public Call:** the service evaluates the policy after canonical validation during acceptance, issuance, and verification.
3. **Result:** a policy can reject a target or a previously issued token when current policy no longer allows it; it cannot make an unsafe target acceptable.
4. **Boundary:** policy rules remain Host-owned, while canonical target and token rules remain package-owned.

The maintained runnable example demonstrates this workflow through the Public API. The external Consumer Verification Harness separately proves the same consumer boundary; it is verification evidence, not a consumer example.

## Rejection Workflow

Input → Public Call → Result → Boundary:

1. **Input:** an unsafe target, malformed token, currently disallowed target, or token at `now >= expiresAt`.
2. **Public Call:** call `accepts()`, `issue()`, or `verify()` through the documented service API.
3. **Result:** normal rejection is `false` or `null`; configuration and classified crypto failures remain exceptional.
4. **Boundary:** the Host must not treat a normal rejection as a fallback target; it chooses its own fallback or response behavior.

## Query Targets Containing Spaces

A target whose query contains a percent-encoded space, such as `/search?q=two%20words`, is accepted by the canonical service. The same encoded space in the path, such as `/p%20x?q=1`, and any raw space anywhere are rejected. A `+` stays a literal `+`. `verify()` returns the exact original representation (`/search?q=two%20words`); it is never rewritten to a decoded or `+` form. A restriction policy sees the single-decoded inspection form (`/search?q=two words`) once per evaluation. This behavior is part of the `v1.0.0-rc.2` preparation work, which is not yet published; the published `v1.0.0-rc.1` rejects such targets.

## Canonical Contract

The [Package Reference](../../RETURN_TARGET_PACKAGE_REFERENCE.md) is the canonical public/runtime/behavioral contract and complete Public Runtime API inventory. This guide provides consumer integration guidance and does not replace it.

## Development Verification

The repository's Consumer Verification Harness uses a separate Composer root and clean-state runs to prove production autoload and external-consumer behavior. It is a verification gate and is not a substitute for the maintained example above.
