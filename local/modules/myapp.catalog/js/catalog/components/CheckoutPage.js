import AppPreloader from './AppPreloader.js';
import CheckoutPersonType from './CheckoutPersonType.js';
import CheckoutLocationAutocomplete from './CheckoutLocationAutocomplete.js';
import CheckoutDeliveryList from './CheckoutDeliveryList.js';
import CheckoutPaymentList from './CheckoutPaymentList.js';
import CheckoutPropertiesForm from './CheckoutPropertiesForm.js';
import CheckoutBasketList from './CheckoutBasketList.js';
import CheckoutTotalsSidebar from './CheckoutTotalsSidebar.js';
import { apiGet, apiPost, catalogApiUrl } from '../apiClient.js';

export default {
    name: 'CheckoutPage',
    components: {
        AppPreloader,
        CheckoutPersonType,
        CheckoutLocationAutocomplete,
        CheckoutDeliveryList,
        CheckoutPaymentList,
        CheckoutPropertiesForm,
        CheckoutBasketList,
        CheckoutTotalsSidebar,
    },
    data() {
        return {
            loading: true,
            calculating: false,
            submitting: false,
            error: '',
            personTypes: [],
            deliveries: [],
            paySystems: [],
            properties: [],
            basket: { items: [], totals: {} },
            totals: {
                itemsSum: 0,
                delivery: 0,
                discount: 0,
                total: 0,
                currency: 'RUB',
            },
            selection: {
                personTypeId: 0,
                locationCode: '',
                locationLabel: '',
                deliveryId: 0,
                paySystemId: 0,
                properties: {},
                deliveryExtra: {},
            },
            calcTimer: null,
            suppressSelectionWatch: false,
        };
    },
    computed: {
        hasLocation() {
            return Boolean(this.selection.locationCode);
        },
        canShowDelivery() {
            return this.selection.personTypeId > 0 && this.hasLocation;
        },
        canShowPayment() {
            return this.canShowDelivery && this.selection.deliveryId > 0;
        },
        canShowProperties() {
            return this.canShowPayment && this.selection.paySystemId > 0;
        },
        canSubmit() {
            return this.canShowProperties && !this.calculating && !this.loading;
        },
    },
    watch: {
        'selection.personTypeId'(value, oldValue) {
            if (this.suppressSelectionWatch || value === oldValue) return;
            this.selection.deliveryId = 0;
            this.selection.paySystemId = 0;
            this.scheduleCalculate();
        },
        'selection.locationCode'(value, oldValue) {
            if (this.suppressSelectionWatch || value === oldValue) return;
            this.selection.deliveryId = 0;
            this.selection.paySystemId = 0;
            this.scheduleCalculate();
        },
        'selection.deliveryId'(value, oldValue) {
            if (this.suppressSelectionWatch || value === oldValue) return;
            this.selection.paySystemId = 0;
            this.selection.deliveryExtra = {};
            this.scheduleCalculate();
        },
        'selection.paySystemId'(value, oldValue) {
            if (this.suppressSelectionWatch || value === oldValue) return;
            this.scheduleCalculate();
        },
        'selection.properties': {
            deep: true,
            handler() {
                this.scheduleCalculate(false);
            },
        },
        'selection.deliveryExtra': {
            deep: true,
            handler() {
                this.scheduleCalculate(false);
            },
        },
    },
    mounted() {
        this.loadInit();
    },
    methods: {
        buildPayload() {
            return {
                personTypeId: this.selection.personTypeId,
                locationCode: this.selection.locationCode,
                locationLabel: this.selection.locationLabel,
                deliveryId: this.selection.deliveryId,
                paySystemId: this.selection.paySystemId,
                properties: this.selection.properties,
                deliveryExtra: this.selection.deliveryExtra,
            };
        },
        scheduleCalculate(resetErrors = true) {
            clearTimeout(this.calcTimer);
            this.calcTimer = setTimeout(() => this.calculate(resetErrors), 350);
        },
        syncSelectionFromResponse(data) {
            const sel = data?.selection || {};
            this.suppressSelectionWatch = true;
            try {
                if (Object.prototype.hasOwnProperty.call(sel, 'deliveryId')) {
                    this.selection.deliveryId = Number(sel.deliveryId) || 0;
                }
                if (Object.prototype.hasOwnProperty.call(sel, 'paySystemId')) {
                    this.selection.paySystemId = Number(sel.paySystemId) || 0;
                }
            } finally {
                queueMicrotask(() => {
                    this.suppressSelectionWatch = false;
                });
            }
        },
        applyCalculateResponse(data) {
            this.deliveries = data.deliveries || [];
            this.paySystems = data.paySystems || [];
            this.properties = data.properties || [];
            this.basket = data.basket || this.basket;
            this.totals = data.totals || this.totals;
            this.mergeIncomingProperties(this.properties);
            this.syncSelectionFromResponse(data);
        },
        normalizePropertyValue(value) {
            if (Array.isArray(value)) {
                const found = value.map((item) => this.normalizePropertyValue(item)).find((item) => item !== '');
                return found || '';
            }
            return String(value ?? '').trim();
        },
        mergeIncomingProperties(properties) {
            const next = { ...this.selection.properties };
            let changed = false;
            for (const property of properties) {
                const keys = [property.code, property.id ? String(property.id) : ''].filter(Boolean);
                const hasValue = keys.some((key) => this.normalizePropertyValue(next[key]) !== '');
                if (hasValue) {
                    continue;
                }
                const incoming = this.normalizePropertyValue(property.value);
                if (!incoming) {
                    continue;
                }
                for (const key of keys) {
                    next[key] = incoming;
                }
                changed = true;
            }
            if (changed) {
                this.selection.properties = next;
            }
        },
        syncPropertyFields() {
            const flushed = this.$refs.propertiesForm?.flushToModel?.();
            if (flushed && typeof flushed === 'object') {
                this.selection.properties = flushed;
                return flushed;
            }
            return this.selection.properties;
        },
        async loadInit() {
            this.loading = true;
            this.error = '';
            try {
                const { res, data } = await apiGet(catalogApiUrl('checkout/init'));
                if (!res.ok || !data.success) {
                    if (data.code === 'EMPTY_BASKET') {
                        window.location.href = '/cart/';
                        return;
                    }
                    this.error = data.error || 'Не удалось загрузить оформление заказа';
                    return;
                }

                this.personTypes = data.personTypes || [];
                this.basket = data.basket || { items: [], totals: {} };
                const basketTotals = data.basket?.totals || {};
                this.totals = {
                    ...this.totals,
                    itemsSum: Number(basketTotals.itemsSum) || 0,
                    discount: Number(basketTotals.discount) || 0,
                    total: Number(basketTotals.total) || 0,
                };
                this.selection = {
                    ...this.selection,
                    ...(data.selection || {}),
                    properties: {},
                    deliveryExtra: {},
                };

                await this.calculate(false);
            } catch (e) {
                this.error = 'Ошибка загрузки оформления заказа';
            } finally {
                this.loading = false;
            }
        },
        async calculate(resetErrors = true) {
            if (this.selection.personTypeId <= 0) {
                return;
            }

            this.calculating = true;
            if (resetErrors) {
                this.error = '';
            }

            try {
                const { res, data } = await apiPost(catalogApiUrl('checkout/calculate'), this.buildPayload());
                if (!res.ok || !data.success) {
                    if (data.code === 'EMPTY_BASKET') {
                        window.location.href = '/cart/';
                        return;
                    }
                    this.error = data.error || 'Не удалось пересчитать заказ';
                    return;
                }

                this.applyCalculateResponse(data);
            } catch (e) {
                this.error = 'Ошибка пересчёта заказа';
            } finally {
                this.calculating = false;
            }
        },
        validateClient() {
            const propertiesState = this.syncPropertyFields();
            for (const property of this.properties) {
                if (!property.required) continue;
                const keys = [property.code, property.id ? String(property.id) : ''].filter(Boolean);
                const value = keys.map((key) => propertiesState[key]).find((item) => String(item ?? '').trim() !== '');
                if (!String(value ?? '').trim()) {
                    this.error = `Заполните поле: ${property.name}`;
                    return false;
                }
            }
            if (!this.selection.deliveryId || !this.selection.paySystemId) {
                this.error = 'Выберите доставку и способ оплаты';
                return false;
            }
            return true;
        },
        async submitOrder() {
            if (!this.validateClient()) {
                return;
            }

            this.submitting = true;
            this.error = '';

            try {
                const { res, data } = await apiPost(catalogApiUrl('checkout/submit'), this.buildPayload());
                if (!res.ok || !data.success) {
                    this.error = data.error || 'Не удалось оформить заказ';
                    return;
                }

                if (data.redirectUrl) {
                    window.location.href = data.redirectUrl;
                    return;
                }

                if (data.successUrl) {
                    window.location.href = data.successUrl;
                    return;
                }
            } catch (e) {
                this.error = 'Ошибка оформления заказа';
            } finally {
                this.submitting = false;
            }
        },
    },
    template: `
        <div class="checkout">
            <AppPreloader v-if="loading" :show="true" :center-page="true" text="Загрузка оформления..." />

            <template v-else>
                <h1 class="checkout__title">Оформление заказа</h1>
                <p v-if="error" class="checkout_error">{{ error }}</p>

                <div class="checkout_layout">
                    <div class="checkout_main">
                        <CheckoutPersonType
                            v-model="selection.personTypeId"
                            :person-types="personTypes"
                        />

                        <CheckoutLocationAutocomplete
                            v-model="selection.locationCode"
                            v-model:label-value="selection.locationLabel"
                            :disabled="selection.personTypeId <= 0"
                        />

                        <CheckoutDeliveryList
                            v-model="selection.deliveryId"
                            v-model:delivery-extra="selection.deliveryExtra"
                            :deliveries="deliveries"
                            :disabled="!canShowDelivery"
                        />

                        <CheckoutPaymentList
                            v-model="selection.paySystemId"
                            :pay-systems="paySystems"
                            :disabled="!canShowPayment"
                        />

                        <CheckoutPropertiesForm
                            ref="propertiesForm"
                            v-model="selection.properties"
                            :properties="properties"
                            :disabled="!canShowProperties"
                        />

                        <CheckoutBasketList :items="basket.items || []" />
                    </div>

                    <CheckoutTotalsSidebar
                        :totals="totals"
                        :submitting="submitting"
                        :disabled="!canSubmit"
                        @submit="submitOrder"
                    />
                </div>
            </template>
        </div>
    `,
};
