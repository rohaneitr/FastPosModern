'use client';

import React, { useState } from 'react';

interface PrescriptionModalProps {
  onClose: () => void;
  onSubmit: (data: { doctor: string; patient: string; file: File | null; notes: string }) => void;
}

export function PrescriptionModal({ onClose, onSubmit }: PrescriptionModalProps) {
  const [doctor, setDoctor] = useState('');
  const [patient, setPatient] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [notes, setNotes] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!doctor && !patient && !file) {
      alert("Please provide at least a Doctor Name, Patient ID, or upload a prescription file.");
      return;
    }
    onSubmit({ doctor, patient, file, notes });
  };

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 animate-in fade-in duration-200">
      <div className="bg-background border border-border rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col">
        <div className="p-6 border-b border-border bg-rose-500/10">
          <h2 className="text-xl font-bold text-rose-500 flex items-center gap-2">
            <span>🛡️</span> Prescription Required
          </h2>
          <p className="text-text-muted mt-1 text-sm">
            One or more medicines in the cart are strictly Rx-only. Please attach the prescription reference.
          </p>
        </div>

        <form onSubmit={handleSubmit} className="p-6 flex flex-col gap-4">
          <div>
            <label className="block text-sm font-semibold text-text-muted mb-1">Doctor Name</label>
            <input 
              type="text" 
              value={doctor}
              onChange={(e) => setDoctor(e.target.value)}
              className="w-full bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-rose-500/50"
              placeholder="e.g. Dr. John Smith"
            />
          </div>

          <div>
            <label className="block text-sm font-semibold text-text-muted mb-1">Patient Name / ID</label>
            <input 
              type="text" 
              value={patient}
              onChange={(e) => setPatient(e.target.value)}
              className="w-full bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-rose-500/50"
              placeholder="e.g. P-4929"
            />
          </div>

          <div>
            <label className="block text-sm font-semibold text-text-muted mb-1">Prescription Image (Optional)</label>
            <input 
              type="file" 
              accept="image/*,.pdf"
              onChange={(e) => setFile(e.target.files?.[0] || null)}
              className="w-full text-sm text-text-muted file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-surface file:text-white hover:file:bg-surface/80"
            />
          </div>

          <div>
            <label className="block text-sm font-semibold text-text-muted mb-1">Pharmacist Notes</label>
            <textarea 
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="w-full bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-rose-500/50 h-20"
              placeholder="Dosage warnings, allergies, etc."
            />
          </div>

          <div className="flex justify-end gap-3 mt-4 pt-4 border-t border-border">
            <button 
              type="button" 
              onClick={onClose}
              className="px-4 py-2 hover:bg-white/5 rounded-lg font-semibold transition-colors"
            >
              Cancel Sale
            </button>
            <button 
              type="submit" 
              className="px-6 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold transition-all shadow-lg shadow-rose-600/20"
            >
              Authorize Prescription
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
