import SectionCard  from './SectionCard.js';
export default {
    name: 'CatalogList',
    components: { SectionCard },     
    data() {
        return {
            store: {
                total: window.__PAGE_DATA__.catalog.total,                
                limit: 20,
                filter: {
                    page: 1,
                    sort: 'new',
                },
                items: window.__PAGE_DATA__.catalog.items
            }
        };
    },
    template: `
        <div class="catalog-page">
            <filter-panel v-if="store.filterMeta" :meta="store.filterMeta" :filter="store.filter" @change="onFilterChange" />
            <section class="catalog-page__content">
                <div class="catalog-page__toolbar">
                    <span>Найдено: {{ store.total }}</span>                    
                </div>

                <div v-if="store.loading" class="catalog-page__loader">Загрузка…</div>

                <div v-else class="catalog_list">
                    <SectionCard v-for="section in store.items" :key="section.ID" :section="section"/>
                </div>
            </section>
        </div>
    `,
};