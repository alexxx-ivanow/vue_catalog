export default {
    name: 'ProductDetail',
    emits: ['add-to-cart'],
    data() {
        return {
            product: window.__PAGE_DATA__.product.data,
            adding: false,
            added: false,
        };
    },
    methods: {
        async handleAddToCart() {
            this.adding = true;

            try {
                const res = await fetch('/api/v1/catalog/cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        productId: this.product.ID,
                        quantity: 1,
                        sessid: window.__BX__.sessid,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    this.added = true;
                    // Update sessid if server rotates it.
                    if (data.sessid) window.__BX__.sessid = data.sessid;
                    setTimeout(() => {
                        this.added = false;
                    }, 2000);
                }

                //this.$emit('add-to-cart', this.product);
            } catch (error) {
                console.log('Ошибка добавления товара: ', error);
            } finally {
                this.adding = false;
            }
        },
    },
    template: `
        <div class="catalog_list product_detail_wrap">
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
        </div>
    `,
};
