import { createRouter, createWebHistory } from '@ionic/vue-router'
import { defineComponent, h } from 'vue'
import { IonRouterOutlet, IonTabBar, IonTabButton, IonTabs, IonLabel, IonIcon } from '@ionic/vue'
import { homeOutline, appsOutline, alertCircleOutline, settingsOutline } from 'ionicons/icons'
import HomePage from './views/HomePage.vue'
import AppsPage from './views/AppsPage.vue'
import AlertsPage from './views/AlertsPage.vue'
import SettingsPage from './views/SettingsPage.vue'
import AppDetailPage from './views/AppDetailPage.vue'
import AppFormPage from './views/AppFormPage.vue'
import LoginPage from './views/LoginPage.vue'

const Tabs = defineComponent({
  setup: () => () => h(IonTabs, null, {
    default: () => [
      h(IonRouterOutlet),
      h(IonTabBar, { slot: 'bottom' }, [
        h(IonTabButton, { tab: 'home', href: '/tabs/home' }, [h(IonIcon, { icon: homeOutline }), h(IonLabel, 'Inicio')]),
        h(IonTabButton, { tab: 'apps', href: '/tabs/apps' }, [h(IonIcon, { icon: appsOutline }), h(IonLabel, 'Apps')]),
        h(IonTabButton, { tab: 'alerts', href: '/tabs/alerts' }, [h(IonIcon, { icon: alertCircleOutline }), h(IonLabel, 'Alertas')]),
        h(IonTabButton, { tab: 'settings', href: '/tabs/settings' }, [h(IonIcon, { icon: settingsOutline }), h(IonLabel, 'Ajustes')]),
      ]),
    ],
  }),
})

export default createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/tabs/home' },
    { path: '/login', component: LoginPage },
    {
      path: '/tabs/',
      component: Tabs,
      children: [
        { path: 'home', component: HomePage },
        { path: 'apps', component: AppsPage },
        { path: 'alerts', component: AlertsPage },
        { path: 'settings', component: SettingsPage },
        { path: 'apps/new', component: AppFormPage },
        { path: 'apps/:id', component: AppDetailPage },
        { path: 'apps/:id/edit', component: AppFormPage },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/tabs/home' },
  ],
})
