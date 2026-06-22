/**
 * The dataLayer notebook. Page code writes plain facts here; Web GTM (managed in
 * the GUI) reads them and fires the browser tags (Meta Pixel, GA4). Never put
 * secrets here — the GTM container is publicly downloadable.
 */
declare global {
  interface Window {
    dataLayer?: Record<string, unknown>[];
  }
}

export function pushEvent(event: string, params: Record<string, unknown> = {}): void {
  if (typeof window === "undefined") return;
  window.dataLayer = window.dataLayer ?? [];
  window.dataLayer.push({ event, ...params });
}

export {};
