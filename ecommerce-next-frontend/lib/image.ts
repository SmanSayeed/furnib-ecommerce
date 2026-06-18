import { config } from "./config";

/** Resolve a backend-stored image path to an absolute URL. */
export function imageUrl(path?: string | null): string | null {
  if (!path) return null;
  if (path.startsWith("http://") || path.startsWith("https://")) return path;
  return `${config.backendOrigin}/storage/${path.replace(/^\/+/, "")}`;
}
