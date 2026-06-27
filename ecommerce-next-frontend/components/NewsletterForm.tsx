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
      <div className="flex overflow-hidden rounded-lg bg-white">
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
          className="min-w-0 flex-1 bg-white px-3 py-2.5 text-sm text-stone-800 outline-none placeholder:text-stone-400"
        />
        <button
          type="submit"
          disabled={status === "loading"}
          className="shrink-0 bg-stone-900 px-4 text-sm font-semibold text-white transition hover:bg-black disabled:opacity-60"
        >
          {status === "loading" ? "…" : "Subscribe"}
        </button>
      </div>
      {status === "ok" && (
        <p className="text-xs font-medium text-white">
          Thanks — you&apos;re subscribed!
        </p>
      )}
      {status === "error" && (
        <p className="text-xs text-red-100">
          Couldn&apos;t subscribe. Please check the email and try again.
        </p>
      )}
    </form>
  );
}
