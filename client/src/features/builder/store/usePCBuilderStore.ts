import { create } from 'zustand';

export interface ComponentSpecs {
  socket_type?: string;
  memory_type?: string;
  form_factor?: string;
  wattage?: number;
}

export interface BuilderComponent {
  id: number;
  name: string;
  price: number;
  category: string; // 'cpu', 'motherboard', 'ram', 'gpu', 'psu', 'case', 'storage'
  specs: ComponentSpecs;
}

interface PCBuilderStore {
  selectedComponents: Record<string, BuilderComponent | null>;
  totalWattage: number;
  totalPrice: number;
  
  // Constraints cache
  activeSocket: string | null;
  activeMemoryType: string | null;
  activeFormFactor: string | null;
  
  selectComponent: (category: string, component: BuilderComponent) => void;
  removeComponent: (category: string) => void;
  clearBuild: () => void;
  
  // Validation Helper
  canSelect: (component: BuilderComponent) => { allowed: boolean; reason: string };
  isValidBuild: () => boolean;
}

export const usePCBuilderStore = create<PCBuilderStore>((set, get) => ({
  selectedComponents: {
    cpu: null,
    motherboard: null,
    ram: null,
    gpu: null,
    psu: null,
    case: null,
    storage: null,
  },
  totalWattage: 0,
  totalPrice: 0,
  activeSocket: null,
  activeMemoryType: null,
  activeFormFactor: null,

  selectComponent: (category, component) => set((state) => {
    // DEPENDENCY CHAIN RESET: If changing core component, reset dependents
    let newSelected = { ...state.selectedComponents };
    
    if (category === 'cpu' && state.selectedComponents.cpu?.id !== component.id) {
      newSelected.motherboard = null; // Socket might change
      newSelected.ram = null; // Memory generation might change
    }
    
    if (category === 'motherboard' && state.selectedComponents.motherboard?.id !== component.id) {
      newSelected.ram = null; // Memory gen
      newSelected.case = null; // Form factor
    }

    newSelected[category] = component;
    
    // Recalculate totals and constraints
    let wattage = 0;
    let price = 0;
    let socket: string | null = null;
    let memType: string | null = null;
    let formFactor: string | null = null;

    Object.values(newSelected).forEach((comp) => {
      if (comp) {
        price += Number(comp.price);
        wattage += comp.specs.wattage || 0;
        // The Motherboard typically dictates the socket and memory type for the rest of the build
        if (comp.category === 'motherboard' && comp.specs.socket_type) socket = comp.specs.socket_type;
        if (comp.category === 'motherboard' && comp.specs.memory_type) memType = comp.specs.memory_type;
        if (comp.category === 'motherboard' && comp.specs.form_factor) formFactor = comp.specs.form_factor;
        
        // If no motherboard is selected yet, let the CPU dictate the socket requirement
        if (!socket && comp.category === 'cpu' && comp.specs.socket_type) socket = comp.specs.socket_type;
      }
    });

    return {
      selectedComponents: newSelected,
      totalPrice: price,
      totalWattage: wattage,
      activeSocket: socket,
      activeMemoryType: memType,
      activeFormFactor: formFactor,
    };
  }),

  removeComponent: (category) => set((state) => {
    const newSelected = { ...state.selectedComponents, [category]: null };
    
    // Recalculate totals and constraints
    let wattage = 0;
    let price = 0;
    let socket: string | null = null;
    let memType: string | null = null;
    let formFactor: string | null = null;

    Object.values(newSelected).forEach((comp) => {
      if (comp) {
        price += Number(comp.price);
        wattage += comp.specs.wattage || 0;
        if (comp.category === 'motherboard' && comp.specs.socket_type) socket = comp.specs.socket_type;
        if (comp.category === 'motherboard' && comp.specs.memory_type) memType = comp.specs.memory_type;
        if (comp.category === 'motherboard' && comp.specs.form_factor) formFactor = comp.specs.form_factor;
        
        if (!socket && comp.category === 'cpu' && comp.specs.socket_type) socket = comp.specs.socket_type;
      }
    });

    return {
      selectedComponents: newSelected,
      totalPrice: price,
      totalWattage: wattage,
      activeSocket: socket,
      activeMemoryType: memType,
      activeFormFactor: formFactor,
    };
  }),

  clearBuild: () => set({
    selectedComponents: {
      cpu: null,
      motherboard: null,
      ram: null,
      gpu: null,
      psu: null,
      case: null,
      storage: null,
    },
    totalWattage: 0,
    totalPrice: 0,
    activeSocket: null,
    activeMemoryType: null,
    activeFormFactor: null,
  }),

  canSelect: (component: BuilderComponent) => {
    const { activeSocket, activeMemoryType, activeFormFactor, totalWattage } = get();

    // 1. Socket Check (CPU <-> Motherboard)
    if (activeSocket && component.specs.socket_type && activeSocket !== component.specs.socket_type) {
      return { allowed: false, reason: `Requires Socket ${activeSocket}, but part is ${component.specs.socket_type}` };
    }

    // 2. Memory Check (RAM <-> Motherboard)
    if (activeMemoryType && component.specs.memory_type && activeMemoryType !== component.specs.memory_type) {
      return { allowed: false, reason: `Requires Memory ${activeMemoryType}, but part is ${component.specs.memory_type}` };
    }

    // 3. Form Factor Check (Motherboard <-> Case)
    if (activeFormFactor && component.category === 'case' && component.specs.form_factor && activeFormFactor !== component.specs.form_factor) {
      return { allowed: false, reason: `Motherboard is ${activeFormFactor}, but Case supports ${component.specs.form_factor}` }; 
    }

    // 4. Power Supply Check
    if (component.category === 'psu' && component.specs.wattage) {
      // Allow 20% overhead
      if (component.specs.wattage < (totalWattage * 1.2)) {
        return { allowed: false, reason: `PSU (${component.specs.wattage}W) insufficient. Build requires at least ${Math.ceil(totalWattage * 1.2)}W` };
      }
    }

    return { allowed: true, reason: '' };
  },

  isValidBuild: () => {
    const { selectedComponents, totalWattage } = get();
    // A valid build requires at minimum a CPU, Motherboard, RAM, Case, and PSU.
    const required = ['cpu', 'motherboard', 'ram', 'psu', 'case'];
    for (const req of required) {
      if (!selectedComponents[req]) return false;
    }
    
    const psu = selectedComponents['psu'];
    if (psu && psu.specs.wattage && psu.specs.wattage < (totalWattage * 1.2)) return false;
    
    return true;
  }
}));
