'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function ApiKeysPage() {
  const [tokens, setTokens] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [isGenerating, setIsGenerating] = useState(false);
  const [tokenName, setTokenName] = useState('');
  const [newToken, setNewToken] = useState<string | null>(null);

  useEffect(() => {
    fetchTokens();
  }, []);

  const fetchTokens = async () => {
    try {
      const res = await api.get('/api-keys');
      setTokens(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleGenerate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!tokenName.trim()) return;
    
    setIsGenerating(true);
    setNewToken(null);
    try {
      const res = await api.post('/api-keys', { name: tokenName });
      setNewToken(res.data.token);
      setTokenName('');
      fetchTokens();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to generate token');
    } finally {
      setIsGenerating(false);
    }
  };

  const handleRevoke = async (id: number) => {
    if (!confirm('Are you sure you want to revoke this API key? Applications using it will immediately lose access.')) return;
    try {
      await api.delete(`/api-keys/${id}`);
      fetchTokens();
    } catch (err) {
      alert('Failed to revoke token');
    }
  };

  const copyToClipboard = () => {
    if (newToken) {
      navigator.clipboard.writeText(newToken);
      alert('API Key copied to clipboard!');
    }
  };

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-indigo-500">
          Developer API Keys
        </h1>
        <p className="text-text-muted mt-1">Manage Personal Access Tokens for headless commerce and external integrations.</p>
      </div>

      {newToken && (
        <div className="glass-card p-6 rounded-2xl border border-emerald-500 bg-emerald-500/10 shadow-[0_0_30px_rgba(16,185,129,0.15)]">
          <h2 className="text-xl font-bold text-white mb-2 flex items-center gap-2">
            <span className="text-2xl">🎉</span> API Key Generated Successfully
          </h2>
          <p className="text-emerald-200 mb-4 text-sm">
            Please copy this key and store it securely. For security reasons, <strong className="text-white">you will not be able to see it again!</strong>
          </p>
          <div className="flex items-center gap-3 bg-black/50 p-4 rounded-xl border border-emerald-500/30">
            <code className="text-emerald-400 flex-1 font-mono break-all">{newToken}</code>
            <button 
              onClick={copyToClipboard}
              className="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-bold transition-colors"
            >
              Copy
            </button>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        {/* Create Token Form */}
        <div className="lg:col-span-1">
          <form onSubmit={handleGenerate} className="glass-card p-6 rounded-2xl border border-border sticky top-8">
            <h2 className="text-lg font-bold text-white mb-4">Generate New Key</h2>
            <div className="mb-4">
              <label className="block text-sm font-semibold text-text-muted mb-2">Token Name / Identifier</label>
              <input 
                type="text" 
                value={tokenName}
                onChange={e => setTokenName(e.target.value)}
                placeholder="e.g. WooCommerce Integration"
                required
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-indigo-500/50 transition-all"
              />
            </div>
            <button 
              type="submit" 
              disabled={isGenerating || !tokenName.trim()}
              className="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-bold shadow-lg shadow-indigo-600/20 transition-all disabled:opacity-50 flex justify-center items-center gap-2"
            >
              {isGenerating ? 'Generating...' : 'Generate API Key'}
            </button>
          </form>
        </div>

        {/* Tokens List */}
        <div className="lg:col-span-2">
          <div className="glass-card p-6 rounded-2xl border border-border h-full">
            <h2 className="text-lg font-bold text-white mb-6">Active API Keys</h2>
            
            {loading ? (
              <p className="text-text-muted">Loading keys...</p>
            ) : tokens.length === 0 ? (
              <div className="text-center py-12 border border-dashed border-border rounded-xl">
                <span className="text-4xl opacity-50 mb-3 block">🔑</span>
                <p className="text-text-muted">No API keys generated yet.</p>
              </div>
            ) : (
              <div className="flex flex-col gap-4">
                {tokens.map(token => (
                  <div key={token.id} className="bg-surface/50 border border-border p-5 rounded-xl flex justify-between items-center group hover:border-indigo-500/30 transition-colors">
                    <div>
                      <h3 className="font-bold text-white text-lg flex items-center gap-2">
                        {token.name}
                      </h3>
                      <div className="flex items-center gap-4 mt-2 text-xs text-text-muted">
                        <span>Created: {new Date(token.created_at).toLocaleDateString()}</span>
                        <span>Last used: {token.last_used_at ? new Date(token.last_used_at).toLocaleString() : 'Never'}</span>
                      </div>
                    </div>
                    <button 
                      onClick={() => handleRevoke(token.id)}
                      className="px-4 py-2 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 hover:text-rose-300 rounded-lg font-semibold transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
                    >
                      Revoke
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

      </div>
    </div>
  );
}
