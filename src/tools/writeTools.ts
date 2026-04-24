/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/tools/writeTools.ts — Write MCP tools with safety gates.
 * Covers: register, transfer, renew, nameservers, contacts, lock, ID protect,
 * release, and delete. Destructive operations require confirm: "I_CONFIRM"
 * and a client_request_id UUID.
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import {
	DomainLookupSchema,
	DomainIdSchema,
	DomainNameSchema,
	RegPeriodSchema,
	NameserverSchema,
	ContactFieldsSchema,
	ClientRequestIdSchema,
	ConfirmTokenSchema,
} from "../schemas/common.js";
import { registrarCall } from "../services/registrarClient.js";
import { toolError, toolText } from "../services/errors.js";
import { CONTACT_ROLE_ALIASES } from "../constants.js";

/** Normalise contact role aliases (Technical → tech) before sending upstream. */
function normalizeContactRoles(
	contacts: Record<string, Record<string, unknown>>,
): Record<string, Record<string, unknown>> {
	const out: Record<string, Record<string, unknown>> = {};
	for (const [role, fields] of Object.entries(contacts)) {
		const canonical =
			CONTACT_ROLE_ALIASES[role.toLowerCase()] ?? role.toLowerCase();
		out[canonical] = fields;
	}
	return out;
}

/** Normalise boolean-like values to true/false. */
function normalizeBool(value: unknown): boolean {
	if (typeof value === "boolean") return value;
	if (typeof value === "string") {
		return ["true", "1", "yes"].includes(value.toLowerCase());
	}
	return Boolean(value);
}

const RegistrantContactSchema = z
	.object({
		firstname: z.string().min(1),
		lastname: z.string().min(1),
		email: z.string().email(),
		companyname: z.string().optional(),
		address1: z.string().optional(),
		address2: z.string().optional(),
		city: z.string().optional(),
		state: z.string().optional(),
		postcode: z.string().optional(),
		country: z.string().length(2).optional(),
		phonenumber: z
			.string()
			.regex(/^\+\d{7,15}$/)
			.optional(),
	})
	.strict();

const ContactsMapSchema = z.record(RegistrantContactSchema);

export function registerWriteTools(server: McpServer): void {
	// ─────────────────────────────────────────────
	// registrar_register_domain
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_register_domain",
		{
			title: "Register a New Domain",
			description: `Register a new domain name.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - regperiod (number): Registration period in years (1–10)
  - dnsmanagement (boolean): Enable DNS management
  - emailforwarding (boolean): Enable email forwarding
  - idprotection (boolean): Enable ID protection / WHOIS privacy
  - contacts (object): Map of registrant/admin/tech/billing contact objects
  - client_request_id (string): UUID v4 to prevent duplicate submissions
  - confirm (literal 'I_CONFIRM'): Safety confirmation required

WARNING: This action will register (and may charge for) a new domain. Confirm intent before calling.`,
			inputSchema: DomainLookupSchema.extend({
				regperiod: RegPeriodSchema,
				dnsmanagement: z.boolean().describe("Enable DNS management"),
				emailforwarding: z.boolean().describe("Enable email forwarding"),
				idprotection: z.boolean().describe("Enable WHOIS ID protection"),
				contacts: ContactsMapSchema.describe(
					"Registrant/admin/tech/billing contacts",
				),
				client_request_id: ClientRequestIdSchema,
				confirm: ConfirmTokenSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: false,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { confirm: _c, client_request_id: _r, ...rest } = params;
				const data = await registrarCall("RegisterDomain", {
					...rest,
					contacts: normalizeContactRoles(rest.contacts),
				});
				return toolText(
					`**Domain Registered: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_transfer_domain
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_transfer_domain",
		{
			title: "Transfer a Domain",
			description: `Initiate a domain transfer to this registrar.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - eppcode (string): Transfer authorization / EPP code
  - regperiod (number): Registration period in years (1–10)
  - dnsmanagement (boolean): Enable DNS management after transfer
  - emailforwarding (boolean): Enable email forwarding
  - idprotection (boolean): Enable ID protection
  - nameservers (string[]): Array of nameserver hostnames (min 2)
  - contacts (object): Map of contact objects
  - client_request_id (string): UUID v4 idempotency key
  - confirm (literal 'I_CONFIRM'): Safety confirmation required

WARNING: This initiates a transfer which may incur charges. Confirm intent before calling.`,
			inputSchema: DomainLookupSchema.extend({
				eppcode: z.string().min(1).describe("EPP / transfer auth code"),
				regperiod: RegPeriodSchema,
				dnsmanagement: z.boolean(),
				emailforwarding: z.boolean(),
				idprotection: z.boolean(),
				nameservers: z
					.array(NameserverSchema)
					.min(2, "At least 2 nameservers are required for transfer"),
				contacts: ContactsMapSchema,
				client_request_id: ClientRequestIdSchema,
				confirm: ConfirmTokenSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: false,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { confirm: _c, client_request_id: _r, ...rest } = params;
				const data = await registrarCall("TransferDomain", {
					...rest,
					contacts: normalizeContactRoles(rest.contacts),
				});
				return toolText(
					`**Transfer Initiated: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_renew_domain
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_renew_domain",
		{
			title: "Renew a Domain",
			description: `Renew a domain registration for the specified number of years.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - regperiod (number): Renewal period in years (1–10)
  - client_request_id (string): UUID v4 idempotency key
  - confirm (literal 'I_CONFIRM'): Safety confirmation required

WARNING: Renewal will extend the domain and may incur charges. Confirm intent before calling.`,
			inputSchema: DomainLookupSchema.extend({
				regperiod: RegPeriodSchema,
				client_request_id: ClientRequestIdSchema,
				confirm: ConfirmTokenSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: false,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { confirm: _c, client_request_id: _r, ...rest } = params;
				const data = await registrarCall("RenewDomain", rest);
				return toolText(
					`**Domain Renewed: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_set_nameservers
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_set_nameservers",
		{
			title: "Set Domain Nameservers",
			description: `Update the nameservers for a domain. ns1 and ns2 are required; ns3..ns5 are optional.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - ns1..ns5 (string): Nameserver hostnames
  - client_request_id (string): UUID v4 idempotency key

Use when: "Point example.com to ns1.provider.net and ns2.provider.net."`,
			inputSchema: DomainLookupSchema.extend({
				ns1: NameserverSchema,
				ns2: NameserverSchema,
				ns3: NameserverSchema.optional(),
				ns4: NameserverSchema.optional(),
				ns5: NameserverSchema.optional(),
				client_request_id: ClientRequestIdSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { client_request_id: _r, ...rest } = params;
				const data = await registrarCall("SaveNameservers", rest);
				return toolText(
					`**Nameservers Updated: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_set_contact_details
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_set_contact_details",
		{
			title: "Save Domain Contact Details",
			description: `Update contact details for one or more contact roles on a domain.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - contactdetails (object): Map of role → contact fields (Registrant/Admin/Tech/Billing)
  - client_request_id (string): UUID v4 idempotency key
  - confirm (literal 'I_CONFIRM'): Safety confirmation required

Note: Only pass roles you want to update. Unset roles are not modified.
WARNING: This overwrites existing contact data for the supplied roles.`,
			inputSchema: DomainLookupSchema.extend({
				contactdetails: z
					.record(ContactFieldsSchema)
					.describe("Map of contact role to contact fields"),
				client_request_id: ClientRequestIdSchema,
				confirm: ConfirmTokenSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: true,
				openWorldHint: false,
			},
		},
		async (params) => {
			try {
				const { confirm: _c, client_request_id: _r, ...rest } = params;
				const data = await registrarCall("SaveContactDetails", rest);
				return toolText(
					`**Contact Details Updated: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_set_registrar_lock
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_set_registrar_lock",
		{
			title: "Set Registrar Lock",
			description: `Enable or disable the registrar (transfer) lock for a domain.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - lockstatus (boolean): true to lock, false to unlock
  - client_request_id (string): UUID v4 idempotency key

Use when: "Lock example.com against transfer" or "Unlock example.com so it can be transferred."`,
			inputSchema: DomainLookupSchema.extend({
				lockstatus: z.boolean().describe("true = locked, false = unlocked"),
				client_request_id: ClientRequestIdSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { client_request_id: _r, ...rest } = params;
				const data = await registrarCall("SaveRegistrarLock", {
					...rest,
					lockstatus: normalizeBool(rest.lockstatus),
				});
				return toolText(
					`**Registrar Lock ${params.lockstatus ? "Enabled" : "Disabled"}: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_toggle_id_protect
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_toggle_id_protect",
		{
			title: "Toggle ID Protection (WHOIS Privacy)",
			description: `Enable or disable WHOIS ID protection for a domain.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - idprotect (boolean): true = enable, false = disable
  - client_request_id (string): UUID v4 idempotency key

Use when: "Enable privacy protection for example.com" or "Disable WHOIS privacy."`,
			inputSchema: DomainLookupSchema.extend({
				idprotect: z
					.boolean()
					.describe("true = enable ID protection, false = disable"),
				client_request_id: ClientRequestIdSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: true,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { client_request_id: _r, ...rest } = params;
				const data = await registrarCall("IDProtectToggle", {
					...rest,
					idprotect: normalizeBool(rest.idprotect),
				});
				return toolText(
					`**ID Protection ${params.idprotect ? "Enabled" : "Disabled"}: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_release_domain_tag
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_release_domain_tag",
		{
			title: "Release Domain / Change IPS Tag",
			description: `Change the IPS tag or release the domain to another registrar (registry-dependent, e.g. .uk domains).

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - newtag (string): New IPS tag to apply
  - client_request_id (string): UUID v4 idempotency key
  - confirm (literal 'I_CONFIRM'): Safety confirmation required

WARNING: This may immediately transfer control of the domain. Cannot be reversed through this API. Confirm intent before calling.`,
			inputSchema: DomainLookupSchema.extend({
				newtag: z.string().min(1).describe("New IPS tag for the domain"),
				client_request_id: ClientRequestIdSchema,
				confirm: ConfirmTokenSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: false,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { confirm: _c, client_request_id: _r, ...rest } = params;
				const data = await registrarCall("ReleaseDomain", rest);
				return toolText(
					`**Domain Released: ${params.domainname} → ${params.newtag}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);

	// ─────────────────────────────────────────────
	// registrar_request_delete
	// ─────────────────────────────────────────────
	server.registerTool(
		"registrar_request_delete",
		{
			title: "Request Domain Deletion",
			description: `Submit a delete request for a domain. This is typically irreversible once the registry processes it.

Args:
  - domainid (number): WHMCS domain ID
  - domainname (string): Full domain name
  - client_request_id (string): UUID v4 idempotency key
  - confirm (literal 'I_CONFIRM'): Safety confirmation required

WARNING: Deleting a domain may result in permanent loss of the domain name.
This action CANNOT be undone once processed. Only call this if explicitly instructed by the domain owner.`,
			inputSchema: DomainLookupSchema.extend({
				client_request_id: ClientRequestIdSchema,
				confirm: ConfirmTokenSchema,
			}).strict(),
			annotations: {
				readOnlyHint: false,
				destructiveHint: true,
				idempotentHint: false,
				openWorldHint: true,
			},
		},
		async (params) => {
			try {
				const { confirm: _c, client_request_id: _r, ...rest } = params;
				const data = await registrarCall("RequestDelete", rest);
				return toolText(
					`**Delete Request Submitted: ${params.domainname}**\n\n` +
						JSON.stringify(data, null, 2),
				);
			} catch (err) {
				return toolError(err);
			}
		},
	);
}
