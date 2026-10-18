import { createApp } from 'vue';
import CatalogBreadcrumbs from './components/CatalogBreadcrumbs.js';

const mountEl = document.getElementById('app-breadcrumbs');
if (mountEl) {
    createApp(CatalogBreadcrumbs).mount(mountEl);
}
