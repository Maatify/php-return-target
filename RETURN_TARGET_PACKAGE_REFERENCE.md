# Return Target Package Reference

## Package Identity

- **Composer:** `maatify/php-return-target`
- **Namespace:** `Maatify\ReturnTarget`
- **PHP:** `^8.4`
- **License:** `proprietary`
- **Status:** Development / Unpublished

## Package Purpose

This package is intended to provide framework-agnostic safe internal return-target handling.

## Current Boundary

- Runtime implementation has not started.
- Public Runtime API inventory: **None implemented yet**.
- The package currently has no persistence, database, SQL, or PDO behavior.
- The package does not own framework, HTTP, router, session, or controller behavior.
- The package does not contain Host-specific authentication flows.
- Return-target interfaces, validators, token handling, crypto, expiry, middleware, and redirect resolution are not implemented in this boundary.

## Source Topology

**Source Topology: Single Capability**

The `src/` tree will be materialized when the first Runtime Work Unit establishes the approved source implementation. No placeholder source directory or class is part of this bootstrap.

## Composer Ownership

`composer.json` is the canonical source for Composer identity, dependencies, production autoloading, configuration, stability policy, and distribution metadata. This Package Reference does not duplicate or override that manifest.
