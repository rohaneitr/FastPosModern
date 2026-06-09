import { useState, useEffect } from 'react';
import useSWR from 'swr';
import api from '@/lib/api';

const fetcher = (url: string) => api.get(url).then(res => res.data);

export function usePOSData() {
  const { data: registerData, error: registerError, isLoading: registerLoading, mutate: mutateRegister } = useSWR('/register/status', fetcher);
  
  // We only fetch these if the register is open, but we can manage that dependency via SWR by conditionally passing the URL, 
  // or simply fetch them globally as they are needed anyway. 
  // For safety, let's keep it similar to the previous logic where they are fetched after register is checked, 
  // but SWR handles it nicely if we conditionally fetch.
  
  const isOpen = registerData?.is_open;

  const { data: productsData, isLoading: productsLoading } = useSWR(isOpen ? '/products' : null, fetcher, { revalidateOnFocus: false });
  const { data: contactsData } = useSWR(isOpen ? '/contacts?type=customer' : null, fetcher, { revalidateOnFocus: false });
  const { data: settingsData } = useSWR(isOpen ? '/settings' : null, fetcher, { revalidateOnFocus: false });

  // Pharmacy Module Check
  const [hasPharmacyModule, setHasPharmacyModule] = useState(false);
  const [locationId, setLocationId] = useState<number | null>(null);

  useEffect(() => {
    try {
      const userJson = localStorage.getItem('fastpos_user');
      if (userJson) {
        const u = JSON.parse(userJson);
        const mods = u.business?.active_modules || [];
        setHasPharmacyModule(Array.isArray(mods) && mods.includes('pharmacy'));
        setLocationId(u.current_location_id || u.business?.locations?.[0]?.id || null);
      }
    } catch {}
  }, []);

  const products = Array.isArray(productsData?.data) ? productsData.data : Array.isArray(productsData) ? productsData : [];
  const contacts = Array.isArray(contactsData?.data) ? contactsData.data : Array.isArray(contactsData) ? contactsData : [];
  
  const businessData = {
    name: settingsData?.business?.name || 'FastPOS',
    tax_number_1: settingsData?.business?.tax_number_1 || '',
  };

  let invoiceSettings = {
    invoice_prefix: 'INV-',
    invoice_header_text: '',
    invoice_footer_text: 'Thank you for your business!',
    show_logo: true,
    show_address: true,
    show_tax_number: false,
    show_due_balance: true,
    show_barcode: true,
    paper_size: '80mm'
  };

  if (settingsData?.business?.settings) {
    try {
      const parsed = typeof settingsData.business.settings === 'string' 
        ? JSON.parse(settingsData.business.settings) 
        : settingsData.business.settings;
      invoiceSettings = { ...invoiceSettings, ...parsed };
    } catch(e) {}
  }

  return {
    registerStatus: registerData || { is_open: false },
    isRegisterLoading: registerLoading,
    refreshRegister: mutateRegister,
    products,
    productsLoading,
    contacts,
    businessData,
    invoiceSettings,
    hasPharmacyModule,
    locationId
  };
}
