import React from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Button, EmptyState, ProgressBar, SectionTitle } from '../../components/ui';
import TaskItem from '../../components/TaskItem';
import { deleteProject, getProject, getTags, getTasks, toggleTask } from '../../db/repository';
import { formatDateTime } from '../../utils/dates';
import { PROJECT_STATUS } from './ProjectsScreen';

export default function ProjectDetailScreen({ route, navigation }) {
  const { id } = route.params;
  const db = useSQLiteContext();
  const { colors } = useTheme();
  const { notifyChange } = useSync();

  const { data } = useLocalQuery(
    async (database) => {
      const tags = await getTags(database);
      return {
        project: await getProject(database, id),
        tasks: await getTasks(database, { projectId: id, status: 'todas' }),
        tagsById: Object.fromEntries(tags.map((t) => [t.id, t])),
      };
    },
    [id]
  );

  if (!data) return null;
  if (!data.project || data.project.deleted_at) return <EmptyState icon="folder-remove-outline" title="Proyecto no encontrado" />;
  const { project, tasks } = data;
  const pending = tasks.filter((t) => t.status !== 'completada');
  const completed = tasks.filter((t) => t.status === 'completada');

  const remove = () =>
    Alert.alert('Eliminar proyecto', `¿Eliminar "${project.name}"? Sus tareas se conservarán sin proyecto.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          await deleteProject(db, id);
          notifyChange();
          navigation.goBack();
        },
      },
    ]);

  const onToggle = async (t) => {
    await toggleTask(db, t);
    notifyChange();
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      <View style={[styles.header, { backgroundColor: project.color }]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <MaterialCommunityIcons name="folder-open" size={30} color="#fff" />
          <Text style={styles.badge}>{PROJECT_STATUS[project.status]?.label}</Text>
        </View>
        <Text style={styles.title}>{project.name}</Text>
        {project.description ? <Text style={{ color: '#ffffffdd' }}>{project.description}</Text> : null}
        {project.due_date ? (
          <Text style={{ color: '#fff', fontWeight: '600' }}>Entrega: {formatDateTime(project.due_date + 'T12:00:00', false)}</Text>
        ) : null}
        <View style={{ marginTop: 10 }}>
          <ProgressBar value={project.progress} color="#fff" height={10} />
          <Text style={{ color: '#fff', fontWeight: '800', marginTop: 6 }}>
            {project.progress}% completado · {project.tasks_done}/{project.tasks_total} tareas
          </Text>
        </View>
      </View>

      <SectionTitle
        right={
          <Button title="Tarea" icon="plus" small variant="ghost" onPress={() => navigation.navigate('TaskForm', { projectId: id })} />
        }
      >
        Pendientes ({pending.length})
      </SectionTitle>
      {pending.length === 0 && <Text style={{ color: colors.textSecondary }}>No hay tareas pendientes.</Text>}
      {pending.map((t) => (
        <TaskItem
          key={t.id}
          task={t}
          tagsById={data.tagsById}
          onToggle={() => onToggle(t)}
          onPress={() => navigation.navigate('TaskDetail', { id: t.id })}
        />
      ))}

      {completed.length > 0 && (
        <>
          <SectionTitle>Completadas ({completed.length})</SectionTitle>
          {completed.map((t) => (
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

      <View style={{ flexDirection: 'row', gap: 10, marginTop: 24 }}>
        <Button title="Editar" icon="pencil" style={{ flex: 1 }} onPress={() => navigation.navigate('ProjectForm', { id })} />
        <Button title="Eliminar" icon="delete" variant="danger" style={{ flex: 1 }} onPress={remove} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  header: { borderRadius: 16, padding: 18, gap: 6 },
  title: { color: '#fff', fontSize: 24, fontWeight: '800' },
  badge: {
    color: '#fff',
    backgroundColor: '#ffffff33',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 10,
    fontWeight: '700',
    overflow: 'hidden',
  },
});
