"use client";

import React from "react";
import { Wrench, ArrowLeft } from "lucide-react";
import { useRouter } from "next/navigation";

export default function UnderConstructionPage() {
  const router = useRouter();

  return (
    <div className="flex flex-col items-center justify-center min-h-[70vh] p-6 text-center animate-in fade-in zoom-in duration-500">
      <div className="w-24 h-24 bg-indigo-50 text-indigo-500 rounded-3xl flex items-center justify-center mb-6 shadow-sm border border-indigo-100 rotate-12">
        <Wrench className="w-12 h-12 -rotate-12" />
      </div>
      <h1 className="text-3xl font-extrabold text-slate-800 tracking-tight mb-3">Under Construction</h1>
      <p className="text-slate-500 max-w-md mx-auto mb-8 leading-relaxed">
        This module is currently being built by our engineering team. It will be available in an upcoming FastPOS release.
      </p>
      <button
        onClick={() => router.back()}
        className="flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-6 py-3 rounded-xl font-bold shadow-sm transition-all"
      >
        <ArrowLeft className="w-4 h-4" />
        Go Back
      </button>
    </div>
  );
}
