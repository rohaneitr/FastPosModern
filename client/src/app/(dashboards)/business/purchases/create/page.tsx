"use client";

import React, { useEffect } from "react";
import { useForm, useFieldArray, useWatch, Control } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { Plus, Trash2, Save, ShoppingBag, Loader2 } from "lucide-react";
import toast from "react-hot-toast";
import clsx from "clsx";
import api from "@/lib/api";
import useSWR from "swr";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

// Zod Validation Schema
const purchaseLineSchema = z.object({
  product_id: z.number({ message: "Product is required" }).min(1, "Product is required"),
  quantity: z.number({ message: "Quantity is required" }).positive("Quantity must be > 0"),
  unit_cost: z.number({ message: "Cost is required" }).min(0, "Cost must be >= 0"),
});

const purchaseSchema = z.object({
  supplier_id: z.number({ message: "Supplier is required" }).min(1, "Supplier is required"),
  location_id: z.number({ message: "Location is required" }).min(1, "Location is required"),
  reference_no: z.string().min(1, "Reference No. is required"),
  lines: z.array(purchaseLineSchema).min(1, "At least one product line is required"),
});

type PurchaseFormValues = z.infer<typeof purchaseSchema>;

// Isolated Total Calculator to prevent full form re-renders on keystrokes
function TotalCalculator({ control }: { control: Control<PurchaseFormValues> }) {
  const lines = useWatch({
    control,
    name: "lines",
  });

  const total = lines.reduce((acc, line) => {
    const qty = Number(line.quantity) || 0;
    const cost = Number(line.unit_cost) || 0;
    return acc + (qty * cost);
  }, 0);

  return (
    <div className="text-2xl font-bold text-slate-800 tracking-tight">
      ${total.toFixed(2)}
    </div>
  );
}

export default function CreatePurchasePage() {
  const { data: locationsData } = useSWR('/tenant/locations', fetcher);
  const { data: contactsData } = useSWR('/tenant/contacts?type=supplier', fetcher);
  const { data: productsData } = useSWR('/tenant/inventory', fetcher); // Use inventory endpoint

  const locations = locationsData?.data || [];
  const suppliers = contactsData?.data || [];
  const products = productsData?.data || [];

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<PurchaseFormValues>({
    resolver: zodResolver(purchaseSchema),
    defaultValues: {
      supplier_id: 0,
      location_id: 0,
      reference_no: `PO-${Date.now().toString().slice(-6)}`,
      lines: [{ product_id: 0, quantity: 1, unit_cost: 0 }]
    }
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "lines",
  });

  const onSubmit = async (data: PurchaseFormValues) => {
    try {
      await api.post('/tenant/purchases/receive', data);
      toast.success("Purchase Order received & WAC updated!");
      reset({
        supplier_id: 0,
        location_id: 0,
        reference_no: `PO-${Date.now().toString().slice(-6)}`,
        lines: [{ product_id: 0, quantity: 1, unit_cost: 0 }]
      });
    } catch (err: unknown) {
      const errorObj = err as Record<string, any>;
      toast.error(errorObj.response?.data?.message || "Failed to process purchase");
    }
  };

  return (
    <div className="max-w-6xl mx-auto p-6 text-slate-900">
      
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <ShoppingBag className="w-6 h-6 text-indigo-600" />
            Receive Purchase Order
          </h1>
          <p className="text-slate-500 text-sm mt-1">Receive stock, calculate Weighted Average Cost (WAC), and update baseline.</p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        
        {/* Top Controls Grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Supplier</label>
            <select 
              {...register("supplier_id", { valueAsNumber: true })}
              className={clsx(
                "w-full px-3 py-2 bg-slate-50 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                errors.supplier_id ? "border-rose-500" : "border-slate-200"
              )}
            >
              <option value="0">Select Supplier...</option>
              {suppliers.map((s: any) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
            {errors.supplier_id && <span className="text-xs font-semibold text-rose-500">{errors.supplier_id.message}</span>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Receiving Location</label>
            <select 
              {...register("location_id", { valueAsNumber: true })}
              className={clsx(
                "w-full px-3 py-2 bg-slate-50 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                errors.location_id ? "border-rose-500" : "border-slate-200"
              )}
            >
              <option value="0">Select Location...</option>
              {locations.map((l: any) => (
                <option key={l.id} value={l.id}>{l.name}</option>
              ))}
            </select>
            {errors.location_id && <span className="text-xs font-semibold text-rose-500">{errors.location_id.message}</span>}
          </div>

          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Reference / PO Number</label>
            <input 
              {...register("reference_no")}
              className={clsx(
                "w-full px-3 py-2 bg-slate-50 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                errors.reference_no ? "border-rose-500" : "border-slate-200"
              )}
              placeholder="e.g. PO-123456"
            />
            {errors.reference_no && <span className="text-xs font-semibold text-rose-500">{errors.reference_no.message}</span>}
          </div>
        </div>

        {/* Dynamic Field Array (High-Performance) */}
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h2 className="font-bold text-slate-800">Purchase Lines</h2>
            <button
              type="button"
              onClick={() => append({ product_id: 0, quantity: 1, unit_cost: 0 })}
              className="flex items-center gap-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
            >
              <Plus className="w-4 h-4" />
              Add Row
            </button>
          </div>
          
          <div className="p-0">
            {fields.length === 0 ? (
              <div className="p-8 text-center text-slate-500">
                <p>No lines added. Click "Add Row" to start.</p>
                {errors.lines?.root && <span className="text-sm font-bold text-rose-500 block mt-2">{errors.lines.root.message}</span>}
              </div>
            ) : (
              <div className="w-full">
                {fields.map((field, index) => (
                  <div key={field.id} className="flex flex-col sm:flex-row items-start sm:items-center gap-4 p-4 border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                    
                    <div className="w-full sm:flex-1">
                      <select 
                        {...register(`lines.${index}.product_id` as const, { valueAsNumber: true })}
                        className={clsx(
                          "w-full px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                          errors.lines?.[index]?.product_id ? "border-rose-500" : "border-slate-200"
                        )}
                      >
                        <option value="0">Select Product...</option>
                        {products.map((p: any) => (
                          <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                      </select>
                      {errors.lines?.[index]?.product_id && (
                        <span className="text-xs font-semibold text-rose-500 block mt-1">{errors.lines[index]?.product_id?.message}</span>
                      )}
                    </div>

                    <div className="w-full sm:w-32">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-semibold">Qty</span>
                        <input 
                          type="number"
                          step="0.0001"
                          {...register(`lines.${index}.quantity` as const, { valueAsNumber: true })}
                          className={clsx(
                            "w-full pl-10 pr-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                            errors.lines?.[index]?.quantity ? "border-rose-500" : "border-slate-200"
                          )}
                        />
                      </div>
                      {errors.lines?.[index]?.quantity && (
                        <span className="text-xs font-semibold text-rose-500 block mt-1">{errors.lines[index]?.quantity?.message}</span>
                      )}
                    </div>

                    <div className="w-full sm:w-40">
                      <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold">$</span>
                        <input 
                          type="number"
                          step="0.01"
                          {...register(`lines.${index}.unit_cost` as const, { valueAsNumber: true })}
                          className={clsx(
                            "w-full pl-8 pr-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                            errors.lines?.[index]?.unit_cost ? "border-rose-500" : "border-slate-200"
                          )}
                          placeholder="Unit Cost"
                        />
                      </div>
                      {errors.lines?.[index]?.unit_cost && (
                        <span className="text-xs font-semibold text-rose-500 block mt-1">{errors.lines[index]?.unit_cost?.message}</span>
                      )}
                    </div>

                    <button
                      type="button"
                      onClick={() => remove(index)}
                      className="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors self-end sm:self-auto"
                      disabled={fields.length === 1}
                    >
                      <Trash2 className="w-5 h-5" />
                    </button>

                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Footer actions */}
        <div className="flex flex-col sm:flex-row items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
          <div className="flex flex-col">
            <span className="text-sm font-semibold text-slate-500 uppercase tracking-wider">Total Amount</span>
            <TotalCalculator control={control} />
          </div>
          
          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full sm:w-auto mt-4 sm:mt-0 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-bold text-base transition-colors disabled:opacity-50"
          >
            {isSubmitting ? <Loader2 className="w-5 h-5 animate-spin" /> : <Save className="w-5 h-5" />}
            {isSubmitting ? 'Processing...' : 'Execute Receive & Costing'}
          </button>
        </div>

      </form>
    </div>
  );
}
