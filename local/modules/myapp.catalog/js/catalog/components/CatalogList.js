import SectionCard from './SectionCard.js';
import { catalogApiUrl, getReadHeaders } from '../apiClient.js';

const SKELETON_COUNT = 8;

function readInitialCatalogFromPage() {
    const pageType = String(window.__PAGE_DATA__?.page?.type || '');
    const catalog = window.__PAGE_DATA__?.catalog;
    if (pageType !== 'catalog' || !catalog || !Array.isArray(catalog.items)) {
        return null;
    }

    return {
        items: catalog.items,
        total: Number(catalog.total ?? catalog.items.length),
    };
}

export default {
    name: 'CatalogList',
    components: { SectionCard },
    data() {
        const initial = readInitialCatalogFromPage();

        return {
            store: {
                total: initial ? initial.total : 0,
                limit: 20,
                filter: {
                    page: 1,
                    sort: 'new',
                },
                items: initial ? initial.items : [],
                loading: !initial,
                loadError: '',
            },
            skeletonCount: SKELETON_COUNT,
            hydratedFromPage: Boolean(initial),
        };
    },
    mounted() {
        if (this.hydratedFromPage) {
            return;
        }
        this.loadSections();
    },
    methods: {
        async loadSections() {
            this.store.loading = true;
            this.store.loadError = '';

            try {
                const res = await fetch(catalogApiUrl('catalog/sections'), {
                    headers: getReadHeaders(),
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data?.error || 'Ошибка загрузки каталога');
                }

                const items = Array.isArray(data) ? data : [];
                this.store.items = items;
                this.store.total = items.length;
            } catch (error) {
                console.error('Ошибка загрузки каталога: ', error);
                this.store.loadError = 'Не удалось загрузить каталог';
                this.store.items = [];
                this.store.total = 0;
            } finally {
                this.store.loading = false;
            }
        },
    },
    template: `
        <div class="catalog-page">
            <section class="catalog-page__content">
                <div class="catalog-page__toolbar">
                    <span v-if="store.loading" class="catalog-skeleton-line catalog-skeleton-line--toolbar" aria-hidden="true"></span>
                    <span v-else>Найдено: {{ store.total }}</span>
                </div>

                <div v-if="store.loading" class="catalog_list" aria-busy="true" aria-label="Загрузка каталога">
                    <div
                        v-for="n in skeletonCount"
                        :key="'sk-' + n"
                        class="catalog_item catalog-skeleton-card"
                        aria-hidden="true"
                    >
                        <div class="catalog-skeleton-card__image"></div>
                        <div class="catalog-skeleton-card__line catalog-skeleton-card__line--title"></div>
                        <div class="catalog-skeleton-card__line catalog-skeleton-card__line--text"></div>
                    </div>
                </div>

                <div v-else-if="store.loadError" class="catalog-empty">{{ store.loadError }}</div>

                <div v-else class="catalog_list">
                    <SectionCard v-for="section in store.items" :key="section.ID" :section="section"/>
                </div>
            </section>
        </div>
    `,
};
