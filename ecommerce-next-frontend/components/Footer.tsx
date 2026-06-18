import { config } from "@/lib/config";
import { whatsappGeneral } from "@/lib/whatsapp";

export function Footer() {
  return (
    <footer className="mt-20 border-t border-border bg-surface">
      <div className="mx-auto max-w-5xl px-6 py-14 text-center">
        <h2 className="text-3xl font-bold tracking-tight">{config.contact.company}</h2>
        <p className="mt-3 max-w-xl mx-auto text-sm leading-relaxed text-muted">
          Elegant, refined furniture for modern living and professional spaces.
          Most best-selling models are ready stock with fast delivery.
        </p>
        <div className="mt-6 space-y-1 text-sm text-muted">
          <p>{config.contact.address}</p>
          <p>
            Tel: {config.contact.phone} &nbsp;|&nbsp; Email: {config.contact.email}
          </p>
        </div>
        <a
          href={whatsappGeneral()}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-6 inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white transition hover:bg-accent-hover"
        >
          Contact us on WhatsApp
        </a>
        <p className="mt-8 text-xs text-muted/60">
          © {config.siteName} — All rights reserved.
        </p>
      </div>
    </footer>
  );
}
