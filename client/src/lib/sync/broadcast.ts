export type BroadcastEvent = 'INVENTORY_MUTATED' | 'CART_CLEARED';

class GlobalSync {
  private channel: BroadcastChannel | null = null;
  private listeners: Map<BroadcastEvent, Set<() => void>> = new Map();

  constructor() {
    if (typeof window !== 'undefined' && 'BroadcastChannel' in window) {
      this.channel = new BroadcastChannel('fastpos-global-sync');
      this.channel.onmessage = (event: MessageEvent) => {
        const type = event.data?.type as BroadcastEvent;
        if (type && this.listeners.has(type)) {
          this.listeners.get(type)?.forEach((callback) => callback());
        }
      };
    }
  }

  public broadcast(type: BroadcastEvent) {
    if (this.channel) {
      this.channel.postMessage({ type });
    }
  }

  public subscribe(type: BroadcastEvent, callback: () => void) {
    if (!this.listeners.has(type)) {
      this.listeners.set(type, new Set());
    }
    this.listeners.get(type)?.add(callback);

    return () => {
      this.listeners.get(type)?.delete(callback);
    };
  }

  public cleanup() {
    if (this.channel) {
      this.channel.close();
    }
  }
}

export const globalSync = new GlobalSync();
