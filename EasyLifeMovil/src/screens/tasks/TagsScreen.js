import React, { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { useLocalQuery } from '../../hooks/useLocalQuery';
import { Button, Card, ColorPicker, EmptyState, Input, PALETTE } from '../../components/ui';
import { deleteTag, getTags, saveRecord } from '../../db/repository';

/** Administrar etiquetas para categorizar tareas. */
export default function TagsScreen() {
  const db = useSQLiteContext();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const [editing, setEditing] = useState(null); // {id?, name, color}
  const { data: tags } = useLocalQuery(getTags, [], []);

  const save = async () => {
    if (!editing.name.trim()) return;
    if (tags.some((t) => t.name.toLowerCase() === editing.name.trim().toLowerCase() && t.id !== editing.id)) {
      Alert.alert('Etiqueta duplicada', 'Ya existe una etiqueta con ese nombre.');
      return;
    }
    await saveRecord(db, 'tags', { ...editing, name: editing.name.trim() });
    setEditing(null);
    notifyChange();
  };

  const remove = (t) =>
    Alert.alert('Eliminar etiqueta', `¿Eliminar #${t.name}? Se quitará de todas las tareas.`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: async () => {
          await deleteTag(db, t.id);
          notifyChange();
        },
      },
    ]);

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
      {editing ? (
        <Card>
          <Input
            label="Nombre de la etiqueta"
            value={editing.name}
            onChangeText={(name) => setEditing({ ...editing, name })}
            maxLength={40}
            autoFocus
          />
          <ColorPicker value={editing.color} onChange={(color) => setEditing({ ...editing, color })} />
          <View style={{ flexDirection: 'row', gap: 10 }}>
            <Button title="Cancelar" variant="outline" small style={{ flex: 1 }} onPress={() => setEditing(null)} />
            <Button title="Guardar" icon="check" small style={{ flex: 1 }} onPress={save} />
          </View>
        </Card>
      ) : (
        <Button
          title="Nueva etiqueta"
          icon="tag-plus"
          onPress={() => setEditing({ name: '', color: PALETTE[tags.length % PALETTE.length] })}
          style={{ marginBottom: 14 }}
        />
      )}

      {tags.length === 0 && !editing && (
        <EmptyState
          icon="tag-outline"
          title="Sin etiquetas"
          subtitle="Usa etiquetas como #Universidad o #Casa para categorizar tus tareas."
        />
      )}
      {tags.map((t) => (
        <View key={t.id} style={[styles.row, { backgroundColor: colors.card, borderColor: colors.border }]}>
          <View style={[styles.dot, { backgroundColor: t.color }]} />
          <Text style={{ flex: 1, color: colors.text, fontSize: 16, fontWeight: '600' }}>#{t.name}</Text>
          <Pressable onPress={() => setEditing({ id: t.id, name: t.name, color: t.color })} hitSlop={8} style={{ marginRight: 16 }}>
            <MaterialCommunityIcons name="pencil-outline" size={20} color={colors.primary} />
          </Pressable>
          <Pressable onPress={() => remove(t)} hitSlop={8}>
            <MaterialCommunityIcons name="trash-can-outline" size={20} color={colors.danger} />
          </Pressable>
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 12, borderWidth: 1, marginBottom: 8 },
  dot: { width: 14, height: 14, borderRadius: 7 },
});
