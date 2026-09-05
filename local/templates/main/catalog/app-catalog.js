// /local/templates/main/js/catalog/app-catalog.js
import { createApp } from 'vue';
import CatalogList from './components/CatalogList.js';

const mountEl = document.getElementById('app-catalog');
if (mountEl) {
    createApp(CatalogList).mount(mountEl);
}
console.log(window.__PAGE_DATA__.catalog)