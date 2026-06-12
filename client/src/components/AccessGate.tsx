"use client";

import React, { useEffect, useState } from "react";
import { useAuthStore } from "@/store/useAuthStore";

interface AccessGateProps {
  requiredPermission: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function AccessGate({ requiredPermission, children, fallback = null }: AccessGateProps) {
  const [mounted, setMounted] = useState(false);
  const hasPermission = useAuthStore((state) => state.hasPermission);

  // Prevent Hydration Mismatch by waiting until the component mounts on the client
  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted) {
    // Return null or a subtle skeleton during SSR to prevent mismatch
    return null; 
  }

  if (hasPermission(requiredPermission)) {
    return <>{children}</>;
  }

  return <>{fallback}</>;
}
