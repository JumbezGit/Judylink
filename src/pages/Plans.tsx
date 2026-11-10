import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router";
import { motion } from "framer-motion";
import {
  ArrowRight,
  BriefcaseBusiness,
  CarFront,
  Check,
  FileCheck2,
  Globe2,
  Hotel,
  Loader2,
  LogIn,
  LockKeyhole,
  Mail,
  MapPinned,
  Printer,
  ShieldCheck,
  UserPlus,
  type LucideIcon,
} from "lucide-react";
import { createCompanyRegistration, fetchCompanyPaymentStatus } from "../lib/api";

type Plan = {
  id: string;
  name: string;
  websiteType: string;
  icon: LucideIcon;
  audience: string;
  price: number;
  setupFee: number;
  billing: string;
  features: string[];
};

const plans: Plan[] = [
  {
    id: "tours-travel",
    name: "Tours & Travel",
    websiteType: "Tour & Travel",
    icon: MapPinned,
    audience: "For tour operators, travel agencies, guides, and destination companies",
    price: 99000,
    setupFee: 249000,
    billing: "per month",
    features: [
      "Tour packages and itinerary manager",
      "Online booking and customer requests",
      "Destination gallery and pricing",
      "Staff, guide, and task management",
    ],
  },
  {
    id: "hotels-lodges",
    name: "Hotels & Lodges",
    websiteType: "Hotel / Lodge",
    icon: Hotel,
    audience: "For hotels, lodges, apartments, resorts, and guest houses",
    price: 129000,
    setupFee: 299000,
    billing: "per month",
    features: [
      "Room and accommodation listings",
      "Reservation and availability requests",
      "Seasonal rates and special offers",
      "Guest records and booking management",
    ],
  },
  {
    id: "stationery-printing",
    name: "Stationery & Printing",
    websiteType: "Stationery & Printing",
    icon: Printer,
    audience: "For stationery shops, printers, office suppliers, and copy centres",
    price: 69000,
    setupFee: 179000,
    billing: "per month",
    features: [
      "Product and service catalogue",
      "Customer quotation requests",
      "Printing order specifications",
      "Offers, gallery, and contact tools",
    ],
  },
  {
    id: "car-rental",
    name: "Car Rental",
    websiteType: "Car Rental",
    icon: CarFront,
    audience: "For vehicle rental companies, drivers, and transfer operators",
    price: 99000,
    setupFee: 249000,
    billing: "per month",
    features: [
      "Vehicle catalogue and daily rates",
      "Rental and transfer requests",
      "Driver and vehicle availability",
      "Customer and booking management",
    ],
  },
  {
    id: "general-business",
    name: "General Business",
    websiteType: "General Business",
    icon: BriefcaseBusiness,
    audience: "For shops, service providers, consultants, and growing companies",
    price: 79000,
    setupFee: 199000,
    billing: "per month",
    features: [
      "Company profile and service pages",
      "Products, projects, and gallery",
      "Customer enquiry management",
      "Custom branding and contact forms",
    ],
  },
];

const formatTzs = (amount: number) => `TZS ${amount.toLocaleString("en-TZ")}`;

const registrationSteps = ["Business Type", "Registration", "Payment", "Complete"];

function RegistrationSteps({ currentStep }: { currentStep: number }) {
  return (
    <div className="mx-auto max-w-4xl px-2 py-3 sm:px-6">
      <div className="grid grid-cols-4">
        {registrationSteps.map((label, index) => {
          const step = index + 1;
          const isComplete = step < currentStep;
          const isCurrent = step === currentStep;
          return (
            <div key={label} className="relative flex flex-col items-center text-center">
              {index > 0 && (
                <span className={`absolute right-1/2 top-5 h-0.5 w-full ${step <= currentStep ? "bg-[#2FABE1]" : "bg-gray-200"}`} />
              )}
              <span
                className={`relative z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-black transition ${
                  isComplete || isCurrent
                    ? "border-[#2FABE1] bg-[#2FABE1] text-white"
                    : "border-gray-200 bg-white text-[#172033]/35"
                }`}
              >
                {isComplete ? <Check size={17} strokeWidth={3} /> : step}
              </span>
              <span className={`mt-2 text-[11px] font-bold sm:text-xs ${isCurrent ? "text-[#172033]" : isComplete ? "text-[#2FABE1]" : "text-[#172033]/35"}`}>
                {label}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default function Plans() {
  const [currentStep, setCurrentStep] = useState(1);
  const [selectedPlanId, setSelectedPlanId] = useState("tours-travel");
  const [agreed, setAgreed] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState("");
  const [registrationRef, setRegistrationRef] = useState("");
  const [paymentStatus, setPaymentStatus] = useState<"pending" | "paid" | "failed" | "refunded">("pending");
  const [paymentMessage, setPaymentMessage] = useState("");
  const [paidPlanName, setPaidPlanName] = useState("");
  const [paidAmount, setPaidAmount] = useState<number | null>(null);
  const [retryCheckoutUrl, setRetryCheckoutUrl] = useState("");
  const [form, setForm] = useState({
    companyName: "",
    ownerName: "",
    email: "",
    phone: "",
    username: "",
    password: "",
    confirmPassword: "",
    websiteType: "Tour & Travel",
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    const previousTitle = document.title;
    document.title = "Judylink | Plans & Registration";
    return () => {
      document.title = previousTitle;
    };
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const paymentReturn = params.get("payment");
    const trackingId = params.get("OrderTrackingId") || "";
    const merchantReference = params.get("OrderMerchantReference") || "";
    const reference = params.get("reference") || "";
    if (!paymentReturn || (!trackingId && !merchantReference)) return;

    setCurrentStep(4);
    setRegistrationRef(reference);
    setPaymentMessage("Verifying your payment with ScayPay...");
    fetchCompanyPaymentStatus(trackingId, merchantReference)
      .then((result) => {
        setRegistrationRef(result.registrationRef);
        setPaymentStatus(result.paymentStatus);
        setPaidPlanName(result.planName);
        setPaidAmount(result.amount);
        setRetryCheckoutUrl(result.checkoutUrl);
        setPaymentMessage(
          result.paymentStatus === "paid"
            ? "Payment confirmed. Your registration is ready for super admin approval."
            : paymentReturn === "cancelled"
              ? "Payment was not completed. You can return to the secure checkout and try again."
              : "Payment is still pending. You can continue checkout or check again shortly.",
        );
      })
      .catch((error) => {
        setPaymentMessage(error instanceof Error ? error.message : "Could not verify payment");
      });
  }, []);

  const selectedPlan = useMemo(
    () => plans.find((plan) => plan.id === selectedPlanId) || plans[0],
    [selectedPlanId],
  );
  const firstPayment = selectedPlan.price + selectedPlan.setupFee;

  const setField = (key: keyof typeof form, value: string) => {
    setForm((current) => ({ ...current, [key]: value }));
    setErrors((current) => {
      const next = { ...current };
      delete next[key];
      return next;
    });
  };

  const validate = () => {
    const nextErrors: Record<string, string> = {};
    if (!form.companyName.trim()) nextErrors.companyName = "Company name is required";
    if (!form.ownerName.trim()) nextErrors.ownerName = "Owner name is required";
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) nextErrors.email = "Valid email is required";
    if (!form.phone.trim()) nextErrors.phone = "Phone number is required";
    if (!form.username.match(/^[a-zA-Z0-9][a-zA-Z0-9._-]{3,49}$/)) nextErrors.username = "Use 4-50 letters, numbers, dots, hyphens, or underscores";
    if (form.password.length < 8 || form.password.length > 72) nextErrors.password = "Password must be 8-72 characters";
    if (form.confirmPassword !== form.password) nextErrors.confirmPassword = "Passwords do not match";
    if (!agreed) nextErrors.agreed = "Please accept the registration review terms";
    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!validate()) return;
    setSubmitting(true);
    setSubmitError("");
    setCurrentStep(3);
    try {
      const saved = await createCompanyRegistration({
        companyName: form.companyName,
        ownerName: form.ownerName,
        email: form.email,
        phone: form.phone,
        username: form.username,
        password: form.password,
        websiteType: form.websiteType,
        planId: selectedPlan.id,
        planName: selectedPlan.name,
        monthlyPrice: selectedPlan.price,
        setupFee: selectedPlan.setupFee,
        firstPayment,
        currency: "TZS",
      });
      setRegistrationRef(saved.registrationRef);
      if (!saved.checkoutUrl) throw new Error("ScayPay did not return a checkout link");
      window.location.assign(saved.checkoutUrl);
    } catch (error) {
      setCurrentStep(2);
      setSubmitError(error instanceof Error ? error.message : "Could not save company registration");
    } finally {
      setSubmitting(false);
    }
  };

  const inputClass = (key: string) =>
    `w-full rounded-xl border bg-white px-4 py-3 text-sm text-[#172033] outline-none transition focus:border-[#2FABE1] ${
      errors[key] ? "border-red-300 bg-red-50" : "border-gray-200"
    }`;

  if (currentStep === 4) {
    return (
      <div className="professional-glow-bg min-h-screen bg-white px-4 pb-16 pt-28 sm:px-6 lg:px-8">
        <div className="mx-auto mb-6 max-w-4xl">
          <RegistrationSteps currentStep={4} />
        </div>
        <div className="mx-auto max-w-3xl rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-xl shadow-black/5 sm:p-10">
          <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[#2FABE1]/10">
            <FileCheck2 className="text-[#2FABE1]" size={36} />
          </div>
          <p className="mb-2 text-sm font-semibold uppercase tracking-widest text-[#FAA723]">
            {paymentStatus === "paid" ? "Payment confirmed" : "Registration saved"}
          </p>
          <h1 className="mb-4 text-3xl font-black text-[#172033]" style={{ fontFamily: "Poppins, sans-serif" }}>
            {paymentStatus === "paid" ? "Your account is waiting for super admin approval" : "Complete your ScayPay payment"}
          </h1>
          <p className="mx-auto mb-8 max-w-2xl text-sm leading-6 text-[#172033]/65">
            {paymentMessage || "Your username and password were saved securely. Complete payment before your company account can be approved."}
          </p>

          <div className="mb-8 grid gap-3 text-left sm:grid-cols-3">
            {[
              ["Reference", registrationRef || "Pending"],
              ["System", paidPlanName || selectedPlan.name],
              ["First payment", formatTzs(paidAmount ?? firstPayment)],
            ].map(([label, value]) => (
              <div key={label} className="rounded-xl border border-gray-100 bg-[#ffffff] p-4">
                <p className="text-xs font-semibold uppercase tracking-widest text-[#172033]/40">{label}</p>
                <p className="mt-1 font-bold text-[#172033]">{value}</p>
              </div>
            ))}
          </div>

          <div className="flex flex-wrap justify-center gap-3">
            {paymentStatus !== "paid" && retryCheckoutUrl && (
              <a href={retryCheckoutUrl} className="rounded-xl border-2 border-[#2FABE1]/30 px-5 py-3 text-sm font-bold text-[#2FABE1] transition hover:bg-[#2FABE1]/5">
                Continue Payment
              </a>
            )}
            <button
              type="button"
              onClick={() => window.location.assign("/plans")}
              className="inline-flex items-center gap-2 rounded-xl bg-[#FAA723] px-5 py-3 text-sm font-bold text-[#172033] transition hover:bg-[#FAA723]/90"
            >
              New Registration <ArrowRight size={16} />
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="professional-glow-bg min-h-screen bg-white">
      <header className="sticky top-0 z-50 border-b border-gray-100 bg-white px-4 py-4 shadow-sm sm:px-6 lg:px-8">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
          <Link to="/plans" className="flex items-center gap-3 text-[#172033]">
            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#2FABE1] text-white">
              <Globe2 size={21} />
            </span>
            <span>
              <span className="block text-sm font-black" style={{ fontFamily: "Poppins, sans-serif" }}>Judylink</span>
              <span className="block text-xs text-[#172033]/45">Systems &amp; registration</span>
            </span>
          </Link>
          <Link
            to="/login"
            className="inline-flex items-center gap-2 rounded-xl bg-[#172033] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#2FABE1]"
          >
            <LogIn size={17} />
            <span>Login</span>
          </Link>
        </div>
      </header>
      <section className="bg-[#172033] px-4 py-14 text-white sm:px-6 lg:px-8">
        <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1fr_380px] lg:items-end">
          <div>
            <div className="mb-4 flex items-center gap-2 text-sm text-white/45">
              <Link to="/plans" className="transition hover:text-white">Judylink</Link>
              <span>/</span>
              <span className="text-white">Systems &amp; Registration</span>
            </div>
            <p className="mb-3 text-sm font-semibold uppercase tracking-widest text-[#FAA723]">Choose your system</p>
            <h1 className="max-w-4xl text-3xl font-black leading-tight sm:text-5xl" style={{ fontFamily: "Poppins, sans-serif" }}>
              Choose your login, pay registration, then wait for account approval.
            </h1>
            <p className="mt-5 max-w-2xl text-sm leading-6 text-white/65 sm:text-base">
              Select the system made for your business, review its features and price, then submit your company registration.
            </p>
          </div>

          <div className="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <div className="mb-4 flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#2FABE1]">
                <ShieldCheck size={22} />
              </div>
              <div>
                <p className="text-sm font-bold">Approval workflow</p>
                <p className="text-xs text-white/55">Verified payment before activation</p>
              </div>
            </div>
            <div className="space-y-3 text-sm">
              {["User creates username and password", "ScayPay confirms payment", "Super admin activates account"].map((item, index) => (
                <div key={item} className="flex items-center gap-3">
                  <span className="flex h-7 w-7 items-center justify-center rounded-full bg-white/10 text-xs font-bold text-[#FAA723]">
                    {index + 1}
                  </span>
                  <span className="text-white/75">{item}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="mb-10">
          <RegistrationSteps currentStep={currentStep} />
        </div>

        {currentStep === 1 && (
          <>
            <div className="mb-8 text-center">
              <p className="text-sm font-semibold uppercase tracking-widest text-[#2FABE1]">Step 1</p>
              <h2 className="mt-2 text-2xl font-black text-[#172033] sm:text-3xl" style={{ fontFamily: "Poppins, sans-serif" }}>
                Choose your business type
              </h2>
              <p className="mt-2 text-sm text-[#172033]/55">Select the system that best matches your business, then continue to registration.</p>
            </div>
            <div className="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {plans.map((plan, index) => {
            const isSelected = selectedPlanId === plan.id;
            const PlanIcon = plan.icon;
            return (
              <motion.div
                key={plan.id}
                role="button"
                tabIndex={0}
                initial={{ opacity: 0, y: 18 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.06 }}
                onClick={() => {
                  setSelectedPlanId(plan.id);
                  setField("websiteType", plan.websiteType);
                }}
                onKeyDown={(event) => {
                  if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    setSelectedPlanId(plan.id);
                    setField("websiteType", plan.websiteType);
                  }
                }}
                className={`relative flex h-full cursor-pointer flex-col rounded-2xl border p-5 text-left shadow-sm transition ${
                  isSelected
                    ? "border-[#2FABE1] bg-white shadow-[#2FABE1]/10 ring-4 ring-[#2FABE1]/10"
                    : "border-gray-100 bg-white hover:border-[#2FABE1]/40"
                }`}
              >
                <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-xl bg-[#2FABE1]/10 text-[#2FABE1]">
                  <PlanIcon size={21} />
                </div>
                <h2 className="text-xl font-black text-[#172033]" style={{ fontFamily: "Poppins, sans-serif" }}>{plan.name}</h2>
                <p className="mt-2 min-h-12 text-sm leading-6 text-[#172033]/55">{plan.audience}</p>
                <div className="my-5">
                  <span className="text-3xl font-black text-[#172033]">{formatTzs(plan.price)}</span>
                  <span className="text-sm text-[#172033]/50"> / {plan.billing}</span>
                  <p className="mt-1 text-xs font-semibold text-[#FAA723]">{formatTzs(plan.setupFee)} registration fee</p>
                </div>
                <div className="space-y-2">
                  {plan.features.map((feature) => (
                    <div key={feature} className="flex items-start gap-2 text-sm text-[#172033]/70">
                      <Check className="mt-0.5 shrink-0 text-[#2FABE1]" size={15} />
                      <span>{feature}</span>
                    </div>
                  ))}
                </div>
                {isSelected ? (
                  <button
                    type="button"
                    onClick={(event) => {
                      event.stopPropagation();
                      setCurrentStep(2);
                      window.scrollTo({ top: 0, behavior: "smooth" });
                    }}
                    className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FAA723] px-5 py-3 text-sm font-black text-[#172033] shadow-md transition hover:bg-[#FAA723]/90"
                  >
                    Continue <ArrowRight size={17} />
                  </button>
                ) : (
                  <div className="mt-6 flex items-center gap-2 text-sm font-bold text-[#2FABE1]">
                    <span className="h-[17px] w-[17px]" />
                    Choose system
                  </div>
                )}
              </motion.div>
            );
          })}
            </div>
          </>
        )}

        {currentStep === 2 && (
          <>
            <div className="mb-8 text-center">
              <p className="text-sm font-semibold uppercase tracking-widest text-[#2FABE1]">Step 2</p>
              <h2 className="mt-2 text-2xl font-black text-[#172033] sm:text-3xl" style={{ fontFamily: "Poppins, sans-serif" }}>
                Complete your registration
              </h2>
              <p className="mt-2 text-sm text-[#172033]/55">Create the login for your {selectedPlan.name} system.</p>
            </div>
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
          <form onSubmit={submit} className="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
            <div className="mb-6 flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FAA723]/15 text-[#FAA723]">
                <UserPlus size={22} />
              </div>
              <div>
                <h2 className="text-xl font-black text-[#172033]" style={{ fontFamily: "Poppins, sans-serif" }}>Create Account Request</h2>
                <p className="text-sm text-[#172033]/50">Super admin will review this after payment.</p>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Company Name *</label>
                <input className={inputClass("companyName")} value={form.companyName} onChange={(event) => setField("companyName", event.target.value)} placeholder="Zanzibar Trips Ltd" />
                {errors.companyName && <p className="mt-1 text-xs text-red-500">{errors.companyName}</p>}
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Owner Name *</label>
                <input className={inputClass("ownerName")} value={form.ownerName} onChange={(event) => setField("ownerName", event.target.value)} placeholder="Full name" />
                {errors.ownerName && <p className="mt-1 text-xs text-red-500">{errors.ownerName}</p>}
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70" htmlFor="business-type">Business Type *</label>
                <input
                  id="business-type"
                  className={`${inputClass("websiteType")} cursor-default bg-slate-50 text-[#172033]/70`}
                  value={form.websiteType}
                  readOnly
                  aria-readonly="true"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Email Address *</label>
                <input className={inputClass("email")} value={form.email} onChange={(event) => setField("email", event.target.value)} placeholder="owner@example.com" type="email" />
                {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Phone Number *</label>
                <input className={inputClass("phone")} value={form.phone} onChange={(event) => setField("phone", event.target.value)} placeholder="+255 700 000 000" />
                {errors.phone && <p className="mt-1 text-xs text-red-500">{errors.phone}</p>}
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Username *</label>
                <input className={inputClass("username")} value={form.username} onChange={(event) => setField("username", event.target.value.toLowerCase())} placeholder="company-admin" autoComplete="username" />
                {errors.username && <p className="mt-1 text-xs text-red-500">{errors.username}</p>}
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Password *</label>
                <input className={inputClass("password")} value={form.password} onChange={(event) => setField("password", event.target.value)} placeholder="At least 8 characters" type="password" autoComplete="new-password" />
                {errors.password && <p className="mt-1 text-xs text-red-500">{errors.password}</p>}
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-semibold text-[#172033]/70">Confirm Password *</label>
                <input className={inputClass("confirmPassword")} value={form.confirmPassword} onChange={(event) => setField("confirmPassword", event.target.value)} placeholder="Repeat password" type="password" autoComplete="new-password" />
                {errors.confirmPassword && <p className="mt-1 text-xs text-red-500">{errors.confirmPassword}</p>}
              </div>
            </div>

            <label className="mt-6 flex cursor-pointer items-start gap-3">
              <input checked={agreed} onChange={(event) => setAgreed(event.target.checked)} type="checkbox" className="mt-1 accent-[#2FABE1]" />
              <span className="text-sm leading-6 text-[#172033]/65">
                I understand I will continue to ScayPay sandbox and registration is activated only after confirmed payment and super admin approval.
              </span>
            </label>
            {errors.agreed && <p className="mt-1 text-xs text-red-500">{errors.agreed}</p>}
            {submitError && (
              <div className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {submitError}
              </div>
            )}

            <div className="mt-7 grid gap-3 sm:grid-cols-[auto_1fr]">
              <button
                type="button"
                onClick={() => setCurrentStep(1)}
                className="rounded-xl border-2 border-[#2FABE1]/25 px-6 py-3.5 text-sm font-black text-[#2FABE1] transition hover:bg-[#2FABE1]/5"
              >
                Back
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FAA723] px-6 py-3.5 text-sm font-black text-[#172033] shadow-lg transition hover:bg-[#FAA723]/90 disabled:opacity-60"
              >
                {submitting ? (
                  <>
                    <Loader2 className="animate-spin" size={17} /> Opening ScayPay...
                  </>
                ) : (
                  <>
                    Continue to Secure Payment <ArrowRight size={17} />
                  </>
                )}
              </button>
            </div>
          </form>

          <aside className="h-fit rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
            <div className="mb-5 flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#2FABE1]/10 text-[#2FABE1]">
                <LockKeyhole size={20} />
              </div>
              <div>
                <h2 className="font-black text-[#172033]" style={{ fontFamily: "Poppins, sans-serif" }}>Payment Summary</h2>
                <p className="text-xs text-[#172033]/45">ScayPay sandbox checkout</p>
              </div>
            </div>

            <div className="space-y-3 text-sm">
              <div className="flex justify-between border-b border-gray-100 pb-3">
                <span className="text-[#172033]/55">Selected system</span>
                <span className="font-bold text-[#172033]">{selectedPlan.name}</span>
              </div>
              <div className="flex justify-between border-b border-gray-100 pb-3">
                <span className="text-[#172033]/55">Monthly price</span>
                <span className="font-bold text-[#172033]">{formatTzs(selectedPlan.price)}</span>
              </div>
              <div className="flex justify-between border-b border-gray-100 pb-3">
                <span className="text-[#172033]/55">Registration fee</span>
                <span className="font-bold text-[#172033]">{formatTzs(selectedPlan.setupFee)}</span>
              </div>
              <div className="flex justify-between pt-1 text-base">
                <span className="font-bold text-[#172033]">First payment</span>
                <span className="font-black text-[#2FABE1]">{formatTzs(firstPayment)}</span>
              </div>
            </div>

            <div className="mt-6 rounded-xl border border-[#FAA723]/20 bg-[#FAA723]/10 p-4">
              <div className="mb-2 flex items-center gap-2 text-sm font-bold text-[#172033]">
                <Mail size={16} className="text-[#FAA723]" />
                After submission
              </div>
              <p className="text-xs leading-5 text-[#172033]/60">
                After you submit, ScayPay opens securely. Your chosen login remains inactive until payment is confirmed and the company account is approved.
              </p>
            </div>
          </aside>
        </div>
          </>
        )}

        {currentStep === 3 && (
          <div className="mx-auto max-w-2xl rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-xl shadow-black/5 sm:p-12">
            <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[#2FABE1]/10 text-[#2FABE1]">
              <Loader2 className="animate-spin" size={38} />
            </div>
            <p className="text-sm font-semibold uppercase tracking-widest text-[#FAA723]">Step 3</p>
            <h2 className="mt-2 text-3xl font-black text-[#172033]" style={{ fontFamily: "Poppins, sans-serif" }}>
              Opening secure payment
            </h2>
            <p className="mx-auto mt-4 max-w-lg text-sm leading-6 text-[#172033]/60">
              Please wait while we create your {selectedPlan.name} registration and connect you to ScayPay.
            </p>
            <div className="mt-6 rounded-xl border border-[#FAA723]/20 bg-[#FAA723]/10 px-4 py-3 text-sm font-semibold text-[#172033]/70">
              Do not close or refresh this page.
            </div>
          </div>
        )}
      </section>
      <footer className="border-t border-gray-100 bg-white px-4 py-6 text-center text-sm text-[#172033]/45 sm:px-6 lg:px-8">
        Judylink &middot; System selection and company registration
      </footer>
    </div>
  );
}
