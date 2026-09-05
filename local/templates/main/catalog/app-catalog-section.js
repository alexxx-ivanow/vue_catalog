// /local/templates/main/js/catalog/app-catalog-section.js
import { createApp } from 'vue';
import SectionList from './components/SectionList.js';

const mountEl = document.getElementById('app-catalog');
if (mountEl) {
    createApp(SectionList).mount(mountEl);
}
console.log(window.__PAGE_DATA__.section)