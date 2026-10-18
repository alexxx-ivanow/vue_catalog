import { createApp } from 'vue';
import CatalogList from './components/CatalogList.js';

const mountEl = document.getElementById('app-catalog');
if (mountEl) {
    createApp(CatalogList).mount(mountEl);
}
