import React, { useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Button, Card, EmptyState, SectionTitle } from '../../components/ui';
import TaskItem from '../../components/TaskItem';
import { REMINDER_OPTIONS } from '../../components/ReminderPicker';
import { deleteEvent, getEvent, getRemindersOf, getTasks, saveRecord, toggleTask } from '../../db/repository';
import { formatDateTime, formatLongDate, formatTime } from '../../utils/dates';

export default function EventDetailScreen({ route, navigation }) {
  const { id } = route.params;
  const db = useSQLiteContext();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const [assignOpen, setAssignOpen] = useState(false);

  const { data } = useLocalQuery(
    async (database) => ({
      event: await getEvent(database, id),
      tasks: await getTasks(database, { eventId: id }),
      reminders: await getRemindersOf(database, 'event', id),
      available: (await getTasks(database, { status: 'pendientes' })).filter((t) => t.event_id !== id),
    }),
    [id]
  );

  if (!data) return null;
  if (!data.event || data.event.deleted_at) return <EmptyState icon="calendar-remove" title="Evento no encontrado" />;
  const { event } = data;
  const color = event.type_color || colors.primary;

  const remove = () =>
    Alert.alert('Eliminar evento', `¿Eliminar "${event.title}"? Las tareas asignadas se conservarán.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          await deleteEvent(db, id);
          notifyChange();
          navigation.goBack();
        },
      },
    ]);

  const assign = async (task) => {
    await saveRecord(db, 'tasks', { id: task.id, event_id: id });
    notifyChange();
    setAssignOpen(false);
  };
  const unassign = async (task) => {
    await saveRecord(db, 'tasks', { id: task.id, event_id: null });
    notifyChange();
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      <View style={[styles.header, { backgroundColor: color }]}>
        <MaterialCommunityIcons name={event.type_icon || 'calendar'} size={32} color="#fff" />
        <Text style={styles.title}>{event.title}</Text>
        {event.type_name && <Text style={styles.type}>{event.type_name}</Text>}
      </View>

      <Card>
        <InfoRow icon="calendar" text={formatLongDate(new Date(event.start_at))} colors={colors} />
        <InfoRow
          icon="clock-outline"
          colors={colors}
          text={event.all_day ? 'Todo el día' : `${formatTime(event.start_at)}${event.end_at ? ' - ' + formatDateTime(event.end_at) : ''}`}
        />
        {event.location ? <InfoRow icon="map-marker-outline" text={event.location} colors={colors} /> : null}
        {data.reminders.length > 0 && (
          <InfoRow
            icon="bell-outline"
            colors={colors}
            text={
              data.reminders
                .map((r) => REMINDER_OPTIONS.find((o) => o.value === r.minutes_before)?.label || `${r.minutes_before} min`)
                .join(', ') + ' antes'
            }
          />
        )}
        {event.description ? <Text style={{ color: colors.text, marginTop: 10, lineHeight: 21 }}>{event.description}</Text> : null}
        {event.sync_error ? <Text style={{ color: colors.danger, marginTop: 8 }}>Error al sincronizar: {event.sync_error}</Text> : null}
      </Card>

      <SectionTitle right={<Button title="Asignar" icon="link-variant" small variant="ghost" onPress={() => setAssignOpen(true)} />}>
        Tareas del evento
      </SectionTitle>
      {data.tasks.length === 0 && (
        <Text style={{ color: colors.textSecondary, marginBottom: 8 }}>No hay tareas asignadas a este evento.</Text>
      )}
      {data.tasks.map((t) => (
        <View key={t.id} style={{ flexDirection: 'row', alignItems: 'center' }}>
          <View style={{ flex: 1 }}>
            <TaskItem
              task={t}
              onToggle={async () => {
                await toggleTask(db, t);
                notifyChange();
              }}
              onPress={() => navigation.navigate('TaskDetail', { id: t.id })}
            />
          </View>
          <Pressable onPress={() => unassign(t)} hitSlop={8} style={{ paddingLeft: 8 }}>
            <MaterialCommunityIcons name="link-variant-off" size={20} color={colors.textSecondary} />
          </Pressable>
        </View>
      ))}
      <Button
        title="Nueva tarea para este evento"
        icon="plus"
        variant="outline"
        small
        style={{ marginTop: 4 }}
        onPress={() => navigation.navigate('TaskForm', { eventId: id, dueDate: event.start_at })}
      />

      <View style={{ flexDirection: 'row', gap: 10, marginTop: 24 }}>
        <Button title="Editar" icon="pencil" style={{ flex: 1 }} onPress={() => navigation.navigate('EventForm', { id })} />
        <Button title="Eliminar" icon="delete" variant="danger" style={{ flex: 1 }} onPress={remove} />
      </View>

      {/* Modal para asignar tareas existentes */}
      <Modal visible={assignOpen} animationType="slide" transparent onRequestClose={() => setAssignOpen(false)}>
        <View style={styles.modalBg}>
          <View style={[styles.modal, { backgroundColor: colors.background }]}>
            <SectionTitle
              right={
                <Pressable onPress={() => setAssignOpen(false)}>
                  <MaterialCommunityIcons name="close" size={24} color={colors.text} />
                </Pressable>
              }
            >
              Asignar tarea
            </SectionTitle>
            <ScrollView>
              {data.available.length === 0 && <Text style={{ color: colors.textSecondary }}>No hay tareas pendientes disponibles.</Text>}
              {data.available.map((t) => (
                <TaskItem key={t.id} task={t} onPress={() => assign(t)} onToggle={() => assign(t)} />
              ))}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const InfoRow = ({ icon, text, colors }) => (
  <View style={{ flexDirection: 'row', gap: 10, alignItems: 'center', paddingVertical: 5 }}>
    <MaterialCommunityIcons name={icon} size={20} color={colors.primary} />
    <Text style={{ color: colors.text, flex: 1 }}>{text}</Text>
  </View>
);

const styles = StyleSheet.create({
  header: { borderRadius: 16, padding: 20, marginBottom: 12, gap: 6 },
  title: { color: '#fff', fontSize: 22, fontWeight: '800' },
  type: { color: '#ffffffcc', fontWeight: '700' },
  modalBg: { flex: 1, backgroundColor: '#0008', justifyContent: 'flex-end' },
  modal: { maxHeight: '75%', borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: 16, paddingTop: 0 },
});
