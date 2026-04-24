/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/services/errors.ts — MCP-safe error and success response helpers.
 */

/**
 * Wraps an error thrown by registrarCall into an MCP-safe tool error response.
 *
 * Usage inside a registerTool handler:
 *   } catch (err) {
 *     return toolError(err);
 *   }
 */
export function toolError(err: unknown): {
	isError: true;
	content: [{ type: "text"; text: string }];
} {
	const msg = err instanceof Error ? err.message : String(err);
	return {
		isError: true,
		content: [{ type: "text", text: msg }],
	};
}

/** Wraps a plain text success string into a tool result content array. */
export function toolText(text: string): {
	content: [{ type: "text"; text: string }];
} {
	return { content: [{ type: "text", text }] };
}
