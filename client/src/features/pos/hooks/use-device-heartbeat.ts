'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';

export interface DeviceState {
  locked: boolean;
  reason?: string;
  isLimitReached?: boolean;
}

export function useDeviceHeartbeat() {
  const [deviceLocked, setDeviceLocked] = useState<DeviceState>({ locked: true, reason: 'Validating device...' });
  const [isActivating, setIsActivating] = useState(false);

  useEffect(() => {
    let hwHash = localStorage.getItem('pos_hardware_hash');
    if (!hwHash) {
      hwHash = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2) + Date.now().toString(36);
      localStorage.setItem('pos_hardware_hash', hwHash);
    }

    const activateDevice = async () => {
      try {
        const res = await api.post('/devices/activate', { hardware_hash: hwHash });
        const licenseKey = res.data.license_key;
        localStorage.setItem('pos_license_key', licenseKey);
        setDeviceLocked({ locked: false });

        const heartbeatInterval = setInterval(() => {
          api.post('/devices/heartbeat', { license_key: licenseKey, hardware_hash: hwHash })
            .catch((err) => {
              if (err.response?.status === 403 || err.response?.status === 402 || err.response?.status === 401) {
                setDeviceLocked({ locked: true, reason: err.response?.data?.message || 'Device access revoked or license expired.' });
              }
            });
        }, 5 * 60 * 1000);

        return () => clearInterval(heartbeatInterval);
      } catch (err: any) {
        const isLimit = err.response?.data?.code === 'QUOTA_EXCEEDED';
        setDeviceLocked({
          locked: true,
          reason: err.response?.data?.message || 'Device Limit Reached or Activation Failed.',
          isLimitReached: isLimit
        });
      }
    };

    let cleanup: any;
    activateDevice().then(res => cleanup = res);
    return () => {
      if (cleanup) cleanup();
    };
  }, []);

  const manualActivate = async (licenseKey: string) => {
    setIsActivating(true);
    const hwHash = localStorage.getItem('pos_hardware_hash');
    try {
      const res = await api.post('/devices/activate', { hardware_hash: hwHash, license_key: licenseKey });
      localStorage.setItem('pos_license_key', res.data.license_key);
      setDeviceLocked({ locked: false });
      toast.success('Device activated successfully!');
      window.location.reload();
    } catch (err: any) {
      const isLimit = err.response?.data?.code === 'QUOTA_EXCEEDED';
      setDeviceLocked({
        locked: true,
        reason: err.response?.data?.message || 'Activation Failed. Invalid key or limit reached.',
        isLimitReached: isLimit
      });
      toast.error(err.response?.data?.message || 'Activation Failed. Invalid key or limit reached.');
    } finally {
      setIsActivating(false);
    }
  };

  const forceActivate = async (licenseKey?: string) => {
    const keyToUse = licenseKey || localStorage.getItem('pos_license_key');
    if (!keyToUse) return;
    setIsActivating(true);
    const hwHash = localStorage.getItem('pos_hardware_hash');
    try {
      const res = await api.post('/devices/activate', { hardware_hash: hwHash, license_key: keyToUse, force_release: true });
      localStorage.setItem('pos_license_key', res.data.license_key);
      setDeviceLocked({ locked: false, isLimitReached: false });
      toast.success('Previous devices disconnected. Activated successfully!');
      window.location.reload();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Force Activation Failed.');
    } finally {
      setIsActivating(false);
    }
  };

  return {
    deviceLocked,
    setDeviceLocked,
    isActivating,
    manualActivate,
    forceActivate
  };
}
