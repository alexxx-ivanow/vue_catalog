import AppPreloader from '../common/AppPreloader.js';
import { catalogApiUrl, getJsonHeaders, getReadHeaders } from '../../api-client.js';
import { syncCartCountFromResponse } from '../../cart-counter.js';

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
                    <p class="catalog_item__price">{{ product.PRICE }} ₽</p>
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
