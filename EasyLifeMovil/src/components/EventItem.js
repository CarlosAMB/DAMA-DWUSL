import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import { formatShortDate, formatTime } from '../utils/dates';

/** Tarjeta de evento con el color e icono de su tipo. */
export default function EventItem({ event, onPress, showDate }) {
  const { colors } = useTheme();
  const color = event.type_color || colors.primary;

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.item, { backgroundColor: colors.card, borderColor: colors.border, opacity: pressed ? 0.85 : 1 }]}
    >
      <View style={[styles.bar, { backgroundColor: color }]} />
      <View style={[styles.icon, { backgroundColor: color + '22' }]}>
        <MaterialCommunityIcons name={event.type_icon || 'calendar'} size={22} color={color} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.title, { color: colors.text }]} numberOfLines={1}>
          {event.title}
        </Text>
        <Text style={{ color: colors.textSecondary, fontSize: 13 }}>
          {showDate ? `${formatShortDate(event.start_at)} · ` : ''}
          {event.all_day ? 'Todo el día' : `${formatTime(event.start_at)}${event.end_at ? ' - ' + formatTime(event.end_at) : ''}`}
        </Text>
        {event.location ? (
          <Text style={{ color: colors.textSecondary, fontSize: 12 }} numberOfLines={1}>
            <MaterialCommunityIcons name="map-marker" size={12} /> {event.location}
          </Text>
        ) : null}
      </View>
      {event.type_name && <Text style={{ color, fontSize: 11, fontWeight: '700' }}>{event.type_name}</Text>}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 12,
    paddingLeft: 16,
    borderRadius: 14,
    borderWidth: 1,
    marginBottom: 8,
    overflow: 'hidden',
  },
  bar: { position: 'absolute', left: 0, top: 0, bottom: 0, width: 5 },
  icon: { width: 42, height: 42, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 15, fontWeight: '700' },
});
