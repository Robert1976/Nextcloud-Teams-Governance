import { createApp } from 'vue'
import App from './App.vue'

const mountTarget = document.getElementById('teamsgovernance')

if (mountTarget) {
	const app = createApp(App)
	app.mount(mountTarget)
}
