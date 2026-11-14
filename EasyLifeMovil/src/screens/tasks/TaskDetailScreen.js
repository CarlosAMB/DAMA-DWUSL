import React, { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Button, Card, EmptyState, PriorityBadge, ProgressBar, SectionTitle } from '../../components/ui';
import { REMINDER_OPTIONS } from '../../components/ReminderPicker';
import {
  deleteRecord,
  deleteTask,
  getEvent,
  getRemindersOf,
  getSubtasks,
  getTags,
  getTask,
  saveRecord,
  toggleTask,
} from '../../db/repository';
import { formatDateTime, isOverdue, relativeDue } from '../../utils/dates';

const STATUS_LABEL = { pendiente: 'Pendiente', en_progreso: 'En progreso', completada: 'Completada' };

export default function TaskDetailScreen({ route, navigation }) {
  const { id } = route.params;
  const db = useSQLiteContext();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const [newSub, setNewSub] = useState('');

  const { data } = useLocalQuery(
    async (database) => {
      const task = await getTask(database, id);
      return {
        task,
        subtasks: await getSubtasks(database, id),
        tags: await getTags(database),
        reminders: await getRemindersOf(database, 'task', id),
        event: task?.event_id ? await getEvent(database, task.event_id) : null,
      };
    },
    [id]
  );

  if (!data) return null;
  if (!data.task || data.task.deleted_at) return <EmptyState icon="file-remove-outline" title="Tarea no encontrada" />;
  const { task, subtasks } = data;
  const done = task.status === 'completada';
  const progress = subtasks.length ? Math.round((subtasks.filter((s) => s.is_done).length * 100) / subtasks.length) : done ? 100 : 0;

  const toggleSub = async (s) => {
    await saveRecord(db, 'subtasks', { id: s.id, is_done: !s.is_done });
    notifyChange();
  };
  const removeSub = async (s) => {
    await deleteRecord(db, 'subtasks', s.id);
    notifyChange();
  };
  const addSub = async () => {
    if (!newSub.trim()) return;
    await saveRecord(db, 'subtasks', { task_id: id, title: newSub.trim(), is_done: false, position: subtasks.length });
    setNewSub('');
    notifyChange();
  };

  const setInProgress = async () => {
    await saveRecord(db, 'tasks', { id, status: 'en_progreso', completed_at: null });
    notifyChange();
  };

  const remove = () =>
    Alert.alert('Eliminar tarea', `¿Eliminar "${task.title}" y sus subtareas?`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          await deleteTask(db, id);
          notifyChange();
          navigation.goBack();
        },
      },
    ]);

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      <Card>
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 12 }}>
          <Pressable
            onPress={async () => {
              await toggleTask(db, task);
              notifyChange();
            }}
            hitSlop={10}
          >
            <MaterialCommunityIcons
              name={done ? 'checkbox-marked-circle' : 'checkbox-blank-circle-outline'}
              size={32}
              color={done ? colors.success : colors.textSecondary}
            />
          </Pressable>
          <View style={{ flex: 1 }}>
            <Text style={[styles.title, { color: colors.text, textDecorationLine: done ? 'line-through' : 'none' }]}>{task.title}</Text>
            <View style={{ flexDirection: 'row', gap: 8, marginTop: 6, flexWrap: 'wrap', alignItems: 'center' }}>
              <PriorityBadge priority={task.priority} />
              <Text
                style={{
                  color: done ? colors.success : task.status === 'en_progreso' ? colors.info : colors.textSecondary,
                  fontWeight: '700',
                  fontSize: 12,
                }}
              >
                {STATUS_LABEL[task.status]}
              </Text>
            </View>
          </View>
        </View>

        <View style={{ marginTop: 14, gap: 8 }}>
          {task.due_date && (
            <Info
              icon="calendar-clock"
              colors={colors}
              color={isOverdue(task) ? colors.danger : undefined}
              text={`${formatDateTime(task.due_date)} · ${relativeDue(task.due_date)}`}
            />
          )}
          {task.project_name && (
            <Pressable onPress={() => navigation.navigate('ProjectDetail', { id: task.project_id })}>
              <Info icon="folder-outline" colors={colors} text={task.project_name} color={task.project_color} />
            </Pressable>
          )}
          {data.event && !data.event.deleted_at && (
            <Pressable onPress={() => navigation.navigate('EventDetail', { id: data.event.id })}>
              <Info icon="calendar-star" colors={colors} text={`Evento: ${data.event.title}`} />
            </Pressable>
          )}
          {data.reminders.length > 0 && (
            <Info
              icon="bell-outline"
              colors={colors}
              text={
                data.reminders
                  .map((r) => REMINDER_OPTIONS.find((o) => o.value === r.minutes_before)?.label || `${r.minutes_before} min`)
                  .join(', ') + ' antes'
              }
            />
          )}
          {task.completed_at && (
            <Info icon="check-all" colors={colors} color={colors.success} text={`Completada el ${formatDateTime(task.completed_at)}`} />
          )}
        </View>

        {task.tag_ids.length > 0 && (
          <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap', marginTop: 10 }}>
            {task.tag_ids.map((tid) => {
              const t = data.tags.find((x) => x.id === tid);
              return t ? (
                <Text key={tid} style={{ color: t.color, fontWeight: '700' }}>
                  #{t.name}
                </Text>
              ) : null;
            })}
          </View>
        )}

        {task.description ? <Text style={{ color: colors.text, marginTop: 12, lineHeight: 21 }}>{task.description}</Text> : null}
        {task.sync_error ? <Text style={{ color: colors.danger, marginTop: 8 }}>Error al sincronizar: {task.sync_error}</Text> : null}
      </Card>

      <SectionTitle right={<Text style={{ color: colors.textSecondary, fontWeight: '700' }}>{progress}%</Text>}>Progreso</SectionTitle>
      <ProgressBar value={progress} color={done ? colors.success : colors.primary} />

      <SectionTitle>Subtareas</SectionTitle>
      {subtasks.length === 0 && (
        <Text style={{ color: colors.textSecondary, marginBottom: 8 }}>Divide la tarea en pasos más pequeños.</Text>
      )}
      {subtasks.map((s) => (
        <View key={s.id} style={[styles.subRow, { borderBottomColor: colors.border }]}>
          <Pressable onPress={() => toggleSub(s)} hitSlop={8}>
            <MaterialCommunityIcons
              name={s.is_done ? 'checkbox-marked' : 'checkbox-blank-outline'}
              size={24}
              color={s.is_done ? colors.success : colors.textSecondary}
            />
          </Pressable>
          <Text
            style={{
              flex: 1,
              color: s.is_done ? colors.textSecondary : colors.text,
              textDecorationLine: s.is_done ? 'line-through' : 'none',
            }}
          >
            {s.title}
          </Text>
          <Pressable onPress={() => removeSub(s)} hitSlop={8}>
            <MaterialCommunityIcons name="trash-can-outline" size={20} color={colors.textSecondary} />
          </Pressable>
        </View>
      ))}
      <View style={[styles.addRow, { borderColor: colors.border, backgroundColor: colors.card }]}>
        <MaterialCommunityIcons name="plus" size={20} color={colors.primary} />
        <TextInput
          value={newSub}
          onChangeText={setNewSub}
          onSubmitEditing={addSub}
          placeholder="Agregar subtarea"
          returnKeyType="done"
          placeholderTextColor={colors.textSecondary}
          style={{ flex: 1, color: colors.text, height: 44 }}
        />
        {newSub ? (
          <Pressable onPress={addSub}>
            <Text style={{ color: colors.primary, fontWeight: '700' }}>Agregar</Text>
          </Pressable>
        ) : null}
      </View>

      {task.status === 'pendiente' && (
        <Button title="Marcar en progreso" icon="progress-clock" variant="ghost" onPress={setInProgress} style={{ marginTop: 20 }} />
      )}
      <View style={{ flexDirection: 'row', gap: 10, marginTop: 12 }}>
        <Button title="Editar" icon="pencil" style={{ flex: 1 }} onPress={() => navigation.navigate('TaskForm', { id })} />
        <Button title="Eliminar" icon="delete" variant="danger" style={{ flex: 1 }} onPress={remove} />
      </View>
    </ScrollView>
  );
}

const Info = ({ icon, text, colors, color }) => (
  <View style={{ flexDirection: 'row', gap: 8, alignItems: 'center' }}>
    <MaterialCommunityIcons name={icon} size={18} color={color || colors.primary} />
    <Text style={{ color: color || colors.text, flex: 1 }}>{text}</Text>
  </View>
);

const styles = StyleSheet.create({
  title: { fontSize: 20, fontWeight: '800' },
  subRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  addRow: { flexDirection: 'row', alignItems: 'center', gap: 8, borderWidth: 1, borderRadius: 12, paddingHorizontal: 12, marginTop: 10 },
});
