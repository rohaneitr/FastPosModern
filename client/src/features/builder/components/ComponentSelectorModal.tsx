import React from 'react';
import { Modal } from '@/components/ui/modal';
import { usePCBuilderStore, BuilderComponent } from '../store/usePCBuilderStore';
import { Button } from '@/components/ui/button';

// Mock Data for demonstration. In a real app, this is fetched via useSWR('/api/builder/components?category=cpu')
const MOCK_COMPONENTS: Record<string, BuilderComponent[]> = {
  cpu: [
    { id: 101, name: 'Intel Core i9-14900K', price: 589.99, category: 'cpu', specs: { socket_type: 'LGA1700', wattage: 253 } },
    { id: 102, name: 'AMD Ryzen 9 7950X3D', price: 599.99, category: 'cpu', specs: { socket_type: 'AM5', wattage: 162 } },
  ],
  motherboard: [
    { id: 201, name: 'ASUS ROG Maximus Z790 Hero', price: 629.99, category: 'motherboard', specs: { socket_type: 'LGA1700', memory_type: 'DDR5', form_factor: 'ATX', wattage: 45 } },
    { id: 202, name: 'Gigabyte X670E AORUS MASTER', price: 489.99, category: 'motherboard', specs: { socket_type: 'AM5', memory_type: 'DDR5', form_factor: 'E-ATX', wattage: 50 } },
    { id: 203, name: 'MSI PRO B660M-A', price: 139.99, category: 'motherboard', specs: { socket_type: 'LGA1700', memory_type: 'DDR4', form_factor: 'Micro-ATX', wattage: 35 } },
  ],
  ram: [
    { id: 301, name: 'Corsair Vengeance 32GB (2x16GB) DDR5-6000', price: 114.99, category: 'ram', specs: { memory_type: 'DDR5', wattage: 15 } },
    { id: 302, name: 'G.Skill Ripjaws V 32GB (2x16GB) DDR4-3600', price: 64.99, category: 'ram', specs: { memory_type: 'DDR4', wattage: 10 } },
  ],
  gpu: [
    { id: 401, name: 'NVIDIA GeForce RTX 4090', price: 1599.99, category: 'gpu', specs: { wattage: 450 } },
    { id: 402, name: 'AMD Radeon RX 7900 XTX', price: 999.99, category: 'gpu', specs: { wattage: 355 } },
  ],
  psu: [
    { id: 701, name: 'Corsair RM1000x 1000W 80+ Gold', price: 189.99, category: 'psu', specs: { wattage: 1000 } },
    { id: 702, name: 'EVGA SuperNOVA 650 G5 650W', price: 109.99, category: 'psu', specs: { wattage: 650 } },
  ],
  case: [
    { id: 601, name: 'Lian Li PC-O11 Dynamic', price: 149.99, category: 'case', specs: { form_factor: 'E-ATX' } },
    { id: 602, name: 'Fractal Design Meshify C', price: 109.99, category: 'case', specs: { form_factor: 'ATX' } },
  ],
  storage: [
    { id: 501, name: 'Samsung 990 PRO 2TB NVMe SSD', price: 169.99, category: 'storage', specs: { wattage: 8 } },
  ]
};

interface Props {
  category: string;
  onClose: () => void;
}

export default function ComponentSelectorModal({ category, onClose }: Props) {
  const { selectComponent, canSelect, selectedComponents } = usePCBuilderStore();
  
  const components = MOCK_COMPONENTS[category] || [];
  const selectedId = selectedComponents[category]?.id;

  const handleSelect = (comp: BuilderComponent) => {
    selectComponent(category, comp);
    onClose();
  };

  return (
    <Modal open={true} onClose={onClose} title={`Select ${category}`} maxWidth="xl">
      <div className="text-gray-400 mb-4">
        Choose a component to add to your build. Incompatible items are disabled automatically.
      </div>

      <div className="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
        {components.map((comp) => {
          const validation = canSelect(comp);
          const isSelected = selectedId === comp.id;

          return (
            <div 
              key={comp.id} 
              className={`p-4 rounded-lg border transition-all flex justify-between items-center ${
                isSelected 
                  ? 'border-indigo-500 bg-indigo-500/10' 
                  : validation.allowed 
                    ? 'border-border/50 bg-white/5 hover:border-gray-500 hover:bg-white/10 cursor-pointer' 
                    : 'border-rose-900/50 bg-rose-950/20 opacity-50 cursor-not-allowed'
              }`}
              onClick={() => validation.allowed && !isSelected && handleSelect(comp)}
            >
              <div>
                <h4 className={`font-bold ${isSelected ? 'text-indigo-400' : 'text-white'}`}>
                  {comp.name}
                </h4>
                
                <div className="flex gap-3 mt-1.5 text-xs">
                  {comp.specs.socket_type && <span className="text-blue-400 font-mono bg-blue-500/10 px-1.5 py-0.5 rounded">Socket {comp.specs.socket_type}</span>}
                  {comp.specs.memory_type && <span className="text-emerald-400 font-mono bg-emerald-500/10 px-1.5 py-0.5 rounded">{comp.specs.memory_type}</span>}
                  {comp.specs.form_factor && <span className="text-purple-400 font-mono bg-purple-500/10 px-1.5 py-0.5 rounded">{comp.specs.form_factor}</span>}
                  {comp.specs.wattage ? <span className="text-amber-400 font-mono bg-amber-500/10 px-1.5 py-0.5 rounded">{comp.specs.wattage}W</span> : null}
                </div>

                {!validation.allowed && (
                  <p className="text-xs text-rose-500 font-bold mt-2 flex items-center gap-1">
                    <span>⚠️</span> {validation.reason}
                  </p>
                )}
              </div>

              <div className="text-right ml-4 shrink-0">
                <div className="text-lg font-bold text-white mb-2">${comp.price.toFixed(2)}</div>
                {isSelected ? (
                  <Button variant="secondary" size="sm" disabled className="bg-indigo-500/20 text-indigo-400 opacity-100">
                    Selected
                  </Button>
                ) : (
                  <Button 
                    size="sm" 
                    disabled={!validation.allowed}
                    onClick={(e) => { e.stopPropagation(); handleSelect(comp); }}
                  >
                    Add to Build
                  </Button>
                )}
              </div>
            </div>
          );
        })}
        
        {components.length === 0 && (
          <div className="text-center p-8 text-gray-500">
            No components available for this category.
          </div>
        )}
      </div>
    </Modal>
  );
}
