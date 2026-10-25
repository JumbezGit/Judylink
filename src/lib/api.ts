import type { Tour } from "../data/tours";

const CONFIGURED_API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/backend/api";

function resolveApiBaseUrl() {
  if (!CONFIGURED_API_BASE_URL.startsWith("http")) return CONFIGURED_API_BASE_URL;

  const url = new URL(CONFIGURED_API_BASE_URL);
  const configuredForThisComputer = ["localhost", "127.0.0.1"].includes(url.hostname);
  const openedFromAnotherDevice = !["localhost", "127.0.0.1"].includes(window.location.hostname);
  if (configuredForThisComputer && openedFromAnotherDevice) {
    url.hostname = window.location.hostname;
  }
  return url.toString().replace(/\/$/, "");
}

const API_BASE_URL = resolveApiBaseUrl();
const BACKEND_BASE_URL = API_BASE_URL.replace(/\/api\/?$/, "");
const SITE_SLUG = String(import.meta.env.VITE_SITE_SLUG || "").trim();
const SITE_API_KEY = String(import.meta.env.VITE_SITE_API_KEY || "").trim();

function siteApiUrl(path: string) {
  const url = new URL(`${API_BASE_URL}/${path}`, window.location.origin);
  if (SITE_SLUG) url.searchParams.set("site", SITE_SLUG);
  return url.toString();
}

function siteApiHeaders(): HeadersInit {
  return SITE_API_KEY ? { "X-Site-API-Key": SITE_API_KEY } : {};
}

async function readApiJson(response: Response, fallbackMessage: string) {
  const text = await response.text();
  if (!text.trim()) {
    throw new Error(`${fallbackMessage}: server returned empty response (${response.status})`);
  }

  try {
    return JSON.parse(text);
  } catch {
    const clean = text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
    throw new Error(clean || `${fallbackMessage}: server returned invalid JSON (${response.status})`);
  }
}

function mediaUrl(path: string) {
  if (!path || path.startsWith("http://") || path.startsWith("https://") || !API_BASE_URL.startsWith("http")) {
    return path;
  }

  return new URL(path, API_BASE_URL).toString();
}

export interface BookingPayload {
  tour: Tour;
  date: string;
  time: string;
  adults: number;
  children: number;
  pickup: boolean;
  pickupLocation: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  nationality: string;
  country: string;
  contactMethod: string;
  specialRequests: string;
  promoCode: string;
  paymentMethod: string;
  subtotal: number;
  discount: number;
  serviceFee: number;
  total: number;
  agreedTerms: boolean;
  agreedCancellation: boolean;
}

export interface GalleryItem {
  id: string;
  src: string;
  title: string;
  location: string;
  category: string;
  span?: "wide" | "tall" | "normal";
}

export interface CompanyRegistrationPayload {
  companyName: string;
  ownerName: string;
  email: string;
  phone: string;
  username: string;
  password: string;
  websiteType: string;
  planId: string;
  planName: string;
  monthlyPrice: number;
  setupFee: number;
  firstPayment: number;
  currency: string;
}

export async function createBooking(payload: BookingPayload) {
  const response = await fetch(siteApiUrl("bookings.php"), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      ...siteApiHeaders(),
    },
    body: JSON.stringify({
      tourSlug: payload.tour.slug,
      tourName: payload.tour.name,
      date: payload.date,
      time: payload.time,
      adults: payload.adults,
      children: payload.children,
      pickup: payload.pickup,
      pickupLocation: payload.pickupLocation,
      firstName: payload.firstName,
      lastName: payload.lastName,
      email: payload.email,
      phone: payload.phone,
      nationality: payload.nationality,
      country: payload.country,
      contactMethod: payload.contactMethod,
      specialRequests: payload.specialRequests,
      promoCode: payload.promoCode,
      paymentMethod: payload.paymentMethod,
      subtotal: payload.subtotal,
      discount: payload.discount,
      serviceFee: payload.serviceFee,
      total: payload.total,
      currency: payload.tour.currency,
      agreedTerms: payload.agreedTerms,
      agreedCancellation: payload.agreedCancellation,
    }),
  });

  const data = await readApiJson(response, "Could not save booking").catch(() => null);
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || "Could not save booking");
  }

  return data as { ok: true; id: number; bookingRef: string };
}

export async function createCompanyRegistration(payload: CompanyRegistrationPayload) {
  const response = await fetch(`${API_BASE_URL}/company-registrations.php`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  });

  const data = await readApiJson(response, "Could not save company registration").catch(() => null);
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || "Could not save company registration");
  }

  return data as {
    ok: true;
    id: number;
    registrationRef: string;
    status: string;
    checkoutUrl: string;
    paymentReference: string;
  };
}

export async function fetchCompanyPaymentStatus(trackingId: string, merchantReference: string) {
  const query = new URLSearchParams({
    OrderTrackingId: trackingId,
    OrderMerchantReference: merchantReference,
  });
  const response = await fetch(`${BACKEND_BASE_URL}/scaypay/status.php?${query.toString()}`);
  const data = await readApiJson(response, "Could not verify payment").catch(() => null);
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || "Could not verify payment");
  }
  return data as {
    ok: true;
    registrationRef: string;
    planName: string;
    paymentReference: string;
    paymentStatus: "pending" | "paid" | "failed" | "refunded";
    gatewayStatus: string;
    amount: number;
    currency: string;
    checkoutUrl: string;
  };
}

interface ApiTour {
  id: number;
  slug: string;
  name: string;
  category: string;
  location: string;
  duration: string;
  group_size: string;
  rating: number;
  review_count: number;
  price: number;
  currency: string;
  description: string;
  highlights: string[];
  includes: string[];
  excludes: string[];
  what_to_bring: string[];
  difficulty: string;
  languages: string[];
  pickup: boolean;
  cancellation: string;
  image_path: string;
  gallery: string[];
  itinerary: { time: string; activity: string }[];
  faqs: { q: string; a: string }[];
  tags: string[];
  is_popular?: boolean;
}

function toTour(tour: ApiTour): Tour {
  return {
    id: String(tour.id),
    slug: tour.slug,
    name: tour.name,
    category: tour.category,
    location: tour.location,
    duration: tour.duration,
    groupSize: tour.group_size,
    rating: Number(tour.rating),
    reviewCount: Number(tour.review_count),
    price: Number(tour.price),
    currency: tour.currency,
    description: tour.description,
    highlights: tour.highlights,
    includes: tour.includes,
    excludes: tour.excludes,
    whatToBring: tour.what_to_bring,
    difficulty: tour.difficulty,
    languages: tour.languages,
    pickup: Boolean(tour.pickup),
    cancellation: tour.cancellation,
    image: mediaUrl(tour.image_path),
    gallery: (tour.gallery.length ? tour.gallery : [tour.image_path]).map(mediaUrl),
    itinerary: tour.itinerary,
    faqs: tour.faqs,
    tags: tour.tags,
    isPopular: Boolean(tour.is_popular),
  };
}

export async function fetchTours() {
  const response = await fetch(siteApiUrl("tours.php"), {
    headers: siteApiHeaders(),
  });
  const data = await readApiJson(response, "Could not load tours");
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || "Could not load tours");
  }
  return (data.tours as ApiTour[]).map(toTour);
}

export async function fetchTour(slug: string) {
  const url = new URL(siteApiUrl("tours.php"));
  url.searchParams.set("slug", slug);
  const response = await fetch(url, {
    headers: siteApiHeaders(),
  });
  const data = await readApiJson(response, "Could not load tour");
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || "Could not load tour");
  }
  return toTour(data.tour as ApiTour);
}

export async function fetchGalleryItems() {
  const response = await fetch(siteApiUrl("gallery.php"), {
    headers: siteApiHeaders(),
  });
  const data = await readApiJson(response, "Could not load gallery");
  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || "Could not load gallery");
  }
  return (data.items as Array<{
    id: number;
    image_path: string;
    title: string;
    location: string;
    category: string;
    span?: "wide" | "tall" | "normal";
  }>).map((item) => ({
    id: String(item.id),
    src: mediaUrl(item.image_path),
    title: item.title,
    location: item.location,
    category: item.category,
    span: item.span,
  } satisfies GalleryItem));
}
