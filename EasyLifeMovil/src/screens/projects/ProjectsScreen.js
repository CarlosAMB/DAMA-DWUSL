import React from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Card, EmptyState, Fab, ProgressBar } from '../../components/ui';
import { getProjects } from '../../db/repository';
import { formatDateTime } from '../../utils/dates';

export const PROJECT_STATUS = {
  activo: { label: 'Activo', icon: 'play-circle-outline' },
  pausado: { label: 'Pausado', icon: 'pause-circle-outline' },
  completado: { label: 'Completado', icon: 'check-circle-outline' },
  archivado: { label: 'Archivado', icon: 'archive-outline' },
};

export default function ProjectsScreen({ navigation }) {
  const { colors } = useTheme();
  const { data: projects } = useLocalQuery(getProjects, [], []);

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <FlatList
        data={projects}
        keyExtractor={(p) => p.id}
        contentContainerStyle={{ padding: 16, paddingBottom: 100 }}
        ListEmptyComponent={
          <EmptyState icon="folder-plus-outline" title="Sin proyectos" subtitle="Agrupa tus tareas en proyectos para seguir su progreso." />
        }
        renderItem={({ item: p }) => (
          <Card onPress={() => navigation.navigate('ProjectDetail', { id: p.id })} style={{ borderLeftWidth: 5, borderLeftColor: p.color }}>
            <View style={styles.row}>
              <View style={[styles.icon, { backgroundColor: p.color + '22' }]}>
                <MaterialCommunityIcons name="folder" size={24} color={p.color} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.name, { color: colors.text }]} numberOfLines={1}>
                  {p.name}
                </Text>
                <Text style={{ color: colors.textSecondary, fontSize: 12 }}>
                  {PROJECT_STATUS[p.status]?.label} · {p.tasks_done}/{p.tasks_total} tareas
                  {p.due_date ? ` · Vence ${formatDateTime(p.due_date + 'T12:00:00', false)}` : ''}
                </Text>
              </View>
              <Text style={{ color: p.color, fontWeight: '800', fontSize: 16 }}>{p.progress}%</Text>
            </View>
            <View style={{ marginTop: 12 }}>
              <ProgressBar value={p.progress} color={p.color} />
            </View>
          </Card>
        )}
      />
      <Fab onPress={() => navigation.navigate('ProjectForm')} />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  icon: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  name: { fontSize: 16, fontWeight: '700' },
});
