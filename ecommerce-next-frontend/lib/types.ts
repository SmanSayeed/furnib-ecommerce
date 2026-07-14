export type Seo = {
  meta_title?: string | null;
  meta_description?: string | null;
  og_image?: string | null;
};

export type Category = {
  id: number;
  title: string;
  slug: string;
  details: string | null;
  header_image: string | null;
  header_mobile_url: string | null;
  thumbnail_image: string | null;
  position_order: number;
  seo: Seo;
};

export type Money = {
  minor: number;
  display: number;
  formatted: string;
};

export type ProductImage = {
  path: string;
  alt: string | null;
  position: number;
};

export type ProductAdvance = {
  required: boolean;
  type: "full" | "partial" | null;
  partial_type: "percentage" | "amount" | "shipping" | null;
  partial_amount: number | null;
};

export type Product = {
  id: number;
  title: string;
  slug: string;
  sku: string;
  details: string | null;
  video: string | null;
  main_image: string | null;
  images?: ProductImage[];
  price: Money;
  discount_price: Money | null;
  in_stock: boolean;
  // Optional numeric on-hand quantity. The public products API may not return
  // this yet; when absent we fall back to the boolean in_stock flag.
  stock_amount?: number | null;
  // When true this product never incurs a delivery charge — shown as "Free".
  free_shipping?: boolean;
  advance?: ProductAdvance;
  is_featured: boolean;
  is_new: boolean;
  social_thumbnail: string | null;
  seo: Seo;
};

export type ShippingZone = {
  id: number;
  name: string;
  cost: Money;
};

// Per-product shipping zone: the zone's base cost plus this product's optional
// per-unit extra. Effective cost = base + extra_per_unit × quantity.
export type ProductShippingZone = {
  id: number;
  name: string;
  /** The zone's base cost — charged once per order. */
  base: Money;
  /** What the FIRST unit of this product adds in this zone. */
  extra_per_unit: Money;
  /**
   * What EACH FURTHER unit adds. The server derives this (the 2-unit line minus
   * the 1-unit line), so when the product has no multi-quantity discount it comes
   * back equal to `extra_per_unit` — and the one formula below stays correct:
   *
   *   shipping = base + extra_per_unit + multi_extra_per_unit × (qty − 1)
   */
  multi_extra_per_unit: Money;
};

export type OrderItemLine = {
  title: string;
  sku: string;
  price: Money;
  qty: number;
  line_total: Money;
};

// Ready-to-push GA4/Meta dataLayer payload built server-side (Laravel
// OrderResource). The storefront pushes it verbatim — no PII handling in JS.
export type OrderTracking = {
  event: string;
  event_id: string;
  ecommerce: Record<string, unknown>;
  user_data: Record<string, unknown>;
  order_info: Record<string, unknown>;
};

export type PlacedOrder = {
  order_no: string;
  status: string;
  payment_status: string;
  subtotal: Money;
  shipping_cost: Money;
  total: Money;
  advance_amount: Money;
  advance_paid: Money;
  address: string;
  invoice_url: string;
  items: OrderItemLine[];
  tracking?: OrderTracking;
};

// A single customer-visible payment row (success or pending), newest first.
export type PaymentHistoryRow = {
  type: string; // full | partial | shipping | manual
  direction: string; // credit | debit
  status: string; // success | pending
  amount: string; // "Tk 150"
  amount_minor: number;
  gateway: string;
  date: string | null;
};

// Live paid/due snapshot for a placed order (Laravel OrderStatusController),
// fetched after returning from the gateway so the success page shows the truth.
export type OrderStatus = {
  order_no: string;
  status: string;
  payment_status: string;
  total: Money;
  shipping_cost: Money;
  advance_amount: Money;
  advance_paid: Money;
  due: Money;
  advance_required: boolean;
  // Self-service pay options (COD success page mirrors the /pay page).
  shipping_minor: number;
  due_minor: number;
  free_shipping: boolean;
  can_pay_shipping: boolean;
  can_pay_full: boolean;
  payments: PaymentHistoryRow[];
};

export type PageMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type CategoryWithProducts = {
  category: Category;
  products: Product[];
  meta: PageMeta;
};

export type SocialLinks = {
  facebook?: string;
  instagram?: string;
  youtube?: string;
  linkedin?: string;
  x?: string;
  pinterest?: string;
  tiktok?: string;
};

export type CmsPageLink = {
  slug: string;
  title: string;
};

export type CmsPage = {
  slug: string;
  title: string;
  body_html: string | null;
};

// Payment-gateway compliance fields surfaced by the public settings API. All
// optional/nullable — the footer only renders each when present.
export type SiteCompliance = {
  trade_license_no: string | null;
  registered_address: string | null;
  delivery_inside_dhaka: string | null;
  delivery_outside_dhaka: string | null;
  payment_banner_url: string | null;
};

// A single partner badge (e.g. "Member of", "Delivery Partner") shown in the
// footer. Rendered only when `enabled && image_url`.
export type Badge = {
  enabled: boolean;
  heading: string;
  image_url: string | null;
  url: string | null;
};

export type SiteSettings = {
  site_name: string | null;
  tagline: string | null;
  whatsapp: string | null;
  // Per-button show/hide for the single WhatsApp number (admin-managed).
  whatsapp_buttons?: {
    floating: boolean;
    inquiry: boolean;
    footer: boolean;
  };
  contact: {
    phone: string | null;
    phone_2: string | null;
    email: string | null;
    address: string | null;
  };
  logo_light: string | null;
  logo_dark: string | null;
  logo_footer: string | null;
  favicon: string | null;
  banners: Array<{ desktop: string | null; mobile: string | null }>;
  socials?: SocialLinks;
  // Published pages shown in the footer "About Us" column, each → /p/{slug}.
  footer_pages?: Array<{ slug: string; title: string }>;
  compliance?: SiteCompliance | null;
  footer_contact?: { hours: string | null } | null;
  footer_badges?: { member_of: Badge; delivery_partner: Badge } | null;
};
