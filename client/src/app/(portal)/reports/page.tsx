'use client';

import React, { useState } from 'react';
import { Lock, FileText, CheckCircle, AlertTriangle } from 'lucide-react';
import api from '@/lib/api';

export default function PatientPortal() {
    const [step, setStep] = useState<1 | 2>(1);
    const [formData, setFormData] = useState({ order_number: '', mobile_number: '', otp: '' });
    const [loading, setLoading] = useState(false);
    const [signedUrl, setSignedUrl] = useState<string | null>(null);

    const handleRequestOtp = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        try {
            await api.post('/clinical/portal/request-otp', {
                order_number: formData.order_number,
                mobile_number: formData.mobile_number,
            });
            setStep(2);
        } catch (error: any) {
            alert(error.response?.data?.message || 'Failed to request OTP');
        } finally {
            setLoading(false);
        }
    };

    const handleVerifyOtp = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        try {
            const res = await api.post('/clinical/portal/verify-otp', {
                order_number: formData.order_number,
                otp: formData.otp,
            });
            setSignedUrl(res.data.signed_url);
        } catch (error: any) {
            alert(error.response?.data?.message || 'Invalid OTP');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
            <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
                <div className="bg-indigo-600 p-8 text-center text-white relative overflow-hidden">
                    <div className="absolute inset-0 bg-[url('/assets/noise.png')] opacity-20 mix-blend-overlay"></div>
                    <Lock className="mx-auto mb-4 relative z-10" size={48} />
                    <h1 className="text-2xl font-black relative z-10 tracking-tight">Secure Patient Portal</h1>
                    <p className="text-indigo-200 mt-2 text-sm relative z-10">Access your clinical reports securely.</p>
                </div>

                <div className="p-8">
                    {!signedUrl ? (
                        <>
                            {step === 1 ? (
                                <form onSubmit={handleRequestOtp} className="space-y-6 animate-in fade-in slide-in-from-right-4">
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-2">Report / Invoice ID</label>
                                        <input 
                                            required 
                                            type="text" 
                                            placeholder="e.g. LAB-2026-999"
                                            value={formData.order_number}
                                            onChange={e => setFormData({...formData, order_number: e.target.value})}
                                            className="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 outline-none transition-all font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-bold text-gray-700 mb-2">Registered Mobile Number</label>
                                        <input 
                                            required 
                                            type="tel" 
                                            placeholder="+1 234 567 8900"
                                            value={formData.mobile_number}
                                            onChange={e => setFormData({...formData, mobile_number: e.target.value})}
                                            className="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 outline-none transition-all"
                                        />
                                    </div>
                                    <div className="bg-amber-50 border border-amber-200 text-amber-800 text-xs p-3 rounded-lg flex items-start gap-2">
                                        <AlertTriangle size={16} className="shrink-0 mt-0.5" />
                                        <p>For your privacy, reports are protected by 2FA. We will send a secure OTP to this number.</p>
                                    </div>
                                    <button disabled={loading} className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-indigo-600/30 disabled:opacity-50">
                                        {loading ? 'Verifying...' : 'Request Secure Access'}
                                    </button>
                                </form>
                            ) : (
                                <form onSubmit={handleVerifyOtp} className="space-y-6 animate-in fade-in slide-in-from-right-4">
                                    <div className="text-center mb-6">
                                        <div className="bg-emerald-100 text-emerald-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <CheckCircle size={32} />
                                        </div>
                                        <h3 className="font-bold text-gray-900">OTP Sent!</h3>
                                        <p className="text-sm text-gray-500 mt-1">Please enter the 6-digit code sent to your phone.</p>
                                    </div>
                                    <div>
                                        <input 
                                            required 
                                            type="text" 
                                            maxLength={6}
                                            placeholder="------"
                                            value={formData.otp}
                                            onChange={e => setFormData({...formData, otp: e.target.value})}
                                            className="w-full border-2 border-gray-200 rounded-xl px-4 py-4 text-center text-2xl tracking-[0.5em] font-black focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 outline-none transition-all"
                                        />
                                    </div>
                                    <button disabled={loading} className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-indigo-600/30 disabled:opacity-50">
                                        {loading ? 'Verifying...' : 'Unlock Report'}
                                    </button>
                                </form>
                            )}
                        </>
                    ) : (
                        <div className="text-center animate-in zoom-in-95 duration-500">
                            <div className="bg-emerald-100 text-emerald-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                                <FileText size={40} />
                            </div>
                            <h2 className="text-2xl font-black text-gray-900 mb-2">Access Granted</h2>
                            <p className="text-gray-500 mb-8">Your clinical report has been securely decrypted.</p>
                            <a 
                                href={signedUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl transition-all shadow-xl shadow-emerald-500/30"
                            >
                                View & Download PDF
                            </a>
                            <p className="text-xs text-gray-400 mt-4">Link expires in 30 minutes for security.</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
