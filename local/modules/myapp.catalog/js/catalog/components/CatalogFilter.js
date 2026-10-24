export default {
    name: 'CatalogFilter',

    props: {
        meta: { type: Object, default: () => ({}) },
        modelValue: { type: Object, default: () => ({}) },
        applying: { type: Boolean, default: false },
    },

    emits: ['update:modelValue', 'apply'],

    data() {
        return {
            openSections: {
                manufacturers: false,
                sizes: false,
                materials: false,
                price: false,
            },
            openSectionsInitialized: false,
        };
    },

    mounted() {
        this.applyOpenSectionsFromActiveFilters();
    },

    watch: {
        modelValue: {
            deep: true,
            handler() {
                this.applyOpenSectionsFromActiveFilters();
            },
        },
        meta: {
            deep: true,
            handler() {
                this.applyOpenSectionsFromActiveFilters();
            },
        },
    },

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
        applyOpenSectionsFromActiveFilters() {
            if (this.openSectionsInitialized) {
                return;
            }

            const filter = this.localFilter;
            const min = this.priceMeta.min;
            const max = this.priceMeta.max;
            const metaPriceReady = Number.isFinite(max);

            const hasCheckbox =
                filter.manufacturers.length > 0 ||
                filter.sizes.length > 0 ||
                filter.materials.length > 0;

            if (!metaPriceReady && !hasCheckbox) {
                return;
            }

            let priceActive = false;
            if (metaPriceReady) {
                priceActive =
                    Number(filter.price_from) > min || Number(filter.price_to) < max;
            }

            this.openSections = {
                manufacturers: filter.manufacturers.length > 0,
                sizes: filter.sizes.length > 0,
                materials: filter.materials.length > 0,
                price: priceActive,
            };
            this.openSectionsInitialized = true;
        },
        toggleSection(key) {
            if (!Object.prototype.hasOwnProperty.call(this.openSections, key)) {
                return;
            }
            this.openSections[key] = !this.openSections[key];
        },
        isSectionOpen(key) {
            return Boolean(this.openSections[key]);
        },
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
        collapseAllSections() {
            this.openSections = {
                manufacturers: false,
                sizes: false,
                materials: false,
                price: false,
            };
        },
        reset() {
            this.localFilter = {
                manufacturers: [],
                sizes: [],
                materials: [],
                price_from: this.priceMeta.min,
                price_to: this.priceMeta.max,
            };
            this.collapseAllSections();
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

            <div
                v-if="manufacturers.length"
                class="catalog-filter__field"
                :class="{ 'catalog-filter__field--open': isSectionOpen('manufacturers') }"
            >
                <button
                    type="button"
                    class="catalog-filter__field-head"
                    :aria-expanded="isSectionOpen('manufacturers')"
                    @click="toggleSection('manufacturers')"
                >
                    <span class="catalog-filter__label">Производитель</span>
                    <span class="catalog-filter__toggle" aria-hidden="true"></span>
                </button>
                <div v-show="isSectionOpen('manufacturers')" class="catalog-filter__field-body">
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
            </div>

            <div
                v-if="sizes.length"
                class="catalog-filter__field"
                :class="{ 'catalog-filter__field--open': isSectionOpen('sizes') }"
            >
                <button
                    type="button"
                    class="catalog-filter__field-head"
                    :aria-expanded="isSectionOpen('sizes')"
                    @click="toggleSection('sizes')"
                >
                    <span class="catalog-filter__label">Размеры</span>
                    <span class="catalog-filter__toggle" aria-hidden="true"></span>
                </button>
                <div v-show="isSectionOpen('sizes')" class="catalog-filter__field-body">
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
            </div>

            <div
                v-if="materials.length"
                class="catalog-filter__field"
                :class="{ 'catalog-filter__field--open': isSectionOpen('materials') }"
            >
                <button
                    type="button"
                    class="catalog-filter__field-head"
                    :aria-expanded="isSectionOpen('materials')"
                    @click="toggleSection('materials')"
                >
                    <span class="catalog-filter__label">Материал</span>
                    <span class="catalog-filter__toggle" aria-hidden="true"></span>
                </button>
                <div v-show="isSectionOpen('materials')" class="catalog-filter__field-body">
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
            </div>

            <div
                class="catalog-filter__field"
                :class="{ 'catalog-filter__field--open': isSectionOpen('price') }"
            >
                <button
                    type="button"
                    class="catalog-filter__field-head"
                    :aria-expanded="isSectionOpen('price')"
                    @click="toggleSection('price')"
                >
                    <span class="catalog-filter__label">Цена</span>
                    <span class="catalog-filter__toggle" aria-hidden="true"></span>
                </button>
                <div v-show="isSectionOpen('price')" class="catalog-filter__field-body">
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
            </div>

            <div class="catalog-filter__buttons">
                <button
                    type="button"
                    class="catalog_item__btn catalog-filter__apply"
                    :disabled="applying"
                    :aria-busy="applying"
                    @click="apply"
                >
                    <svg
                        v-if="applying"
                        class="app-preloader__spinner catalog-filter__apply-spinner"
                        viewBox="0 0 50 50"
                        aria-hidden="true"
                    >
                        <circle class="app-preloader__track" cx="25" cy="25" r="20"></circle>
                        <circle class="app-preloader__stroke" cx="25" cy="25" r="20"></circle>
                    </svg>
                    <span v-else>Применить</span>
                </button>
                <button type="button" class="catalog-filter__reset" :disabled="applying" @click="reset">Сбросить</button>
            </div>
        </div>
    `,
};
