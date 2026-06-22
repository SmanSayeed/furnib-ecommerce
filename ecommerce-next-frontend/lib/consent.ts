"use client";

/**
 * Lightweight cookie-based consent. No analytics or marketing tag loads until
 * the visitor explicitly accepts — the privacy-preserving default. The choice
 * is exposed as an external store so React components react to it without any
 * setState-in-effect.
 */
export type Consent = "granted" | "denied";

const COOKIE = "furnib_consent";
const ONE_YEAR = 60 * 60 * 24 * 365;

export function getConsent(): Consent | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(/(?:^|;\s*)furnib_consent=(granted|denied)/);
  return (match?.[1] as Consent | undefined) ?? null;
}

export function setConsent(value: Consent): void {
  if (typeof document === "undefined") return;
  document.cookie = `${COOKIE}=${value}; path=/; max-age=${ONE_YEAR}; samesite=lax`;
  window.dispatchEvent(new Event("furnib:consent"));
}

export function hasConsent(): boolean {
  return getConsent() === "granted";
}

/** Subscribe to consent changes (for useSyncExternalStore). */
export function subscribeConsent(callback: () => void): () => void {
  if (typeof window === "undefined") return () => {};
  window.addEventListener("furnib:consent", callback);
  return () => window.removeEventListener("furnib:consent", callback);
}

export function consentSnapshot(): Consent | null {
  return getConsent();
}

/** Server render knows nothing about the user's cookie. */
export function consentServerSnapshot(): Consent | null {
  return null;
}
