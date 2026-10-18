import { createApp } from 'vue';
import SectionList from './components/SectionList.js';

const mountEl = document.getElementById('app-catalog');
if (mountEl) {
    createApp(SectionList).mount(mountEl);
}
