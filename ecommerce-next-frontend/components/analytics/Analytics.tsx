import Script from "next/script";
import type { Marketing } from "@/lib/marketing";

/**
 * Loads Web GTM (Google-hosted, free GUI) on every page. The container is always
 * present so Google Tag Assistant / GTM Preview can detect it ("GTM works"). The
 * actual tags — Meta Pixel, GA4, Clarity — are configured and managed inside the
 * GTM GUI, listening to our dataLayer events. The owner adds or changes
 * campaigns/pixels by clicking in GTM, with no redeploy.
 *
 * Security: only the public GTM container id reaches the browser (the Meta CAPI
 * token never leaves the server). The id is also format-checked before it is
 * interpolated into the inline loader, so a malformed settings value can never
 * break out of the string and inject script.
 */
const GTM_ID = /^GTM-[A-Z0-9]+$/;

export function Analytics({ marketing }: { marketing: Marketing }) {
  const gtmId = marketing.gtm_id;
  if (!gtmId || !GTM_ID.test(gtmId)) return null;

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
