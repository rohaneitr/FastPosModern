import { create } from 'zustand';
import { persist, StateStorage, createJSONStorage } from 'zustand/middleware';
import { get, set, del } from 'idb-keyval';

// Custom storage engine using idb-keyval for Zustand
const idbStorage: StateStorage = {
  getItem: async (name: string): Promise<string | null> => {
    return (await get(name)) || null;
  },
  setItem: async (name: string, value: string): Promise<void> => {
    await set(name, value);
  },
  removeItem: async (name: string): Promise<void> => {
    await del(name);
  },
};

export interface UnsyncedTransaction {
  uuid: string;
  payload: Record<string, any>;
  timestamp: number;
}

interface SyncState {
  unsynced_transactions: UnsyncedTransaction[];
  addTransaction: (tx: UnsyncedTransaction) => void;
  removeTransaction: (uuid: string) => void;
  clearQueue: () => void;
}

export const useSyncStore = create<SyncState>()(
  persist(
    (set) => ({
      unsynced_transactions: [],
      addTransaction: (tx) => 
        set((state) => ({ 
          unsynced_transactions: [...state.unsynced_transactions, tx] 
        })),
      removeTransaction: (uuid) => 
        set((state) => ({ 
          unsynced_transactions: state.unsynced_transactions.filter(t => t.uuid !== uuid) 
        })),
      clearQueue: () => set({ unsynced_transactions: [] }),
    }),
    {
      name: 'fpm-offline-sync-queue',
      storage: createJSONStorage(() => idbStorage),
    }
  )
);
