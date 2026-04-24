/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/services/requestContext.ts — Per-request AsyncLocalStorage context.
 * Stores the reseller API key for the duration of a single HTTP request so
 * registrarClient can read it without it ever touching global state or env vars.
 */

import { AsyncLocalStorage } from "async_hooks";

interface RequestContext {
	/** The reseller's API key, extracted from the incoming HTTP Authorization header. */
	apiKey: string;
}

const storage = new AsyncLocalStorage<RequestContext>();

/**
 * Run `fn` inside a request context that carries `apiKey`.
 * All async work spawned inside `fn` will see the same context.
 */
export function withRequestContext<T>(apiKey: string, fn: () => T): T {
	return storage.run({ apiKey }, fn);
}

/**
 * Retrieve the API key for the current request.
 * Throws if called outside of a `withRequestContext` scope (programming error).
 */
export function getRequestApiKey(): string {
	const ctx = storage.getStore();
	if (!ctx) {
		throw new Error(
			"auth_error: No request context found. " +
				"Ensure the request carries a valid Authorization header.",
		);
	}
	return ctx.apiKey;
}
