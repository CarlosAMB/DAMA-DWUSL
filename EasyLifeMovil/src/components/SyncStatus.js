import React from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSync } from '../context/SyncContext';
import { useTheme } from '../context/ThemeContext';

/** Indicador en la cabecera: en linea / sin conexion / sincronizando / cambios pendientes. */
export default function SyncStatus() {
  const { isOnline, syncing, pending, syncNow, lastError, realtime } = useSync();
  const { colors } = useTheme();

  let icon = realtime ? 'cloud-check' : 'cloud-check-outline'; // relleno = recibiendo cambios en tiempo real (Firestore)
  let color = colors.success;
  let text = '';
  if (!isOnline) {
    icon = 'cloud-off-outline';
    color = colors.warning;
    text = 'Sin conexión';
  } else if (lastError) {
    icon = 'cloud-alert';
    color = colors.danger;
  } else if (pending > 0) {
    icon = 'cloud-upload-outline';
    color = colors.info;
    text = String(pending);
  }

  return (
    <Pressable onPress={() => syncNow({ silent: true })} style={styles.box} hitSlop={8}>
      {syncing ? <ActivityIndicator size="small" color={colors.primary} /> : <MaterialCommunityIcons name={icon} size={22} color={color} />}
      {!!text && !syncing && <Text style={{ color, fontSize: 12, fontWeight: '700' }}>{text}</Text>}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  box: { flexDirection: 'row', alignItems: 'center', gap: 4, marginRight: 14 },
});
