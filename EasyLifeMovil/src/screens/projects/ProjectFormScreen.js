import React, { useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Chip, ColorPicker, Input, Label } from '../../components/ui';
import DateTimeField from '../../components/DateTimeField';
import { getProject, saveRecord } from '../../db/repository';
import { dayKey } from '../../utils/dates';
import { PROJECT_STATUS } from './ProjectsScreen';

export default function ProjectFormScreen({ route, navigation }) {
  const db = useSQLiteContext();
  const { colors } = useTheme();
  const { notifyChange } = useSync();
  const editingId = route.params?.id;
  const [form, setForm] = useState({ name: '', description: '', color: '#4F46E5', status: 'activo', due_date: null });
  const [error, setError] = useState('');

  useEffect(() => {
    navigation.setOptions({ title: editingId ? 'Editar proyecto' : 'Nuevo proyecto' });
    if (editingId) {
      getProject(db, editingId).then(
        (p) => p && setForm({ name: p.name, description: p.description || '', color: p.color, status: p.status, due_date: p.due_date })
      );
    }
  }, [db, editingId, navigation]);

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }));

  const save = async () => {
    if (!form.name.trim()) return setError('El nombre es obligatorio.');
    const data = { ...form, name: form.name.trim() };
    await saveRecord(db, 'projects', editingId ? { ...data, id: editingId } : data);
    notifyChange();
    navigation.goBack();
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
      <Input label="Nombre *" value={form.name} onChangeText={set('name')} placeholder="Ej. Tesis, Mudanza, Viaje" error={error} />
      <Input label="Descripción" value={form.description} onChangeText={set('description')} multiline />
      <Label>Color</Label>
      <ColorPicker value={form.color} onChange={set('color')} />
      <Label>Estado</Label>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', marginBottom: 8 }}>
        {Object.entries(PROJECT_STATUS).map(([k, v]) => (
          <Chip key={k} label={v.label} icon={v.icon} selected={form.status === k} onPress={() => set('status')(k)} />
        ))}
      </View>
      {/* La fecha del proyecto es solo dia (sin hora) */}
      <DateTimeField
        label="Fecha de entrega"
        mode="date"
        clearable
        value={form.due_date ? new Date(form.due_date + 'T12:00:00').toISOString() : null}
        onChange={(iso) => set('due_date')(iso ? dayKey(iso) : null)}
      />
      <Text style={{ color: colors.textSecondary, fontSize: 12, marginBottom: 16 }}>
        El progreso se calcula automáticamente con las tareas completadas del proyecto.
      </Text>
      <Button title="Guardar proyecto" icon="content-save" onPress={save} />
    </ScrollView>
  );
}
