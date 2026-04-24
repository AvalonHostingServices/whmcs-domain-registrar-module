/**
 * Copyright (c) 2026 Avalon Hosting Services
 * SPDX-License-Identifier: MIT
 *
 * src/services/registrarClient.ts — HTTP client for the upstream Domain Reseller
 * Registrar API. Builds the request envelope and handles typed errors.
 */

import axios, { AxiosError } from "axios";
import { RegistrarResponse } from "../types.js";
import { REQUEST_TIMEOUT_MS } from "../constants.js";
import { getRequestApiKey } from "./requestContext.js";

/**
 * The static upstream API endpoint — always
 * https://manage.avalonhosting.services/modules/addons/domain_reseller/api.php
 * Injected via REGISTRAR_API_URL environment variable at server startup.
 */
function getApiEndpoint(): string {
	const url = process.env.REGISTRAR_API_URL;
	if (!url) {
		throw new Error(
			"REGISTRAR_API_URL environment variable is not set. " +
				"Set it to the registrar JSON API endpoint.",
		);
	}
	return url;
}

/**
 * Sends a request to the registrar API using the standard envelope:
 *   { api_key, action, params }
 *
 * The api_key is the reseller's own key, extracted from the HTTP request
 * Authorization header by the server and stored in per-request context.
 *
 * Returns the parsed `data` object on success.
 * Throws a descriptive Error on upstream errors, HTTP errors, or timeouts.
 */
export async function registrarCall<T = Record<string, unknown>>(
	action: string,
	params: Record<string, unknown>,
): Promise<T> {
	const endpoint = getApiEndpoint();
	// Per-request reseller key — never read from global env.
	const apiKey = getRequestApiKey();

	const body = {
		api_key: apiKey,
		action,
		params,
	};

	let raw: RegistrarResponse<T>;

	try {
		const resp = await axios.post<RegistrarResponse<T>>(endpoint, body, {
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
			},
			timeout: REQUEST_TIMEOUT_MS,
		});
		raw = resp.data;
	} catch (err) {
		throw mapHttpError(err);
	}

	if (!raw || typeof raw.status === "undefined") {
		throw new Error(
			"upstream_contract_violation: Response is missing the 'status' field. " +
				"The registrar API returned unexpected JSON.",
		);
	}

	if (raw.status !== "success") {
		const msg = raw.message ?? "Unknown error from registrar API";
		throw new Error(`registrar_error: ${msg}`);
	}

	return (raw.data ?? {}) as T;
}

function mapHttpError(err: unknown): Error {
	if (err instanceof AxiosError) {
		if (err.response) {
			switch (err.response.status) {
				case 401:
					return new Error(
						"auth_error: API key was rejected (401). Check REGISTRAR_API_KEY.",
					);
				case 403:
					return new Error(
						"permission_denied: Access forbidden (403). " +
							"Your API key may lack permission for this operation.",
					);
				case 404:
					return new Error(
						"not_found: The registrar API endpoint was not found (404). " +
							"Check REGISTRAR_API_URL.",
					);
				case 429:
					return new Error(
						"rate_limit: Too many requests (429). Wait before retrying.",
					);
				default:
					return new Error(
						`http_error: Registrar API returned HTTP ${err.response.status}.`,
					);
			}
		}
		if (err.code === "ECONNABORTED" || err.code === "ETIMEDOUT") {
			return new Error(
				"timeout: Request to registrar API timed out. " +
					"The upstream server did not respond within the allowed window.",
			);
		}
		if (err.code === "ECONNREFUSED" || err.code === "ENOTFOUND") {
			return new Error(
				`connection_error: Could not connect to registrar API (${err.code}). ` +
					"Check REGISTRAR_API_URL.",
			);
		}
	}
	return new Error(
		`unexpected_error: ${err instanceof Error ? err.message : String(err)}`,
	);
}
