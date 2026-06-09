'use client';

export function usePosSounds() {
  const playSound = (path: string) => {
    try {
      const audio = new Audio(path);
      audio.volume = 0.5;
      audio.play().catch(e => {
        // Autoplay policy or other error
        console.warn('Audio playback failed:', e);
      });
    } catch (err) {
      console.warn('Audio not supported:', err);
    }
  };

  const playScanSuccess = () => playSound('/sounds/scan-success.mp3');
  const playScanError = () => playSound('/sounds/scan-error.mp3');
  const playTaskSuccess = () => playSound('/sounds/task-success.mp3');

  return {
    playScanSuccess,
    playScanError,
    playTaskSuccess
  };
}
