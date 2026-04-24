/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/tools/readTools.ts — Read-only MCP tools (readOnlyHint: true).
 * Covers: availability check, nameservers, contacts, EPP code, registrar lock,
 * domain sync, transfer sync, and TLD pricing.
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import {
	DomainLookupSchema,
	DomainNameSchema,
	DomainIdSchema,
	ResponseFormatSchema,
} from "../schemas/common.js";
import { registrarCall } from "../services/registrarClient.js";
import { toolError, toolText } from "../services/errors.js";
import {
	ResponseFormat,
	NameserverData,
	SyncData,
	TransferSyncData,
	TldPricingData,
} from "../types.js";
import { CHARACTER_LIMIT } from "../constants.js";

function truncate(text: string): string {
	if (text.length <= CHARACTER_LIMIT) return text;
	return (
		text.slice(0, CHARACTER_LIMIT) +
		`\n\n[Response truncated at ${CHARACTER_LIMIT} characters. Add filters or use pagination.]`
	);
}

export function registerReadTools(server: McpServer): void {
	// ─────────────────────────────────────────────
	// registrar_check_availability
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_check_availability",
		{
			title: "Check Domain Availability",
			description: `Check whether a domain name is available for registration.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name, e.g. example.com
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns:
  - status: 'available' | 'unavailable' | 'unknown'

Use when: "Is example.com available?" or "Check if a domain can be registered."
Do NOT use when: You need to register the domain (use registrar_register_domain).`,
			inputSchema: DomainLookupSchema.extend({
				domain: z
					.string()
					.min(1)
					.describe(
						"SLD portion of the domain, e.g. 'example' (required by upstream)",
					),
				response_format: ResponseFormatSchema,
			}).strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<{ status: string }>(
					"CheckAvailability",
					{
						domainid: params.domainid,
						domainname: params.domainname,
						domain: params.domain,
					},
				);

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(truncate(JSON.stringify(data, null, 2)));
				}

				return toolText(
					`**Domain Availability: ${params.domainname}**\nStatus: **${data.status}**`,
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_get_nameservers
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_get_nameservers",
		{
			title: "Get Domain Nameservers",
			description: `Retrieve the nameservers currently configured for a domain.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns: ns1..ns5 (empty string if slot is unused)

Use when: "What nameservers does example.com use?"
Do NOT use when: You want to change nameservers (use registrar_set_nameservers).`,
			inputSchema: DomainLookupSchema.extend({
				response_format: ResponseFormatSchema,
			}).strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<NameserverData>("GetNameservers", {
					domainid: params.domainid,
					domainname: params.domainname,
				});

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(JSON.stringify(data, null, 2));
				}

				const entries = (["ns1", "ns2", "ns3", "ns4", "ns5"] as const)
					.filter((k) => data[k])
					.map((k) => `- **${k}**: ${data[k]}`);

				return toolText(
					`**Nameservers for ${params.domainname}**\n\n${entries.join("\n") || "No nameservers found."}`,
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_get_contact_details
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_get_contact_details",
		{
			title: "Get Domain Contact Details",
			description: `Retrieve contact details for a domain across all available roles (Registrant, Admin, Tech, Billing).

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns: contact objects keyed by role name.
Note: PII is returned as-is from the registrar. Handle with care.

Use when: "Who is the registrant contact for example.com?"`,
			inputSchema: DomainLookupSchema.extend({
				response_format: ResponseFormatSchema,
			}).strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: false,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<
					Record<string, Record<string, string>>
				>("GetContactDetails", {
					domainid: params.domainid,
					domainname: params.domainname,
				});

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(truncate(JSON.stringify(data, null, 2)));
				}

				const lines: string[] = [
					`**Contact Details for ${params.domainname}**`,
					"",
				];
				for (const [role, fields] of Object.entries(data)) {
					lines.push(`### ${role}`);
					for (const [k, v] of Object.entries(fields)) {
						if (v) lines.push(`- **${k}**: ${v}`);
					}
					lines.push("");
				}
				return toolText(truncate(lines.join("\n")));
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_get_epp_code
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_get_epp_code",
		{
			title: "Get Domain EPP / Auth Code",
			description: `Retrieve the EPP transfer authorization code for a domain.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name

Returns:
  - eppcode: The auth code string (treat as sensitive)

Use when: "Get the transfer auth code for example.com."
Security: Do NOT log or store the returned auth code. Use it once for transfer purposes only.`,
			inputSchema: DomainLookupSchema.strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: false,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<{ eppcode: string }>("GetEPPCode", {
					domainid: params.domainid,
					domainname: params.domainname,
				});
				// Return the code but remind caller to handle it securely.
				return toolText(
					`**EPP Code for ${params.domainname}**\n\n` +
						`\`${data.eppcode}\`\n\n` +
						`_Treat this as sensitive. Use it once for transfer purposes only._`,
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_get_registrar_lock
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_get_registrar_lock",
		{
			title: "Get Registrar Lock Status",
			description: `Get the current registrar lock (transfer lock) status for a domain.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns: lock status from the upstream registrar.

Use when: "Is example.com locked against transfer?"`,
			inputSchema: DomainLookupSchema.extend({
				response_format: ResponseFormatSchema,
			}).strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<Record<string, unknown>>(
					"GetRegistrarLock",
					{
						domainid: params.domainid,
						domainname: params.domainname,
					},
				);

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(JSON.stringify(data, null, 2));
				}

				return toolText(
					`**Registrar Lock for ${params.domainname}**\n\n` +
						Object.entries(data)
							.map(([k, v]) => `- **${k}**: ${String(v)}`)
							.join("\n"),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_sync_domain
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_sync_domain",
		{
			title: "Sync Domain Registration State",
			description: `Synchronize and return the current registration state of a domain (active, cancelled, transferred away, expiry date).

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - sld (string): Second-level domain label, e.g. 'example'
  - tld (string): Top-level domain, e.g. 'com'
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns: active, cancelled, transferredAway, expirydate

Use when: "What is the current state of example.com?" or "When does example.com expire?"`,
			inputSchema: DomainLookupSchema.extend({
				sld: z
					.string()
					.min(1)
					.describe("Second-level domain label, e.g. 'example'"),
				tld: z.string().min(2).describe("Top-level domain, e.g. 'com'"),
				response_format: ResponseFormatSchema,
			}).strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<SyncData>("Sync", {
					domainid: params.domainid,
					domainname: params.domainname,
					sld: params.sld,
					tld: params.tld,
				});

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(JSON.stringify(data, null, 2));
				}

				const statusLine = data.active
					? "✅ Active"
					: data.cancelled
						? "❌ Cancelled"
						: data.transferredAway
							? "↗️ Transferred Away"
							: "⚠️ Unknown";

				return toolText(
					`**Domain Sync: ${params.domainname}**\n\n` +
						`- **Status**: ${statusLine}\n` +
						`- **Expiry Date**: ${data.expirydate || "N/A"}`,
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_sync_transfer
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_sync_transfer",
		{
			title: "Sync Domain Transfer Status",
			description: `Synchronize and return the transfer status for a domain currently being transferred.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - sld (string): Second-level domain label
  - tld (string): Top-level domain
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns: completed, failed, expirydate, reason

Use when: "Has the transfer for example.com completed?" or "Why did the transfer fail?"`,
			inputSchema: DomainLookupSchema.extend({
				sld: z
					.string()
					.min(1)
					.describe("Second-level domain label, e.g. 'example'"),
				tld: z.string().min(2).describe("Top-level domain, e.g. 'com'"),
				response_format: ResponseFormatSchema,
			}).strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<TransferSyncData>("TransferSync", {
					domainid: params.domainid,
					domainname: params.domainname,
					sld: params.sld,
					tld: params.tld,
				});

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(JSON.stringify(data, null, 2));
				}

				const statusLine = data.completed
					? "✅ Completed"
					: data.failed
						? `❌ Failed${data.reason ? ` — ${data.reason}` : ""}`
						: "⏳ In Progress";

				return toolText(
					`**Transfer Sync: ${params.domainname}**\n\n` +
						`- **Status**: ${statusLine}\n` +
						`- **Expiry Date**: ${data.expirydate || "N/A"}`,
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_get_tld_pricing
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_get_tld_pricing",
		{
			title: "Get TLD Pricing",
			description: `Retrieve TLD pricing from the registrar for import into WHMCS.

Args:
  - currency (string): WHMCS default currency code, e.g. 'USD'
  - response_format ('markdown'|'json'): Output format (default: 'markdown')

Returns: currency info and a map of TLD pricing for register/renew/transfer by year key.

Use when: "What are the prices for .com, .net, .org registrations?"`,
			inputSchema: z
				.object({
					currency: z
						.string()
						.min(2)
						.max(10)
						.describe("WHMCS default currency code, e.g. 'USD'"),
					response_format: ResponseFormatSchema,
				})
				.strict(),
			annotations: {
				readOnlyHint: true,
				destructiveHint: false,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const data = await registrarCall<TldPricingData>("GetTldPricing", {
					currency: params.currency,
				});

				if (params.response_format === ResponseFormat.JSON) {
					return toolText(truncate(JSON.stringify(data, null, 2)));
				}

				const lines: string[] = [
					`**TLD Pricing (currency: ${data.currency?.code ?? params.currency})**`,
					"",
				];
				for (const [tld, pricing] of Object.entries(data.tlds ?? {})) {
					lines.push(`### .${tld}`);
					for (const [op, years] of Object.entries(pricing)) {
						const yearStr = Object.entries(years)
							.map(([y, p]) => `${y}=${p}`)
							.join(", ");
						lines.push(`- **${op}**: ${yearStr}`);
					}
					lines.push("");
				}
				return toolText(truncate(lines.join("\n")));
			} catch (err) {
				return toolError(err);
			}
		},
	);
}
