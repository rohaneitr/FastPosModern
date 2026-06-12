import React from 'react';
import { View, Text, StyleSheet, ActivityIndicator } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import apiClient from '../core/apiClient';

interface TelemetryData {
  mrr: number;
  active_tenants: number;
  server_status: 'operational' | 'degraded';
}

const fetchTelemetryPulse = async (): Promise<TelemetryData> => {
  const { data } = await apiClient.get('/mobile/telemetry/pulse');
  return data;
};

export default function MobileDashboard() {
  const { data, isLoading, isError, dataUpdatedAt } = useQuery<TelemetryData, Error>({
    queryKey: ['telemetryPulse'],
    queryFn: fetchTelemetryPulse,
    staleTime: 60000, // Data fresh for 1 minute
  });

  if (isLoading && !data) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#4F46E5" />
      </View>
    );
  }

  // Calculate offline cache duration
  let offlineNotice = null;
  if (isError && dataUpdatedAt) {
    const minutesAgo = Math.floor((Date.now() - dataUpdatedAt) / 60000);
    offlineNotice = `Offline Mode - Last updated ${minutesAgo} mins ago`;
  } else if (isError && !data) {
     return (
        <View style={styles.center}>
          <Text style={styles.errorText}>No network connection and no cached data available.</Text>
        </View>
     );
  }

  return (
    <View style={styles.container}>
      {offlineNotice && (
        <View style={styles.offlineBadge}>
          <Text style={styles.offlineText}>{offlineNotice}</Text>
        </View>
      )}

      <Text style={styles.title}>SuperAdmin Telemetry</Text>
      
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Monthly Recurring Revenue</Text>
        <Text style={styles.cardValue}>${data?.mrr?.toFixed(2) || '0.00'}</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Active Tenants</Text>
        <Text style={styles.cardValue}>{data?.active_tenants || 0}</Text>
      </View>
      
      <View style={[styles.card, data?.server_status === 'degraded' ? styles.warningBorder : styles.successBorder]}>
        <Text style={styles.cardTitle}>Server Status</Text>
        <Text style={[styles.cardValue, { fontSize: 18 }]}>
           {(data?.server_status || 'Unknown').toUpperCase()}
        </Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#F3F4F6' },
  container: { flex: 1, padding: 20, backgroundColor: '#F3F4F6' },
  offlineBadge: { backgroundColor: '#FCD34D', padding: 8, borderRadius: 8, marginBottom: 16 },
  offlineText: { color: '#92400E', textAlign: 'center', fontWeight: 'bold', fontSize: 12 },
  title: { fontSize: 24, fontWeight: 'bold', color: '#1F2937', marginBottom: 20 },
  card: { backgroundColor: '#FFFFFF', padding: 20, borderRadius: 16, marginBottom: 16, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 4, elevation: 2 },
  cardTitle: { fontSize: 14, color: '#6B7280', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8 },
  cardValue: { fontSize: 32, fontWeight: '900', color: '#111827' },
  errorText: { color: '#EF4444', textAlign: 'center', fontWeight: '500' },
  successBorder: { borderLeftWidth: 4, borderLeftColor: '#10B981' },
  warningBorder: { borderLeftWidth: 4, borderLeftColor: '#F59E0B' }
});
