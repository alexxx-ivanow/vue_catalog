import ProductCard from './ProductCard.js';
import CatalogPagination from './CatalogPagination.js';
import CatalogFilter from './CatalogFilter.js';
import { catalogApiUrl, getReadHeaders } from '../apiClient.js';
import { clampFilterPrices, normalizePriceBounds } from '../filterPriceBounds.js';

const SKELETON_PRODUCT_COUNT = 8;
const DEFAULT_LIMIT = 12;

function parseIntList(params, key) {
    const values = [
        ...params.getAll(key),
        ...params.getAll(key + '[]'),
    ];
    const result = [];
    values.forEach((value) => {
        const intValue = Number(value);
        if (Number.isFinite(intValue) && intValue > 0) {
            result.push(intValue);
        }
    });
    return [...new Set(result)];
}

function parseStringList(params, key) {
    const values = [
        ...params.getAll(key),
        ...params.getAll(key + '[]'),
    ];
    const result = [];
    values.forEach((value) => {
        const str = String(value || '').trim();
        if (str !== '') {
            result.push(str);
        }
    });
    return [...new Set(result)];
}

function readInitialStateFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const page = Math.max(1, Number(params.get('page')) || 1);
    let limit = Number(params.get('limit')) || DEFAULT_LIMIT;
    if (!Number.isFinite(limit) || limit <= 0) {
        limit = DEFAULT_LIMIT;
    }
    limit = Math.max(1, Math.min(48, limit));

    const priceFromRaw = params.get('price_from');
    const priceToRaw = params.get('price_to');

    return {
        page,
        limit,
        filter: {
            manufacturers: parseIntList(params, 'manufacturers'),
            sizes: parseIntList(params, 'sizes'),
            materials: parseStringList(params, 'materials'),
            price_from: priceFromRaw !== null && priceFromRaw !== '' ? Number(priceFromRaw) : 0,
            price_to: priceToRaw !== null && priceToRaw !== '' ? Number(priceToRaw) : 0,
        },
    };
}

function readInitialSectionFromPage(urlState) {
    const pageType = String(window.__PAGE_DATA__?.page?.type || '');
    const section = window.__PAGE_DATA__?.section;
    const goods = section?.data;
    const filterMeta = section?.filter;

    if (
        pageType !== 'section'
        || !goods
        || !Array.isArray(goods.items)
        || !filterMeta
        || typeof filterMeta !== 'object'
        || Array.isArray(filterMeta)
    ) {
        return null;
    }

    return {
        total: Number(goods.total || 0),
        limit: Number(goods.limit || urlState.limit || DEFAULT_LIMIT),
        page: Number(goods.page || urlState.page || 1),
        pages: Number(goods.pages || 1),
        sectionId: Number(section.id || 0),
        items: goods,
        sectionName: goods.sectionName || '',
        subsections: Array.isArray(goods.subsections) ? goods.subsections : [],
        filterMeta: {
            manufacturers: Array.isArray(filterMeta.manufacturers) ? filterMeta.manufacturers : [],
            sizes: Array.isArray(filterMeta.sizes) ? filterMeta.sizes : [],
            materials: Array.isArray(filterMeta.materials) ? filterMeta.materials : [],
            price: normalizePriceBounds(filterMeta),
        },
        filter: clampFilterPrices(urlState.filter, filterMeta),
    };
}

export default {
    name: 'SectionList',
    components: { ProductCard, CatalogPagination, CatalogFilter },
    data() {
        const urlState = readInitialStateFromUrl();
        const initial = readInitialSectionFromPage(urlState);

        return {
            store: {
                total: initial ? initial.total : 0,
                limit: initial ? initial.limit : urlState.limit,
                page: initial ? initial.page : urlState.page,
                pages: initial ? initial.pages : 1,
                sectionId: initial
                    ? initial.sectionId
                    : Number(window.__PAGE_DATA__?.section?.id || 0),
                items: initial ? initial.items : { items: [] },
                sectionName: initial ? initial.sectionName : '',
                subsections: initial ? initial.subsections : [],
                loading: !initial,
                filtersLoaded: Boolean(initial),
                loadError: '',
                filterMeta: initial
                    ? initial.filterMeta
                    : {
                        manufacturers: [],
                        sizes: [],
                        materials: [],
                        price: { min: 0, max: 0 },
                    },
                filter: initial ? initial.filter : urlState.filter,
            },
            skeletonProductCount: SKELETON_PRODUCT_COUNT,
            isInitialLoad: !initial,
            hydratedFromPage: Boolean(initial),
        };
    },
    async mounted() {
        if (this.hydratedFromPage) {
            this.isInitialLoad = false;
            return;
        }
        await Promise.all([
            this.loadFilters(),
            this.loadGoods({ page: this.store.page, resetPage: false }),
        ]);
        this.isInitialLoad = false;
    },
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

            const { min: minPrice, max: maxPrice } = normalizePriceBounds(this.store.filterMeta);
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
                const res = await fetch(catalogApiUrl('catalog/filters') + '?' + params.toString(), {
                    headers: getReadHeaders(),
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data?.error || 'Ошибка загрузки фильтров');
                }

                this.store.filterMeta = {
                    ...data,
                    price: normalizePriceBounds(data),
                };
                this.store.filter = clampFilterPrices(this.store.filter, this.store.filterMeta);
            } catch (error) {
                console.error('Ошибка загрузки фильтров: ', error);
            } finally {
                this.store.filtersLoaded = true;
            }
        },
        async loadGoods({ page = 1, resetPage = false } = {}) {
            this.store.loading = true;
            this.store.loadError = '';

            try {
                const params = new URLSearchParams();
                const requestParams = {
                    ...this.buildFilterParams(),
                    section: this.store.sectionId,
                    page: resetPage ? 1 : page,
                    limit: this.store.limit,
                };
                Object.entries(requestParams).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((entry) => params.append(key + '[]', String(entry)));
                    } else if (value !== null && value !== undefined && value !== '') {
                        params.set(key, String(value));
                    }
                });

                const res = await fetch(catalogApiUrl('catalog/goods') + '?' + params.toString(), {
                    headers: getReadHeaders(),
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data?.error || 'Ошибка загрузки списка');
                }

                this.onPageData(data);
            } catch (error) {
                console.error('Ошибка загрузки списка: ', error);
                this.store.loadError = 'Не удалось загрузить товары';
                this.store.items = { items: [] };
                this.store.subsections = [];
            } finally {
                this.store.loading = false;
            }
        },
        async applyFilter() {
            await this.loadGoods({ page: 1, resetPage: true });
        },
        onPageData(data) {
            this.store.items = data;
            this.store.total = Number(data.total || 0);
            this.store.page = Number(data.page || 1);
            this.store.pages = Number(data.pages || 1);
            this.store.limit = Number(data.limit || this.store.limit);
            this.store.sectionName = data.sectionName || this.store.sectionName;
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

            const { min: minPrice, max: maxPrice } = normalizePriceBounds(this.store.filterMeta);
            if (Number(this.store.filter.price_from) > minPrice) {
                nextUrl.searchParams.set('price_from', String(this.store.filter.price_from));
            }
            if (Number(this.store.filter.price_to) < maxPrice) {
                nextUrl.searchParams.set('price_to', String(this.store.filter.price_to));
            }
            window.history.replaceState({}, '', nextUrl.toString());
            if (!this.isInitialLoad) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },
    },
    template: `
        <div class="catalog-page">
            <h1 class="catalog-page__title">
                <span v-if="store.loading && !store.sectionName" class="catalog-skeleton-line catalog-skeleton-line--title" aria-hidden="true"></span>
                <template v-else>{{ store.sectionName }}</template>
            </h1>
            <div class="catalog-layout">
                <aside class="catalog-layout__sidebar">
                    <div v-if="!store.filtersLoaded" class="catalog-filter-skeleton" aria-hidden="true">
                        <div class="catalog-skeleton-line catalog-skeleton-line--block"></div>
                        <div class="catalog-skeleton-line catalog-skeleton-line--block"></div>
                        <div class="catalog-skeleton-line catalog-skeleton-line--block catalog-skeleton-line--short"></div>
                    </div>
                    <CatalogFilter
                        v-else
                        v-model="store.filter"
                        :meta="store.filterMeta"
                        :applying="store.loading"
                        @apply="applyFilter"
                    />
                </aside>

                <section class="catalog-layout__content">
                    <div v-if="store.loading && !store.subsections.length" class="section-subcategories section-subcategories--skeleton" aria-hidden="true">
                        <span class="section-subcategories__skeleton-item"></span>
                        <span class="section-subcategories__skeleton-item"></span>
                        <span class="section-subcategories__skeleton-item"></span>
                        <span class="section-subcategories__skeleton-item"></span>
                    </div>
                    <div v-else-if="store.subsections.length" class="section-subcategories">
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
                        <span v-if="store.loading" class="catalog-skeleton-line catalog-skeleton-line--toolbar" aria-hidden="true"></span>
                        <span v-else>Найдено: {{ store.total }}</span>
                    </div>

                    <div v-if="store.loading" class="catalog_list" aria-busy="true" aria-label="Загрузка товаров">
                        <div
                            v-for="n in skeletonProductCount"
                            :key="'psk-' + n"
                            class="catalog_item catalog-skeleton-card"
                            aria-hidden="true"
                        >
                            <div class="catalog-skeleton-card__image"></div>
                            <div class="catalog-skeleton-card__line catalog-skeleton-card__line--title"></div>
                            <div class="catalog-skeleton-card__line catalog-skeleton-card__line--text"></div>
                            <div class="catalog-skeleton-card__line catalog-skeleton-card__line--price"></div>
                            <div class="catalog-skeleton-card__btn"></div>
                        </div>
                    </div>
                    <template v-else>
                        <div v-if="store.loadError" class="catalog-empty">{{ store.loadError }}</div>
                        <div v-else-if="store.items.items && store.items.items.length" class="catalog_list">
                            <ProductCard v-for="product in store.items.items" :key="product.ID" :product="product" />
                        </div>
                        <div v-else class="catalog-empty">Товары не найдены</div>
                    </template>

                    <div v-if="store.loading" class="catalog-pagination-placeholder" aria-hidden="true">
                        <span class="catalog-pagination-placeholder__btn"></span>
                        <span class="catalog-pagination-placeholder__btn"></span>
                        <span class="catalog-pagination-placeholder__btn"></span>
                        <span class="catalog-pagination-placeholder__dots">...</span>
                        <span class="catalog-pagination-placeholder__btn"></span>
                    </div>
                    <CatalogPagination
                        v-else
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
