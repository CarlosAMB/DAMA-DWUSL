import React, { useState } from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import { formatDateTime } from '../utils/dates';

/**
 * Selector de fecha y hora. value / onChange trabajan con ISO (UTC).
 * mode: 'datetime' | 'date'
 */
export default function DateTimeField({ label, value, onChange, mode = 'datetime', clearable, placeholder = 'Sin fecha' }) {
  const { colors } = useTheme();
  const [step, setStep] = useState(null); // 'date' | 'time'
  const current = value ? new Date(value) : new Date();

  const onPick = (event, selected) => {
    if (event.type === 'dismissed' || !selected) {
      setStep(null);
      return;
    }

    if (step === 'date') {
      const d = new Date(current);
      d.setFullYear(selected.getFullYear(), selected.getMonth(), selected.getDate());
      if (!value && mode === 'datetime') d.setHours(9, 0, 0, 0);
      if (mode === 'date') d.setHours(0, 0, 0, 0);
      onChange(d.toISOString());
      setStep(mode === 'datetime' && Platform.OS === 'android' ? 'time' : null);
    } else {
      const d = new Date(current);
      d.setHours(selected.getHours(), selected.getMinutes(), 0, 0);
      onChange(d.toISOString());
      setStep(null);
    }
  };

  return (
    <View style={{ marginBottom: 14 }}>
      {label && <Text style={[styles.label, { color: colors.textSecondary }]}>{label}</Text>}
      <View style={[styles.box, { backgroundColor: colors.input, borderColor: colors.border }]}>
        <Pressable style={styles.touch} onPress={() => setStep('date')}>
          <MaterialCommunityIcons name="calendar-clock" size={20} color={colors.textSecondary} />
          <Text style={{ color: value ? colors.text : colors.textSecondary, fontSize: 16 }}>
            {value ? formatDateTime(value, mode === 'datetime') : placeholder}
          </Text>
        </Pressable>
        {value && mode === 'datetime' && (
          <Pressable onPress={() => setStep('time')} hitSlop={8} style={{ paddingHorizontal: 6 }}>
            <MaterialCommunityIcons name="clock-edit-outline" size={20} color={colors.primary} />
          </Pressable>
        )}
        {value && clearable && (
          <Pressable onPress={() => onChange(null)} hitSlop={8}>
            <MaterialCommunityIcons name="close-circle" size={20} color={colors.textSecondary} />
          </Pressable>
        )}
      </View>
      {step && <DateTimePicker value={current} mode={step} is24Hour display="default" onChange={onPick} />}
    </View>
  );
}

const styles = StyleSheet.create({
  label: { fontSize: 13, fontWeight: '600', marginBottom: 6 },
  box: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 12, paddingHorizontal: 12, minHeight: 48 },
  touch: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 12 },
});
