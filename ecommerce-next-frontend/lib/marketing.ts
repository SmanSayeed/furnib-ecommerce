import { config } from "./config";

/**
 * Public, client-safe analytics IDs. The Meta CAPI token is a server-side
 * secret and is NEVER part of this payload (the backend whitelists these keys).
 */
export type Marketing = {
  gtm_id: string | null;
  ga4_id: string | null;
  fb_pixel_id: string | null;
  clarity_id: string | null;
};

const EMPTY: Marketing = {
  gtm_id: null,
  ga4_id: null,
  fb_pixel_id: null,
  clarity_id: null,
};

export async function getMarketing(): Promise<Marketing> {
  try {
    const res = await fetch(`${config.apiBaseUrl}/marketing`, {
      next: { revalidate: 300 },
      headers: { Accept: "application/json" },
    });
    if (!res.ok) throw new Error(`marketing -> ${res.status}`);
    const json = (await res.json()) as { data: Partial<Marketing> };
    return { ...EMPTY, ...json.data };
  } catch {
    return EMPTY;
  }
}
