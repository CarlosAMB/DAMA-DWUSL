import React, { useState } from 'react';
import { Pressable, ScrollView, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useTheme } from '../../context/ThemeContext';
import { Card } from '../../components/ui';

const FAQ = [
  [
    '¿Funciona sin internet?',
    'Sí. Todo se guarda primero en tu teléfono. Cuando vuelve la conexión, EasyLife sincroniza automáticamente. El ícono de nube en la parte superior indica el estado.',
  ],
  ['¿Cómo creo un evento?', 'Ve a Calendario y toca el botón +. Elige el tipo (reunión, cita, cumpleaños…), la fecha y los recordatorios.'],
  ['¿Cómo asigno tareas a un evento?', 'Abre el evento y toca "Asignar", o al crear una tarea elige el evento en "Asignar a evento".'],
  ['¿Qué son las subtareas?', 'Pasos pequeños dentro de una tarea. Al marcarlos verás el porcentaje de avance.'],
  ['¿Cómo organizo tareas en proyectos?', 'Crea un proyecto en la pestaña Proyectos y asígnale tareas. Su progreso se calcula solo.'],
  [
    '¿Por qué no recibo recordatorios?',
    'Revisa en Perfil > Preferencias que las notificaciones estén activas y que el teléfono haya dado permiso a EasyLife.',
  ],
  ['¿Cómo paso mis eventos a Google Calendar?', 'En Perfil > Integraciones exporta el archivo .ics y ábrelo con Google Calendar.'],
  ['¿Si cambio de teléfono pierdo mis datos?', 'No. Inicia sesión en el nuevo teléfono y se descargarán desde el servidor.'],
];

export default function HelpScreen() {
  const { colors } = useTheme();
  const [open, setOpen] = useState(0);
  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      {FAQ.map(([q, a], i) => (
        <Card key={q} onPress={() => setOpen(open === i ? -1 : i)}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <MaterialCommunityIcons name="help-circle-outline" size={20} color={colors.primary} />
            <Text style={{ flex: 1, color: colors.text, fontWeight: '700' }}>{q}</Text>
            <MaterialCommunityIcons name={open === i ? 'chevron-up' : 'chevron-down'} size={20} color={colors.textSecondary} />
          </View>
          {open === i && <Text style={{ color: colors.textSecondary, marginTop: 8, lineHeight: 20 }}>{a}</Text>}
        </Card>
      ))}
    </ScrollView>
  );
}
