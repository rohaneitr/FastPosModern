"use client";

import { useEffect } from "react";
import { ShieldAlert, RotateCcw } from "lucide-react";

export default function TerminalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {

  }, [error]);

  return (
    <div className="flex h-screen w-full items-center justify-center bg-slate-50 p-6 text-center animate-in fade-in duration-500">
      <div className="max-w-md bg-white p-10 rounded-3xl shadow-xl border border-slate-100 flex flex-col items-center">
        <div className="w-20 h-20 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mb-6 shadow-inner">
          <ShieldAlert className="w-10 h-10" />
        </div>
        <h2 className="text-2xl font-bold text-slate-800 mb-2">POS System Error</h2>
        <p className="text-slate-500 text-sm mb-8 leading-relaxed">
          A critical UI fault occurred in the terminal. No transactions were processed. Please reset the view.
        </p>
        <button
          onClick={() => reset()}
          className="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-xl font-bold text-lg shadow-lg shadow-indigo-200 transition-all active:scale-95"
        >
          <RotateCcw className="w-5 h-5" />
          Reboot Terminal
        </button>
      </div>
    </div>
  );
}
