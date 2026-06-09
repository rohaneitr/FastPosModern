'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import api from '@/lib/api';
import { useCartStore } from '@/store/useCartStore';
import { useCurrency } from '@/lib/currency';
import { useTranslation } from '@/lib/i18n';
import { useSearchParams, useRouter } from 'next/navigation';

export default function POSPage() {
  const { t } = useTranslation();
  const searchParams = useSearchParams();
  const router = useRouter();
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const searchInputRef = useRef<HTMLInputElement>(null);
  
  // Pharmacy Hybrid States
  const [activeTab, setActiveTab] = useState<'all' | 'general' | 'pharmacy'>('all');
  const [hasPharmacyModule, setHasPharmacyModule] = useState(false);
  const [genericAlternatives, setGenericAlternatives] = useState<any[]>([]);
  const [showAlternativesModal, setShowAlternativesModal] = useState(false);
  const [selectedGenericName, setSelectedGenericName] = useState('');
  
  // Barcode buffer state
  const barcodeBuffer = useRef('');
  const barcodeTimeout = useRef<NodeJS.Timeout | null>(null);
  const lastKeyTime = useRef<number>(Date.now());
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);
  const [deviceLocked, setDeviceLocked] = useState<{locked: boolean, reason?: string, isLimitReached?: boolean}>({locked: true, reason: 'Validating device...'});
  const [manualLicenseKey, setManualLicenseKey] = useState('');
  const [isActivating, setIsActivating] = useState(false);
  const [sendSms, setSendSms] = useState(false);
  const [customerPhone, setCustomerPhone] = useState('');
  
  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  // Zustand Store
  const { items, taxRate, addItem, removeItem, updateQuantity, updateItemField, clearCart } = useCartStore();
  const { format } = useCurrency();

  // Removed mock products to prevent phantom sales in production

  const [registerStatus, setRegisterStatus] = useState<any>(null);
  const [isRegisterLoading, setIsRegisterLoading] = useState(true);
  const [openingCash, setOpeningCash] = useState('');
  const [countedCash, setCountedCash] = useState('');
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [isSubmittingRegister, setIsSubmittingRegister] = useState(false);

  const [contacts, setContacts] = useState<any[]>([]);
  const [contactId, setContactId] = useState<string>('');
  const [amountPaid, setAmountPaid] = useState<string>('');

  const [invoiceSettings, setInvoiceSettings] = useState({
    invoice_prefix: 'INV-',
    invoice_header_text: '',
    invoice_footer_text: 'Thank you for your business!',
    show_logo: true,
    show_address: true,
    show_tax_number: false,
    show_due_balance: true,
    show_barcode: true,
    paper_size: '80mm'
  });
  const [businessData, setBusinessData] = useState({ name: 'FastPOS', tax_number_1: '' });

  // Quotation Support
  const [convertQuotationId, setConvertQuotationId] = useState<number | null>(null);
  const [showQuotationModal, setShowQuotationModal] = useState(false);
  const [quotations, setQuotations] = useState<any[]>([]);

  useEffect(() => {
    const qid = searchParams.get('load_quotation');
    if (qid) {
      api.get(`/sales/${qid}`).then(res => {
        const tx = res.data;
        if (tx && tx.lines) {
          clearCart();
          tx.lines.forEach((line: any) => {
            addItem({
              id: line.product_id,
              product_id: line.product_id,
              name: line.product_name,
              price: line.unit_price,
              quantity: line.quantity,
              has_serial_number: line.sku?.includes('SN') || false // basic heuristic
            });
          });
          if (tx.contact_id) setContactId(tx.contact_id.toString());
          setConvertQuotationId(parseInt(qid));
          showToast(`Loaded Quotation #${tx.invoice_no}`, 'success');
          // Clean URL
          router.replace(`/${window.location.pathname.split('/')[1]}/user/pos`);
        }
      }).catch(err => {
        showToast('Failed to load quotation', 'error');
      });
    }
  }, [searchParams, addItem, clearCart, router]);

  // Serial Number Modal State
  const [activeSerialItem, setActiveSerialItem] = useState<any>(null);
  const [availableSerials, setAvailableSerials] = useState<string[]>([]);
  const [isFetchingSerials, setIsFetchingSerials] = useState(false);

  useEffect(() => {
    // 1. Generate/Retrieve Hardware Fingerprint
    let hwHash = localStorage.getItem('pos_hardware_hash');
    if (!hwHash) {
      hwHash = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2) + Date.now().toString(36);
      localStorage.setItem('pos_hardware_hash', hwHash);
    }

    // 2. Activate Device
    api.post('/devices/activate', { hardware_hash: hwHash })
      .then(res => {
        const licenseKey = res.data.license_key;
        localStorage.setItem('pos_license_key', licenseKey);
        setDeviceLocked({locked: false});
        
        // Setup Heartbeat (every 5 mins)
        const heartbeatInterval = setInterval(() => {
          api.post('/devices/heartbeat', { license_key: licenseKey, hardware_hash: hwHash })
            .catch(err => {
              if (err.response?.status === 403 || err.response?.status === 402 || err.response?.status === 401) {
                 setDeviceLocked({locked: true, reason: err.response?.data?.message || 'Device access revoked or license expired.'});
                 // Force logout logic or lock
              }
            });
        }, 5 * 60 * 1000);
        
        return () => clearInterval(heartbeatInterval);
      })
      .catch(err => {
        const isLimit = err.response?.data?.code === 'QUOTA_EXCEEDED';
        setDeviceLocked({
          locked: true, 
          reason: err.response?.data?.message || 'Device Limit Reached or Activation Failed.',
          isLimitReached: isLimit
        });
      });

    // 3. Normal POS init
    api.get('/register/status')
      .then(res => {
        if (res.data.is_open) {
          setRegisterStatus(res.data);
          fetchProducts();
          fetchContacts();
          fetchSettings();
        } else {
          setRegisterStatus({ is_open: false });
        }
      })
      .catch(err => {
         setRegisterStatus({ is_open: false });
      })
      .finally(() => {
        setIsRegisterLoading(false);
      });

    // Check Modules
    try {
      const userJson = localStorage.getItem('fastpos_user');
      if (userJson) {
        const u = JSON.parse(userJson);
        const mods = u.business?.active_modules || [];
        setHasPharmacyModule(Array.isArray(mods) && mods.includes('pharmacy'));
      }
    } catch {}
  }, []);

  const handleManualActivation = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!manualLicenseKey) return;
    setIsActivating(true);
    let hwHash = localStorage.getItem('pos_hardware_hash');
    try {
      const res = await api.post('/devices/activate', { hardware_hash: hwHash, license_key: manualLicenseKey });
      const newLicenseKey = res.data.license_key;
      localStorage.setItem('pos_license_key', newLicenseKey);
      setDeviceLocked({locked: false});
      showToast('Device activated successfully!', 'success');
      // Trigger a re-render or reload to initialize POS fully
      window.location.reload();
    } catch (err: any) {
      const isLimit = err.response?.data?.code === 'QUOTA_EXCEEDED';
      setDeviceLocked({
        locked: true, 
        reason: err.response?.data?.message || 'Activation Failed. Invalid key or limit reached.', 
        isLimitReached: isLimit
      });
      showToast(err.response?.data?.message || 'Activation Failed. Invalid key or limit reached.', 'error');
    } finally {
      setIsActivating(false);
    }
  };

  const handleForceActivation = async () => {
    // If they already tried to enter a manual license key or they are using the one from localStorage
    const keyToUse = manualLicenseKey || localStorage.getItem('pos_license_key');
    if (!keyToUse) return;
    setIsActivating(true);
    let hwHash = localStorage.getItem('pos_hardware_hash');
    try {
      const res = await api.post('/devices/activate', { hardware_hash: hwHash, license_key: keyToUse, force_release: true });
      const newLicenseKey = res.data.license_key;
      localStorage.setItem('pos_license_key', newLicenseKey);
      setDeviceLocked({locked: false, isLimitReached: false});
      showToast('Previous devices disconnected. Activated successfully!', 'success');
      window.location.reload();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Force Activation Failed.', 'error');
    } finally {
      setIsActivating(false);
    }
  };

  const fetchSettings = async () => {
    try {
      const res = await api.get('/settings');
      if (res.data?.business) {
        setBusinessData({
          name: res.data.business.name || 'FastPOS',
          tax_number_1: res.data.business.tax_number_1 || ''
        });
        if (res.data.business.settings) {
          const parsed = typeof res.data.business.settings === 'string' ? JSON.parse(res.data.business.settings) : res.data.business.settings;
          setInvoiceSettings({
            invoice_prefix: parsed.invoice_prefix ?? 'INV-',
            invoice_header_text: parsed.invoice_header_text ?? '',
            invoice_footer_text: parsed.invoice_footer_text ?? 'Thank you for your business!',
            show_logo: parsed.show_logo ?? true,
            show_address: parsed.show_address ?? true,
            show_tax_number: parsed.show_tax_number ?? false,
            show_due_balance: parsed.show_due_balance ?? true,
            show_barcode: parsed.show_barcode ?? true,
            paper_size: parsed.paper_size ?? '80mm'
          });
        }
      }
    } catch(e) {}
  };

  const fetchProducts = () => {
    setLoading(true);
    api.get('/products')
      .then((res) => {
        if (res.data && res.data.data && res.data.data.length > 0) {
          setProducts(res.data.data);
        } else if (res.data && Array.isArray(res.data) && res.data.length > 0) {
          setProducts(res.data);
        } else {
          setProducts([]);
        }
      })
      .catch((err) => {
        console.error("Failed to fetch products API.", err.message);
        setProducts([]);
      })
      .finally(() => {
        setLoading(false);
      });
  };

  const fetchContacts = () => {
    api.get('/contacts?type=customer')
      .then(res => setContacts(res.data?.data || res.data || []))
      .catch(console.error);
  };

  const handleOpenRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmittingRegister(true);
    try {
      await api.post('/register/open', { opening_amount: parseFloat(openingCash) });
      showToast('Register opened successfully!', 'success');
      const res = await api.get('/register/status');
      setRegisterStatus(res.data);
      fetchProducts();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to open register', 'error');
    } finally {
      setIsSubmittingRegister(false);
    }
  };

  const handleCloseRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmittingRegister(true);
    try {
      const res = await api.post('/register/close', { counted_cash: parseFloat(countedCash) });
      showToast(`Register closed. Discrepancy: $${res.data.discrepancy}`, 'success');
      setRegisterStatus({ is_open: false });
      setShowCloseModal(false);
      setProducts([]); // Clear products to prevent sales
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to close register', 'error');
    } finally {
      setIsSubmittingRegister(false);
    }
  };

  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'bkash' | 'sslcommerz' | 'card' | 'advance'>('cash');
  const [isCheckingOut, setIsCheckingOut] = useState(false);
  const [receiptData, setReceiptData] = useState<any>(null);

  // Contact Summary (Wallet)
  const [contactSummary, setContactSummary] = useState<any>(null);
  
  useEffect(() => {
    if (contactId) {
      api.get(`/contacts/${contactId}/ledger/summary`)
        .then(res => setContactSummary(res.data))
        .catch(() => setContactSummary(null));
    } else {
      setContactSummary(null);
    }
  }, [contactId]);

  // Calculate Totals
  const subtotal = items.reduce((sum: number, item: any) => sum + (Number(item.price) * Number(item.quantity) * Number(item.fractional_ratio || 1)), 0);
  const taxAmount = subtotal * Number(taxRate);
  const total = subtotal + taxAmount;

  // --- Start Barcode & Shortcuts ---
  const latestProducts = useRef(products);
  useEffect(() => { latestProducts.current = products; }, [products]);
  
  const latestItems = useRef(items);
  useEffect(() => { latestItems.current = items; }, [items]);

  const handleCheckoutRef = useRef<any>(null);
  useEffect(() => { handleCheckoutRef.current = handleCheckout; }, [handleCheckout]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Modals: Close on Escape
      if (e.key === 'Escape') {
        setShowQuotationModal(false);
        setConvertQuotationId(null);
        setActiveSerialItem(null);
        if (document.activeElement instanceof HTMLElement) {
          document.activeElement.blur();
        }
        return;
      }

      // Search Focus: F3 or Ctrl+K
      if (e.key === 'F3' || (e.ctrlKey && e.key.toLowerCase() === 'k')) {
        e.preventDefault();
        searchInputRef.current?.focus();
        return;
      }

      // Checkout: F12 or F9
      if (e.key === 'F12' || e.key === 'F9') {
        e.preventDefault();
        if (latestItems.current.length === 0) {
          showToast(t('pos.emptyCart') || 'Cart is empty', 'error');
          return;
        }
        if (handleCheckoutRef.current) handleCheckoutRef.current();
        return;
      }

      // --- Barcode Scanner Interceptor ---
      const activeTag = document.activeElement?.tagName.toLowerCase();
      if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
        return; 
      }

      const now = Date.now();
      if (now - lastKeyTime.current > 50) {
        barcodeBuffer.current = '';
      }
      lastKeyTime.current = now;

      if (e.key === 'Enter') {
        if (barcodeBuffer.current.length > 2) {
          e.preventDefault();
          const scannedSku = barcodeBuffer.current;
          const matchedProduct = latestProducts.current.find((p: any) => p.sku === scannedSku || p.barcode === scannedSku || p.id.toString() === scannedSku);
          
          if (matchedProduct) {
             addItem(matchedProduct);
             showToast(`Added ${matchedProduct.name}`, 'success');
          } else {
             showToast(`Product not found for SKU: ${scannedSku}`, 'error');
          }
        }
        barcodeBuffer.current = '';
      } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        barcodeBuffer.current += e.key;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [addItem, t]);
  // --- End Barcode & Shortcuts ---

  async function handleCheckout() {
    if (items.length === 0) {
      showToast(t('pos.emptyCart') || 'Cart is empty', 'error');
      return;
    }
    if (!registerStatus?.is_open) {
      showToast('Register is closed. Cannot process checkout.', 'error');
      return;
    }
    setIsCheckingOut(true);
    
    let locationId = null;
    try {
      const userJson = localStorage.getItem('fastpos_user');
      if (userJson) {
        const user = JSON.parse(userJson);
        locationId = user.current_location_id || user.business?.locations?.[0]?.id;
      }
    } catch {}

    if (!locationId) {
       showToast("No active location bound to this session. Cannot process checkout.", "error");
       setIsCheckingOut(false);
       return;
    }

    // Validate Serial Numbers
    const itemsMissingSerials = items.filter((item: any) => item.has_serial_number && (item.serial_numbers?.length || 0) !== item.quantity);
    if (itemsMissingSerials.length > 0) {
      showToast(`Please select serial numbers for ${itemsMissingSerials[0].name}`, 'error');
      openSerialModal(itemsMissingSerials[0]);
      setIsCheckingOut(false);
      return;
    }

    const payload = {
      location_id: locationId,
      payment_method: paymentMethod,
      tax_rate: taxRate,
      discount_type: "fixed",
      discount_amount: 0,
      contact_id: contactId || undefined,
      amount_paid: amountPaid !== '' ? parseFloat(amountPaid) : total,
      convert_quotation_id: convertQuotationId || undefined,
      send_sms: sendSms,
      customer_phone: !contactId ? customerPhone : undefined,
      items: items.map((item: any) => ({
        product_id: item.product_id || item.id,
        quantity: item.quantity,
        price: item.price,
        fractional_ratio: item.fractional_ratio || 1,
        dosage_instructions: item.dosage_instructions || undefined,
        serial_numbers: item.has_serial_number ? item.serial_numbers : undefined
      }))
    };

    try {
      const response = await api.post('/checkout', payload);
      
      // Store data for receipt printing
      setReceiptData({
        transaction_id: response.data.transaction_id,
        invoice_no: response.data.invoice_no,
        items: [...items],
        subtotal,
        taxAmount,
        total,
        paymentMethod
      });

      showToast(`Sale Successful! Invoice: ${response.data.invoice_no || 'Created'}`, 'success');
      
      // Show Receipt/Success Modal instead of auto-clearing
      // We keep receiptData populated so the print view is active
      setTimeout(() => {
        window.print();
      }, 500);

    } catch (error: any) {
      console.error("Checkout failed", error);
      if (error.response?.status === 422 && error.response?.data?.errors?.inventory) {
         showToast(`Inventory Error: ${error.response.data.errors.inventory[0]}`, 'error');
      } else if (error.response?.status === 402) {
         showToast("Subscription Expired: Payment required.", 'error');
      } else {
         showToast(`Checkout failed: ${error.response?.data?.message || error.message}`, 'error');
      }
    } finally {
      setIsCheckingOut(false);
    }
  };

  const openSerialModal = async (item: any) => {
    setActiveSerialItem(item);
    setIsFetchingSerials(true);
    try {
      const res = await api.get(`/products/${item.product_id || item.id}/serials`);
      setAvailableSerials(res.data);
    } catch (err) {
      showToast('Failed to fetch available serials', 'error');
    } finally {
      setIsFetchingSerials(false);
    }
  };

  const toggleSerialNumber = (serial: string) => {
    if (!activeSerialItem) return;
    
    const currentSerials = activeSerialItem.serial_numbers || [];
    let newSerials = [...currentSerials];
    
    if (currentSerials.includes(serial)) {
      newSerials = newSerials.filter((s: string) => s !== serial);
    } else {
      if (newSerials.length >= activeSerialItem.quantity) {
        showToast(`You can only select ${activeSerialItem.quantity} serial(s).`, 'error');
        return;
      }
      newSerials.push(serial);
    }
    
    updateItemField(activeSerialItem.id, 'serial_numbers', newSerials);
    setActiveSerialItem({ ...activeSerialItem, serial_numbers: newSerials });
  };

  const loadQuotations = async () => {
    setShowQuotationModal(true);
    try {
      const res = await api.get('/sales?status=quotation');
      setQuotations(res.data?.data || []);
    } catch (err) {
      showToast('Failed to load quotations', 'error');
    }
  };

  const applyQuotation = async (id: number) => {
    try {
      const res = await api.get(`/sales/${id}`);
      const tx = res.data;
      
      clearCart();
      tx.lines.forEach((line: any) => {
        addItem({
          id: line.product_id,
          name: line.product_name,
          price: parseFloat(line.unit_price),
        });
        updateQuantity(line.product_id, parseFloat(line.quantity));
      });
      
      setContactId(tx.contact_id || '');
      setConvertQuotationId(tx.id);
      setShowQuotationModal(false);
      showToast('Quotation loaded successfully', 'success');
    } catch (err) {
      showToast('Failed to apply quotation', 'error');
    }
  };

  const fetchAlternatives = async (genericName: string) => {
    setSelectedGenericName(genericName);
    setIsFetchingSerials(true);
    setShowAlternativesModal(true);
    try {
      const res = await api.get(`/products/alternatives?generic_name=${encodeURIComponent(genericName)}`);
      setGenericAlternatives(res.data);
    } catch (err) {
      showToast('Failed to load alternatives', 'error');
    } finally {
      setIsFetchingSerials(false);
    }
  };

  return (
    <div className="flex h-full gap-4 relative">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}
      
      {/* Left: Product Grid */}
      <div className="flex-1 flex flex-col gap-4">
        {deviceLocked.locked && (
          <div className="absolute inset-0 z-50 bg-background/90 backdrop-blur-md flex items-center justify-center rounded-xl border border-border">
            <div className="bg-surface border border-rose-500/30 p-8 rounded-2xl w-full max-w-md shadow-2xl text-center">
              <span className="text-6xl mb-4 block">🚫</span>
              <h2 className="text-2xl font-bold text-white mb-2">Device Locked</h2>
              <p className="text-rose-400 font-medium mb-6">{deviceLocked.reason}</p>
              
              {deviceLocked.isLimitReached ? (
                <>
                  <p className="text-text-muted text-sm mb-6">Device Limit Reached. Do you want to disconnect previous devices and activate this one?</p>
                  <div className="flex gap-3 mt-2">
                     <button onClick={() => setDeviceLocked({...deviceLocked, isLimitReached: false})} className="flex-1 bg-surface border border-border hover:bg-white/5 text-white font-bold py-3 rounded-xl transition-all">Cancel</button>
                     <button onClick={handleForceActivation} disabled={isActivating} className="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl shadow-lg transition-all flex justify-center items-center gap-2">
                       {isActivating ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                       Force Activate Here
                     </button>
                  </div>
                </>
              ) : (
                <>
                  <p className="text-text-muted text-sm mb-6">Please contact your business administrator to resolve this issue or manage device activations in settings.</p>
                  
                  <div className="border-t border-border pt-6 mt-2">
                    <p className="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Manual Activation</p>
                    <form onSubmit={handleManualActivation} className="flex flex-col gap-3">
                      <input 
                        type="text" 
                        value={manualLicenseKey} 
                        onChange={e => setManualLicenseKey(e.target.value)} 
                        placeholder="Enter License Key..." 
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white text-center font-mono text-sm outline-none focus:border-primary transition-colors"
                        required 
                      />
                      <button 
                        type="submit" 
                        disabled={isActivating || !manualLicenseKey} 
                        className="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl shadow-lg disabled:opacity-50 transition-all flex justify-center items-center gap-2"
                      >
                        {isActivating ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                        Activate Device
                      </button>
                    </form>
                  </div>
                </>
              )}
            </div>
          </div>
        )}
        
        {/* Search & Filter Bar */}
        <div className="glass-card p-4 rounded-xl flex flex-col gap-4">
          <div className="flex gap-4 items-center justify-between">
            <div className="flex gap-4 flex-1 max-w-xl">
              <input 
                ref={searchInputRef}
                type="text" 
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder={t('pos.searchProducts') || 'Search (Press Ctrl+K)'} 
                className="flex-1 bg-background/50 border border-border rounded-lg px-4 py-2 outline-none focus:border-primary transition-colors disabled:opacity-50"
                disabled={!registerStatus?.is_open}
              />
              <button className="bg-surface border border-border px-4 py-2 rounded-lg hover:bg-white/5 transition-colors disabled:opacity-50" disabled={!registerStatus?.is_open}>
                Categories
              </button>
            </div>
            {registerStatus?.is_open && (
              <button onClick={() => setShowCloseModal(true)} className="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-4 py-2 rounded-lg hover:bg-rose-500/20 transition-colors font-bold text-sm">
                Close Register
              </button>
            )}
          </div>
          {hasPharmacyModule && (
            <div className="flex gap-2">
              {(['all', 'general', 'pharmacy'] as const).map(tab => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-4 py-1.5 rounded-full text-sm font-bold transition-all border ${activeTab === tab ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-400 shadow-sm' : 'bg-surface border-border text-text-muted hover:text-white'}`}
                >
                  {tab === 'all' ? 'All Items' : tab === 'general' ? 'General 🛒' : 'Pharmacy 💊'}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Grid */}
        <div className="flex-1 overflow-y-auto pr-2 relative">
          {(!registerStatus?.is_open && !isRegisterLoading) && (
            <div className="absolute inset-0 z-10 bg-background/80 backdrop-blur-sm flex items-center justify-center rounded-xl border border-border">
              <div className="bg-surface border border-border p-6 rounded-2xl w-full max-w-md shadow-2xl text-center">
                <span className="text-5xl mb-4 block">🔒</span>
                <h2 className="text-2xl font-bold text-white mb-2">{t('pos.registerClosed')}</h2>
                <p className="text-text-muted text-sm mb-6">You must open your cash register to start processing transactions for this session.</p>
                <form onSubmit={handleOpenRegister} className="flex flex-col gap-4 text-left">
                  <div>
                    <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Opening Float / Cash</label>
                    <div className="relative">
                      <span className="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold">$</span>
                      <input type="number" step="0.01" min="0" required value={openingCash} onChange={e => setOpeningCash(e.target.value)}
                        className="w-full bg-background border border-border rounded-xl pl-8 pr-4 py-3 text-white font-mono text-lg outline-none focus:border-emerald-500 transition-colors"
                        placeholder="0.00" />
                    </div>
                  </div>
                  <button type="submit" disabled={isSubmittingRegister} className="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-emerald-500/20 disabled:opacity-50 transition-all flex justify-center items-center gap-2">
                    {isSubmittingRegister ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                    Open Register
                  </button>
                </form>
              </div>
            </div>
          )}
          {loading ? (
            <div className="flex justify-center items-center h-full text-text-muted">Loading products...</div>
          ) : (
            <div className="grid grid-cols-3 xl:grid-cols-4 gap-4">
              {products
                .filter(p => {
                  const matchesSearch = p.name.toLowerCase().includes(searchQuery.toLowerCase()) || (p.sku && p.sku.toLowerCase().includes(searchQuery.toLowerCase())) || (p.generic_name && p.generic_name.toLowerCase().includes(searchQuery.toLowerCase()));
                  const matchesTab = activeTab === 'all' 
                    ? true 
                    : activeTab === 'pharmacy' ? (p.is_medicine || !!p.generic_name) 
                    : (!p.is_medicine && !p.generic_name);
                  return matchesSearch && matchesTab;
                })
                .map((p) => (
                <div 
                  key={p.id} 
                  onClick={() => addItem(p)}
                  className="glass-card rounded-xl p-4 flex flex-col items-center justify-center gap-3 cursor-pointer hover:border-primary/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.3)] transition-all group relative"
                >
                  <div className="text-4xl group-hover:scale-110 transition-transform duration-300">{p.image || '📦'}</div>
                  <div className="text-center w-full">
                    <div className="font-medium line-clamp-1">{p.name} {hasPharmacyModule && (p.is_medicine || p.generic_name) ? '💊' : '🛒'}</div>
                    {hasPharmacyModule && p.generic_name && (
                      <div className="text-[10px] text-text-muted truncate mt-0.5">{p.generic_name}</div>
                    )}
                    <div className="text-sm text-primary font-semibold mt-1">{format(parseFloat(p.price || p.sell_price_inc_tax || 0))}</div>
                  </div>
                  {hasPharmacyModule && p.generic_name && (
                    <button 
                      onClick={(e) => { e.stopPropagation(); fetchAlternatives(p.generic_name); }} 
                      className="absolute top-2 right-2 text-[10px] bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full hover:bg-emerald-500/40"
                    >
                      Alternatives
                    </button>
                  )}
                </div>
              ))}
              {products.length > 0 && products.filter(p => {
                  const matchesSearch = p.name.toLowerCase().includes(searchQuery.toLowerCase()) || (p.sku && p.sku.toLowerCase().includes(searchQuery.toLowerCase())) || (p.generic_name && p.generic_name.toLowerCase().includes(searchQuery.toLowerCase()));
                  const matchesTab = activeTab === 'all' ? true : activeTab === 'pharmacy' ? (p.is_medicine || !!p.generic_name) : (!p.is_medicine && !p.generic_name);
                  return matchesSearch && matchesTab;
                }).length === 0 && (
                 <div className="col-span-3 text-center text-text-muted py-8">{t('products.noProductsMatch') || 'No products match your search.'}</div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Right: Cart/Register */}
      <div className="w-[400px] glass-card rounded-xl flex flex-col">
        {/* Cart Header */}
        <div className="p-4 border-b border-border flex flex-col gap-2">
          <div className="flex justify-between items-center">
            <h2 className="font-semibold text-lg flex items-center gap-2">
              Current Sale 
              <span className="bg-primary/20 text-primary text-xs px-2 py-0.5 rounded-full">{items.length}</span>
            </h2>
            <div className="flex gap-2">
              <button 
                onClick={loadQuotations}
                className="text-xs bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 px-2 py-1 rounded transition-colors font-bold"
              >
                Load Quote
              </button>
              <button 
                onClick={clearCart}
                className="text-xs text-danger hover:bg-danger/10 px-2 py-1 rounded transition-colors"
              >
                Clear
              </button>
            </div>
          </div>
          {convertQuotationId && (
            <div className="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-lg text-xs font-bold flex justify-between items-center mt-1">
              <span>Converting Quotation #{convertQuotationId}</span>
              <button onClick={() => { setConvertQuotationId(null); clearCart(); }} className="text-emerald-500 hover:text-emerald-300">✕</button>
            </div>
          )}
        </div>

        {/* Cart Items */}
        <div className="flex-1 p-4 overflow-y-auto flex flex-col gap-3">
          {items.length === 0 ? (
            <div className="flex-1 flex flex-col items-center justify-center text-text-muted">
              <div className="text-4xl mb-2 opacity-50">🛒</div>
              <p>Your cart is empty</p>
              <p className="text-xs mt-1 opacity-70">Click a product to add it</p>
            </div>
          ) : (
            items.map((item: any) => (
              <div key={item.id} className="bg-surface/80 border border-border rounded-lg p-3 flex flex-col gap-2">
                <div className="flex justify-between items-start">
                  <div className="font-medium pr-2 flex flex-col w-full">
                    <span className="truncate">{item.name} {hasPharmacyModule && (item.is_medicine || item.generic_name) ? '💊' : ''}</span>
                    {hasPharmacyModule && (item.is_medicine || item.generic_name) && (
                      <div className="flex flex-col gap-1.5 mt-1">
                        <select 
                          value={item.fractional_ratio || 1} 
                          onChange={(e) => updateItemField(item.id, 'fractional_ratio', parseFloat(e.target.value))}
                          className="bg-background border border-border text-xs rounded outline-none p-1 w-24 text-white"
                          onClick={e => e.stopPropagation()}
                        >
                          <option value={1}>1 Box</option>
                          <option value={item.unit_conversion_ratio && item.unit_conversion_ratio > 1 ? (1 / item.unit_conversion_ratio) : 0.1}>1 Strip</option>
                        </select>
                        <div className="flex gap-1 flex-wrap">
                          {["1+0+1", "0+1+0", "After Meal"].map(d => (
                            <span 
                              key={d} 
                              onClick={(e) => { e.stopPropagation(); updateItemField(item.id, 'dosage_instructions', item.dosage_instructions === d ? null : d); }}
                              className={`text-[10px] px-1.5 py-0.5 rounded cursor-pointer border transition-colors ${item.dosage_instructions === d ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/50 shadow-sm' : 'bg-surface text-text-muted border-border hover:text-white'}`}
                            >
                              {d}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                    {item.has_serial_number && (
                      <button onClick={() => openSerialModal(item)} className={`text-xs text-left mt-1 font-bold flex items-center gap-1 ${(item.serial_numbers?.length || 0) === item.quantity ? 'text-success' : 'text-warning'}`}>
                        {(item.serial_numbers?.length || 0) === item.quantity ? '✓ Serials Selected' : '⚠️ Select Serials'} ({(item.serial_numbers?.length || 0)}/{item.quantity})
                      </button>
                    )}
                  </div>
                  <button onClick={() => removeItem(item.id)} className="text-text-muted hover:text-danger text-sm ml-2">✕</button>
                </div>
                <div className="flex justify-between items-center mt-1">
                  <div className="flex items-center gap-3 bg-background/50 rounded-lg p-1 border border-border">
                    <button 
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                      className="w-6 h-6 flex items-center justify-center rounded hover:bg-surface text-text-muted hover:text-white"
                    >-</button>
                    <span className="text-sm w-4 text-center">{item.quantity}</span>
                    <button 
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      className="w-6 h-6 flex items-center justify-center rounded hover:bg-surface text-text-muted hover:text-white"
                    >+</button>
                  </div>
                  <div className="font-semibold">{format(Number(item.price) * Number(item.quantity) * Number(item.fractional_ratio || 1))}</div>
                </div>
              </div>
            ))
          )}
        </div>

        {/* Cart Totals & Actions */}
        <div className="p-4 border-t border-border bg-surface/40 rounded-b-xl flex flex-col gap-3">
          <div className="flex justify-between text-sm text-text-muted">
            <span>{t('pos.subtotal')}</span>
            <span>{format(subtotal)}</span>
          </div>
          <div className="flex justify-between text-sm text-text-muted">
            <span>{t('pos.tax')} ({(Number(taxRate) * 100).toFixed(0)}%)</span>
            <span>{format(taxAmount)}</span>
          </div>
          <div className="flex justify-between font-bold text-2xl mt-2 pt-3 border-t border-border">
            <span>{t('pos.total')}</span>
            <span className="text-success">{format(total)}</span>
          </div>
          
          <div className="mt-2">
            <span className="text-xs font-semibold text-text-muted uppercase tracking-wider mb-2 block">Payment Method</span>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
              {[
                { id: 'cash', label: 'Cash', icon: '💵' },
                { id: 'bkash', label: 'bKash', icon: '📱' },
                { id: 'sslcommerz', label: 'SSL', icon: '🌐' },
                { id: 'card', label: 'Card', icon: '💳' },
                { id: 'advance', label: 'Wallet', icon: '💼' }
              ].map(method => {
                const isAdvance = method.id === 'advance';
                const hasAdvance = contactSummary && contactSummary.total_due < 0 && Math.abs(contactSummary.total_due) >= total;
                const isDisabled = isAdvance && !hasAdvance;
                
                return (
                  <button 
                    key={method.id}
                    onClick={() => setPaymentMethod(method.id as any)}
                    disabled={isDisabled}
                    className={`py-2 px-1 rounded-lg text-sm font-semibold transition-all border flex items-center justify-center gap-2
                      ${paymentMethod === method.id 
                        ? 'bg-primary/20 border-primary text-primary shadow-sm' 
                        : 'bg-background border-border text-text-muted hover:bg-surface hover:text-white'}
                      ${isDisabled ? 'opacity-50 cursor-not-allowed' : ''}
                    `}
                  >
                    <span className="text-lg">{method.icon}</span> {method.label}
                  </button>
                );
              })}
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2">
            <div>
              <span className="text-xs font-semibold text-text-muted uppercase tracking-wider mb-1 block">{t('pos.customerOptional')}</span>
              <select value={contactId} onChange={e => setContactId(e.target.value)} className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-white outline-none mb-1">
                <option value="">{t('pos.walkinCustomer')}</option>
                {contacts.map(c => <option key={c.id} value={c.id}>{c.name || `${c.first_name} ${c.last_name}`}</option>)}
              </select>
              {contactSummary && contactSummary.total_due < 0 && (
                <span className="text-xs font-bold text-emerald-400">Wallet: {format(Math.abs(contactSummary.total_due))}</span>
              )}
            </div>
            <div>
              <span className="text-xs font-semibold text-text-muted uppercase tracking-wider mb-1 block">{t('pos.amtPaid')}</span>
              <input type="number" step="0.01" min="0" value={amountPaid} onChange={e => setAmountPaid(e.target.value)} placeholder={`Total: ${total.toFixed(2)}`} className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-white font-mono outline-none" />
            </div>
          </div>

          <div className="mt-2 flex items-center justify-between bg-surface/50 p-3 rounded-lg border border-border">
            <div className="flex items-center gap-2">
              <input 
                type="checkbox" 
                id="sendSmsToggle" 
                checked={sendSms} 
                onChange={e => setSendSms(e.target.checked)}
                className="w-4 h-4 text-primary bg-background border-border rounded"
              />
              <label htmlFor="sendSmsToggle" className="text-sm font-medium text-white cursor-pointer select-none">
                Send Digital Receipt via SMS/WhatsApp
              </label>
            </div>
            {sendSms && !contactId && (
              <input 
                type="tel" 
                placeholder="Mobile Number" 
                value={customerPhone}
                onChange={e => setCustomerPhone(e.target.value)}
                className="w-32 bg-background border border-border rounded px-2 py-1 text-xs text-white outline-none"
                required
              />
            )}
          </div>
          
          <button 
            onClick={handleCheckout}
            disabled={items.length === 0 || isCheckingOut || !registerStatus?.is_open}
            className={`w-full mt-2 font-bold py-4 rounded-xl transition-all shadow-lg text-lg flex justify-center items-center gap-2
              ${(items.length === 0 || !registerStatus?.is_open)
                ? 'bg-surface text-text-muted cursor-not-allowed border border-border' 
                : 'bg-primary hover:bg-primary-hover text-white hover:shadow-[0_0_20px_rgba(59,130,246,0.4)]'
              }`}
          >
            {isCheckingOut ? (
              <>
                <svg className="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
              </>
            ) : `Pay ${format(total)}`}
          </button>
        </div>
      </div>

      {/* Serial Selection Modal */}
      {activeSerialItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center mb-6">
              <div>
                <h2 className="text-xl font-bold text-white">Select Serial Numbers</h2>
                <p className="text-text-muted text-sm mt-1">{activeSerialItem.name} - Need {activeSerialItem.quantity}</p>
              </div>
              <button onClick={() => setActiveSerialItem(null)} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
            </div>
            
            <div className="bg-background/50 rounded-xl p-4 border border-border mb-6 max-h-[300px] overflow-y-auto">
              {isFetchingSerials ? (
                <div className="flex justify-center items-center py-8">
                  <span className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                </div>
              ) : availableSerials.length === 0 ? (
                <div className="text-center text-warning py-4">
                  ⚠️ No available serial numbers found in stock. You must receive stock with serial numbers first.
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                  {availableSerials.map((serial) => {
                    const isSelected = (activeSerialItem.serial_numbers || []).includes(serial);
                    return (
                      <button
                        key={serial}
                        onClick={() => toggleSerialNumber(serial)}
                        className={`p-2 rounded-lg text-sm font-mono transition-colors border
                          ${isSelected 
                            ? 'bg-primary text-white border-primary shadow-sm shadow-primary/20' 
                            : 'bg-background border-border text-text-muted hover:bg-surface hover:text-white'
                          }`}
                      >
                        {serial}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>

            <button 
              onClick={() => setActiveSerialItem(null)} 
              className="w-full font-bold py-3 bg-primary hover:bg-primary-hover text-white rounded-xl transition-all shadow-lg"
            >
              Done ({(activeSerialItem.serial_numbers || []).length}/{activeSerialItem.quantity} Selected)
            </button>
          </div>
        </div>
      )}

      {/* Close Register Modal */}
      {showCloseModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
             <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white">Close Register Session</h2>
              <button onClick={() => setShowCloseModal(false)} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
            </div>
            
            <div className="bg-background/50 rounded-xl p-4 border border-border mb-6">
              <div className="flex justify-between text-sm mb-2">
                <span className="text-text-muted">Opening Float:</span>
                <span className="font-mono text-white">${parseFloat(registerStatus?.register?.opening_amount || 0).toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-sm mb-2">
                <span className="text-text-muted">Cash Sales & Dues Today:</span>
                <span className="font-mono text-emerald-400">+ ${parseFloat(registerStatus?.cash_sales || 0).toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-sm mb-2">
                <span className="text-text-muted">Cash Expenses Today:</span>
                <span className="font-mono text-rose-400">- ${parseFloat(registerStatus?.cash_expenses || 0).toFixed(2)}</span>
              </div>
              <div className="border-t border-border/50 my-2"></div>
              <div className="flex justify-between font-bold">
                <span className="text-white">Expected Cash in Drawer:</span>
                <span className="font-mono text-white">${parseFloat(registerStatus?.expected_cash || 0).toFixed(2)}</span>
              </div>
            </div>

            <form onSubmit={handleCloseRegister} className="flex flex-col gap-4">
              <div>
                <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Counted Cash *</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold">$</span>
                  <input type="number" step="0.01" min="0" required value={countedCash} onChange={e => setCountedCash(e.target.value)}
                    className="w-full bg-background border border-border rounded-xl pl-8 pr-4 py-3 text-white font-mono text-lg outline-none focus:border-rose-500 transition-colors"
                    placeholder="0.00" />
                </div>
                {countedCash && (
                  <p className={`text-xs font-bold mt-2 ${parseFloat(countedCash) - parseFloat(registerStatus?.expected_cash || 0) < 0 ? 'text-rose-500' : 'text-emerald-500'}`}>
                    Discrepancy: ${(parseFloat(countedCash) - parseFloat(registerStatus?.expected_cash || 0)).toFixed(2)}
                  </p>
                )}
              </div>
              <div className="flex gap-3 mt-2">
                <button type="button" onClick={() => setShowCloseModal(false)} className="flex-1 py-3 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors">Cancel</button>
                <button type="submit" disabled={isSubmittingRegister} className="flex-[2] bg-rose-500 hover:bg-rose-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-rose-500/20">
                  {isSubmittingRegister ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                  Confirm Close Register
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Sale Success Modal */}
      {receiptData && !receiptData.isQuotation && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4 hide-on-print">
          <div className="bg-surface border border-border rounded-2xl p-8 w-full max-w-md shadow-2xl animate-in zoom-in-95 text-center flex flex-col items-center">
            <span className="text-6xl mb-4">✅</span>
            <h2 className="text-2xl font-bold text-white mb-2">Sale Successful!</h2>
            <p className="text-emerald-400 font-mono text-xl mb-6">{receiptData.invoice_no}</p>
            
            <div className="flex flex-col gap-3 w-full">
              <button onClick={() => window.print()} className="w-full bg-surface border border-border hover:bg-white/5 text-white font-bold py-3 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
                🖨️ Print Receipt
              </button>
              <button 
                onClick={async () => {
                  const email = prompt("Enter customer email address:", "");
                  if (!email) return;
                  try {
                    await api.post(`/sales/${receiptData.transaction_id}/email`, { email });
                    showToast('Email queued for delivery!', 'success');
                  } catch (err: any) {
                    showToast(err.response?.data?.message || 'Failed to send email', 'error');
                  }
                }} 
                className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2"
              >
                ✉️ Send via Email
              </button>
              <button 
                onClick={() => {
                  setReceiptData(null);
                  clearCart();
                  setContactId('');
                  setAmountPaid('');
                  setConvertQuotationId(null);
                  setSendSms(false);
                  setCustomerPhone('');
                  searchInputRef.current?.focus();
                }} 
                className="w-full mt-4 bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-primary/20"
              >
                New Sale &rarr;
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Hidden Printable Receipt Area (Dynamic Designer Injected) */}
      {receiptData && (
        <div className="hidden print:block absolute top-0 left-0 bg-white text-black z-[9999] p-2 text-xs font-mono w-full min-h-screen">
          <div className={`mx-auto bg-white text-black relative z-10 ${invoiceSettings.paper_size === '80mm' ? 'w-[80mm]' : 'w-[210mm]'}`}>
            
            {/* Header Section */}
            <div className="text-center mb-6">
              {invoiceSettings.show_logo && (
                <div className="w-16 h-16 border-2 border-black rounded-full mx-auto mb-3 flex items-center justify-center font-bold text-black uppercase tracking-widest">LOGO</div>
              )}
              <h1 className="font-bold text-2xl uppercase tracking-wider">{businessData.name}</h1>
              {invoiceSettings.show_address && (
                <p className="text-sm text-black mt-1">123 Main Street, Tech Park<br/>City, State 12345</p>
              )}
              {invoiceSettings.show_tax_number && businessData.tax_number_1 && (
                <p className="text-sm text-black mt-1 font-semibold">VAT/Tax No: {businessData.tax_number_1}</p>
              )}
              {invoiceSettings.invoice_header_text && (
                <p className="text-lg font-bold mt-3 uppercase border-b-2 border-t-2 border-dashed border-black py-1">{invoiceSettings.invoice_header_text}</p>
              )}
            </div>

            {/* Meta Data */}
            <div className="text-sm mb-4 flex justify-between border-b-2 border-black pb-2">
              <div>
                <p>{t('sales.invoice')}: <b>{receiptData.invoice_no}</b></p>
                <p>{t('common.date')}: {new Date().toLocaleString()}</p>
              </div>
              <div className="text-right">
                <p>{t('business.cashier')}: {JSON.parse(localStorage.getItem('fastpos_user') || '{}')?.name || 'Staff'}</p>
              </div>
            </div>

            <div className="w-full">
              <table className="w-full text-left font-mono border-collapse border-b-2 border-black mb-4">
                <thead>
                  <tr className="border-b border-black">
                    <th className="p-1 text-black uppercase font-bold">{t('common.name')}</th>
                    <th className="p-1 text-black uppercase text-center font-bold">{t('common.quantity')}</th>
                    <th className="p-1 text-black uppercase text-right font-bold">{t('common.price')}</th>
                    <th className="p-1 text-black uppercase text-right font-bold">{t('common.total')}</th>
                  </tr>
                </thead>
                <tbody>
                  {receiptData.items?.length > 0 ? (
                    receiptData.items.map((item: any, index: number) => (
                      <tr key={item.id || index} className="border-b border-dashed border-gray-400">
                        <td className="p-1 text-black leading-tight">{item.name}</td>
                        <td className="p-1 text-black text-center leading-tight">{item.quantity}</td>
                        <td className="p-1 text-black text-right leading-tight">{format(item.price)}</td>
                        <td className="p-1 text-black text-right leading-tight">{format(item.price * item.quantity)}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={4} className="p-1 text-center">{t('common.noData')}</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            <div className="border-t-2 border-black pt-4 w-full ml-auto md:w-2/3 lg:w-1/2 float-right clear-both">
              <div className="flex justify-between mb-1">
                <span>{t('common.subtotal')}:</span>
                <span>{format(receiptData.subtotal)}</span>
              </div>
              <div className="flex justify-between mb-1">
                <span>{t('common.tax')}:</span>
                <span>{format(receiptData.taxAmount)}</span>
              </div>
              <div className="flex justify-between font-bold text-2xl mt-2 border-t-2 border-black pt-2 mb-2">
                <span>{t('common.total')}:</span>
                <span>{format(receiptData.total)}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span>{t('pos.paymentMethod')} ({receiptData.paymentMethod.toUpperCase()}):</span>
                <span>{format(receiptData.amountPaid || receiptData.total)}</span>
              </div>
            </div>

            <div className="clear-both"></div>

            {invoiceSettings.show_due_balance && contactSummary && contactSummary.total_due < 0 && (
              <div className="mt-8 mb-6 p-4 border-2 border-black text-center font-bold text-lg uppercase tracking-widest">
                <p className="text-sm font-semibold tracking-normal mb-1">Remaining Wallet Advance</p>
                {format(Math.abs(contactSummary.total_due))}
              </div>
            )}
            
            <div className="text-center mt-8 clear-both pt-8">
              {invoiceSettings.show_barcode && (
                <div className="mb-4 flex flex-col items-center">
                  <div className="w-48 h-12 bg-[repeating-linear-gradient(90deg,#000,#000_3px,transparent_3px,transparent_6px)]"></div>
                  <span className="text-xs font-mono mt-1 tracking-widest">{receiptData.invoice_no}</span>
                </div>
              )}
              {invoiceSettings.invoice_footer_text && (
                <p className="text-sm whitespace-pre-wrap font-semibold italic">{invoiceSettings.invoice_footer_text}</p>
              )}
            </div>
          </div>
          <style dangerouslySetInnerHTML={{__html: `
            @media print {
              body { background-color: white !important; color: black !important; }
              body * { visibility: hidden; }
              .print\\:block, .print\\:block * { visibility: visible; }
              .print\\:block { position: absolute; left: 0; top: 0; width: 100% !important; background: white !important; color: black !important; padding: 0 !important; margin: 0 !important; }
              @page { size: ${invoiceSettings.paper_size === '80mm' ? '80mm auto' : '210mm auto'}; margin: 0; }
            }
          `}} />
        </div>
      )}

      {/* Quotation Selection Modal */}
      {showQuotationModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-2xl shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white">Load Quotation</h2>
              <button onClick={() => setShowQuotationModal(false)} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
            </div>
            
            <div className="bg-background/50 rounded-xl border border-border overflow-hidden max-h-[400px] overflow-y-auto">
              {quotations.length === 0 ? (
                <div className="p-8 text-center text-text-muted">No pending quotations found.</div>
              ) : (
                <div className="w-full overflow-x-auto">

</div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
