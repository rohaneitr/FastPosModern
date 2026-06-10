"use client";

import React from "react";
import { AlertCircle, CreditCard, Receipt, FileText } from "lucide-react";
import { useAuthStore } from "@/store/useAuthStore";
import { useAuth } from "@/contexts/AuthContext";

export default function SuspendedBillingPage() {
  const { user } = useAuthStore();
  const { logout } = useAuth();
  
  const isBusinessAdmin = user?.roles?.some((r: any) => r.name === 'BusinessAdmin') || false;

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 p-6 font-sans">
      <div className="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-100">
        <div className="bg-rose-50 p-8 flex flex-col items-center justify-center border-b border-rose-100">
          <div className="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mb-4">
            <AlertCircle className="w-10 h-10" />
          </div>
          <h1 className="text-2xl font-black text-rose-700 text-center tracking-tight">Account Suspended</h1>
          <p className="text-rose-600/80 text-center font-medium mt-2">Your subscription has expired or a payment failed.</p>
        </div>

        <div className="p-8">
          {isBusinessAdmin ? (
            <div className="flex flex-col gap-6">
              <div className="bg-slate-50 p-4 rounded-xl border border-slate-100 text-sm text-slate-600">
                To restore service to your POS terminals and dashboard, please update your billing details and settle any past-due invoices.
              </div>
              
              <button className="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl flex items-center justify-center gap-2 transition-colors">
                <CreditCard className="w-5 h-5" />
                Update Payment Method
              </button>
              
              <button className="w-full py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl flex items-center justify-center gap-2 transition-colors">
                <Receipt className="w-5 h-5" />
                View Past Invoices
              </button>
            </div>
          ) : (
            <div className="flex flex-col gap-4 text-center">
              <p className="text-slate-600 font-medium">
                The account administrator must settle the outstanding balance to restore POS operations.
              </p>
              <div className="bg-amber-50 text-amber-700 p-4 rounded-xl border border-amber-100 flex items-center justify-center gap-2 font-bold text-sm">
                <FileText className="w-4 h-4" />
                Please contact your manager.
              </div>
            </div>
          )}

          <div className="mt-8 pt-6 border-t border-slate-100 text-center">
            <button 
              onClick={() => logout()}
              className="text-slate-500 font-semibold hover:text-slate-800 transition-colors"
            >
              Log out securely
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
