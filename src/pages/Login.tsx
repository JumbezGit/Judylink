import { useEffect } from "react";
import { Link } from "react-router";

export default function Login() {
  const loginUrl = import.meta.env.VITE_ADMIN_URL || "/backend/secure-operation-4f2a/";

  useEffect(() => {
    document.title = "Judylink | Login";
    window.location.replace(loginUrl);
  }, [loginUrl]);

  return (
    <div className="grid min-h-screen place-items-center bg-slate-50 px-4 text-center">
      <div className="grid w-full max-w-sm justify-items-center gap-4 rounded-2xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/10">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-[#2FABE1]" />
        <div>
          <p className="mb-1 text-sm font-bold text-[#2FABE1]">Judylink</p>
          <h1 className="text-xl font-black text-[#172033]">Opening login...</h1>
          <p className="mt-2 text-sm text-[#172033]/60">You are being redirected to the secure login page.</p>
        </div>
        <a href={loginUrl} className="text-sm font-bold text-[#2FABE1] hover:underline">
          Continue to login
        </a>
        <Link to="/plans" className="text-xs font-semibold text-[#172033]/50 hover:text-[#172033]">
          Back to plans
        </Link>
      </div>
    </div>
  );
}
