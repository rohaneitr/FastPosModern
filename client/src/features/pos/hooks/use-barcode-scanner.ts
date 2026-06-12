import { useEffect, useRef } from 'react';
import { useCartStore } from '@/store/useCartStore';
import toast from 'react-hot-toast';

interface UseBarcodeScannerProps {
  products: any[];
  searchRef: React.RefObject<HTMLInputElement | null>;
  onCheckoutShortcut: () => void;
  onEscapeShortcut: () => void;
}

export function useBarcodeScanner({
  products,
  searchRef,
  onCheckoutShortcut,
  onEscapeShortcut
}: UseBarcodeScannerProps) {
  const { addItem, items } = useCartStore();
  const barcodeBuffer = useRef('');
  const lastKeyTime = useRef<number>(Date.now());
  const latestProducts = useRef(products);
  const latestItems = useRef(items);

  // Keep refs updated for event listeners
  useEffect(() => { latestProducts.current = products; }, [products]);
  useEffect(() => { latestItems.current = items; }, [items]);

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Modals: Close on Escape
      if (e.key === 'Escape') {
        onEscapeShortcut();
        if (document.activeElement instanceof HTMLElement) {
          document.activeElement.blur();
        }
        return;
      }

      // Search Focus: F3 or Ctrl+K
      if (e.key === 'F3' || (e.ctrlKey && e.key.toLowerCase() === 'k')) {
        e.preventDefault();
        searchRef.current?.focus();
        return;
      }

      // Checkout: F12 or F9
      if (e.key === 'F12' || e.key === 'F9') {
        e.preventDefault();
        if (latestItems.current.length === 0) {
          toast.error('Cart is empty');
          return;
        }
        onCheckoutShortcut();
        return;
      }

      // Barcode Scanner Interceptor
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
          const matchedProduct = latestProducts.current.find(
            (p: any) => p.sku === scannedSku || p.barcode === scannedSku || p.id.toString() === scannedSku
          );
          
          if (matchedProduct) {
             addItem(matchedProduct);
             toast.success(`Added ${matchedProduct.name}`);
          } else {
             toast.error(`Product not found for SKU: ${scannedSku}`);
          }
        }
        barcodeBuffer.current = '';
      } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        barcodeBuffer.current += e.key;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [addItem, searchRef, onCheckoutShortcut, onEscapeShortcut]);
}
