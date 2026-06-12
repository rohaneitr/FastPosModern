'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { usePosSounds } from '@/hooks/usePosSounds';

export default function ProfileSettingsPage() {
  const { playTaskSuccess } = usePosSounds();
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [user, setUser] = useState<any>(null);

  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    theme_preference: 'system',
  });
  
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [avatarPreview, setAvatarPreview] = useState<string>('');

  useEffect(() => {
    fetchProfile();
  }, []);

  const fetchProfile = async () => {
    try {
      const res = await api.get('/profile');
      setUser(res.data);
      setForm({
        first_name: res.data.first_name || '',
        last_name: res.data.last_name || '',
        theme_preference: res.data.theme_preference || 'system',
      });
      if (res.data.avatar) {
        // Need to prefix with API base URL if relative
        setAvatarPreview(res.data.avatar.startsWith('http') ? res.data.avatar : `${process.env.NEXT_PUBLIC_API_URL?.replace('/api/v1', '')}/storage/${res.data.avatar}`);
      }
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // Basic validation
      if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
      }
      setAvatarFile(file);
      const url = URL.createObjectURL(file);
      setAvatarPreview(url);
    }
  };

  const handleThemeChange = (val: string) => {
    setForm({ ...form, theme_preference: val });
    
    // Apply immediately to HTML element
    const root = document.documentElement;
    if (val === 'dark') {
      root.classList.add('dark');
    } else if (val === 'light') {
      root.classList.remove('dark');
    } else {
      // System
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
         root.classList.add('dark');
      } else {
         root.classList.remove('dark');
      }
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      const formData = new FormData();
      formData.append('first_name', form.first_name);
      formData.append('last_name', form.last_name);
      formData.append('theme_preference', form.theme_preference);
      if (avatarFile) {
        formData.append('avatar', avatarFile);
      }

      await api.post('/profile/update', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      playTaskSuccess();
      alert('Profile updated successfully!');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to update profile');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <div className="p-8 text-white">Loading profile...</div>;

  return (
    <div className="max-w-3xl mx-auto py-8 animate-in fade-in duration-500">
      <h1 className="text-3xl font-bold text-white mb-2">Profile & Settings</h1>
      <p className="text-text-muted mb-8">Manage your personal information, avatar, and theme preferences.</p>

      <div className="bg-surface border border-border rounded-2xl shadow-xl overflow-hidden">
        <form onSubmit={handleSubmit} className="p-8 flex flex-col gap-8">
          
          <div className="flex items-center gap-6">
            <div className="relative group w-24 h-24 rounded-full overflow-hidden border-2 border-border/50 bg-background flex items-center justify-center shrink-0">
              {avatarPreview ? (
                <img src={avatarPreview} alt="Avatar" className="w-full h-full object-cover" />
              ) : (
                <span className="text-4xl text-text-muted font-bold uppercase">
                  {form.first_name.charAt(0)}{form.last_name.charAt(0)}
                </span>
              )}
              <label className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center cursor-pointer transition-opacity text-xs font-bold text-white tracking-wider uppercase">
                Change
                <input type="file" className="hidden" accept="image/*" onChange={handleAvatarChange} />
              </label>
            </div>
            <div>
              <h3 className="text-lg font-bold text-white mb-1">Profile Picture</h3>
              <p className="text-sm text-text-muted">Upload a new avatar (JPEG, PNG, max 2MB).</p>
            </div>
          </div>

          <hr className="border-border/50" />

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="flex flex-col gap-2">
              <label className="text-sm font-medium text-text-muted">First Name</label>
              <input required value={form.first_name} onChange={e => setForm({...form, first_name: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
            </div>
            <div className="flex flex-col gap-2">
              <label className="text-sm font-medium text-text-muted">Last Name</label>
              <input value={form.last_name} onChange={e => setForm({...form, last_name: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
            </div>
          </div>

          <hr className="border-border/50" />

          <div className="flex flex-col gap-4">
            <h3 className="text-lg font-bold text-white">Theme Preference</h3>
            <div className="grid grid-cols-3 gap-4">
              {['light', 'dark', 'system'].map(theme => (
                <button
                  key={theme}
                  type="button"
                  onClick={() => handleThemeChange(theme)}
                  className={`py-3 px-4 rounded-xl border ${form.theme_preference === theme ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-text-muted hover:border-text-muted'} font-medium capitalize transition-all duration-150 active:scale-[0.97]`}
                >
                  {theme}
                </button>
              ))}
            </div>
          </div>

          <div className="flex justify-end pt-4">
            <button 
              type="submit" 
              disabled={submitting}
              className="bg-primary hover:brightness-110 text-white px-8 py-3 rounded-xl font-bold transition-all duration-150 active:scale-[0.97] shadow-lg disabled:opacity-50"
            >
              {submitting ? 'Saving...' : 'Save Changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
