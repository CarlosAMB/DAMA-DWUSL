import React, { useEffect, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, ScrollView, Switch, Text, View } from 'react-native';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../../context/AuthContext';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Chip, Input, Label } from '../../components/ui';
import DateTimeField from '../../components/DateTimeField';
import ReminderPicker from '../../components/ReminderPicker';
import { getEvent, getEventTypes, getRemindersOf, saveRecord, setReminders } from '../../db/repository';

/** Crear / editar evento. params: { id?, date? } */
export default function EventFormScreen({ route, navigation }) {
  const db = useSQLiteContext();
  const { user } = useAuth();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const editingId = route.params?.id;

  const initialStart = () => {
    const d = route.params?.date ? new Date(route.params.date) : new Date();
    d.setMinutes(0, 0, 0);
    d.setHours(route.params?.date ? 9 : d.getHours() + 1);
    return d;
  };

  const [types, setTypes] = useState([]);
  const [form, setForm] = useState(() => {
    const s = initialStart();
    return {
      title: '',
      description: '',
      location: '',
      event_type_id: null,
      all_day: false,
      start_at: s.toISOString(),
      end_at: new Date(s.getTime() + 3600000).toISOString(),
    };
  });
  const [reminders, setRemindersState] = useState([user?.preferences?.default_reminder_minutes ?? 15]);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    navigation.setOptions({ title: editingId ? 'Editar evento' : 'Nuevo evento' });
    (async () => {
      setTypes(await getEventTypes(db));
      if (editingId) {
        const e = await getEvent(db, editingId);
        if (e) {
          setForm({
            title: e.title,
            description: e.description || '',
            location: e.location || '',
            event_type_id: e.event_type_id,
            all_day: e.all_day,
            start_at: e.start_at,
            end_at: e.end_at,
          });
          setRemindersState((await getRemindersOf(db, 'event', editingId)).map((r) => r.minutes_before ?? 0));
        }
      }
    })();
  }, [db, editingId, navigation]);

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  const onChangeStart = (iso) => {
    // Mantiene la duracion del evento al mover el inicio
    const duration = form.end_at ? new Date(form.end_at) - new Date(form.start_at) : 3600000;
    setForm((f) => ({
      ...f,
      start_at: iso,
      end_at: f.end_at ? new Date(new Date(iso).getTime() + Math.max(duration, 0)).toISOString() : null,
    }));
  };

  const save = async () => {
    if (!form.title.trim()) return setError('El título es obligatorio.');
    if (form.end_at && new Date(form.end_at) < new Date(form.start_at)) return setError('La fecha de fin debe ser posterior al inicio.');

    setSaving(true);
    try {
      const data = { ...form, title: form.title.trim() };
      if (form.all_day) {
        const s = new Date(form.start_at);
        s.setHours(0, 0, 0, 0);
        const e = new Date(form.end_at || form.start_at);
        e.setHours(23, 59, 0, 0);
        data.start_at = s.toISOString();
        data.end_at = e.toISOString();
      }
      const id = await saveRecord(db, 'events', editingId ? { ...data, id: editingId } : data);
      // Para eventos de todo el dia el recordatorio se calcula desde las 8:00
      const base = form.all_day ? new Date(new Date(data.start_at).setHours(8, 0, 0, 0)).toISOString() : data.start_at;
      await setReminders(db, 'event', id, base, reminders);
      notifyChange();
      navigation.goBack();
    } catch (e) {
      Alert.alert('Error', 'No se pudo guardar el evento.');
      console.warn(e);
    } finally {
      setSaving(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
        <Input
          label="Título *"
          value={form.title}
          onChangeText={set('title')}
          placeholder="Ej. Reunión de equipo"
          error={error && !form.title.trim() ? error : ''}
        />

        <Label>Tipo de evento</Label>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', marginBottom: 8 }}>
          {types.map((t) => (
            <Chip
              key={t.id}
              label={t.name}
              color={t.color}
              selected={form.event_type_id === t.id}
              onPress={() => set('event_type_id')(form.event_type_id === t.id ? null : t.id)}
            />
          ))}
          {types.length === 0 && <Text style={{ color: colors.textSecondary }}>Sincroniza para cargar los tipos de evento.</Text>}
        </View>

        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
          <Text style={{ color: colors.text, fontSize: 15, fontWeight: '600' }}>Todo el día</Text>
          <Switch value={form.all_day} onValueChange={set('all_day')} trackColor={{ true: colors.primary }} />
        </View>

        <DateTimeField label="Inicio" value={form.start_at} onChange={onChangeStart} mode={form.all_day ? 'date' : 'datetime'} />
        <DateTimeField label="Fin" value={form.end_at} onChange={set('end_at')} mode={form.all_day ? 'date' : 'datetime'} clearable />

        <Input label="Ubicación" icon="map-marker-outline" value={form.location} onChangeText={set('location')} placeholder="Opcional" />
        <Input
          label="Descripción"
          value={form.description}
          onChangeText={set('description')}
          multiline
          placeholder="Notas del evento (se guardan encriptadas)"
        />

        <ReminderPicker value={reminders} onChange={setRemindersState} />

        {error ? <Text style={{ color: colors.danger, marginBottom: 10 }}>{error}</Text> : null}
        <Button title="Guardar evento" icon="content-save" onPress={save} loading={saving} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
