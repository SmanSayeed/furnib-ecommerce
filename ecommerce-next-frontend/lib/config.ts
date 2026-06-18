export const config = {
  siteName: "Furnib.com",
  tagline: "Premium Home & Office Furniture",
  apiBaseUrl: process.env.API_BASE_URL ?? "http://localhost:8000/api/v1",
  publicApiBaseUrl:
    process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1",
  backendOrigin:
    process.env.NEXT_PUBLIC_BACKEND_ORIGIN ?? "http://localhost:8000",
  siteUrl: process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000",
  whatsapp: process.env.NEXT_PUBLIC_WHATSAPP ?? "8801712345678",
  contact: {
    company: "Furnib.com",
    phone: "+880 1712-345678",
    email: "hello@furnib.com",
    address: "Dhaka, Bangladesh",
  },
} as const;
