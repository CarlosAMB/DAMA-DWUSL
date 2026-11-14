import React from 'react';
import { View } from 'react-native';
import { Chip, Label } from './ui';

/** Opciones de recordatorio en "minutos antes". */
export const REMINDER_OPTIONS = [
  { value: 0, label: 'A la hora' },
  { value: 5, label: '5 min' },
  { value: 15, label: '15 min' },
  { value: 30, label: '30 min' },
  { value: 60, label: '1 hora' },
  { value: 1440, label: '1 día' },
  { value: 10080, label: '1 semana' },
];

/** Seleccion multiple de recordatorios. value: array de minutos. */
export default function ReminderPicker({ value = [], onChange, label = 'Recordatorios' }) {
  const toggle = (m) => onChange(value.includes(m) ? value.filter((x) => x !== m) : [...value, m].sort((a, b) => a - b));
  return (
    <View style={{ marginBottom: 8 }}>
      <Label>{label}</Label>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
        {REMINDER_OPTIONS.map((o) => (
          <Chip key={o.value} label={o.label} icon="bell-outline" selected={value.includes(o.value)} onPress={() => toggle(o.value)} />
        ))}
      </View>
    </View>
  );
}
