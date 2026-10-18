export default {
    name: 'CatalogFilter',

    props: {
        meta: { type: Object, default: () => ({}) },
        modelValue: { type: Object, default: () => ({}) },
    },

    emits: ['update:modelValue', 'apply'],
    computed: {
        manufacturers() {
            return Array.isArray(this.meta.manufacturers) ? this.meta.manufacturers : [];
        },
        sizes() {
            return Array.isArray(this.meta.sizes) ? this.meta.sizes : [];
        },
        materials() {
            return Array.isArray(this.meta.materials) ? this.meta.materials : [];
        },
        priceMeta() {
            const source = this.meta.price || {};
            const min = Number(source.min ?? 0);
            const max = Number(source.max ?? min);
            return {
                min: Number.isFinite(min) ? min : 0,
                max: Number.isFinite(max) ? max : 0,
            };
        },
        localFilter: {
            get() {
                const source = this.modelValue || {};
                return {
                    manufacturers: Array.isArray(source.manufacturers) ? source.manufacturers : [],
                    sizes: Array.isArray(source.sizes) ? source.sizes : [],
                    materials: Array.isArray(source.materials) ? source.materials : [],
                    price_from: source.price_from ?? this.priceMeta.min,
                    price_to: source.price_to ?? this.priceMeta.max,
                };
            },
            set(value) {
                this.$emit('update:modelValue', value);
            },
        },
    },
    methods: {
        toggleInt(field, rawValue) {
            const value = Number(rawValue);
            const next = { ...this.localFilter };
            const list = Array.isArray(next[field]) ? [...next[field]] : [];
            const idx = list.indexOf(value);
            if (idx >= 0) {
                list.splice(idx, 1);
            } else {
                list.push(value);
            }
            next[field] = list;
            this.localFilter = next;
        },
        toggleString(field, rawValue) {
            const value = String(rawValue);
            const next = { ...this.localFilter };
            const list = Array.isArray(next[field]) ? [...next[field]] : [];
            const idx = list.indexOf(value);
            if (idx >= 0) {
                list.splice(idx, 1);
            } else {
                list.push(value);
            }
            next[field] = list;
            this.localFilter = next;
        },
        onPriceInput(field, rawValue) {
            const value = Number(rawValue);
            const next = { ...this.localFilter };
            next[field] = Number.isFinite(value) ? value : 0;
            if (field === 'price_from' && next.price_from > next.price_to) {
                next.price_to = next.price_from;
            }
            if (field === 'price_to' && next.price_to < next.price_from) {
                next.price_from = next.price_to;
            }
            this.localFilter = next;
        },
        apply() {
            this.$emit('apply');
        },
        reset() {
            this.localFilter = {
                manufacturers: [],
                sizes: [],
                materials: [],
                price_from: this.priceMeta.min,
                price_to: this.priceMeta.max,
            };
            this.$emit('apply');
        },
        isChecked(field, rawValue) {
            const list = this.localFilter[field] || [];
            return list.includes(rawValue);
        },
    },
    template: `
        <div class="catalog-filter">
            <h3 class="catalog-filter__title">Фильтр</h3>

            <div class="catalog-filter__field">
                <label class="catalog-filter__label">Производитель</label>
                <label
                    class="catalog-filter__check"
                    v-for="m in manufacturers"
                    :key="m.ID"
                >
                    <input
                        type="checkbox"
                        :checked="isChecked('manufacturers', Number(m.ID))"
                        @change="toggleInt('manufacturers', m.ID)"
                    />
                    <span>{{ m.VALUE }}</span>
                </label>
            </div>

            <div class="catalog-filter__field">
                <label class="catalog-filter__label">Размеры</label>
                <label
                    class="catalog-filter__check"
                    v-for="s in sizes"
                    :key="s.ID"
                >
                    <input
                        type="checkbox"
                        :checked="isChecked('sizes', Number(s.ID))"
                        @change="toggleInt('sizes', s.ID)"
                    />
                    <span>{{ s.VALUE }}</span>
                </label>
            </div>

            <div class="catalog-filter__field">
                <label class="catalog-filter__label">Материал</label>
                <label
                    class="catalog-filter__check"
                    v-for="m in materials"
                    :key="m.VALUE"
                >
                    <input
                        type="checkbox"
                        :checked="isChecked('materials', m.VALUE)"
                        @change="toggleString('materials', m.VALUE)"
                    />
                    <span>{{ m.VALUE }}</span>
                </label>
            </div>

            <div class="catalog-filter__field">
                <label class="catalog-filter__label">Цена</label>
                <div class="catalog-filter__price-inputs">
                    <input
                        type="number"
                        :min="priceMeta.min"
                        :max="priceMeta.max"
                        :value="localFilter.price_from"
                        @input="onPriceInput('price_from', $event.target.value)"
                        placeholder="От"
                    />
                    <input
                        type="number"
                        :min="priceMeta.min"
                        :max="priceMeta.max"
                        :value="localFilter.price_to"
                        @input="onPriceInput('price_to', $event.target.value)"
                        placeholder="До"
                    />
                </div>
                <div class="catalog-filter__price-display">
                    <span>От {{ localFilter.price_from }} ₽</span>
                    <span>До {{ localFilter.price_to }} ₽</span>
                </div>
                <div class="catalog-filter__range">
                    <input
                        type="range"
                        :min="priceMeta.min"
                        :max="priceMeta.max"
                        :value="localFilter.price_from"
                        @input="onPriceInput('price_from', $event.target.value)"
                    />
                    <input
                        type="range"
                        :min="priceMeta.min"
                        :max="priceMeta.max"
                        :value="localFilter.price_to"
                        @input="onPriceInput('price_to', $event.target.value)"
                    />
                </div>
            </div>

            <div class="catalog-filter__buttons">
                <button class="catalog_item__btn" @click="apply">Применить</button>
                <button class="catalog-filter__reset" @click="reset">Сбросить</button>
            </div>
        </div>
    `,
};