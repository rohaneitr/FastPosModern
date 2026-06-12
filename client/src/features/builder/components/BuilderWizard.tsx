import React, { useState } from 'react';
import dynamic from 'next/dynamic';
import { usePCBuilderStore, BuilderComponent } from '../store/usePCBuilderStore';
import { Button } from '@/components/ui/button';
import { useCartStore } from '@/store/useCartStore';
import toast from 'react-hot-toast';

// Dynamically load the heavy part selection modal to preserve Core Web Vitals
const ComponentSelectorModal = dynamic(() => import('./ComponentSelectorModal'), { ssr: false });

const BUILD_STEPS = [
  { id: 'cpu', label: '1. Processor (CPU)' },
  { id: 'motherboard', label: '2. Motherboard' },
  { id: 'ram', label: '3. Memory (RAM)' },
  { id: 'gpu', label: '4. Graphics Card (GPU)' },
  { id: 'storage', label: '5. Storage' },
  { id: 'case', label: '6. Case' },
  { id: 'psu', label: '7. Power Supply' },
];

export function BuilderWizard() {
  const { 
    selectedComponents, 
    totalPrice, 
    totalWattage, 
    removeComponent, 
    isValidBuild,
    clearBuild
  } = usePCBuilderStore();

  const addItemToCart = useCartStore((state) => state.addItem);
  
  const [activeCategory, setActiveCategory] = useState<string | null>(null);

  const handleConfirmAssembly = () => {
    if (!isValidBuild()) {
      toast.error('Incomplete or invalid build!');
      return;
    }

    // Generate a pseudo-composite product for the Cart
    const parentId = Date.now(); // In a real app, this would be an API call to save the BOM
    const parentSku = `CUSTOM-PC-${parentId.toString().slice(-4)}`;

    addItemToCart({
      id: parentId,
      name: 'Custom Gaming PC Assembly',
      sku: parentSku,
      price: totalPrice,
      type: 'composite', // Flag it so the backend knows to expand it
      quantity: 1,
      // For a real implementation, we'd attach the selected components here or fetch from backend
    });

    toast.success('Custom PC Assembly added to Cart!');
    clearBuild();
  };

  return (
    <div className="flex flex-col lg:flex-row gap-6 h-full">
      {/* Left: Wizard Steps */}
      <div className="flex-1 glass-card p-6 border border-border rounded-xl">
        <h2 className="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-indigo-500 mb-6">
          System Builder
        </h2>

        <div className="space-y-4">
          {BUILD_STEPS.map((step) => {
            const selected = selectedComponents[step.id];

            return (
              <div key={step.id} className="p-4 rounded-lg bg-surface/50 border border-border/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all hover:bg-surface">
                <div>
                  <h3 className="text-sm font-bold text-gray-400 uppercase tracking-wider">{step.label}</h3>
                  {selected ? (
                    <div className="mt-1">
                      <p className="font-semibold text-white">{selected.name}</p>
                      <p className="text-xs text-emerald-400 font-mono mt-0.5">+ ${selected.price.toFixed(2)}</p>
                    </div>
                  ) : (
                    <p className="text-sm text-gray-500 mt-1 italic">No component selected</p>
                  )}
                </div>

                <div className="flex gap-2">
                  {selected && (
                    <Button variant="ghost" className="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10" onClick={() => removeComponent(step.id)}>
                      Remove
                    </Button>
                  )}
                  <Button variant="secondary" onClick={() => setActiveCategory(step.id)}>
                    {selected ? 'Change' : 'Select Part'}
                  </Button>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Right: Summary Panel */}
      <div className="w-full lg:w-80 glass-card p-6 border border-border rounded-xl flex flex-col">
        <h2 className="text-xl font-bold text-white mb-6">Assembly Summary</h2>
        
        <div className="space-y-4 mb-8 flex-1">
          <div className="flex justify-between items-center pb-4 border-b border-border/50">
            <span className="text-gray-400">Total Price</span>
            <span className="text-2xl font-bold text-white">${totalPrice.toFixed(2)}</span>
          </div>
          
          <div className="flex justify-between items-center pb-4 border-b border-border/50">
            <span className="text-gray-400">Est. Wattage</span>
            <span className={`font-mono font-bold ${totalWattage > 0 ? 'text-amber-400' : 'text-gray-500'}`}>
              {totalWattage}W
            </span>
          </div>

          {!isValidBuild() && (
            <div className="p-3 rounded bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm">
              <span className="font-bold">⚠️ Warning:</span> Missing required components or power constraints not met.
            </div>
          )}
        </div>

        <Button 
          className="w-full h-12 text-lg font-bold shadow-lg shadow-indigo-500/20" 
          disabled={!isValidBuild()}
          onClick={handleConfirmAssembly}
        >
          Confirm Assembly
        </Button>
      </div>

      {/* Dynamic Modal */}
      {activeCategory && (
        <ComponentSelectorModal 
          category={activeCategory} 
          onClose={() => setActiveCategory(null)} 
        />
      )}
    </div>
  );
}
