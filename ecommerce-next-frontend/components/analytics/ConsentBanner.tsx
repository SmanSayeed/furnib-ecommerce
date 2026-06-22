"use client";

import { useSyncExternalStore } from "react";
import {
  consentServerSnapshot,
  consentSnapshot,
  setConsent,
  subscribeConsent,
} from "@/lib/consent";

/**
 * Cookie-consent gate. Shows once until the visitor chooses. Accepting unlocks
 * GTM + the tracking beacons; declining keeps everything off. Default = nothing
 * loads (privacy-preserving). Only renders when analytics is actually wired
 * (a GTM id exists) so we never nag users on a non-tracked site.
 */
export function ConsentBanner({ enabled }: { enabled: boolean }) {
  const consent = useSyncExternalStore(
    subscribeConsent,
    consentSnapshot,
    consentServerSnapshot,
  );

  if (!enabled || consent !== null) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-[60] border-t border-border bg-surface/95 px-4 py-4 backdrop-blur md:bottom-4 md:left-1/2 md:right-auto md:w-[40rem] md:-translate-x-1/2 md:rounded-2xl md:border md:shadow-xl">
      <div className="mx-auto flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-muted">
          We use cookies for analytics and ads to improve your experience. You can accept or
          decline non-essential cookies.
        </p>
        <div className="flex shrink-0 gap-2">
          <button
            type="button"
            onClick={() => setConsent("denied")}
            className="rounded-xl border border-border px-4 py-2 text-sm font-medium transition hover:bg-surface-2"
          >
            Decline
          </button>
          <button
            type="button"
            onClick={() => setConsent("granted")}
            className="rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-on-accent transition hover:bg-accent-hover"
          >
            Accept
          </button>
        </div>
      </div>
    </div>
  );
}
