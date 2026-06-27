"use client";

import { useState } from "react";
import { config } from "@/lib/config";

type Status = "idle" | "loading" | "ok" | "error";

export function NewsletterForm() {
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState<Status>("idle");

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (status === "loading") return;
    setStatus("loading");

    try {
      const res = await fetch(`${config.publicApiBaseUrl}/newsletter`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ email }),
      });

      if (res.ok) {
        setStatus("ok");
        setEmail("");
      } else {
        setStatus("error");
      }
    } catch {
      setStatus("error");
    }
  }

  return (
    <form onSubmit={submit} className="mt-3 space-y-2">
      <div className="flex overflow-hidden rounded-lg border border-border bg-surface">
        <input
          type="email"
          required
          value={email}
          onChange={(e) => {
            setEmail(e.target.value);
            if (status !== "idle") setStatus("idle");
          }}
          placeholder="Your email"
          aria-label="Email address"
          className="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none"
        />
        <button
          type="submit"
          disabled={status === "loading"}
          className="shrink-0 bg-accent px-4 text-sm font-semibold text-white transition hover:bg-accent-hover disabled:opacity-60"
        >
          {status === "loading" ? "…" : "Subscribe"}
        </button>
      </div>
      {status === "ok" && (
        <p className="text-xs text-green-600 dark:text-green-400">
          Thanks — you&apos;re subscribed!
        </p>
      )}
      {status === "error" && (
        <p className="text-xs text-red-500">
          Couldn&apos;t subscribe. Please check the email and try again.
        </p>
      )}
    </form>
  );
}
