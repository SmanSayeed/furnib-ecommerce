import { config } from "./config";
import type {
  Category,
  CategoryWithProducts,
  CmsPage,
  CmsPageLink,
  PageMeta,
  Product,
  ProductShippingZone,
  ShippingZone,
  SiteSettings,
} from "./types";

const REVALIDATE = 60;

async function api<T>(path: string): Promise<T> {
  const res = await fetch(`${config.apiBaseUrl}${path}`, {
    next: { revalidate: REVALIDATE },
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error(`API ${path} -> ${res.status}`);
  return (await res.json()) as T;
}

export async function getCategories(): Promise<Category[]> {
  try {
    const json = await api<{ data: Category[] }>("/categories");
    return json.data;
  } catch {
    return [];
  }
}

export async function getCategory(
  slug: string,
  page = 1,
): Promise<CategoryWithProducts | null> {
  try {
    const json = await api<{ data: Category; products: Product[]; meta: PageMeta }>(
      `/categories/${slug}?page=${page}`,
    );
    return { category: json.data, products: json.products, meta: json.meta };
  } catch {
    return null;
  }
}

export async function getProduct(slug: string): Promise<Product | null> {
  try {
    const json = await api<{ data: Product }>(`/products/${slug}`);
    return json.data;
  } catch {
    return null;
  }
}

export async function getShippingZones(): Promise<ShippingZone[]> {
  try {
    const json = await api<{ data: ShippingZone[] }>("/shipping-zones");
    return json.data;
  } catch {
    return [];
  }
}

export async function getProductShippingZones(
  slug: string,
): Promise<ProductShippingZone[]> {
  try {
    const json = await api<{ data: ProductShippingZone[] }>(
      `/products/${slug}/shipping-zones`,
    );
    return json.data;
  } catch {
    return [];
  }
}

export async function getSettings(): Promise<SiteSettings | null> {
  try {
    const json = await api<{ data: SiteSettings }>("/settings");
    return json.data;
  } catch {
    return null;
  }
}

export async function getPages(): Promise<CmsPageLink[]> {
  try {
    const json = await api<{ data: CmsPageLink[] }>("/pages");
    return json.data;
  } catch {
    return [];
  }
}

export async function getPage(slug: string): Promise<CmsPage | null> {
  try {
    const json = await api<{ data: CmsPage }>(`/pages/${slug}`);
    return json.data;
  } catch {
    return null;
  }
}

export type {
  Category,
  CategoryWithProducts,
  CmsPage,
  CmsPageLink,
  PageMeta,
  Product,
  ProductShippingZone,
  ShippingZone,
  SiteSettings,
};
