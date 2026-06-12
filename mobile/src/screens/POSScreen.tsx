import React, { useState, useEffect, useCallback } from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  FlatList, Alert, ActivityIndicator, SafeAreaView,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SyncManager } from '../lib/SyncManager';

interface CartItem {
  product_id: number;
  name: string;
  price: number;
  quantity: number;
}

interface POSScreenProps {
  onLogout: () => void;
}

export default function POSScreen({ onLogout }: POSScreenProps) {
  const [products, setProducts] = useState<any[]>([]);
  const [cart, setCart] = useState<CartItem[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);

  useEffect(() => {
    loadProducts();
    loadPendingCount();
  }, []);

  const loadProducts = async () => {
    setLoading(true);
    try {
      const prods = await SyncManager.pullProducts();
      setProducts(prods);
    } catch { /* fallback to cached */ }
    finally { setLoading(false); }
  };

  const loadPendingCount = async () => {
    const count = await SyncManager.getPendingCount();
    setPendingCount(count);
  };

  const addToCart = (product: any) => {
    setCart(prev => {
      const existing = prev.find(i => i.product_id === product.id);
      if (existing) {
        return prev.map(i => i.product_id === product.id
          ? { ...i, quantity: i.quantity + 1 }
          : i
        );
      }
      return [...prev, {
        product_id: product.id,
        name: product.name,
        price: parseFloat(product.sell_price_inc_tax || product.default_sell_price || 0),
        quantity: 1,
      }];
    });
  };

  const removeFromCart = (productId: number) => {
    setCart(prev => prev.filter(i => i.product_id !== productId));
  };

  const updateQty = (productId: number, qty: number) => {
    if (qty <= 0) return removeFromCart(productId);
    setCart(prev => prev.map(i => i.product_id === productId ? { ...i, quantity: qty } : i));
  };

  const subtotal = cart.reduce((sum, i) => sum + i.price * i.quantity, 0);
  const tax = subtotal * 0.1;
  const total = subtotal + tax;

  const handleCheckout = async () => {
    if (cart.length === 0) { Alert.alert('Empty Cart', 'Add items first'); return; }

    const userData = await AsyncStorage.getItem('user_data');
    const user = userData ? JSON.parse(userData) : null;
    const locationId = user?.business?.locations?.[0]?.id || 1;

    const transaction = {
      invoice_no: `MOB-${Date.now()}`,
      location_id: locationId,
      transaction_date: new Date().toISOString(),
      payment_method: 'cash',
      tax_rate: 0.1,
      items: cart.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
        price: i.price,
      })),
    };

    await SyncManager.storeOfflineTransaction(transaction);
    setCart([]);
    await loadPendingCount();
    Alert.alert('Success', `Sale recorded. Total: $${total.toFixed(2)}`);
  };

  const handleSync = async () => {
    setSyncing(true);
    try {
      const result = await SyncManager.fullSync();
      setProducts(result.products);
      await loadPendingCount();
      Alert.alert('Sync Complete', `Pushed ${result.pushResult.synced_count} transactions. ${result.pushResult.remaining} pending.`);
    } catch {
      Alert.alert('Sync Failed', 'Will retry automatically.');
    } finally { setSyncing(false); }
  };

  const handleLogout = async () => {
    await AsyncStorage.multiRemove(['auth_token', 'user_data']);
    onLogout();
  };

  const filtered = products.filter(p =>
    p.name?.toLowerCase().includes(search.toLowerCase()) ||
    p.sku?.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>⚡ FastPOS</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.syncBtn} onPress={handleSync} disabled={syncing}>
            {syncing ? <ActivityIndicator size="small" color="#6366f1" /> : (
              <Text style={styles.syncText}>
                ↻ Sync {pendingCount > 0 ? `(${pendingCount})` : ''}
              </Text>
            )}
          </TouchableOpacity>
          <TouchableOpacity onPress={handleLogout}>
            <Text style={styles.logoutText}>Logout</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.body}>
        {/* Product Catalog */}
        <View style={styles.catalog}>
          <TextInput
            style={styles.searchInput}
            placeholder="Search products..."
            placeholderTextColor="#555"
            value={search}
            onChangeText={setSearch}
          />
          {loading ? (
            <ActivityIndicator style={{ marginTop: 40 }} color="#6366f1" />
          ) : (
            <FlatList
              data={filtered}
              keyExtractor={item => String(item.id)}
              numColumns={2}
              contentContainerStyle={{ gap: 8, paddingBottom: 16 }}
              columnWrapperStyle={{ gap: 8 }}
              renderItem={({ item }) => (
                <TouchableOpacity style={styles.productCard} onPress={() => addToCart(item)} activeOpacity={0.7}>
                  <Text style={styles.productName} numberOfLines={2}>{item.name}</Text>
                  <Text style={styles.productSku}>{item.sku || '—'}</Text>
                  <Text style={styles.productPrice}>
                    ${parseFloat(item.sell_price_inc_tax || item.default_sell_price || 0).toFixed(2)}
                  </Text>
                </TouchableOpacity>
              )}
              ListEmptyComponent={<Text style={styles.emptyText}>No products found.</Text>}
            />
          )}
        </View>

        {/* Cart */}
        <View style={styles.cartPanel}>
          <Text style={styles.cartTitle}>🛒 Cart ({cart.length})</Text>
          <FlatList
            data={cart}
            keyExtractor={item => String(item.product_id)}
            renderItem={({ item }) => (
              <View style={styles.cartItem}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.cartItemName} numberOfLines={1}>{item.name}</Text>
                  <Text style={styles.cartItemPrice}>${item.price.toFixed(2)} × {item.quantity}</Text>
                </View>
                <View style={styles.qtyControls}>
                  <TouchableOpacity style={styles.qtyBtn} onPress={() => updateQty(item.product_id, item.quantity - 1)}>
                    <Text style={styles.qtyBtnText}>−</Text>
                  </TouchableOpacity>
                  <Text style={styles.qtyValue}>{item.quantity}</Text>
                  <TouchableOpacity style={styles.qtyBtn} onPress={() => updateQty(item.product_id, item.quantity + 1)}>
                    <Text style={styles.qtyBtnText}>+</Text>
                  </TouchableOpacity>
                </View>
                <Text style={styles.lineTotal}>${(item.price * item.quantity).toFixed(2)}</Text>
              </View>
            )}
            ListEmptyComponent={<Text style={styles.emptyCart}>Tap a product to add</Text>}
          />

          {/* Totals */}
          <View style={styles.totals}>
            <View style={styles.totalRow}><Text style={styles.totalLabel}>Subtotal</Text><Text style={styles.totalValue}>${subtotal.toFixed(2)}</Text></View>
            <View style={styles.totalRow}><Text style={styles.totalLabel}>Tax (10%)</Text><Text style={styles.totalValue}>${tax.toFixed(2)}</Text></View>
            <View style={[styles.totalRow, styles.grandTotal]}><Text style={styles.grandLabel}>Total</Text><Text style={styles.grandValue}>${total.toFixed(2)}</Text></View>
          </View>

          <TouchableOpacity style={[styles.checkoutBtn, cart.length === 0 && { opacity: 0.5 }]} onPress={handleCheckout} disabled={cart.length === 0}>
            <Text style={styles.checkoutText}>💳 Charge ${total.toFixed(2)}</Text>
          </TouchableOpacity>
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0a0a14' },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, borderBottomWidth: 1, borderBottomColor: '#1e1e30' },
  headerTitle: { fontSize: 20, fontWeight: '800', color: '#fff' },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 16 },
  syncBtn: { flexDirection: 'row', alignItems: 'center' },
  syncText: { color: '#6366f1', fontWeight: '600', fontSize: 14 },
  logoutText: { color: '#f43f5e', fontWeight: '600', fontSize: 14 },
  body: { flex: 1, flexDirection: 'row' },
  catalog: { flex: 1, padding: 12 },
  searchInput: { backgroundColor: '#12121e', borderWidth: 1, borderColor: '#1e1e30', borderRadius: 10, paddingHorizontal: 14, paddingVertical: 10, color: '#fff', fontSize: 14, marginBottom: 12 },
  productCard: { flex: 1, backgroundColor: '#12121e', borderRadius: 12, padding: 14, borderWidth: 1, borderColor: '#1e1e30' },
  productName: { color: '#fff', fontWeight: '600', fontSize: 14 },
  productSku: { color: '#555', fontSize: 11, marginTop: 2 },
  productPrice: { color: '#6366f1', fontWeight: '700', fontSize: 16, marginTop: 8 },
  emptyText: { color: '#555', textAlign: 'center', marginTop: 40 },
  cartPanel: { width: 300, backgroundColor: '#12121e', borderLeftWidth: 1, borderLeftColor: '#1e1e30', padding: 16 },
  cartTitle: { fontSize: 18, fontWeight: '700', color: '#fff', marginBottom: 12 },
  cartItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: '#1a1a2e' },
  cartItemName: { color: '#fff', fontWeight: '600', fontSize: 13 },
  cartItemPrice: { color: '#666', fontSize: 11 },
  qtyControls: { flexDirection: 'row', alignItems: 'center', marginHorizontal: 8 },
  qtyBtn: { width: 28, height: 28, borderRadius: 6, backgroundColor: '#1e1e30', justifyContent: 'center', alignItems: 'center' },
  qtyBtnText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  qtyValue: { color: '#fff', fontWeight: '700', width: 28, textAlign: 'center' },
  lineTotal: { color: '#6366f1', fontWeight: '700', width: 60, textAlign: 'right' },
  emptyCart: { color: '#444', textAlign: 'center', marginTop: 40, fontSize: 13 },
  totals: { borderTopWidth: 1, borderTopColor: '#1e1e30', paddingTop: 12, marginTop: 12 },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  totalLabel: { color: '#888', fontSize: 14 },
  totalValue: { color: '#ccc', fontSize: 14 },
  grandTotal: { borderTopWidth: 1, borderTopColor: '#1e1e30', paddingTop: 8, marginTop: 4 },
  grandLabel: { color: '#fff', fontSize: 18, fontWeight: '800' },
  grandValue: { color: '#fff', fontSize: 18, fontWeight: '800' },
  checkoutBtn: { backgroundColor: '#6366f1', borderRadius: 14, paddingVertical: 16, alignItems: 'center', marginTop: 16 },
  checkoutText: { color: '#fff', fontWeight: '800', fontSize: 17 },
});
