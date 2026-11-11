import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ScrollView,
  TextInput,
  Switch,
  ActivityIndicator,
  Alert,
  Linking,
  SafeAreaView
} from 'react-native';
import { StatusBar } from 'expo-status-bar';

// Default backend API URL (Use your laptop Wi-Fi IP or Railway cloud URL)
const DEFAULT_SERVER_URL = 'http://192.168.31.82:8000';

const PRESET_COUNTRIES = [
  { code: 'US', name: 'USA 🇺🇸' },
  { code: 'GB', name: 'UK 🇬🇧' },
  { code: 'AU', name: 'Australia 🇦🇺' },
  { code: 'PH', name: 'Philippines 🇵🇭' },
  { code: 'KR', name: 'South Korea 🇰🇷' },
  { code: 'JP', name: 'Japan 🇯🇵' },
  { code: 'CN', name: 'China 🇨🇳' },
  { code: 'AE', name: 'UAE 🇦🇪' },
  { code: 'NG', name: 'Nigeria 🇳🇬' },
  { code: 'KE', name: 'Kenya 🇰🇪' },
];

export default function App() {
  const [serverUrl, setServerUrl] = useState(DEFAULT_SERVER_URL);
  const [isBotOn, setIsBotOn] = useState(true);
  const [rejectDomestic, setRejectDomestic] = useState(true);
  const [selectedCountries, setSelectedCountries] = useState(['US', 'GB', 'AU', 'PH', 'KR', 'JP', 'CN']);
  const [targetProducts, setTargetProducts] = useState([]);
  const [newProductText, setNewProductText] = useState('');
  const [loading, setLoading] = useState(false);
  const [recentLeads, setRecentLeads] = useState([]);

  // Fetch current settings from backend
  const fetchSettings = async () => {
    try {
      const res = await fetch(`${serverUrl}/api/ai-bot/settings`);
      const data = await res.json();
      setIsBotOn(data.enabled);
      setRejectDomestic(data.reject_domestic ?? true);
      if (data.country_filters && data.country_filters.length > 0) {
        setSelectedCountries(data.country_filters);
      }
      if (data.product_filters) {
        setTargetProducts(data.product_filters);
      }
    } catch (err) {
      console.log('Error fetching settings:', err.message);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, [serverUrl]);

  // Toggle Bot ON/OFF
  const handleToggleBot = async () => {
    setLoading(true);
    const nextState = !isBotOn;
    try {
      const res = await fetch(`${serverUrl}/api/ai-bot/toggle`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ enabled: nextState })
      });
      const data = await res.json();
      if (data.enabled !== undefined) {
        setIsBotOn(data.enabled);
        Alert.alert('AI Bot Status', data.enabled ? '🟢 Bot is now ON & Auto-Buying' : '🔴 Bot is now PAUSED');
      }
    } catch (err) {
      Alert.alert('Connection Error', `Could not reach ${serverUrl}. Make sure your phone is on the same Wi-Fi.`);
    } finally {
      setLoading(false);
    }
  };

  // Save criteria to backend
  const saveCriteria = async (updatedCountries, updatedDomestic, updatedProducts) => {
    try {
      await fetch(`${serverUrl}/api/ai-bot/settings`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          enabled: isBotOn,
          reject_domestic: updatedDomestic,
          country_filters: updatedCountries,
          product_filters: updatedProducts
        })
      });
    } catch (e) {
      console.log('Error saving criteria:', e);
    }
  };

  // Toggle country
  const toggleCountry = (code) => {
    let next;
    if (selectedCountries.includes(code)) {
      next = selectedCountries.filter(c => c !== code);
    } else {
      next = [...selectedCountries, code];
    }
    setSelectedCountries(next);
    saveCriteria(next, rejectDomestic, targetProducts);
  };

  // Add Product
  const addProduct = () => {
    const trimmed = newProductText.trim();
    if (!trimmed) return;
    const parts = trimmed.split(',').map(s => s.trim()).filter(Boolean);
    const updated = Array.from(new Set([...targetProducts, ...parts]));
    setTargetProducts(updated);
    setNewProductText('');
    saveCriteria(selectedCountries, rejectDomestic, updated);
  };

  // Remove Product
  const removeProduct = (prodToRemove) => {
    const updated = targetProducts.filter(p => p !== prodToRemove);
    setTargetProducts(updated);
    saveCriteria(selectedCountries, rejectDomestic, updated);
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="light" />
      <ScrollView contentContainerStyle={styles.container}>
        
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.brandTitle}>Bliss Pharmex AI</Text>
          <Text style={styles.brandSubtitle}>Mobile Lead Controller App</Text>
        </View>

        {/* Hero Power Card */}
        <View style={[styles.heroCard, isBotOn ? styles.heroActive : styles.heroPaused]}>
          <View style={[styles.pill, isBotOn ? styles.pillActive : styles.pillPaused]}>
            <View style={[styles.dot, isBotOn ? styles.dotActive : styles.dotPaused]} />
            <Text style={[styles.pillText, isBotOn ? styles.textActive : styles.textPaused]}>
              {isBotOn ? 'AI BOT RUNNING' : 'AI BOT PAUSED'}
            </Text>
          </View>

          <Text style={styles.heroTitle}>
            {isBotOn ? 'Auto-Buying Active' : 'Standby / Paused'}
          </Text>
          <Text style={styles.heroDesc}>
            {isBotOn 
              ? 'Monitoring IndiaMART for matching export leads and auto-sending quotation.' 
              : 'Bot is paused. Inquiries are held safely until you toggle ON.'}
          </Text>

          <TouchableOpacity
            style={[styles.powerBtn, isBotOn ? styles.powerBtnStop : styles.powerBtnStart]}
            onPress={handleToggleBot}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.powerBtnText}>
                {isBotOn ? '⏹ Turn AI Bot OFF' : '▶ Turn AI Bot ON'}
              </Text>
            )}
          </TouchableOpacity>
        </View>

        {/* Domestic Blocker */}
        <View style={styles.sectionCard}>
          <View style={styles.rowBetween}>
            <View style={{ flex: 1, paddingRight: 10 }}>
              <Text style={styles.cardTitle}>Reject Domestic (India)</Text>
              <Text style={styles.cardSub}>Blocks PCD franchise, GST & Indian leads</Text>
            </View>
            <Switch
              value={rejectDomestic}
              onValueChange={(val) => {
                setRejectDomestic(val);
                saveCriteria(selectedCountries, val, targetProducts);
              }}
              trackColor={{ false: '#334155', true: '#10b981' }}
              thumbColor="#fff"
            />
          </View>
        </View>

        {/* Allowed Export Countries */}
        <View style={styles.sectionCard}>
          <Text style={styles.cardTitle}>Allowed Export Markets</Text>
          <Text style={styles.cardSub}>Only leads from selected countries will be purchased</Text>
          
          <View style={styles.chipsContainer}>
            {PRESET_COUNTRIES.map((item) => {
              const isSelected = selectedCountries.includes(item.code);
              return (
                <TouchableOpacity
                  key={item.code}
                  style={[styles.countryChip, isSelected && styles.countryChipSelected]}
                  onPress={() => toggleCountry(item.code)}
                >
                  <Text style={[styles.chipText, isSelected && styles.chipTextSelected]}>
                    {item.name}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </View>
        </View>

        {/* Target Medicines Filter */}
        <View style={styles.sectionCard}>
          <Text style={styles.cardTitle}>Target Products & Medicines</Text>
          <Text style={styles.cardSub}>
            {targetProducts.length > 0 
              ? 'AI will ONLY buy leads matching these medicines' 
              : 'All 923 catalogue medicines allowed'}
          </Text>

          <View style={styles.addRow}>
            <TextInput
              style={styles.input}
              placeholder="e.g. Paracetamol, Amoxicillin"
              placeholderTextColor="#64748b"
              value={newProductText}
              onChangeText={setNewProductText}
            />
            <TouchableOpacity style={styles.addBtn} onPress={addProduct}>
              <Text style={styles.addBtnText}>+ Add</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.chipsContainer}>
            {targetProducts.map((prod, idx) => (
              <View key={idx} style={styles.productBadge}>
                <Text style={styles.productBadgeText}>💊 {prod}</Text>
                <TouchableOpacity onPress={() => removeProduct(prod)}>
                  <Text style={styles.removeX}>×</Text>
                </TouchableOpacity>
              </View>
            ))}
          </View>
        </View>

        {/* Server Settings */}
        <View style={styles.sectionCard}>
          <Text style={styles.cardTitle}>Server Connection URL</Text>
          <TextInput
            style={[styles.input, { marginTop: 8 }]}
            value={serverUrl}
            onChangeText={setServerUrl}
            placeholder="http://192.168.x.x:8000"
            placeholderTextColor="#64748b"
          />
        </View>

      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#090d16',
  },
  container: {
    padding: 16,
    paddingBottom: 40,
  },
  header: {
    marginBottom: 16,
    marginTop: 8,
  },
  brandTitle: {
    fontSize: 22,
    fontWeight: '800',
    color: '#f8fafc',
    letterSpacing: -0.5,
  },
  brandSubtitle: {
    fontSize: 12,
    color: '#94a3b8',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginTop: 2,
  },
  heroCard: {
    backgroundColor: '#131c2e',
    borderRadius: 18,
    padding: 20,
    alignItems: 'center',
    marginBottom: 16,
    borderWidth: 1,
  },
  heroActive: {
    borderColor: 'rgba(16, 185, 129, 0.4)',
  },
  heroPaused: {
    borderColor: 'rgba(239, 68, 68, 0.3)',
  },
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 20,
    marginBottom: 10,
  },
  pillActive: {
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
  },
  pillPaused: {
    backgroundColor: 'rgba(239, 68, 68, 0.15)',
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 6,
  },
  dotActive: {
    backgroundColor: '#10b981',
  },
  dotPaused: {
    backgroundColor: '#ef4444',
  },
  pillText: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  textActive: {
    color: '#10b981',
  },
  textPaused: {
    color: '#ef4444',
  },
  heroTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#f8fafc',
    marginBottom: 4,
  },
  heroDesc: {
    fontSize: 13,
    color: '#94a3b8',
    textAlign: 'center',
    lineHeight: 18,
    marginBottom: 16,
  },
  powerBtn: {
    width: '100%',
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
  },
  powerBtnStart: {
    backgroundColor: '#10b981',
  },
  powerBtnStop: {
    backgroundColor: '#ef4444',
  },
  powerBtnText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
  },
  sectionCard: {
    backgroundColor: '#131c2e',
    borderRadius: 14,
    padding: 16,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: '#1e293b',
  },
  rowBetween: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  cardTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#f8fafc',
  },
  cardSub: {
    fontSize: 12,
    color: '#94a3b8',
    marginTop: 2,
    marginBottom: 10,
  },
  chipsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 6,
  },
  countryChip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: '#0f172a',
    borderWidth: 1,
    borderColor: '#1e293b',
  },
  countryChipSelected: {
    backgroundColor: 'rgba(59, 130, 246, 0.2)',
    borderColor: '#3b82f6',
  },
  chipText: {
    fontSize: 13,
    color: '#94a3b8',
    fontWeight: '500',
  },
  chipTextSelected: {
    color: '#f8fafc',
    fontWeight: '700',
  },
  addRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 10,
  },
  input: {
    flex: 1,
    backgroundColor: '#0f172a',
    borderWidth: 1,
    borderColor: '#1e293b',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    color: '#fff',
    fontSize: 14,
  },
  addBtn: {
    backgroundColor: '#3b82f6',
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addBtnText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 14,
  },
  productBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(59, 130, 246, 0.15)',
    borderWidth: 1,
    borderColor: 'rgba(59, 130, 246, 0.3)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 6,
  },
  productBadgeText: {
    color: '#e2e8f0',
    fontSize: 13,
  },
  removeX: {
    color: '#ef4444',
    fontSize: 16,
    fontWeight: 'bold',
    paddingHorizontal: 4,
  },
});
