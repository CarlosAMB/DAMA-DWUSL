import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useTheme } from '../context/ThemeContext';
import { PriorityBadge } from './ui';
import { isOverdue, relativeDue } from '../utils/dates';

/** Fila de tarea con casilla para completar. */
export default function TaskItem({ task, onPress, onToggle, tagsById = {} }) {
  const { colors } = useTheme();
  const done = task.status === 'completada';
  const overdue = isOverdue(task);

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [styles.item, { backgroundColor: colors.card, borderColor: colors.border, opacity: pressed ? 0.85 : 1 }]}
    >
      <Pressable onPress={onToggle} hitSlop={10} style={{ paddingRight: 12, paddingTop: 2 }}>
        <MaterialCommunityIcons
          name={done ? 'checkbox-marked-circle' : 'checkbox-blank-circle-outline'}
          size={26}
          color={done ? colors.success : colors.textSecondary}
        />
      </Pressable>

      <View style={{ flex: 1 }}>
        <Text
          numberOfLines={2}
          style={[styles.title, { color: done ? colors.textSecondary : colors.text, textDecorationLine: done ? 'line-through' : 'none' }]}
        >
          {task.title}
        </Text>

        <View style={styles.meta}>
          <PriorityBadge priority={task.priority} />
          {task.status === 'en_progreso' && <Text style={{ color: colors.info, fontSize: 12, fontWeight: '600' }}>En progreso</Text>}
          {task.due_date && (
            <View style={styles.metaItem}>
              <MaterialCommunityIcons name="calendar" size={13} color={overdue ? colors.danger : colors.textSecondary} />
              <Text style={{ fontSize: 12, color: overdue ? colors.danger : colors.textSecondary, fontWeight: overdue ? '700' : '400' }}>
                {relativeDue(task.due_date)}
              </Text>
            </View>
          )}
          {task.subtasks_total > 0 && (
            <View style={styles.metaItem}>
              <MaterialCommunityIcons name="format-list-checks" size={13} color={colors.textSecondary} />
              <Text style={{ fontSize: 12, color: colors.textSecondary }}>
                {task.subtasks_done}/{task.subtasks_total}
              </Text>
            </View>
          )}
          {task.project_name && (
            <View style={styles.metaItem}>
              <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: task.project_color }} />
              <Text style={{ fontSize: 12, color: colors.textSecondary }} numberOfLines={1}>
                {task.project_name}
              </Text>
            </View>
          )}
        </View>

        {task.tag_ids?.length > 0 && (
          <View style={[styles.meta, { marginTop: 4 }]}>
            {task.tag_ids.map(
              (id) =>
                tagsById[id] && (
                  <Text key={id} style={{ fontSize: 11, color: tagsById[id].color, fontWeight: '700' }}>
                    #{tagsById[id].name}
                  </Text>
                )
            )}
          </View>
        )}
      </View>

      {task.dirty ? <MaterialCommunityIcons name="cloud-upload-outline" size={16} color={colors.textSecondary} /> : null}
      {task.sync_error ? <MaterialCommunityIcons name="alert-circle" size={16} color={colors.danger} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  item: { flexDirection: 'row', alignItems: 'flex-start', padding: 14, borderRadius: 14, borderWidth: 1, marginBottom: 8 },
  title: { fontSize: 15, fontWeight: '600' },
  meta: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 10, marginTop: 6 },
  metaItem: { flexDirection: 'row', alignItems: 'center', gap: 3 },
});
