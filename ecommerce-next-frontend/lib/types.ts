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
  is_featured: boolean;
  is_new: boolean;
  social_thumbnail: string | null;
  seo: Seo;
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
