import { apiGet, catalogApiUrl } from '../apiClient.js';

export default {
    name: 'CheckoutLocationAutocomplete',
    props: {
        modelValue: { type: String, default: '' },
        labelValue: { type: String, default: '' },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'update:labelValue', 'selected'],
    data() {
        return {
            query: this.labelValue || '',
            items: [],
            open: false,
            loading: false,
            debounceTimer: null,
        };
    },
    watch: {
        labelValue(value) {
            if (value !== this.query) {
                this.query = value;
            }
        },
    },
    methods: {
        onInput() {
            this.$emit('update:labelValue', this.query);
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.search(), 300);
        },
        async search() {
            const q = this.query.trim();
            if (q.length < 2) {
                this.items = [];
                this.open = false;
                return;
            }

            this.loading = true;
            try {
                const params = new URLSearchParams({ q, limit: '10' });
                const { data } = await apiGet(catalogApiUrl('checkout/locations') + '?' + params.toString());
                this.items = Array.isArray(data.items) ? data.items : [];
                this.open = this.items.length > 0;
            } catch (e) {
                this.items = [];
                this.open = false;
            } finally {
                this.loading = false;
            }
        },
        selectItem(item) {
            this.query = item.displayName;
            this.open = false;
            this.$emit('update:modelValue', item.code);
            this.$emit('update:labelValue', item.displayName);
            this.$emit('selected', item);
        },
    },
    template: `
        <section class="checkout_section" :class="{ 'checkout_section--disabled': disabled }">
            <h2 class="checkout_section__title">Местоположение</h2>
            <div class="checkout_location">
                <input
                    class="checkout_location__input"
                    type="text"
                    placeholder="Начните вводить город"
                    v-model="query"
                    :disabled="disabled"
                    @input="onInput"
                    @focus="search"
                />
                <div v-if="open" class="checkout_location__dropdown">
                    <button
                        v-for="item in items"
                        :key="item.code + '-' + item.id"
                        type="button"
                        class="checkout_location__item"
                        @click="selectItem(item)"
                    >{{ item.displayName }}</button>
                </div>
                <p v-if="loading" class="checkout_option__meta">Поиск...</p>
            </div>
        </section>
    `,
};
