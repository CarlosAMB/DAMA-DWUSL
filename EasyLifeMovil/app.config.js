/**
 * Configuracion dinamica de Expo (extiende app.json).
 * google-services.json (Firebase para Android) no se sube a GitHub:
 *  - En local se usa ./google-services.json
 *  - En EAS Build se toma de la variable de archivo GOOGLE_SERVICES_JSON (eas env:create --type file)
 */
module.exports = ({ config }) => ({
  ...config,
  android: {
    ...config.android,
    googleServicesFile: process.env.GOOGLE_SERVICES_JSON ?? './google-services.json',
  },
});
