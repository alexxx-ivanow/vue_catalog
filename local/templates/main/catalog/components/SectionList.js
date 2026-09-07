import ProductCard  from './ProductCard.js';
import CatalogPagination from './CatalogPagination.js';
import CatalogFilter from './CatalogFilter.js';

export default {
    name: 'SectionList',
    components: { ProductCard, CatalogPagination, CatalogFilter },
    methods: {
        buildFilterParams() {
            const params = {};

            if (this.store.filter.manufacturers.length) {
                params.manufacturers = this.store.filter.manufacturers;
            }
            if (this.store.filter.sizes.length) {
                params.sizes = this.store.filter.sizes;
            }
            if (this.store.filter.materials.length) {
                params.materials = this.store.filter.materials;
            }

            const minPrice = Number(this.store.filterMeta.price?.min ?? 0);
            const maxPrice = Number(this.store.filterMeta.price?.max ?? 0);
            if (Number(this.store.filter.price_from) > minPrice) {
                params.price_from = this.store.filter.price_from;
            }
            if (Number(this.store.filter.price_to) < maxPrice) {
                params.price_to = this.store.filter.price_to;
            }

            return params;
        },
        async loadFilters() {
            try {
                const params = new URLSearchParams({
                    section: String(this.store.sectionId),
                });
                const res = await fetch('/api/v1/catalog/filters?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data?.error || 'Ошибка загрузки фильтров');
                }

                this.store.filterMeta = data;
                const minPrice = Number(data.price?.min ?? 0);
                const maxPrice = Number(data.price?.max ?? 0);

                if (!Number.isFinite(this.store.filter.price_from) || this.store.filter.price_from <= 0) {
                    this.store.filter.price_from = minPrice;
                } else {
                    this.store.filter.price_from = Math.max(minPrice, Math.min(this.store.filter.price_from, maxPrice));
                }

                if (!Number.isFinite(this.store.filter.price_to) || this.store.filter.price_to <= 0) {
                    this.store.filter.price_to = maxPrice;
                } else {
                    this.store.filter.price_to = Math.max(minPrice, Math.min(this.store.filter.price_to, maxPrice));
                }
            } catch (error) {
                console.error('Ошибка загрузки фильтров: ', error);
            }
        },
        async applyFilter() {
            this.store.loading = true;
            try {
                const params = new URLSearchParams();
                const requestParams = {
                    ...this.buildFilterParams(),
                    section: this.store.sectionId,
                    page: 1,
                    limit: this.store.limit,
                };
                Object.entries(requestParams).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((entry) => params.append(key + '[]', String(entry)));
                    } else if (value !== null && value !== undefined && value !== '') {
                        params.set(key, String(value));
                    }
                });

                const res = await fetch('/api/v1/catalog/goods?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data?.error || 'Ошибка загрузки списка');
                }

                this.onPageData(data);
            } catch (error) {
                console.error('Ошибка фильтрации: ', error);
            } finally {
                this.store.loading = false;
            }
        },
        onPageData(data) {
            this.store.items = data;
            this.store.total = Number(data.total || 0);
            this.store.page = Number(data.page || 1);
            this.store.pages = Number(data.pages || 1);
            this.store.limit = Number(data.limit || this.store.limit);
            this.store.subsections = Array.isArray(data.subsections) ? data.subsections : this.store.subsections;

            const nextUrl = new URL(window.location.href);
            if (this.store.page <= 1) {
                nextUrl.searchParams.delete('page');
            } else {
                nextUrl.searchParams.set('page', String(this.store.page));
            }
            nextUrl.searchParams.delete('manufacturers[]');
            nextUrl.searchParams.delete('manufacturers');
            nextUrl.searchParams.delete('sizes[]');
            nextUrl.searchParams.delete('sizes');
            nextUrl.searchParams.delete('materials[]');
            nextUrl.searchParams.delete('materials');
            nextUrl.searchParams.delete('price_from');
            nextUrl.searchParams.delete('price_to');
            this.store.filter.manufacturers.forEach((id) => nextUrl.searchParams.append('manufacturers[]', String(id)));
            this.store.filter.sizes.forEach((id) => nextUrl.searchParams.append('sizes[]', String(id)));
            this.store.filter.materials.forEach((name) => nextUrl.searchParams.append('materials[]', String(name)));

            const minPrice = Number(this.store.filterMeta.price?.min ?? 0);
            const maxPrice = Number(this.store.filterMeta.price?.max ?? 0);
            if (Number(this.store.filter.price_from) > minPrice) {
                nextUrl.searchParams.set('price_from', String(this.store.filter.price_from));
            }
            if (Number(this.store.filter.price_to) < maxPrice) {
                nextUrl.searchParams.set('price_to', String(this.store.filter.price_to));
            }
            window.history.replaceState({}, '', nextUrl.toString());
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    },
    async mounted() {
        await this.loadFilters();
    },
    data() {
        const pagination = window.__PAGE_DATA__.section.pagination || {};
        const initialFilter = window.__PAGE_DATA__.section.filter || {};

        return {
            store: {
                total: Number(pagination.total || window.__PAGE_DATA__.section.data.total || 0),
                limit: Number(pagination.limit || window.__PAGE_DATA__.section.data.limit || 20),
                page: Number(pagination.page || window.__PAGE_DATA__.section.data.page || 1),
                pages: Number(pagination.pages || window.__PAGE_DATA__.section.data.pages || 1),
                sectionId: Number(window.__PAGE_DATA__.section.id || 0),
                items: window.__PAGE_DATA__.section.data,
                sectionName: window.__PAGE_DATA__.section.data.sectionName,
                subsections: Array.isArray(window.__PAGE_DATA__.section.data.subsections)
                    ? window.__PAGE_DATA__.section.data.subsections
                    : [],
                loading: false,
                filterMeta: {
                    manufacturers: [],
                    sizes: [],
                    materials: [],
                    price: { min: 0, max: 0 },
                },
                filter: {
                    manufacturers: Array.isArray(initialFilter.manufacturers) ? initialFilter.manufacturers.map(Number) : [],
                    sizes: Array.isArray(initialFilter.sizes) ? initialFilter.sizes.map(Number) : [],
                    materials: Array.isArray(initialFilter.materials) ? initialFilter.materials.map(String) : [],
                    price_from: Number(initialFilter.price_from ?? 0),
                    price_to: Number(initialFilter.price_to ?? 0),
                },
            },
        };
    },
    template: `
        <div class="catalog-page">
            <h1 style="text-align:center;">{{ store.sectionName }}</h1>
            <div class="catalog-layout">
                <aside class="catalog-layout__sidebar">
                    <CatalogFilter
                        v-if="store.filterMeta"
                        v-model="store.filter"
                        :meta="store.filterMeta"
                        @apply="applyFilter"
                    />
                </aside>

                <section class="catalog-layout__content">
                    <div v-if="store.subsections.length" class="section-subcategories">
                        <a
                            v-for="sub in store.subsections"
                            :key="sub.ID"
                            :href="sub.URL || ('/catalog/section/' + sub.ID)"
                            class="section-subcategories__item"
                        >
                            {{ sub.NAME }}
                        </a>
                    </div>

                    <div class="catalog-page__toolbar">
                        <span>Найдено: {{ store.total }}</span>
                    </div>

                    <div v-if="store.loading" class="catalog-loading">Загрузка...</div>
                    <template v-else>
                        <div v-if="store.items.items.length" class="catalog_list">
                            <ProductCard v-for="product in store.items.items" :key="product.ID" :product="product" />
                        </div>
                        <div v-else class="catalog-empty">Товары не найдены</div>
                    </template>

                    <CatalogPagination
                        :current-page="store.page"
                        :total-pages="store.pages"
                        :limit="store.limit"
                        :section-id="store.sectionId"
                        :request-params="buildFilterParams()"
                        @page-data="onPageData"
                    />
                </section>
            </div>
        </div>
    `,
};