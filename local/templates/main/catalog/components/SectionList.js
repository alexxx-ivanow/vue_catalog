import ProductCard  from './ProductCard.js';
import CatalogPagination from './CatalogPagination.js';

export default {
    name: 'SectionList',
    components: { ProductCard, CatalogPagination },
    methods: {
        onPageData(data) {
            this.store.items = data;
            this.store.total = Number(data.total || 0);
            this.store.page = Number(data.page || 1);
            this.store.pages = Number(data.pages || 1);
            this.store.limit = Number(data.limit || this.store.limit);

            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('page', String(this.store.page));
            window.history.replaceState({}, '', nextUrl.toString());
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    },
    data() {
        const pagination = window.__PAGE_DATA__.section.pagination || {};

        return {
            store: {
                total: Number(pagination.total || window.__PAGE_DATA__.section.data.total || 0),
                limit: Number(pagination.limit || window.__PAGE_DATA__.section.data.limit || 20),
                page: Number(pagination.page || window.__PAGE_DATA__.section.data.page || 1),
                pages: Number(pagination.pages || window.__PAGE_DATA__.section.data.pages || 1),
                sectionId: Number(window.__PAGE_DATA__.section.id || 0),
                items: window.__PAGE_DATA__.section.data,
            },
        };
    },
    template: `
        <div class="catalog-page">
            <section class="catalog-page__content">
                <div class="catalog-page__toolbar">
                    <span>Найдено: {{ store.total }}</span>
                </div>

                <div class="catalog_list">
                    <ProductCard v-for="product in store.items.items" :key="product.ID" :product="product" />
                </div>

                <CatalogPagination
                    :current-page="store.page"
                    :total-pages="store.pages"
                    :limit="store.limit"
                    :section-id="store.sectionId"
                    @page-data="onPageData"
                />
            </section>
        </div>
    `,
};