import React, { useEffect, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../../context/AuthContext';
import { useSync } from '../../context/SyncContext';
import { PRIORITY_COLORS, useTheme } from '../../context/ThemeContext';
import { Button, Chip, Input, Label, PRIORITIES } from '../../components/ui';
import DateTimeField from '../../components/DateTimeField';
import ReminderPicker from '../../components/ReminderPicker';
import {
  getEventsBetween,
  getProjects,
  getRemindersOf,
  getSubtasks,
  getTags,
  getTask,
  saveRecord,
  setReminders,
} from '../../db/repository';
import { addDays, formatShortDate, nowIso } from '../../utils/dates';

const STATUSES = [
  { value: 'pendiente', label: 'Pendiente' },
  { value: 'en_progreso', label: 'En progreso' },
  { value: 'completada', label: 'Completada' },
];

/** Crear / editar tarea. params: { id?, projectId?, eventId?, dueDate? } */
export default function TaskFormScreen({ route, navigation }) {
  const db = useSQLiteContext();
  const { user } = useAuth();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const p = route.params || {};
  const editingId = p.id;

  const [form, setForm] = useState({
    title: '',
    description: '',
    priority: 'media',
    status: 'pendiente',
    due_date: p.dueDate || null,
    project_id: p.projectId || null,
    event_id: p.eventId || null,
    tag_ids: [],
  });
  const [subtasks, setSubtasks] = useState([]); // solo al crear
  const [newSub, setNewSub] = useState('');
  const [reminders, setRemindersState] = useState([]);
  const [options, setOptions] = useState({ projects: [], tags: [], events: [] });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    navigation.setOptions({ title: editingId ? 'Editar tarea' : 'Nueva tarea' });
    (async () => {
      const now = new Date();
      setOptions({
        projects: (await getProjects(db)).filter((x) => x.status !== 'archivado'),
        tags: await getTags(db),
        events: await getEventsBetween(db, addDays(now, -1).toISOString(), addDays(now, 60).toISOString()),
      });
      if (editingId) {
        const t = await getTask(db, editingId);
        if (t) {
          setForm({
            title: t.title,
            description: t.description || '',
            priority: t.priority,
            status: t.status,
            due_date: t.due_date,
            project_id: t.project_id,
            event_id: t.event_id,
            tag_ids: t.tag_ids,
          });
          setRemindersState((await getRemindersOf(db, 'task', editingId)).map((r) => r.minutes_before ?? 0));
        }
      }
    })();
  }, [db, editingId, navigation]);

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));
  const toggleTag = (id) => set('tag_ids')(form.tag_ids.includes(id) ? form.tag_ids.filter((x) => x !== id) : [...form.tag_ids, id]);

  const addSub = () => {
    if (!newSub.trim()) return;
    setSubtasks([...subtasks, newSub.trim()]);
    setNewSub('');
  };

  const save = async () => {
    if (!form.title.trim()) return setError('El título es obligatorio.');
    if (reminders.length && !form.due_date) return setError('Para usar recordatorios asigna una fecha límite.');
    setSaving(true);
    try {
      const data = { ...form, title: form.title.trim(), completed_at: form.status === 'completada' ? nowIso() : null };
      const id = await saveRecord(db, 'tasks', editingId ? { ...data, id: editingId } : data);
      for (let i = 0; i < subtasks.length; i++) {
        await saveRecord(db, 'subtasks', { task_id: id, title: subtasks[i], is_done: false, position: i });
      }
      await setReminders(db, 'task', id, form.due_date, form.status === 'completada' ? [] : reminders);
      notifyChange();
      navigation.goBack();
    } catch (e) {
      console.warn(e);
      Alert.alert('Error', 'No se pudo guardar la tarea.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
        <Input label="Título *" value={form.title} onChangeText={set('title')} placeholder="¿Qué necesitas hacer?" />
        <Input
          label="Descripción"
          value={form.description}
          onChangeText={set('description')}
          multiline
          placeholder="Detalles (se guardan encriptados)"
        />

        <Label>Prioridad</Label>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', marginBottom: 6 }}>
          {PRIORITIES.map((x) => (
            <Chip
              key={x.value}
              label={x.label}
              icon="flag"
              color={PRIORITY_COLORS[x.value]}
              selected={form.priority === x.value}
              onPress={() => set('priority')(x.value)}
            />
          ))}
        </View>

        {editingId && (
          <>
            <Label>Estado</Label>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', marginBottom: 6 }}>
              {STATUSES.map((x) => (
                <Chip key={x.value} label={x.label} selected={form.status === x.value} onPress={() => set('status')(x.value)} />
              ))}
            </View>
          </>
        )}

        <DateTimeField label="Fecha límite" value={form.due_date} onChange={set('due_date')} clearable />

        <Label>Proyecto</Label>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 8 }}>
          <Chip label="Sin proyecto" selected={!form.project_id} onPress={() => set('project_id')(null)} />
          {options.projects.map((x) => (
            <Chip key={x.id} label={x.name} color={x.color} selected={form.project_id === x.id} onPress={() => set('project_id')(x.id)} />
          ))}
        </ScrollView>

        <Label>Asignar a evento</Label>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 8 }}>
          <Chip label="Ninguno" selected={!form.event_id} onPress={() => set('event_id')(null)} />
          {options.events.map((x) => (
            <Chip
              key={x.id}
              label={`${x.title} · ${formatShortDate(x.start_at)}`}
              color={x.type_color}
              selected={form.event_id === x.id}
              onPress={() => set('event_id')(x.id)}
            />
          ))}
        </ScrollView>

        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Label>Etiquetas</Label>
          <Pressable onPress={() => navigation.navigate('Tags')}>
            <Text style={{ color: colors.primary, fontWeight: '600', fontSize: 13 }}>Administrar</Text>
          </Pressable>
        </View>
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', marginBottom: 8 }}>
          {options.tags.length === 0 && <Text style={{ color: colors.textSecondary, marginBottom: 8 }}>Aún no tienes etiquetas.</Text>}
          {options.tags.map((t) => (
            <Chip key={t.id} label={'#' + t.name} color={t.color} selected={form.tag_ids.includes(t.id)} onPress={() => toggleTag(t.id)} />
          ))}
        </View>

        {!editingId && (
          <>
            <Label>Subtareas</Label>
            {subtasks.map((s, i) => (
              <View key={i} style={{ flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 6 }}>
                <MaterialCommunityIcons name="checkbox-blank-outline" size={20} color={colors.textSecondary} />
                <Text style={{ flex: 1, color: colors.text }}>{s}</Text>
                <Pressable onPress={() => setSubtasks(subtasks.filter((_, j) => j !== i))}>
                  <MaterialCommunityIcons name="close" size={18} color={colors.textSecondary} />
                </Pressable>
              </View>
            ))}
            <View style={{ flexDirection: 'row', gap: 8, alignItems: 'flex-start' }}>
              <Input
                style={{ flex: 1 }}
                value={newSub}
                onChangeText={setNewSub}
                placeholder="Agregar paso"
                onSubmitEditing={addSub}
                returnKeyType="done"
              />
              <Button title="" icon="plus" small onPress={addSub} style={{ height: 48, marginTop: 0 }} />
            </View>
          </>
        )}

        <ReminderPicker
          value={reminders}
          onChange={setRemindersState}
          label={`Recordatorios antes de la fecha límite${user?.preferences?.notifications_enabled === false ? ' (notificaciones desactivadas)' : ''}`}
        />

        {error ? <Text style={{ color: colors.danger, marginBottom: 10 }}>{error}</Text> : null}
        <Button title="Guardar tarea" icon="content-save" onPress={save} loading={saving} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
