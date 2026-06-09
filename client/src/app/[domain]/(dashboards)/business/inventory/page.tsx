'use client';

import React, { useState } from 'react';
import toast from 'react-hot-toast';
import { Tabs, TabsList, TabTrigger, TabContent } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { ErrorBoundary } from '@/components/ui/error-boundary';
import { useInventoryData } from '@/lib/queries/use-inventory-data';
import { StockTable } from '@/features/inventory/components/stock-table';
import { AdjustStockModal } from '@/features/inventory/components/adjust-stock-modal';
import { TransferStockModal } from '@/features/inventory/components/transfer-stock-modal';

export default function InventoryPage() {
  const {
    filteredStocks,
    isLoading,
    locations,
    searchQuery,
    setSearchQuery,
    adjustStock,
    transferStock,
  } = useInventoryData();

  const [activeTab, setActiveTab] = useState('overview');
  const [modalState, setModalState] = useState<'closed' | 'adjust' | 'transfer'>('closed');
  const [selectedProduct, setSelectedProduct] = useState<any>(null);

  const handleOpenModal = (type: 'adjust' | 'transfer', product: any = null) => {
    setSelectedProduct(product);
    setModalState(type);
  };

  const handleCloseModal = () => {
    setModalState('closed');
    setTimeout(() => setSelectedProduct(null), 300); // Clear after animation
  };

  const handleAdjustSubmit = async (payload: any) => {
    try {
      await adjustStock(payload);
      toast.success('Stock adjusted successfully!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to adjust stock.');
      throw err; // Re-throw to keep modal open and show loading state
    }
  };

  const handleTransferSubmit = async (payload: any) => {
    try {
      await transferStock(payload);
      toast.success('Stock transfer initiated successfully!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to initiate transfer.');
      throw err;
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12 relative">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            Inventory Management
          </h1>
          <p className="text-text-muted mt-1">Track real-time stock levels, adjust discrepancies, and transfer inventory.</p>
        </div>
        <div className="flex gap-3">
          <Button variant="secondary" onClick={() => handleOpenModal('transfer')}>
            + Transfer Stock
          </Button>
          <Button onClick={() => handleOpenModal('adjust')}>
            + Adjust Stock
          </Button>
        </div>
      </div>

      <Tabs defaultValue="overview" value={activeTab} onValueChange={setActiveTab}>
        <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2 flex-wrap border border-border mb-6">
          <TabsList className="bg-transparent gap-2 p-0 h-auto">
            <TabTrigger value="overview" className="px-6 py-2.5 rounded-lg text-sm font-bold data-[state=active]:bg-emerald-500 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:shadow-emerald-500/20 data-[state=inactive]:text-text-muted data-[state=inactive]:hover:text-white data-[state=inactive]:hover:bg-white/5 data-[state=inactive]:bg-transparent border-0 transition-all">
              Stock Overview
            </TabTrigger>
            <TabTrigger value="adjustments" className="px-6 py-2.5 rounded-lg text-sm font-bold data-[state=active]:bg-emerald-500 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:shadow-emerald-500/20 data-[state=inactive]:text-text-muted data-[state=inactive]:hover:text-white data-[state=inactive]:hover:bg-white/5 data-[state=inactive]:bg-transparent border-0 transition-all">
              Stock Adjustments
            </TabTrigger>
            <TabTrigger value="transfers" className="px-6 py-2.5 rounded-lg text-sm font-bold data-[state=active]:bg-emerald-500 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:shadow-emerald-500/20 data-[state=inactive]:text-text-muted data-[state=inactive]:hover:text-white data-[state=inactive]:hover:bg-white/5 data-[state=inactive]:bg-transparent border-0 transition-all">
              Stock Transfers
            </TabTrigger>
          </TabsList>
        </div>

        <div className="glass-card rounded-xl border border-border p-6 min-h-[500px]">
          <ErrorBoundary>
            <TabContent value="overview" className="mt-0 outline-none">
              <StockTable
                stocks={filteredStocks}
                isLoading={isLoading}
                searchQuery={searchQuery}
                onSearchChange={setSearchQuery}
                onAdjust={(product) => handleOpenModal('adjust', product)}
                onTransfer={(product) => handleOpenModal('transfer', product)}
                showSearch={true}
              />
            </TabContent>

            <TabContent value="adjustments" className="mt-0 outline-none">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-bold text-white">Recent Adjustments</h2>
                <Button size="sm" onClick={() => handleOpenModal('adjust')}>+ New Adjustment</Button>
              </div>
              <StockTable
                stocks={filteredStocks}
                isLoading={isLoading}
                onAdjust={(product) => handleOpenModal('adjust', product)}
                onTransfer={(product) => handleOpenModal('transfer', product)}
                showSearch={false}
              />
            </TabContent>

            <TabContent value="transfers" className="mt-0 outline-none">
              <div className="flex justify-between items-center mb-6">
                <h2 className="text-xl font-bold text-white">Stock Transfers</h2>
                <Button size="sm" onClick={() => handleOpenModal('transfer')}>+ New Transfer</Button>
              </div>
              <StockTable
                stocks={filteredStocks}
                isLoading={isLoading}
                onAdjust={(product) => handleOpenModal('adjust', product)}
                onTransfer={(product) => handleOpenModal('transfer', product)}
                showSearch={false}
              />
            </TabContent>
          </ErrorBoundary>
        </div>
      </Tabs>

      <AdjustStockModal
        open={modalState === 'adjust'}
        onClose={handleCloseModal}
        product={selectedProduct}
        locations={locations}
        onSubmit={handleAdjustSubmit}
      />

      <TransferStockModal
        open={modalState === 'transfer'}
        onClose={handleCloseModal}
        product={selectedProduct}
        locations={locations}
        onSubmit={handleTransferSubmit}
      />
    </div>
  );
}
