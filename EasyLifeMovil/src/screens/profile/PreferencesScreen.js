import React, { useState } from 'react';
import { Alert, ScrollView, Switch, Text, View } from 'react-native';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Card, Chip, Label, Row } from '../../components/ui';
import { REMINDER_OPTIONS } from '../../components/ReminderPicker';
import { rescheduleAll, sendTestNotification, setupNotifications } from '../../services/notifications';
import { setMeta } from '../../db/repository';

const VIEWS = [
  { v: 'day', l: 'Día' },
  { v: 'week', l: 'Semana' },
  { v: 'month', l: 'Mes' },
  { v: 'year', l: 'Año' },
];
const SUMMARY_TIMES = ['06:00', '07:00', '08:00', '09:00', '20:00', '21:00'];

/** Preferencias de organizacion (se guardan en el servidor y se aplican en la app). */
export default function PreferencesScreen() {
  const db = useSQLiteContext();
  const { user, updateProfile, setUser } = useAuth();
  const { colors } = useTheme();
  const [prefs, setPrefs] = useState({ ...user.preferences });
  const [saving, setSaving] = useState(false);
  const set = (k) => (v) => setPrefs({ ...prefs, [k]: v });

  const save = async () => {
    setSaving(true);
    try {
      await updateProfile({ preferences: prefs });
      Alert.alert('Preferencias guardadas');
    } catch {
      // Sin conexion: se aplican localmente y se envian en la proxima sincronizacion
      await setUser({ ...user, preferences: prefs });
      await setMeta(db, 'pending_preferences', JSON.stringify(prefs));
      Alert.alert('Guardado en el dispositivo', 'Sin conexión: las preferencias se enviarán a tu cuenta al sincronizar.');
    } finally {
      await rescheduleAll(db, prefs);
      setSaving(false);
    }
  };

  const test = async () => {
    if (await setupNotifications()) await sendTestNotification();
    else Alert.alert('Permiso denegado', 'Activa las notificaciones de EasyLife en los ajustes del teléfono.');
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      <Card>
        <Label>Vista predeterminada del calendario</Label>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
          {VIEWS.map((x) => (
            <Chip key={x.v} label={x.l} selected={prefs.calendar_view === x.v} onPress={() => set('calendar_view')(x.v)} />
          ))}
        </View>
        <Label>La semana inicia en</Label>
        <View style={{ flexDirection: 'row' }}>
          <Chip label="Lunes" selected={prefs.week_starts_on === 1} onPress={() => set('week_starts_on')(1)} />
          <Chip label="Domingo" selected={prefs.week_starts_on === 0} onPress={() => set('week_starts_on')(0)} />
        </View>
      </Card>

      <Card>
        <Row
          icon="bell-ring-outline"
          label="Notificaciones"
          right={
            <Switch
              value={prefs.notifications_enabled !== false}
              onValueChange={set('notifications_enabled')}
              trackColor={{ true: colors.primary }}
            />
          }
        />
        <Label>Recordatorio predeterminado para eventos nuevos</Label>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
          {REMINDER_OPTIONS.map((o) => (
            <Chip
              key={o.value}
              label={o.label}
              selected={prefs.default_reminder_minutes === o.value}
              onPress={() => set('default_reminder_minutes')(o.value)}
            />
          ))}
        </View>
        <Row
          icon="weather-sunset-up"
          label="Resumen diario de tareas"
          right={
            <Switch value={prefs.daily_summary !== false} onValueChange={set('daily_summary')} trackColor={{ true: colors.primary }} />
          }
        />
        {prefs.daily_summary !== false && (
          <>
            <Label>Hora del resumen</Label>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
              {SUMMARY_TIMES.map((t) => (
                <Chip key={t} label={t} selected={prefs.daily_summary_time === t} onPress={() => set('daily_summary_time')(t)} />
              ))}
            </View>
          </>
        )}
        <Button title="Enviar notificación de prueba" icon="bell-outline" variant="ghost" small onPress={test} style={{ marginTop: 6 }} />
      </Card>

      <Text style={{ color: colors.textSecondary, fontSize: 12, marginBottom: 12 }}>
        Los recordatorios son notificaciones locales: funcionan aunque no tengas internet.
      </Text>
      <Button title="Guardar preferencias" icon="content-save" onPress={save} loading={saving} />
    </ScrollView>
  );
}
