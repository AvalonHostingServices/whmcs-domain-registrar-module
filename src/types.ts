/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/types.ts — TypeScript interfaces and enums mirroring the upstream
 * Domain Reseller Registrar API contract.
 */

/** Shared TypeScript interfaces mirroring the upstream API contract. */

export type ContactRole = "registrant" | "admin" | "tech" | "billing";

export interface ContactFields {
	First_Name?: string;
	Last_Name?: string;
	Company_Name?: string;
	Email?: string;
	Address_1?: string;
	Address_2?: string;
	City?: string;
	State?: string;
	Zip?: string;
	Country?: string;
	Phone?: string;
}

export interface RegistrarResponse<T = Record<string, unknown>> {
	status: "success" | "error";
	data?: T;
	message?: string;
}

export interface NameserverData {
	ns1: string;
	ns2: string;
	ns3?: string;
	ns4?: string;
	ns5?: string;
}

export interface SyncData {
	active: boolean;
	cancelled: boolean;
	transferredAway: boolean;
	expirydate: string;
}

export interface TransferSyncData {
	completed: boolean;
	failed: boolean;
	expirydate: string;
	reason: string;
}

export interface TldPricingData {
	currency: { code: string };
	tlds: Record<string, Record<string, Record<string, number>>>;
}

export enum ResponseFormat {
	MARKDOWN = "markdown",
	JSON = "json",
}
