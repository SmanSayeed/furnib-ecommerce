"use client";

import Script from "next/script";
import { useSyncExternalStore } from "react";
import {
  consentServerSnapshot,
  consentSnapshot,
  subscribeConsent,
} from "@/lib/consent";
import type { Marketing } from "@/lib/marketing";

/**
 * Loads Web GTM (Google-hosted, free GUI) ONLY after the visitor grants consent.
 * Once loaded, the actual tags — Meta Pixel, GA4, Clarity — are configured and
 * managed inside the GTM GUI listening to our dataLayer events. The owner adds
 * or changes campaigns/pixels by clicking in GTM, with no redeploy.
 *
 * Security: only the public GTM container id reaches the browser. The Meta CAPI
 * token never leaves the server.
 */
export function Analytics({ marketing }: { marketing: Marketing }) {
  const consent = useSyncExternalStore(
    subscribeConsent,
    consentSnapshot,
    consentServerSnapshot,
  );

  const gtmId = marketing.gtm_id;
  if (!gtmId || consent !== "granted") return null;

  return (
    <>
      <Script id="gtm-loader" strategy="afterInteractive">
        {`(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${gtmId}');`}
      </Script>
      <noscript>
        <iframe
          src={`https://www.googletagmanager.com/ns.html?id=${gtmId}`}
          height="0"
          width="0"
          style={{ display: "none", visibility: "hidden" }}
          title="gtm"
        />
      </noscript>
    </>
  );
}
