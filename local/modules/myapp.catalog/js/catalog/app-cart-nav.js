import { createApp } from 'vue';
import SiteCartNav from './components/SiteCartNav.js';

const mountEl = document.getElementById('app-cart-nav');
if (mountEl) {
    createApp(SiteCartNav).mount(mountEl);
}
