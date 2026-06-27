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
  base: Money;
  extra_per_unit: Money;
};

export type OrderItemLine = {
  title: string;
  sku: string;
  price: Money;
  qty: number;
  line_total: Money;
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

export type FooterLink = {
  label: string;
  url: string;
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

export type SiteSettings = {
  site_name: string | null;
  tagline: string | null;
  whatsapp: string | null;
  contact: {
    phone: string | null;
    email: string | null;
    address: string | null;
  };
  logo_light: string | null;
  logo_dark: string | null;
  logo_footer: string | null;
  favicon: string | null;
  banners: string[];
  socials?: SocialLinks;
  footer_links?: FooterLink[];
};
