import React, { useLayoutEffect, useMemo, useState } from 'react';
import { FlatList, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../../context/SyncContext';
import { PRIORITY_COLORS, useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Chip, EmptyState, Fab, PRIORITIES } from '../../components/ui';
import TaskItem from '../../components/TaskItem';
import SyncStatus from '../../components/SyncStatus';
import { getProjects, getTags, getTasks, toggleTask } from '../../db/repository';

const STATUS_FILTERS = [
  { value: 'pendientes', label: 'Pendientes' },
  { value: 'completadas', label: 'Completadas' },
  { value: 'todas', label: 'Todas' },
];

export default function TasksScreen({ navigation }) {
  const db = useSQLiteContext();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const [filters, setFilters] = useState({ status: 'pendientes', priority: null, projectId: null, tagId: null, search: '' });
  const [showFilters, setShowFilters] = useState(false);

  useLayoutEffect(() => {
    navigation.setOptions({
      headerRight: () => (
        <View style={{ flexDirection: 'row', alignItems: 'center' }}>
          <Pressable onPress={() => navigation.navigate('Tags')} hitSlop={8} style={{ marginRight: 14 }}>
            <MaterialCommunityIcons name="tag-multiple-outline" size={22} color={colors.text} />
          </Pressable>
          <SyncStatus />
        </View>
      ),
    });
  }, [navigation, colors]);

  const { data } = useLocalQuery(
    async (database) => {
      const [tasks, tags, projects] = await Promise.all([getTasks(database, filters), getTags(database), getProjects(database)]);
      return { tasks, tags, projects };
    },
    [JSON.stringify(filters)],
    { tasks: [], tags: [], projects: [] }
  );

  const tagsById = useMemo(() => Object.fromEntries(data.tags.map((t) => [t.id, t])), [data.tags]);
  const set = (key, value) => setFilters((f) => ({ ...f, [key]: f[key] === value ? null : value }));
  const activeCount = ['priority', 'projectId', 'tagId'].filter((k) => filters[k]).length;

  const onToggle = async (task) => {
    await toggleTask(db, task);
    notifyChange();
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <View style={{ padding: 16, paddingBottom: 0 }}>
        <View style={[styles.search, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <MaterialCommunityIcons name="magnify" size={20} color={colors.textSecondary} />
          <TextInput
            value={filters.search}
            onChangeText={(v) => setFilters((f) => ({ ...f, search: v }))}
            placeholder="Buscar tareas"
            placeholderTextColor={colors.textSecondary}
            style={{ flex: 1, color: colors.text, height: 44 }}
          />
          <Pressable onPress={() => setShowFilters(!showFilters)} hitSlop={8}>
            <MaterialCommunityIcons
              name={activeCount ? 'filter' : 'filter-outline'}
              size={22}
              color={activeCount ? colors.primary : colors.textSecondary}
            />
          </Pressable>
        </View>

        <View style={{ flexDirection: 'row', marginTop: 10 }}>
          {STATUS_FILTERS.map((s) => (
            <Chip
              key={s.value}
              label={s.label}
              selected={filters.status === s.value}
              onPress={() => setFilters((f) => ({ ...f, status: s.value }))}
            />
          ))}
        </View>

        {showFilters && (
          <View>
            <Text style={[styles.filterLabel, { color: colors.textSecondary }]}>Prioridad</Text>
            <View style={styles.wrap}>
              {PRIORITIES.map((p) => (
                <Chip
                  key={p.value}
                  label={p.label}
                  color={PRIORITY_COLORS[p.value]}
                  selected={filters.priority === p.value}
                  onPress={() => set('priority', p.value)}
                />
              ))}
            </View>
            {data.projects.length > 0 && (
              <>
                <Text style={[styles.filterLabel, { color: colors.textSecondary }]}>Proyecto</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                  {data.projects.map((p) => (
                    <Chip
                      key={p.id}
                      label={p.name}
                      color={p.color}
                      selected={filters.projectId === p.id}
                      onPress={() => set('projectId', p.id)}
                    />
                  ))}
                </ScrollView>
              </>
            )}
            {data.tags.length > 0 && (
              <>
                <Text style={[styles.filterLabel, { color: colors.textSecondary }]}>Etiqueta</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                  {data.tags.map((t) => (
                    <Chip
                      key={t.id}
                      label={'#' + t.name}
                      color={t.color}
                      selected={filters.tagId === t.id}
                      onPress={() => set('tagId', t.id)}
                    />
                  ))}
                </ScrollView>
              </>
            )}
          </View>
        )}
      </View>

      <FlatList
        data={data.tasks}
        keyExtractor={(t) => t.id}
        contentContainerStyle={{ padding: 16, paddingBottom: 100 }}
        renderItem={({ item }) => (
          <TaskItem
            task={item}
            tagsById={tagsById}
            onToggle={() => onToggle(item)}
            onPress={() => navigation.navigate('TaskDetail', { id: item.id })}
          />
        )}
        ListEmptyComponent={
          <EmptyState
            icon="checkbox-marked-circle-plus-outline"
            title="No hay tareas"
            subtitle={filters.status === 'pendientes' ? '¡Todo al día! Crea una nueva tarea con el botón +' : 'Prueba con otros filtros.'}
          />
        }
      />
      <Fab onPress={() => navigation.navigate('TaskForm', { projectId: filters.projectId })} />
    </View>
  );
}

const styles = StyleSheet.create({
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, borderWidth: 1, borderRadius: 12, paddingHorizontal: 12 },
  filterLabel: { fontSize: 12, fontWeight: '700', marginBottom: 6, marginTop: 4 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap' },
});
