import React from 'react';
import { NavigationContainer, DefaultTheme, DarkTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import SyncStatus from '../components/SyncStatus';

import LoginScreen from '../screens/auth/LoginScreen';
import RegisterScreen from '../screens/auth/RegisterScreen';
import HomeScreen from '../screens/HomeScreen';
import CalendarScreen from '../screens/calendar/CalendarScreen';
import EventFormScreen from '../screens/calendar/EventFormScreen';
import EventDetailScreen from '../screens/calendar/EventDetailScreen';
import TasksScreen from '../screens/tasks/TasksScreen';
import TaskFormScreen from '../screens/tasks/TaskFormScreen';
import TaskDetailScreen from '../screens/tasks/TaskDetailScreen';
import TagsScreen from '../screens/tasks/TagsScreen';
import ProjectsScreen from '../screens/projects/ProjectsScreen';
import ProjectFormScreen from '../screens/projects/ProjectFormScreen';
import ProjectDetailScreen from '../screens/projects/ProjectDetailScreen';
import ProfileScreen from '../screens/profile/ProfileScreen';
import EditProfileScreen from '../screens/profile/EditProfileScreen';
import ChangePasswordScreen from '../screens/profile/ChangePasswordScreen';
import PreferencesScreen from '../screens/profile/PreferencesScreen';
import IntegrationsScreen from '../screens/profile/IntegrationsScreen';
import PrivacyScreen from '../screens/profile/PrivacyScreen';
import HelpScreen from '../screens/profile/HelpScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

const TAB_ICONS = {
  Inicio: 'home-variant',
  Calendario: 'calendar-month',
  Tareas: 'checkbox-marked-circle-outline',
  Proyectos: 'folder-multiple-outline',
  Perfil: 'account-circle-outline',
};

function MainTabs() {
  const { colors } = useTheme();
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerStyle: { backgroundColor: colors.surface },
        headerTintColor: colors.text,
        headerTitleStyle: { fontWeight: '800' },
        headerRight: () => <SyncStatus />,
        tabBarStyle: { backgroundColor: colors.surface, borderTopColor: colors.border, height: 62, paddingBottom: 8 },
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.textSecondary,
        tabBarIcon: ({ color, size }) => <MaterialCommunityIcons name={TAB_ICONS[route.name]} size={size} color={color} />,
      })}
    >
      <Tab.Screen name="Inicio" component={HomeScreen} options={{ title: 'EasyLife', tabBarLabel: 'Inicio' }} />
      <Tab.Screen name="Calendario" component={CalendarScreen} />
      <Tab.Screen name="Tareas" component={TasksScreen} />
      <Tab.Screen name="Proyectos" component={ProjectsScreen} />
      <Tab.Screen name="Perfil" component={ProfileScreen} />
    </Tab.Navigator>
  );
}

export default function AppNavigator() {
  const { user } = useAuth();
  const { colors, darkMode } = useTheme();

  const navTheme = {
    ...(darkMode ? DarkTheme : DefaultTheme),
    colors: {
      ...(darkMode ? DarkTheme : DefaultTheme).colors,
      background: colors.background,
      card: colors.surface,
      text: colors.text,
      primary: colors.primary,
      border: colors.border,
    },
  };

  const stackOptions = {
    headerStyle: { backgroundColor: colors.surface },
    headerTintColor: colors.text,
    headerTitleStyle: { fontWeight: '700' },
    contentStyle: { backgroundColor: colors.background },
  };

  return (
    <NavigationContainer theme={navTheme}>
      <Stack.Navigator screenOptions={stackOptions}>
        {!user ? (
          <>
            <Stack.Screen name="Login" component={LoginScreen} options={{ headerShown: false }} />
            <Stack.Screen name="Register" component={RegisterScreen} options={{ title: 'Registro' }} />
          </>
        ) : (
          <>
            <Stack.Screen name="Main" component={MainTabs} options={{ headerShown: false }} />
            <Stack.Screen name="EventForm" component={EventFormScreen} options={{ title: 'Evento' }} />
            <Stack.Screen name="EventDetail" component={EventDetailScreen} options={{ title: 'Detalle del evento' }} />
            <Stack.Screen name="TaskForm" component={TaskFormScreen} options={{ title: 'Tarea' }} />
            <Stack.Screen name="TaskDetail" component={TaskDetailScreen} options={{ title: 'Detalle de la tarea' }} />
            <Stack.Screen name="Tags" component={TagsScreen} options={{ title: 'Etiquetas' }} />
            <Stack.Screen name="ProjectForm" component={ProjectFormScreen} options={{ title: 'Proyecto' }} />
            <Stack.Screen name="ProjectDetail" component={ProjectDetailScreen} options={{ title: 'Proyecto' }} />
            <Stack.Screen name="EditProfile" component={EditProfileScreen} options={{ title: 'Datos personales' }} />
            <Stack.Screen name="ChangePassword" component={ChangePasswordScreen} options={{ title: 'Cambiar contraseña' }} />
            <Stack.Screen name="Preferences" component={PreferencesScreen} options={{ title: 'Preferencias' }} />
            <Stack.Screen name="Integrations" component={IntegrationsScreen} options={{ title: 'Integraciones' }} />
            <Stack.Screen name="Privacy" component={PrivacyScreen} options={{ title: 'Privacidad y datos' }} />
            <Stack.Screen name="Help" component={HelpScreen} options={{ title: 'Ayuda' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
