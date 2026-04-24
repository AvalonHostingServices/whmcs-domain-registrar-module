/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/schemas/common.ts — Zod validation schemas shared by read and write tools.
 */

import { z } from "zod";
import { ResponseFormat } from "../types.js";
import { MIN_REG_PERIOD, MAX_REG_PERIOD } from "../constants.js";

/** Lowercase FQDN-like domain name. */
export const DomainNameSchema = z
	.string()
	.min(3)
	.max(253)
	.regex(/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/, {
		message:
			"domainname must be a valid lowercase domain name like example.com",
	})
	.describe("Full domain name, e.g. example.com");

/** Positive integer WHMCS domain ID. */
export const DomainIdSchema = z
	.number()
	.int("domainid must be a whole number")
	.positive("domainid must be positive")
	.describe("WHMCS domain ID");

/** Registration period in years. */
export const RegPeriodSchema = z
	.number()
	.int("regperiod must be a whole number")
	.min(MIN_REG_PERIOD, `regperiod must be at least ${MIN_REG_PERIOD}`)
	.max(MAX_REG_PERIOD, `regperiod must be at most ${MAX_REG_PERIOD}`)
	.describe("Registration period in years");

/** Common domain lookup fields shared by most actions. */
export const DomainLookupSchema = z.object({
	domainid: DomainIdSchema,
	domainname: DomainNameSchema,
});

/** Optional response format toggle for read tools. */
export const ResponseFormatSchema = z
	.nativeEnum(ResponseFormat)
	.default(ResponseFormat.MARKDOWN)
	.describe(
		"Output format: 'markdown' for human-readable, 'json' for machine-readable",
	);

/** Individual nameserver hostname. */
export const NameserverSchema = z
	.string()
	.min(3)
	.max(253)
	.regex(
		/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)+$/,
	)
	.describe("Nameserver hostname, e.g. ns1.provider.net");

/** Single contact detail fields (sent to SaveContactDetails). */
export const ContactFieldsSchema = z
	.object({
		First_Name: z.string().min(1).optional(),
		Last_Name: z.string().min(1).optional(),
		Company_Name: z.string().optional(),
		Email: z.string().email().optional(),
		Address_1: z.string().optional(),
		Address_2: z.string().optional(),
		City: z.string().optional(),
		State: z.string().optional(),
		Zip: z.string().optional(),
		Country: z
			.string()
			.length(2, "Country must be a 2-letter ISO code")
			.optional(),
		Phone: z
			.string()
			.regex(
				/^\+\d{7,15}$/,
				"Phone must be in E.164 format, e.g. +8801000000000",
			)
			.optional(),
	})
	.strict();

/** idempotency key required for all write operations. */
export const ClientRequestIdSchema = z
	.string()
	.uuid("client_request_id must be a valid UUID v4")
	.describe(
		"Unique UUID v4 you generate per call. Prevents accidental duplicate writes. Required for all write operations.",
	);

/** Confirmation token required for destructive operations. */
export const ConfirmTokenSchema = z
	.literal("I_CONFIRM")
	.describe(
		"Safety confirmation. You must pass the exact string 'I_CONFIRM' to proceed with this destructive operation.",
	);
