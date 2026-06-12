import { create } from 'zustand';

interface RateLimitState {
  isRateLimited: boolean;
  retryAfterSeconds: number;
  unblockAt: number | null;
  setRateLimited: (seconds: number) => void;
  clearRateLimit: () => void;
}

export const useRateLimitStore = create<RateLimitState>((set, get) => {
  let timer: ReturnType<typeof setInterval> | null = null;

  return {
    isRateLimited: false,
    retryAfterSeconds: 0,
    unblockAt: null,
    setRateLimited: (seconds) => {
      const unblockAt = Date.now() + seconds * 1000;
      set({ isRateLimited: true, retryAfterSeconds: seconds, unblockAt });
      
      if (timer) clearInterval(timer);

      timer = setInterval(() => {
        const remaining = Math.ceil((get().unblockAt! - Date.now()) / 1000);
        if (remaining <= 0) {
          get().clearRateLimit();
        } else {
          set({ retryAfterSeconds: remaining });
        }
      }, 1000);
    },
    clearRateLimit: () => {
      if (timer) clearInterval(timer);
      set({ isRateLimited: false, retryAfterSeconds: 0, unblockAt: null });
    },
  };
});
