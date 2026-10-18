export default {
    name: 'OrderSuccessPage',
    data() {
        return {
            order: window.__PAGE_DATA__?.order?.data || {},
        };
    },
    methods: {
        formatPrice(value) {
            const num = Number(value) || 0;
            return num.toLocaleString('ru-RU', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }) + ' ₽';
        },
    },
    template: `
        <div class="checkout_success">
            <h1 class="checkout__title">Заказ оформлен</h1>
            <p v-if="order.accountNumber">Номер заказа: <strong>{{ order.accountNumber }}</strong></p>
            <p v-if="order.price">Сумма: <strong>{{ formatPrice(order.price) }}</strong></p>
            <p class="checkout_option__meta" v-if="order.dateInsert">Дата: {{ order.dateInsert }}</p>
            <p><a href="/catalog/" class="cart__link">Вернуться в каталог</a></p>
        </div>
    `,
};
