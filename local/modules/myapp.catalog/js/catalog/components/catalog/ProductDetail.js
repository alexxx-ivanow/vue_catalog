import AppPreloader from '../common/AppPreloader.js';
import { catalogApiUrl, getJsonHeaders, getReadHeaders } from '../../api-client.js';
import { syncCartCountFromResponse } from '../../cart-counter.js';
import { formatCatalogPrice } from '../../format-catalog-price.js';

export default {
    name: 'ProductDetail',
    emits: ['add-to-cart'],
    components: { AppPreloader },
    data() {
        return {
            product: null,
            productId: Number(window.__PAGE_DATA__?.product?.data?.ID || 0),
            loading: true,
            loadError: '',
            adding: false,
            added: false,
        };
    },
    mounted() {
        this.loadProduct();
    },
    methods: {
        displayPrice(product) {
            return formatCatalogPrice(product?.PRICE, product?.CURRENCY);
        },
        propertyValues(property) {
            if (!property) {
                return [];
            }
            if (property.multiple && Array.isArray(property.values)) {
                return property.values.filter((entry) => entry !== null && entry !== '');
            }
            const value = property.value;
            return value !== null && value !== undefined && String(value) !== '' ? [String(value)] : [];
        },
        isFileProperty(property) {
            return String(property?.type || '').toUpperCase() === 'F';
        },
        async loadProduct() {
            if (!this.productId) {
                this.loadError = 'Не удалось определить товар';
                this.loading = false;
                return;
            }

            this.loading = true;
            this.loadError = '';

            try {
                const res = await fetch(catalogApiUrl('catalog/goods/' + this.productId), {
                    headers: getReadHeaders(),
                });

                const data = await res.json();
                if (!res.ok) {
                    this.loadError = data?.error || 'Не удалось загрузить товар';
                    this.product = null;
                    return;
                }

                this.product = data;
            } catch (error) {
                this.loadError = 'Ошибка загрузки товара';
                this.product = null;
            } finally {
                this.loading = false;
            }
        },
        async handleAddToCart() {
            if (!this.product || !this.product.ID) return;
            this.adding = true;

            try {
                const res = await fetch(catalogApiUrl('catalog/cart/add'), {
                    method: 'POST',
                    headers: getJsonHeaders(),
                    body: JSON.stringify({
                        productId: this.product.ID,
                        quantity: 1,
                        sessid: window.__BX__.sessid,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    this.added = true;
                    if (data.sessid) window.__BX__.sessid = data.sessid;
                    syncCartCountFromResponse(data);
                    setTimeout(() => {
                        this.added = false;
                    }, 2000);
                }
            } catch (error) {
                console.log('Ошибка добавления товара: ', error);
            } finally {
                this.adding = false;
            }
        },
    },
    template: `
        <div class="catalog_list product_detail_wrap">
            <AppPreloader v-if="loading" :show="true" :center-page="true" text="Загрузка товара..." />
            <div v-else-if="loadError" class="catalog-empty">{{ loadError }}</div>
            <template v-else>
                <h1>{{ product.NAME }}</h1>
                <div class="catalog_item catalog_item--detail">
                <div class="catalog_item__link">
                    <p class="img_wrap">
                        <img :src="product.DETAIL_PICTURE" :alt="product.NAME" width="400" height="400"/>
                    </p>
                    <p class="catalog_item__name">{{ product.NAME }}</p>
                    <p class="catalog_item__desc" v-html="product.DETAIL_TEXT"></p>
                    <p class="catalog_item__price">{{ displayPrice(product) }}</p>
                    <dl
                        v-if="product.properties && product.properties.length"
                        class="product-detail__properties"
                    >
                        <template v-for="prop in product.properties" :key="prop.code">
                            <dt class="product-detail__prop-name">{{ prop.name }}</dt>
                            <dd class="product-detail__prop-value">
                                <template v-if="propertyValues(prop).length">
                                    <template v-for="(entry, index) in propertyValues(prop)" :key="prop.code + '-' + index">
                                        <a
                                            v-if="isFileProperty(prop)"
                                            :href="entry"
                                            class="product-detail__prop-link"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >{{ entry.split('/').pop() || entry }}</a>
                                        <span v-else>{{ entry }}</span>
                                        <span v-if="index < propertyValues(prop).length - 1">, </span>
                                    </template>
                                </template>
                            </dd>
                        </template>
                    </dl>
                </div>
                <button
                    class="catalog_item__btn"
                    :disabled="adding"
                    @click="handleAddToCart"
                >
                    {{ added ? '✓ Добавлено' : adding ? 'Добавляем...' : 'В корзину' }}
                </button>
            </div>
            </template>
        </div>
    `,
};
