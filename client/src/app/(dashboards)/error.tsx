"use client";

import { useEffect } from "react";
import { AlertTriangle, RotateCcw } from "lucide-react";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Log the error to an error reporting service like Sentry

  }, [error]);

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] p-6 text-center animate-in fade-in zoom-in duration-500">
      <div className="w-24 h-24 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mb-6 shadow-sm border border-rose-100">
        <AlertTriangle className="w-12 h-12" />
      </div>
      <h2 className="text-3xl font-extrabold text-slate-800 tracking-tight mb-3">Something went wrong</h2>
      <p className="text-slate-500 max-w-md mx-auto mb-8 leading-relaxed">
        We encountered an unexpected error processing your request. Our engineering team has been automatically notified.
      </p>
      <button
        onClick={() => reset()}
        className="flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-8 py-3 rounded-xl font-bold shadow-lg transition-all active:scale-95"
      >
        <RotateCcw className="w-5 h-5" />
        Try Again
      </button>
    </div>
  );
}
