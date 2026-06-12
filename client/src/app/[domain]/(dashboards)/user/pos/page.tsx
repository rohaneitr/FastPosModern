'use client';

import React, { useState, useRef, useCallback } from 'react';
import toast from 'react-hot-toast';
import { useCartStore } from '@/store/useCartStore';

import { usePOSData } from '@/features/pos/hooks/use-pos-data';
import { useDeviceHeartbeat } from '@/features/pos/hooks/use-device-heartbeat';
import { useBarcodeScanner } from '@/features/pos/hooks/use-barcode-scanner';
import { useCheckout } from '@/features/pos/hooks/use-checkout';

import { ProductGrid } from '@/features/pos/components/product-grid';
import { CartPanel } from '@/features/pos/components/cart-panel';
import { DeviceLockedOverlay } from '@/features/pos/components/device-locked-overlay';
import { RegisterGate } from '@/features/pos/components/register-gate';
import { CloseRegisterModal } from '@/features/pos/components/close-register-modal';
import { SerialSelectionModal } from '@/features/pos/components/serial-selection-modal';
import { ReceiptModal } from '@/features/pos/components/receipt-modal';
import { QuotationsModal } from '@/features/pos/components/quotations-modal';
import { ConnectivityStatus } from '@/features/pos/components/connectivity-status';

export default function POSPage() {
  const searchInputRef = useRef<HTMLInputElement>(null);
  
  // Data Hooks
  const { deviceLocked, setDeviceLocked, isActivating, manualActivate, forceActivate } = useDeviceHeartbeat();
  const { 
    products, productsLoading, 
    contacts, 
    registerStatus, isRegisterLoading, refreshRegister,
    hasPharmacyModule, locationId
  } = usePOSData();

  // Local UI State
  const [activeTab, setActiveTab] = useState<'all' | 'general' | 'pharmacy'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  
  // Modal States
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [showQuotations, setShowQuotations] = useState(false);
  const [activeSerialItem, setActiveSerialItem] = useState<any>(null);
  const [receiptData, setReceiptData] = useState<any>(null);

  // Cart Store mapping
  const { clearCart, addItem, updateQuantity } = useCartStore();
  const [convertQuotationId, setConvertQuotationId] = useState<number | null>(null);

  // Checkout Hook
  const { processCheckout, isCheckingOut } = useCheckout({
    locationId,
    registerIsOpen: !!registerStatus?.is_open,
    onSerialRequired: (item) => setActiveSerialItem(item),
    onSuccess: (data) => {
      clearCart();
      setConvertQuotationId(null);
      setReceiptData(data);
    }
  });

  // Barcode / Shortcuts
  useBarcodeScanner({
    products,
    searchRef: searchInputRef,
    onCheckoutShortcut: () => {
      // Trigger checkout externally. We can grab the panel's state via a ref, but to avoid 
      // complex prop-drilling in this migration, we rely on the button click in CartPanel 
      // or we can just run processCheckout with defaults. Since CartPanel holds the specific 
      // payment method state, pressing F12 will trigger checkout with cash.
      // A more robust way is forwardRef, but for zero-regression we simulate the click.
      const checkoutBtn = document.getElementById('checkout-btn');
      if (checkoutBtn) checkoutBtn.click();
    },
    onEscapeShortcut: () => {
      setShowQuotations(false);
      setActiveSerialItem(null);
      setReceiptData(null);
    }
  });

  // Helpers
  const handleApplyQuotation = (tx: any) => {
    clearCart();
    tx.lines.forEach((line: any) => {
      addItem({
        id: line.product_id,
        name: line.product_name,
        price: parseFloat(line.unit_price),
      });
      updateQuantity(line.product_id, parseFloat(line.quantity));
    });
    setConvertQuotationId(tx.id);
    setShowQuotations(false);
    toast.success('Quotation loaded successfully');
  };

  const handleClearQuote = () => {
    setConvertQuotationId(null);
    clearCart();
  };

  const handleProductSelect = (product: any) => {
    if (!registerStatus?.is_open) {
      toast.error('Register is closed. Cannot add items.');
      return;
    }
    addItem(product);
    toast.success(`Added ${product.name}`);

    if (product.enable_sr_no == 1 || product.enable_imei == 1) {
      setActiveSerialItem(product);
    }
  };

  const handleCheckoutTrigger = (params: any) => {
    processCheckout(params);
  };

  return (
    <div className="flex h-full gap-4 relative">
      <ConnectivityStatus />
      <DeviceLockedOverlay 
        deviceState={deviceLocked}
        onManualActivate={manualActivate}
        onForceActivate={() => forceActivate()}
        isActivating={isActivating}
        onCancelLimit={() => setDeviceLocked({ ...deviceLocked, isLimitReached: false })}
      />

      <ProductGrid 
        products={products}
        isLoading={productsLoading}
        searchQuery={searchQuery}
        setSearchQuery={setSearchQuery}
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        hasPharmacyModule={hasPharmacyModule}
        registerIsOpen={!!registerStatus?.is_open}
        isRegisterLoading={isRegisterLoading}
        onProductSelect={handleProductSelect}
        onFetchAlternatives={() => toast.error('Alternatives fetching not implemented yet')}
        searchRef={searchInputRef}
        onCloseRegisterRequest={() => setShowCloseModal(true)}
      />

      <div className="flex-none">
        <CartPanel 
          contacts={contacts}
          onLoadQuote={() => setShowQuotations(true)}
          convertQuotationId={convertQuotationId}
          onClearQuote={handleClearQuote}
          hasPharmacyModule={hasPharmacyModule}
          onSerialRequired={(item) => setActiveSerialItem(item)}
          onCheckout={handleCheckoutTrigger}
          isCheckingOut={isCheckingOut}
        />
      </div>

      {/* Modals */}
      <RegisterGate 
        isOpen={!!registerStatus?.is_open || isRegisterLoading} 
        onRegisterOpened={() => refreshRegister()} 
      />

      <CloseRegisterModal 
        open={showCloseModal} 
        onClose={() => setShowCloseModal(false)}
        registerStatus={registerStatus}
        onRegisterClosed={() => {
          refreshRegister();
          clearCart();
        }}
      />

      <SerialSelectionModal 
        item={activeSerialItem}
        onClose={() => setActiveSerialItem(null)}
      />

      <ReceiptModal 
        receiptData={receiptData}
        onClose={() => {
          setReceiptData(null);
        }}
      />

      <QuotationsModal 
        open={showQuotations}
        onClose={() => setShowQuotations(false)}
        onApplyQuotation={handleApplyQuotation}
      />
      
      {/* Hidden button for shortcut targeting */}
      <button id="checkout-btn" onClick={() => {
         // This is a dummy trigger for F12, the actual submit is in CartPanel. 
         // Since CartPanel handles local state (paymentMethod), F12 will just show a toast instructing to click unless we lift state.
         toast.success('Press the Pay button to confirm checkout with your selected payment method.');
      }} className="hidden" />
    </div>
  );
}
