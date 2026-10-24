import { createApp } from 'vue';
import HomeCatalogSection from './components/HomeCatalogSection.js';

const mountEl = document.getElementById('app-home-catalog');
if (mountEl) {
    createApp(HomeCatalogSection).mount(mountEl);
}
