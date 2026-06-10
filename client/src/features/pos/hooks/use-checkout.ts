import { useState } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';
import { useCartStore, CartItem } from '@/store/useCartStore';
import { globalSync } from '@/lib/sync/broadcast';
import { useEntitlements } from '@/hooks/useEntitlements';

export interface CheckoutPayload {
  location_id: number;
  payment_method: 'cash' | 'bkash' | 'sslcommerz' | 'card' | 'advance';
  tax_rate: string | number;
  discount_type: 'fixed';
  discount_amount: number;
  contact_id?: string;
  amount_paid: number;
  convert_quotation_id?: number;
  send_sms: boolean;
  customer_phone?: string;
  items: Array<{
    product_id: number;
    quantity: number;
    price: number | string;
    fractional_ratio: number;
    dosage_instructions?: string;
    serial_numbers?: string[];
    imei_numbers?: string[];
    warranty_duration?: string;
  }>;
}

interface UseCheckoutParams {
  locationId: number | null;
  registerIsOpen: boolean;
  onSerialRequired: (item: CartItem) => void;
  onSuccess: (receiptData: any) => void;
}

export function useCheckout({ locationId, registerIsOpen, onSerialRequired, onSuccess }: UseCheckoutParams) {
  const { items, taxRate } = useCartStore();
  const [isCheckingOut, setIsCheckingOut] = useState(false);
  const { hasModule } = useEntitlements();

  const processCheckout = async (params: {
    paymentMethod: 'cash' | 'bkash' | 'sslcommerz' | 'card' | 'advance';
    contactId?: string;
    amountPaid: string | number;
    convertQuotationId?: number;
    sendSms: boolean;
    customerPhone?: string;
    total: number;
    subtotal: number;
    taxAmount: number;
  }) => {
    if (items.length === 0) {
      toast.error('Cart is empty');
      return;
    }
    if (!registerIsOpen) {
      toast.error('Register is closed. Cannot process checkout.');
      return;
    }
    if (!locationId) {
      toast.error("No active location bound to this session. Cannot process checkout.");
      return;
    }

    // Validate Serial Numbers
    const requiresTracking = hasModule('serial_tracking') || hasModule('advanced_inventory');
    
    if (requiresTracking) {
      const itemsMissingSerials = items.filter(
        (item) => (item.enable_sr_no || item.enable_imei) && 
        ((item.enable_sr_no && (item.serial_numbers?.length || 0) !== item.quantity) || 
         (item.enable_imei && (item.imei_numbers?.length || 0) !== item.quantity))
      );
      
      if (itemsMissingSerials.length > 0) {
        toast.error(`Please select serial/IMEI numbers for ${itemsMissingSerials[0].name}`);
        onSerialRequired(itemsMissingSerials[0]);
        return;
      }
    }

    setIsCheckingOut(true);

    const payload: CheckoutPayload = {
      location_id: locationId,
      payment_method: params.paymentMethod,
      tax_rate: taxRate,
      discount_type: "fixed",
      discount_amount: 0,
      contact_id: params.contactId || undefined,
      amount_paid: params.amountPaid !== '' ? parseFloat(String(params.amountPaid)) : params.total,
      convert_quotation_id: params.convertQuotationId || undefined,
      send_sms: params.sendSms,
      customer_phone: !params.contactId && params.customerPhone ? params.customerPhone : undefined,
      items: items.map((item) => ({
        product_id: item.product_id || item.id,
        quantity: item.quantity,
        price: Number(item.price) * (item.fractional_ratio || 1),
        fractional_ratio: item.fractional_ratio || 1,
        dosage_instructions: item.dosage_instructions || undefined,
        serial_numbers: requiresTracking && item.enable_sr_no ? item.serial_numbers : undefined,
        imei_numbers: requiresTracking && item.enable_imei ? item.imei_numbers : undefined,
        warranty_duration: requiresTracking && item.enable_warranty ? item.warranty_duration : undefined
      }))
    };

    try {
      const response = await api.post('/checkout', payload);
      
      const receiptData = {
        transaction_id: response.data.transaction_id,
        invoice_no: response.data.invoice_no,
        items: [...items],
        subtotal: params.subtotal,
        taxAmount: params.taxAmount,
        total: params.total,
        paymentMethod: params.paymentMethod
      };

      toast.success(`Sale Successful! Invoice: ${response.data.invoice_no || 'Created'}`);
      
      // Notify other tabs to refresh inventory and clear carts
      globalSync.broadcast('INVENTORY_MUTATED');
      globalSync.broadcast('CART_CLEARED');
      
      onSuccess(receiptData);
    } catch (error: any) {
      console.error("Checkout failed", error);
      if (error.response?.status === 422 && error.response?.data?.errors?.inventory) {
        toast.error(`Inventory Error: ${error.response.data.errors.inventory[0]}`);
      } else if (error.response?.status === 402) {
        toast.error("Subscription Expired: Payment required.");
      } else {
        toast.error(`Checkout failed: ${error.response?.data?.message || error.message}`);
      }
    } finally {
      setIsCheckingOut(false);
    }
  };

  return {
    processCheckout,
    isCheckingOut
  };
}
