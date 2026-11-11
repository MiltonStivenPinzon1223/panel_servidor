import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.personal.panelservidor',
  appName: 'Panel del servidor',
  webDir: 'dist',
  android: {
    allowMixedContent: false,
  },
}

export default config
