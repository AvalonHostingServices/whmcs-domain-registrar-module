/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/constants.ts — Shared numeric constants and role aliases
 * used across all MCP tools.
 */

/** Maximum response characters before truncation. */
export const CHARACTER_LIMIT = 25_000;

/** Default pagination page size. */
export const DEFAULT_PAGE_SIZE = 20;

/** Upstream API request timeout in milliseconds. */
export const REQUEST_TIMEOUT_MS = 55_000;

/** Valid registration period range (years). */
export const MIN_REG_PERIOD = 1;
export const MAX_REG_PERIOD = 10;

/** Contact roles recognised by the upstream API. */
export const CONTACT_ROLES = [
	"registrant",
	"admin",
	"tech",
	"billing",
] as const;

/** Canonical mapping from alias → canonical role key sent upstream. */
export const CONTACT_ROLE_ALIASES: Record<string, string> = {
	technical: "tech",
};
