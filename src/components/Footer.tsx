import { Link } from "react-router";
import { Mail, Phone, MapPin, Send } from "lucide-react";
import { useState } from "react";

import logo from "../logo/Hyena Tours & Travel Logo S-1-Recovered-clean.webp";

export default function Footer() {
  const [email, setEmail] = useState("");
  const [subscribed, setSubscribed] = useState(false);
  const [adminClicks, setAdminClicks] = useState(0);
  const instagramUrl = "https://www.instagram.com/hyena_tour?igsh=a2Jkc3lpNThnbnc1";
  const adminUrl = import.meta.env.VITE_ADMIN_URL || "/backend/secure-operation-4f2a/";
  const staffLoginUrl = "/backend/staff-login.php";

  const handleSubscribe = (e: React.FormEvent) => {
    e.preventDefault();
    if (email) { setSubscribed(true); setEmail(""); }
  };

  const handleLogoClick = () => {
    setAdminClicks((clicks) => {
      const next = clicks + 1;
      if (next >= 8) {
        window.location.href = adminUrl;
        return 0;
      }
      return next;
    });
  };

  return (
    <footer className="bg-[#172033] text-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-8">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
          {/* Brand */}
          <div className="lg:col-span-1">
            <div className="flex items-center gap-3 mb-4">
              <button type="button" onClick={handleLogoClick} aria-label="Judylink" className="rounded-full border-0 bg-transparent p-0">
                <img
                  src={logo}
                  alt="Judylink"
                  className="h-20 w-20 rounded-full object-contain"
                />
              </button>
            </div>
            <p className="text-white/60 text-sm leading-relaxed mb-5">
              Your trusted local experts for unforgettable Zanzibar tours, Tanzania safaris, and authentic East African experiences since 2015.
            </p>
            <div className="flex gap-3">
              {[
                { href: instagramUrl, label: "Instagram" },
              ].map(({ href, label }) => (
                <a
                  key={label}
                  href={href}
                  aria-label={`Judylink on ${label}`}
                  target="_blank"
                  rel="noreferrer"
                  className="w-9 h-9 rounded-lg bg-white/10 hover:bg-[#2FABE1] flex items-center justify-center transition-colors duration-200"
                >
                  <span className="text-xs font-black tracking-wide">IG</span>
                </a>
              ))}
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h4 className="font-semibold text-[#FAA723] uppercase tracking-widest text-xs mb-4">Quick Links</h4>
            <ul className="space-y-2.5">
              {[
                { label: "Home", to: "/" },
                { label: "Tours", to: "/tours" },
                { label: "About Us", to: "/about" },
                { label: "Gallery", to: "/gallery" },
                { label: "Contact", to: "/contact" },
                { label: "Privacy Policy", to: "/privacy" },
                { label: "Terms & Conditions", to: "/terms" },
              ].map((l) => (
                <li key={l.to}>
                  <Link to={l.to} className="text-white/60 hover:text-[#2FABE1] text-sm transition-colors duration-200">
                    {l.label}
                  </Link>
                </li>
              ))}
              <li>
                <a href={staffLoginUrl} className="text-white/60 hover:text-[#2FABE1] text-sm transition-colors duration-200">
                  Staff Login
                </a>
              </li>
            </ul>
          </div>

          {/* Popular Tours */}
          <div>
            <h4 className="font-semibold text-[#FAA723] uppercase tracking-widest text-xs mb-4">Popular Tours</h4>
            <ul className="space-y-2.5">
              {[
                "Stone Town & Spice Farm Tour",
                "Safari Blue Experience",
                "Mnemba Island Snorkelling",
                "Prison Island & Sandbank",
                "Jozani Forest & The Rock",
                "Serengeti Safari 4 Days",
              ].map((t) => (
                <li key={t}>
                  <Link to="/tours" className="text-white/60 hover:text-[#2FABE1] text-sm transition-colors duration-200">
                    {t}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact + Newsletter */}
          <div>
            <h4 className="font-semibold text-[#FAA723] uppercase tracking-widest text-xs mb-4">Contact Us</h4>
            <ul className="space-y-3 mb-6">
              <li className="flex items-start gap-3 text-sm text-white/60">
                <MapPin size={15} className="mt-0.5 text-[#2FABE1] shrink-0" />
                <span>Kenyatta Road, Stone Town, Zanzibar, Tanzania</span>
              </li>
              <li className="flex items-center gap-3 text-sm">
                <Phone size={15} className="text-[#2FABE1] shrink-0" />
                <a href="tel:+255777123456" className="text-white/60 hover:text-white transition-colors">+255 777 123 456</a>
              </li>
              <li className="flex items-center gap-3 text-sm">
                <Mail size={15} className="text-[#2FABE1] shrink-0" />
                <a href="mailto:info@hyenatour.com" className="text-white/60 hover:text-white transition-colors">info@hyenatour.com</a>
              </li>
            </ul>

            <h4 className="font-semibold text-[#FAA723] uppercase tracking-widest text-xs mb-3">Newsletter</h4>
            {subscribed ? (
              <p className="text-[#2FABE1] text-sm font-medium">Thank you for subscribing! </p>
            ) : (
              <form onSubmit={handleSubscribe} className="flex gap-2">
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="Your email address"
                  className="flex-1 bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-[#2FABE1] transition-colors"
                />
                <button
                  type="submit"
                  className="p-2.5 bg-[#2FABE1] hover:bg-[#2FABE1] rounded-lg transition-colors"
                >
                  <Send size={15} />
                </button>
              </form>
            )}
          </div>
        </div>

        <div className="border-t border-white/10 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
          <p className="text-white/40 text-sm">
            (c) 2026 Judylink. All rights reserved.
          </p>
          <div className="flex gap-6 text-sm text-white/40">
            <Link to="/privacy" className="hover:text-white/70 transition-colors">Privacy Policy</Link>
            <Link to="/terms" className="hover:text-white/70 transition-colors">Terms & Conditions</Link>
            <Link to="/cancellation" className="hover:text-white/70 transition-colors">Cancellation Policy</Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
