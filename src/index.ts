#!/usr/bin/env node
/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/index.ts — MCP server entry point.
 * Bootstraps the server, registers all tools, and selects the transport
 * (stdio or Streamable HTTP).
 */
/**
 * registrar-mcp-server
 *
 * MCP server for the Domain Reseller Registrar API.
 * Provides LLM-safe tools for domain registration, transfers, nameservers,
 * contacts, locks, sync, and pricing.
 *
 * Environment variables:
 *   REGISTRAR_API_URL   — Required. Full URL of the registrar JSON API endpoint.
 *   REGISTRAR_API_KEY   — Required. API key for authentication.
 *   TRANSPORT           — Optional. 'stdio' (default) or 'http'.
 *   PORT                — Optional. HTTP port when TRANSPORT=http (default: 3000).
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import express from "express";

import { registerReadTools } from "./tools/readTools.js";
import { registerWriteTools } from "./tools/writeTools.js";

// ─── Validate required environment variables at startup ───────────────────────
const requiredEnvVars = ["REGISTRAR_API_URL", "REGISTRAR_API_KEY"] as const;
for (const key of requiredEnvVars) {
	if (!process.env[key]) {
		console.error(`ERROR: Environment variable ${key} is not set.`);
		process.exit(1);
	}
}

// ─── Create server ─────────────────────────────────────────────────────────────
const server = new McpServer({
	name: "registrar-mcp-server",
	version: "1.0.0",
});

registerReadTools(server);
registerWriteTools(server);

// ─── Transport selection ───────────────────────────────────────────────────────
const transport = process.env.TRANSPORT ?? "stdio";

if (transport === "http") {
	await runHTTP();
} else {
	await runStdio();
}

// ─── stdio transport (local / subprocess) ─────────────────────────────────────
async function runStdio(): Promise<void> {
	const t = new StdioServerTransport();
	await server.connect(t);
	// NOTE: Do NOT log to stdout when using stdio transport — it corrupts the MCP stream.
	console.error("registrar-mcp-server running via stdio");
}

// ─── Streamable HTTP transport (remote / multi-client) ────────────────────────
async function runHTTP(): Promise<void> {
	const app = express();
	app.use(express.json());

	// Stateless per-request transport — prevents request ID collisions.
	app.post("/mcp", async (req, res) => {
		const t = new StreamableHTTPServerTransport({
			sessionIdGenerator: undefined, // stateless
			enableJsonResponse: true,
		});
		res.on("close", () => t.close());
		await server.connect(t);
		await t.handleRequest(req, res, req.body);
	});

	// Health check endpoint.
	app.get("/health", (_req, res) => {
		res.json({ status: "ok", server: "registrar-mcp-server" });
	});

	const port = parseInt(process.env.PORT ?? "3000", 10);
	app.listen(port, () => {
		console.error(
			`registrar-mcp-server running on http://localhost:${port}/mcp`,
		);
	});
}
