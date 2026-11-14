import React, { useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../context/AuthContext';
import { useSync } from '../context/SyncContext';
import { useTheme } from '../context/ThemeContext';
import { useLocalQuery } from '../hooks/useLocalQuery';
import { Card, EmptyState, SectionTitle, Button } from '../components/ui';
import TaskItem from '../components/TaskItem';
import EventItem from '../components/EventItem';
import {
  getAnnouncements,
  getEventsBetween,
  getOverdueTasks,
  getStats,
  getTags,
  getTasksDueBetween,
  getUpcomingEvents,
  toggleTask,
} from '../db/repository';
import { addDays, endOfDay, formatLongDate, startOfDay } from '../utils/dates';

const LEVEL_STYLE = {
  info: { icon: 'information-outline', key: 'info' },
  warning: { icon: 'alert-outline', key: 'warning' },
  success: { icon: 'party-popper', key: 'success' },
};

export default function HomeScreen({ navigation }) {
  const db = useSQLiteContext();
  const { user } = useAuth();
  const { colors } = useTheme();
  const { syncNow, notifyChange, isOnline } = useSync();
  const [refreshing, setRefreshing] = useState(false);

  const { data } = useLocalQuery(
    async (database) => {
      const now = new Date();
      const [todayEvents, todayTasks, overdue, upcoming, stats, announcements, tags] = await Promise.all([
        getEventsBetween(database, startOfDay(now).toISOString(), endOfDay(now).toISOString()),
        getTasksDueBetween(database, startOfDay(now).toISOString(), endOfDay(now).toISOString()),
        getOverdueTasks(database),
        getUpcomingEvents(database, 4),
        getStats(database),
        getAnnouncements(database),
        getTags(database),
      ]);
      const tomorrow = startOfDay(addDays(now, 1));
      return {
        todayEvents,
        todayTasks,
        stats,
        announcements,
        overdue: overdue.filter((t) => new Date(t.due_date) < startOfDay(now)),
        upcoming: upcoming.filter((e) => new Date(e.start_at) >= tomorrow),
        tagsById: Object.fromEntries(tags.map((t) => [t.id, t])),
      };
    },
    [],
    null
  );

  const onRefresh = async () => {
    setRefreshing(true);
    await syncNow();
    setRefreshing(false);
  };

  const onToggle = async (task) => {
    await toggleTask(db, task);
    notifyChange();
  };

  const hour = new Date().getHours();
  const greeting = hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
  const firstName = user?.name?.split(' ')[0] || '';

  if (!data) return null;
  const { stats } = data;

  return (
    <ScrollView
      style={{ backgroundColor: colors.background }}
      contentContainerStyle={{ padding: 16, paddingBottom: 40 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[colors.primary]} />}
    >
      <Text style={[styles.greeting, { color: colors.text }]}>
        {greeting}, {firstName} 👋
      </Text>
      <Text style={{ color: colors.textSecondary, marginBottom: 14 }}>{formatLongDate(new Date())}</Text>

      {!isOnline && (
        <View style={[styles.banner, { backgroundColor: colors.warning + '22' }]}>
          <MaterialCommunityIcons name="cloud-off-outline" size={20} color={colors.warning} />
          <Text style={{ color: colors.text, flex: 1 }}>
            Estás sin conexión. Puedes seguir trabajando: tus cambios se sincronizarán después.
          </Text>
        </View>
      )}

      {data.announcements.map((a) => {
        const s = LEVEL_STYLE[a.level] || LEVEL_STYLE.info;
        return (
          <View
            key={a.id}
            style={[styles.banner, { backgroundColor: colors[s.key] + '1A', borderColor: colors[s.key] + '55', borderWidth: 1 }]}
          >
            <MaterialCommunityIcons name={s.icon} size={22} color={colors[s.key]} />
            <View style={{ flex: 1 }}>
              <Text style={{ color: colors.text, fontWeight: '700' }}>{a.title}</Text>
              <Text style={{ color: colors.textSecondary }}>{a.body}</Text>
            </View>
          </View>
        );
      })}

      <View style={styles.statsRow}>
        {[
          {
            label: 'Pendientes',
            value: stats.pending,
            icon: 'clock-outline',
            color: colors.info,
            onPress: () => navigation.navigate('Tareas'),
          },
          {
            label: 'Vencidas',
            value: stats.overdue,
            icon: 'alert-circle-outline',
            color: colors.danger,
            onPress: () => navigation.navigate('Tareas'),
          },
          {
            label: 'Completadas',
            value: stats.completed,
            icon: 'check-all',
            color: colors.success,
            onPress: () => navigation.navigate('Tareas'),
          },
          {
            label: 'Proyectos',
            value: stats.projects,
            icon: 'folder-outline',
            color: colors.primary,
            onPress: () => navigation.navigate('Proyectos'),
          },
        ].map((s) => (
          <Card key={s.label} style={styles.stat} onPress={s.onPress}>
            <MaterialCommunityIcons name={s.icon} size={22} color={s.color} />
            <Text style={[styles.statValue, { color: colors.text }]}>{s.value}</Text>
            <Text style={{ color: colors.textSecondary, fontSize: 11 }}>{s.label}</Text>
          </Card>
        ))}
      </View>

      <View style={{ flexDirection: 'row', gap: 10, marginTop: 6 }}>
        <Button title="Tarea" icon="plus" variant="ghost" small style={{ flex: 1 }} onPress={() => navigation.navigate('TaskForm')} />
        <Button
          title="Evento"
          icon="calendar-plus"
          variant="ghost"
          small
          style={{ flex: 1 }}
          onPress={() => navigation.navigate('EventForm')}
        />
      </View>

      <SectionTitle>Agenda de hoy</SectionTitle>
      {data.todayEvents.length === 0 ? (
        <Text style={{ color: colors.textSecondary }}>No tienes eventos hoy.</Text>
      ) : (
        data.todayEvents.map((e) => <EventItem key={e.id} event={e} onPress={() => navigation.navigate('EventDetail', { id: e.id })} />)
      )}

      {data.overdue.length > 0 && (
        <>
          <SectionTitle>⚠️ Vencidas</SectionTitle>
          {data.overdue.map((t) => (
            <TaskItem
              key={t.id}
              task={t}
              tagsById={data.tagsById}
              onToggle={() => onToggle(t)}
              onPress={() => navigation.navigate('TaskDetail', { id: t.id })}
            />
          ))}
        </>
      )}

      <SectionTitle>Tareas para hoy</SectionTitle>
      {data.todayTasks.length === 0 ? (
        <EmptyState icon="weather-sunny" title="¡Día libre de pendientes!" subtitle="No hay tareas con fecha límite hoy." />
      ) : (
        data.todayTasks.map((t) => (
          <TaskItem
            key={t.id}
            task={t}
            tagsById={data.tagsById}
            onToggle={() => onToggle(t)}
            onPress={() => navigation.navigate('TaskDetail', { id: t.id })}
          />
        ))
      )}

      {data.upcoming.length > 0 && (
        <>
          <SectionTitle>Próximos eventos</SectionTitle>
          {data.upcoming.map((e) => (
            <EventItem key={e.id} event={e} showDate onPress={() => navigation.navigate('EventDetail', { id: e.id })} />
          ))}
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  greeting: { fontSize: 24, fontWeight: '800' },
  banner: { flexDirection: 'row', gap: 10, padding: 12, borderRadius: 12, marginBottom: 10, alignItems: 'center' },
  statsRow: { flexDirection: 'row', gap: 8 },
  stat: { flex: 1, alignItems: 'center', paddingVertical: 12, paddingHorizontal: 4 },
  statValue: { fontSize: 22, fontWeight: '800', marginTop: 4 },
});
