import React from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { PRIORITY_COLORS, useTheme } from '../context/ThemeContext';

/** Componentes visuales reutilizables de EasyLife. */

export const PRIORITIES = [
  { value: 'baja', label: 'Baja' },
  { value: 'media', label: 'Media' },
  { value: 'alta', label: 'Alta' },
  { value: 'urgente', label: 'Urgente' },
];

export const PALETTE = ['#4F46E5', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#8B5CF6', '#14B8A6', '#F97316', '#64748B'];

export function Card({ children, style, onPress }) {
  const { colors } = useTheme();
  const Wrapper = onPress ? Pressable : View;
  return (
    <Wrapper
      onPress={onPress}
      style={({ pressed } = {}) => [
        styles.card,
        { backgroundColor: colors.card, borderColor: colors.border, opacity: pressed ? 0.85 : 1 },
        style,
      ]}
    >
      {children}
    </Wrapper>
  );
}

export function Button({ title, onPress, variant = 'primary', icon, loading, disabled, style, small }) {
  const { colors } = useTheme();
  const palette = {
    primary: { bg: colors.primary, fg: colors.primaryText, border: colors.primary },
    outline: { bg: 'transparent', fg: colors.primary, border: colors.primary },
    danger: { bg: colors.danger, fg: '#fff', border: colors.danger },
    ghost: { bg: colors.primarySoft, fg: colors.primary, border: colors.primarySoft },
  }[variant];

  return (
    <Pressable
      onPress={onPress}
      disabled={disabled || loading}
      style={({ pressed }) => [
        styles.button,
        small && styles.buttonSmall,
        { backgroundColor: palette.bg, borderColor: palette.border, opacity: disabled ? 0.5 : pressed ? 0.8 : 1 },
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={palette.fg} />
      ) : (
        <>
          {icon && <MaterialCommunityIcons name={icon} size={small ? 16 : 20} color={palette.fg} />}
          <Text style={[styles.buttonText, small && { fontSize: 14 }, { color: palette.fg }]}>{title}</Text>
        </>
      )}
    </Pressable>
  );
}

export function Input({ label, error, icon, style, multiline, ...props }) {
  const { colors } = useTheme();
  return (
    <View style={[{ marginBottom: 14 }, style]}>
      {label && <Text style={[styles.label, { color: colors.textSecondary }]}>{label}</Text>}
      <View
        style={[
          styles.inputBox,
          { backgroundColor: colors.input, borderColor: error ? colors.danger : colors.border },
          multiline && { alignItems: 'flex-start' },
        ]}
      >
        {icon && (
          <MaterialCommunityIcons
            name={icon}
            size={20}
            color={colors.textSecondary}
            style={{ marginRight: 8, marginTop: multiline ? 12 : 0 }}
          />
        )}
        <TextInput
          placeholderTextColor={colors.textSecondary}
          style={[styles.input, { color: colors.text }, multiline && { minHeight: 90, textAlignVertical: 'top', paddingTop: 12 }]}
          multiline={multiline}
          {...props}
        />
      </View>
      {error ? <Text style={[styles.error, { color: colors.danger }]}>{error}</Text> : null}
    </View>
  );
}

export function Chip({ label, selected, onPress, color, icon }) {
  const { colors } = useTheme();
  const c = color || colors.primary;
  return (
    <Pressable
      onPress={onPress}
      style={[styles.chip, { borderColor: selected ? c : colors.border, backgroundColor: selected ? c + '22' : colors.card }]}
    >
      {icon && <MaterialCommunityIcons name={icon} size={14} color={selected ? c : colors.textSecondary} />}
      {!icon && color && <View style={[styles.dot, { backgroundColor: c }]} />}
      <Text style={{ color: selected ? c : colors.text, fontWeight: selected ? '700' : '500', fontSize: 13 }}>{label}</Text>
    </Pressable>
  );
}

export function PriorityBadge({ priority }) {
  const color = PRIORITY_COLORS[priority] || PRIORITY_COLORS.media;
  return (
    <View style={[styles.badge, { backgroundColor: color + '22' }]}>
      <MaterialCommunityIcons name="flag" size={12} color={color} />
      <Text style={{ color, fontSize: 11, fontWeight: '700', textTransform: 'capitalize' }}>{priority}</Text>
    </View>
  );
}

export function ProgressBar({ value = 0, color, height = 8 }) {
  const { colors } = useTheme();
  return (
    <View style={{ height, borderRadius: height, backgroundColor: colors.border, overflow: 'hidden' }}>
      <View style={{ width: `${Math.min(100, Math.max(0, value))}%`, height: '100%', backgroundColor: color || colors.primary }} />
    </View>
  );
}

export function EmptyState({ icon = 'clipboard-text-outline', title, subtitle, action }) {
  const { colors } = useTheme();
  return (
    <View style={styles.empty}>
      <MaterialCommunityIcons name={icon} size={56} color={colors.border} />
      <Text style={[styles.emptyTitle, { color: colors.text }]}>{title}</Text>
      {subtitle && <Text style={{ color: colors.textSecondary, textAlign: 'center' }}>{subtitle}</Text>}
      {action}
    </View>
  );
}

export function SectionTitle({ children, right }) {
  const { colors } = useTheme();
  return (
    <View style={styles.sectionRow}>
      <Text style={[styles.sectionTitle, { color: colors.text }]}>{children}</Text>
      {right}
    </View>
  );
}

export function ColorPicker({ value, onChange }) {
  return (
    <View style={styles.colorRow}>
      {PALETTE.map((c) => (
        <Pressable
          key={c}
          onPress={() => onChange(c)}
          style={[styles.colorSwatch, { backgroundColor: c, borderWidth: value === c ? 3 : 0 }]}
        >
          {value === c && <MaterialCommunityIcons name="check" size={16} color="#fff" />}
        </Pressable>
      ))}
    </View>
  );
}

export function Fab({ onPress, icon = 'plus' }) {
  const { colors } = useTheme();
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.fab, { backgroundColor: colors.primary, opacity: pressed ? 0.85 : 1 }]}>
      <MaterialCommunityIcons name={icon} size={28} color={colors.primaryText} />
    </Pressable>
  );
}

export function Label({ children }) {
  const { colors } = useTheme();
  return <Text style={[styles.label, { color: colors.textSecondary }]}>{children}</Text>;
}

export function Row({ icon, label, value, onPress, color, right }) {
  const { colors } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      style={({ pressed }) => [styles.row, { borderBottomColor: colors.border, opacity: pressed ? 0.7 : 1 }]}
    >
      {icon && <MaterialCommunityIcons name={icon} size={22} color={color || colors.primary} style={{ width: 30 }} />}
      <Text style={{ flex: 1, color: color || colors.text, fontSize: 15 }}>{label}</Text>
      {value ? <Text style={{ color: colors.textSecondary, marginRight: 6 }}>{value}</Text> : null}
      {right}
      {onPress && !right && <MaterialCommunityIcons name="chevron-right" size={20} color={colors.textSecondary} />}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 14, borderWidth: 1, padding: 14, marginBottom: 10 },
  button: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    justifyContent: 'center',
    height: 50,
    borderRadius: 12,
    borderWidth: 1.5,
    paddingHorizontal: 18,
  },
  buttonSmall: { height: 38, borderRadius: 10, paddingHorizontal: 12 },
  buttonText: { fontSize: 16, fontWeight: '700' },
  label: { fontSize: 13, fontWeight: '600', marginBottom: 6 },
  inputBox: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderRadius: 12, paddingHorizontal: 12 },
  input: { flex: 1, fontSize: 16, minHeight: 48 },
  error: { fontSize: 12, marginTop: 4 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 20,
    borderWidth: 1.5,
    marginRight: 8,
    marginBottom: 8,
  },
  dot: { width: 8, height: 8, borderRadius: 4 },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 3, paddingHorizontal: 7, paddingVertical: 2, borderRadius: 8 },
  empty: { alignItems: 'center', padding: 32, gap: 8 },
  emptyTitle: { fontSize: 17, fontWeight: '700' },
  sectionRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 18, marginBottom: 10 },
  sectionTitle: { fontSize: 18, fontWeight: '800' },
  colorRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginBottom: 14 },
  colorSwatch: {
    width: 34,
    height: 34,
    borderRadius: 17,
    borderColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 2,
  },
  fab: {
    position: 'absolute',
    right: 20,
    bottom: 20,
    width: 60,
    height: 60,
    borderRadius: 30,
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOpacity: 0.25,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 3 },
  },
  row: { flexDirection: 'row', alignItems: 'center', paddingVertical: 14, borderBottomWidth: StyleSheet.hairlineWidth },
});
