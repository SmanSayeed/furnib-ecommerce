import { config } from "@/lib/config";
import { whatsappGeneral } from "@/lib/whatsapp";

export function Hero() {
  return (
    <section className="relative flex min-h-[80vh] w-full items-center justify-center overflow-hidden border-b border-border">
      <div className="absolute inset-0 bg-gradient-to-b from-surface via-background to-background" />
      <div
        className="absolute inset-0 opacity-[0.08]"
        style={{
          backgroundImage:
            "radial-gradient(circle at 30% 20%, var(--brand) 0, transparent 45%), radial-gradient(circle at 80% 70%, var(--brand) 0, transparent 40%)",
        }}
      />
      <div className="relative z-10 mx-auto max-w-3xl px-6 text-center animate-in">
        <span className="inline-block rounded-full border border-border bg-surface/60 px-4 py-1 text-xs uppercase tracking-[0.2em] text-muted">
          Ready Stock | Fast Delivery
        </span>
        <h1 className="mt-6 text-4xl font-extrabold leading-tight sm:text-6xl">
          {config.tagline}
        </h1>
        <p className="mt-5 text-base text-muted sm:text-lg">
          Elegant and refined furniture for modern living and professional
          spaces. Get in touch with us today to place your order.
        </p>
        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <a
            href={whatsappGeneral()}
            target="_blank"
            rel="noopener noreferrer"
            className="rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white transition hover:bg-accent-hover"
          >
            WhatsApp Us
          </a>
          <a
            href="#collections"
            className="rounded-full border border-border bg-surface px-6 py-3 text-sm font-semibold transition hover:bg-surface-2"
          >
            Browse Collection
          </a>
        </div>
      </div>
    </section>
  );
}
