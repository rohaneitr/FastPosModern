"use client";

import React, { useEffect, useState } from "react";
import { Command } from "cmdk";
import { useRouter } from "next/navigation";
import { Search, Package, ShoppingCart, Activity, FileText, ArrowRight } from "lucide-react";
import { useAuthStore } from "@/store/useAuthStore";
import clsx from "clsx";

export function CommandPalette() {
  const [open, setOpen] = useState(false);
  const router = useRouter();
  const hasPermission = useAuthStore((state) => state.hasPermission);

  // Toggle the menu when ⌘K is pressed
  useEffect(() => {
    const down = (e: KeyboardEvent) => {
      if (e.key === "k" && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        setOpen((open) => !open);
      }
    };
    document.addEventListener("keydown", down);
    return () => document.removeEventListener("keydown", down);
  }, []);

  const runCommand = (command: () => void) => {
    setOpen(false);
    command();
  };

  // RBAC Filtered Commands
  const actions = [
    {
      id: "pos",
      name: "Open POS Terminal",
      icon: <ShoppingCart className="w-4 h-4 mr-2" />,
      shortcut: "T",
      perform: () => router.push("/terminal"),
      permission: "process_sales",
    },
    {
      id: "inventory_transfer",
      name: "Transfer Stock (Downtown to Uptown)",
      icon: <Package className="w-4 h-4 mr-2" />,
      perform: () => router.push("/business/inventory/transfer"),
      permission: "manage_inventory",
    },
    {
      id: "ledger",
      name: "Accounting Ledger",
      icon: <FileText className="w-4 h-4 mr-2" />,
      perform: () => router.push("/business/reports/ledger"),
      permission: "view_reports",
    },
    {
      id: "purchases",
      name: "Receive Purchase Order (WAC)",
      icon: <Activity className="w-4 h-4 mr-2" />,
      perform: () => router.push("/business/purchases/create"),
      permission: "manage_purchases",
    },
  ].filter(action => hasPermission(action.permission));

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[200] bg-slate-900/40 backdrop-blur-sm flex items-start justify-center pt-[15vh]">
      <Command 
        className="w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-100 flex flex-col transform transition-all"
        loop
        onKeyDown={(e) => {
          if (e.key === "Escape") setOpen(false);
        }}
      >
        <div className="flex items-center px-4 py-3 border-b border-slate-100">
          <Search className="w-5 h-5 text-indigo-500 mr-2" />
          <Command.Input 
            autoFocus 
            placeholder="Type a command or search..." 
            className="w-full bg-transparent outline-none text-slate-800 placeholder:text-slate-400 text-lg"
          />
        </div>

        <Command.List className="max-h-[300px] overflow-y-auto p-2 scrollbar-hide">
          <Command.Empty className="py-6 text-center text-slate-500 text-sm font-medium">
            No results found or access denied.
          </Command.Empty>

          <Command.Group heading="Quick Actions" className="text-xs font-bold text-slate-400 uppercase tracking-wider px-2 py-1">
            {actions.map((action) => (
              <Command.Item
                key={action.id}
                onSelect={() => runCommand(action.perform)}
                className={clsx(
                  "flex items-center px-3 py-2.5 mt-1 cursor-pointer rounded-xl text-sm font-semibold text-slate-700",
                  "hover:bg-indigo-50 hover:text-indigo-700 aria-selected:bg-indigo-50 aria-selected:text-indigo-700 transition-colors"
                )}
              >
                {action.icon}
                {action.name}
                <div className="ml-auto flex items-center opacity-0 group-hover:opacity-100 aria-selected:opacity-100 transition-opacity text-indigo-500">
                  <ArrowRight className="w-4 h-4" />
                </div>
              </Command.Item>
            ))}
          </Command.Group>
        </Command.List>
      </Command>
    </div>
  );
}
