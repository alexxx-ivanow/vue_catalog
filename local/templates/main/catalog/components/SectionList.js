import ProductCard  from './ProductCard.js';
import CatalogPagination from './CatalogPagination.js';

export default {
    name: 'SectionList',
    components: { ProductCard, CatalogPagination },
    methods: {        
        async onPageChange(page) {
            if (this.store.loading || page === this.store.page) return;

            this.store.loading = true;

            try {
                const params = new URLSearchParams({
                    section: String(window.__PAGE_DATA__.section.id),
                    page: String(page),
                    limit: String(this.store.limit),
                });

                const res = await fetch('/api/v1/catalog/goods?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();

                this.store.items = data;
                this.store.total = Number(data.total || 0);
                this.store.page = Number(data.page || 1);
                this.store.pages = Number(data.pages || 1);
                this.store.limit = Number(data.limit || this.store.limit);

                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('page', String(this.store.page));
                window.history.replaceState({}, '', nextUrl.toString());
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (error) {
                console.error('Ошибка загрузки страницы раздела: ', error);
            } finally {
                this.store.loading = false;
            }
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
                items: window.__PAGE_DATA__.section.data,
                loading: false,
            },
        };
    },
    template: `
        <div class="catalog-page">
            <section class="catalog-page__content">
                <div class="catalog-page__toolbar">
                    <span>Найдено: {{ store.total }}</span>
                </div>

                <div v-if="store.loading" class="catalog-page__loader">Загрузка…</div>

                <div v-else class="catalog_list">
                    <product-card v-for="product in store.items.items" :key="product.ID" :product="product" />
                </div>

                <catalog-pagination
                    :current-page="store.page"
                    :total-pages="store.pages"
                    @change="onPageChange"
                />
            </section>
        </div>
    `,
};