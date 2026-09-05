export type Category = {
  slug: string;
  name: string;
  description: string;
  image: string;
};

export type Product = {
  id: string;
  slug: string;
  name: string;
  categorySlug: string;
  dosageForm: string;
  strength: string;
  packaging: string;
  moq: string;
  price: string;
  description: string;
  image: string;
  featured: boolean;
  tags: string[];
};

export type Company = {
  name: string;
  legalName: string;
  tagline: string;
  description: string;
  founded: string;
  addressLine1: string;
  city: string;
  state: string;
  postalCode: string;
  country: string;
  phone: string;
  phoneHref: string;
  whatsapp: string;
  whatsappHref: string;
  email: string;
  certifications: string[];
  exportMarkets: string[];
  workingHours: string;
};