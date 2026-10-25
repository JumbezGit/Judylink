import { useState, useEffect } from "react";
import { Link, useLocation } from "react-router";
import { Compass, Home, Images, Info, Menu, Phone, X } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import logo from "../logo/Hyena Tours & Travel Logo S-1-Recovered-clean.webp";
import ThemeToggle from "./ThemeToggle";

const navLinks = [
  { label: "Home", path: "/", icon: Home },
  { label: "Tours", path: "/tours", icon: Compass },
  { label: "About Us", path: "/about", icon: Info },
  { label: "Gallery", path: "/gallery", icon: Images },
  { label: "Contact", path: "/contact", icon: Phone },
];

export default function Header() {
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const location = useLocation();
  const isHome = location.pathname === "/";

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 60);
    window.addEventListener("scroll", onScroll);
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);

  const isTransparent = isHome && !scrolled;
  const headerBg = isTransparent
    ? "bg-transparent"
    : "bg-white/95 backdrop-blur-md shadow-xl";
  const textColor = isTransparent ? "text-white" : "text-[#172033]";
  const mutedTextColor = isTransparent ? "text-white/80 hover:text-white hover:bg-white/10" : "text-[#172033]/70 hover:text-[#172033] hover:bg-[#2FABE1]/10";
  const activeClass = isTransparent ? "text-[#FAA723] bg-white/10" : "text-[#FAA723] bg-[#FAA723]/10";

  return (
    <header className={`theme-static site-header fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${isTransparent ? "is-transparent" : ""} ${headerBg}`}>
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-3 group">
            
            <img
              src={logo}
              alt="Judylink"
              className="h-14 w-14 rounded-full object-contain transition-transform duration-200 group-hover:scale-105"
            />
            <span className="hidden sm:block text-right leading-tight">
              <span className={`block text-sm font-semibold font-black ${textColor}`} style={{ fontFamily: "Poppins, sans-serif" }}>
                <span className="text-[#FAA723]">Judylink</span>
                <span className="text-[#2FABE1]"> Tours</span>
              </span>
              <span className="block text-xs font-semibold" style={{ fontFamily: "Poppins, sans-serif" }}>
                <span className="text-[#FAA723]">&</span>
                <span className="text-[#2FABE1]"> Travel</span>
              </span>
            </span>
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden lg:flex items-center gap-1">
            {navLinks.map((link) => {
              const active = location.pathname === link.path || (link.path !== "/" && location.pathname.startsWith(link.path));
              const Icon = link.icon;
              return (
                <Link
                  key={link.path}
                  to={link.path}
                  className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-normal transition-all duration-200 ${
                    active
                      ? activeClass
                      : mutedTextColor
                  }`}
                >
                  <Icon size={16} strokeWidth={2.2} />
                  {link.label}
                </Link>
              );
            })}
          </nav>

          <div className="flex items-center gap-2">
            <ThemeToggle />
            <button
              className={`lg:hidden p-2 rounded-lg transition-colors ${isTransparent ? "text-white hover:bg-white/10" : "text-[#172033] hover:bg-[#2FABE1]/10"}`}
              onClick={() => setOpen(!open)}
              aria-label="Toggle menu"
            >
              {open ? <X size={24} /> : <Menu size={24} />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile menu */}
      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={{ opacity: 0, height: 0 }}
            className="lg:hidden bg-[#172033] border-t border-white/10 overflow-hidden"
          >
            <div className="px-4 py-4 space-y-1">
              {navLinks.map((link) => {
                const active = location.pathname === link.path;
                const Icon = link.icon;
                return (
                  <Link
                    key={link.path}
                    to={link.path}
                    className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-normal transition-colors ${
                      active ? "text-[#FAA723] bg-white/10" : "text-white/80 hover:text-white hover:bg-white/10"
                    }`}
                  >
                    <Icon size={18} strokeWidth={2.2} />
                    {link.label}
                  </Link>
                );
              })}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </header>
  );
}
