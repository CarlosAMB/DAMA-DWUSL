import React, { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Calendar, LocaleConfig } from 'react-native-calendars';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../../context/AuthContext';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Fab, EmptyState } from '../../components/ui';
import EventItem from '../../components/EventItem';
import TaskItem from '../../components/TaskItem';
import { getEventsBetween, getTasksDueBetween, toggleTask } from '../../db/repository';
import {
  DAYS_SHORT,
  MONTHS,
  MONTHS_SHORT,
  addDays,
  addMonths,
  dayKey,
  endOfDay,
  formatLongDate,
  formatTime,
  isSameDay,
  monthTitle,
  startOfDay,
  startOfWeek,
} from '../../utils/dates';

LocaleConfig.locales.es = {
  monthNames: MONTHS.map((m) => m[0].toUpperCase() + m.slice(1)),
  monthNamesShort: MONTHS_SHORT,
  dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
  dayNamesShort: ['D', 'L', 'M', 'M', 'J', 'V', 'S'],
  today: 'Hoy',
};
LocaleConfig.defaultLocale = 'es';

const VIEWS = [
  { key: 'day', label: 'Día' },
  { key: 'week', label: 'Semana' },
  { key: 'month', label: 'Mes' },
  { key: 'year', label: 'Año' },
];

/** Rango de fechas que se consulta segun la vista. */
function rangeFor(view, date, weekStartsOn) {
  if (view === 'day') return [startOfDay(date), endOfDay(date)];
  if (view === 'week') {
    const s = startOfWeek(date, weekStartsOn);
    return [s, endOfDay(addDays(s, 6))];
  }
  if (view === 'month') {
    const s = new Date(date.getFullYear(), date.getMonth(), 1);
    return [addDays(s, -7), endOfDay(addDays(new Date(date.getFullYear(), date.getMonth() + 1, 0), 7))];
  }
  return [new Date(date.getFullYear(), 0, 1), endOfDay(new Date(date.getFullYear(), 11, 31))];
}

/** Agrupa eventos (todos los dias que abarcan) y tareas por YYYY-MM-DD. */
function groupByDay(events, tasks) {
  const map = {};
  const push = (key, type, item) => {
    (map[key] ||= { events: [], tasks: [] })[type].push(item);
  };
  events.forEach((e) => {
    let d = startOfDay(new Date(e.start_at));
    const end = startOfDay(new Date(e.end_at || e.start_at));
    for (let i = 0; d <= end && i < 62; i++, d = addDays(d, 1)) push(dayKey(d), 'events', e);
  });
  tasks.forEach((t) => push(dayKey(t.due_date), 'tasks', t));
  return map;
}

export default function CalendarScreen({ navigation }) {
  const db = useSQLiteContext();
  const { user } = useAuth();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const weekStartsOn = user?.preferences?.week_starts_on ?? 1;
  const [view, setView] = useState(user?.preferences?.calendar_view || 'month');
  const [date, setDate] = useState(new Date());

  const [from, to] = rangeFor(view, date, weekStartsOn);
  const { data } = useLocalQuery(
    async (database) => {
      const [events, tasks] = await Promise.all([
        getEventsBetween(database, from.toISOString(), to.toISOString()),
        getTasksDueBetween(database, from.toISOString(), to.toISOString()),
      ]);
      return { events, tasks };
    },
    [view, from.getTime(), to.getTime()],
    { events: [], tasks: [] }
  );

  const byDay = useMemo(() => groupByDay(data.events, data.tasks), [data]);

  const move = (dir) => {
    if (view === 'day') setDate(addDays(date, dir));
    if (view === 'week') setDate(addDays(date, dir * 7));
    if (view === 'month') setDate(addMonths(date, dir));
    if (view === 'year') setDate(new Date(date.getFullYear() + dir, date.getMonth(), 1));
  };

  const title =
    view === 'year'
      ? String(date.getFullYear())
      : view === 'month'
        ? monthTitle(date)
        : view === 'week'
          ? `${startOfWeek(date, weekStartsOn).getDate()} - ${addDays(startOfWeek(date, weekStartsOn), 6).getDate()} ${MONTHS_SHORT[addDays(startOfWeek(date, weekStartsOn), 6).getMonth()]}`
          : formatLongDate(date);

  const onToggle = async (t) => {
    await toggleTask(db, t);
    notifyChange();
  };
  const openEvent = (e) => navigation.navigate('EventDetail', { id: e.id });
  const openTask = (t) => navigation.navigate('TaskDetail', { id: t.id });

  const DayList = ({ day }) => {
    const items = byDay[dayKey(day)] || { events: [], tasks: [] };
    if (!items.events.length && !items.tasks.length) {
      return <EmptyState icon="calendar-blank-outline" title="Sin actividades" subtitle="Toca + para crear un evento." />;
    }
    return (
      <>
        {items.events.map((e) => (
          <EventItem key={e.id} event={e} onPress={() => openEvent(e)} />
        ))}
        {items.tasks.length > 0 && <Text style={[styles.subhead, { color: colors.textSecondary }]}>Tareas que vencen</Text>}
        {items.tasks.map((t) => (
          <TaskItem key={t.id} task={t} onToggle={() => onToggle(t)} onPress={() => openTask(t)} />
        ))}
      </>
    );
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      {/* Selector de vista */}
      <View style={[styles.segment, { backgroundColor: colors.card, borderColor: colors.border }]}>
        {VIEWS.map((v) => (
          <Pressable
            key={v.key}
            onPress={() => setView(v.key)}
            style={[styles.segItem, view === v.key && { backgroundColor: colors.primary }]}
          >
            <Text style={{ color: view === v.key ? colors.primaryText : colors.text, fontWeight: '700' }}>{v.label}</Text>
          </Pressable>
        ))}
      </View>

      {/* Navegacion anterior / siguiente */}
      {view !== 'month' && (
        <View style={styles.navRow}>
          <Pressable onPress={() => move(-1)} hitSlop={10}>
            <MaterialCommunityIcons name="chevron-left" size={30} color={colors.primary} />
          </Pressable>
          <Pressable onPress={() => setDate(new Date())}>
            <Text style={[styles.navTitle, { color: colors.text }]}>{title}</Text>
          </Pressable>
          <Pressable onPress={() => move(1)} hitSlop={10}>
            <MaterialCommunityIcons name="chevron-right" size={30} color={colors.primary} />
          </Pressable>
        </View>
      )}

      <ScrollView contentContainerStyle={{ padding: 16, paddingTop: 4, paddingBottom: 100 }}>
        {/* ----------- DIA ----------- */}
        {view === 'day' && <DayList day={date} />}

        {/* ----------- SEMANA ----------- */}
        {view === 'week' &&
          Array.from({ length: 7 }, (_, i) => addDays(startOfWeek(date, weekStartsOn), i)).map((d) => {
            const items = byDay[dayKey(d)] || { events: [], tasks: [] };
            const today = isSameDay(d, new Date());
            return (
              <Pressable
                key={dayKey(d)}
                onPress={() => {
                  setDate(d);
                  setView('day');
                }}
                style={[styles.weekRow, { backgroundColor: colors.card, borderColor: today ? colors.primary : colors.border }]}
              >
                <View style={styles.weekDay}>
                  <Text style={{ color: colors.textSecondary, fontSize: 12, textTransform: 'uppercase' }}>{DAYS_SHORT[d.getDay()]}</Text>
                  <Text style={{ color: today ? colors.primary : colors.text, fontSize: 22, fontWeight: '800' }}>{d.getDate()}</Text>
                </View>
                <View style={{ flex: 1, gap: 4 }}>
                  {items.events.length === 0 && items.tasks.length === 0 && <Text style={{ color: colors.textSecondary }}>—</Text>}
                  {items.events.map((e) => (
                    <View key={e.id} style={styles.weekItem}>
                      <View style={[styles.dot, { backgroundColor: e.type_color || colors.primary }]} />
                      <Text style={{ color: colors.text, flex: 1 }} numberOfLines={1}>
                        {e.all_day ? '' : formatTime(e.start_at) + '  '}
                        {e.title}
                      </Text>
                    </View>
                  ))}
                  {items.tasks.map((t) => (
                    <View key={t.id} style={styles.weekItem}>
                      <MaterialCommunityIcons
                        name={t.status === 'completada' ? 'check-circle' : 'checkbox-blank-circle-outline'}
                        size={12}
                        color={colors.textSecondary}
                      />
                      <Text
                        style={{
                          color: colors.textSecondary,
                          flex: 1,
                          textDecorationLine: t.status === 'completada' ? 'line-through' : 'none',
                        }}
                        numberOfLines={1}
                      >
                        {t.title}
                      </Text>
                    </View>
                  ))}
                </View>
              </Pressable>
            );
          })}

        {/* ----------- MES ----------- */}
        {view === 'month' && (
          <>
            <Calendar
              key={`${colors.background}`}
              current={dayKey(date)}
              firstDay={weekStartsOn}
              onDayPress={(d) => setDate(new Date(d.year, d.month - 1, d.day))}
              onMonthChange={(m) => setDate(new Date(m.year, m.month - 1, 1))}
              markingType="multi-dot"
              markedDates={(() => {
                const marks = Object.fromEntries(
                  Object.entries(byDay).map(([k, v]) => [
                    k,
                    {
                      dots: [
                        ...v.events.slice(0, 3).map((e, i) => ({ key: `e${i}`, color: e.type_color || colors.primary })),
                        ...(v.tasks.length ? [{ key: 't', color: colors.textSecondary }] : []),
                      ],
                    },
                  ])
                );
                const sel = dayKey(date);
                marks[sel] = { ...(marks[sel] || {}), selected: true, selectedColor: colors.primary };
                return marks;
              })()}
              theme={{
                calendarBackground: colors.card,
                dayTextColor: colors.text,
                monthTextColor: colors.text,
                textSectionTitleColor: colors.textSecondary,
                todayTextColor: colors.primary,
                arrowColor: colors.primary,
                textDisabledColor: colors.border,
                textMonthFontWeight: '800',
              }}
              style={{ borderRadius: 14, borderWidth: 1, borderColor: colors.border, paddingBottom: 8, marginBottom: 14 }}
            />
            <Text style={[styles.dayTitle, { color: colors.text }]}>{formatLongDate(date)}</Text>
            <DayList day={date} />
          </>
        )}

        {/* ----------- ANIO ----------- */}
        {view === 'year' && (
          <View style={styles.yearGrid}>
            {Array.from({ length: 12 }, (_, m) => {
              const first = new Date(date.getFullYear(), m, 1);
              const daysInMonth = new Date(date.getFullYear(), m + 1, 0).getDate();
              const offset = (first.getDay() - weekStartsOn + 7) % 7;
              let count = 0;
              return (
                <Pressable
                  key={m}
                  style={[styles.miniMonth, { backgroundColor: colors.card, borderColor: colors.border }]}
                  onPress={() => {
                    setDate(first);
                    setView('month');
                  }}
                >
                  <Text style={{ color: colors.primary, fontWeight: '800', marginBottom: 4 }}>
                    {MONTHS[m][0].toUpperCase() + MONTHS[m].slice(1)}
                  </Text>
                  <View style={styles.miniDays}>
                    {Array.from({ length: offset }, (_, i) => (
                      <View key={`o${i}`} style={styles.miniCell} />
                    ))}
                    {Array.from({ length: daysInMonth }, (_, i) => {
                      const k = dayKey(new Date(date.getFullYear(), m, i + 1));
                      const has = byDay[k];
                      if (has) count += has.events.length + has.tasks.length;
                      const today = k === dayKey(new Date());
                      return (
                        <View
                          key={k}
                          style={[
                            styles.miniCell,
                            has && { backgroundColor: colors.primary + '33', borderRadius: 4 },
                            today && { backgroundColor: colors.primary, borderRadius: 4 },
                          ]}
                        >
                          <Text style={{ fontSize: 8, color: today ? colors.primaryText : colors.text }}>{i + 1}</Text>
                        </View>
                      );
                    })}
                  </View>
                  <Text style={{ color: colors.textSecondary, fontSize: 10, marginTop: 4 }}>{count} actividad(es)</Text>
                </Pressable>
              );
            })}
          </View>
        )}
      </ScrollView>

      <Fab
        onPress={() => navigation.navigate('EventForm', { date: view === 'day' || view === 'month' ? date.toISOString() : undefined })}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  segment: { flexDirection: 'row', margin: 16, marginBottom: 8, borderRadius: 12, borderWidth: 1, padding: 4 },
  segItem: { flex: 1, paddingVertical: 8, borderRadius: 9, alignItems: 'center' },
  navRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 12, marginBottom: 8 },
  navTitle: { fontSize: 17, fontWeight: '800' },
  subhead: { fontSize: 13, fontWeight: '700', marginTop: 10, marginBottom: 6 },
  dayTitle: { fontSize: 16, fontWeight: '800', marginBottom: 10 },
  weekRow: { flexDirection: 'row', gap: 12, padding: 12, borderRadius: 12, borderWidth: 1, marginBottom: 8 },
  weekDay: { width: 44, alignItems: 'center' },
  weekItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  dot: { width: 8, height: 8, borderRadius: 4 },
  yearGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between' },
  miniMonth: { width: '48.5%', borderWidth: 1, borderRadius: 12, padding: 8, marginBottom: 10 },
  miniDays: { flexDirection: 'row', flexWrap: 'wrap' },
  miniCell: { width: `${100 / 7}%`, aspectRatio: 1, alignItems: 'center', justifyContent: 'center' },
});
