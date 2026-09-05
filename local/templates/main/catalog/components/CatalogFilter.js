import { reactive, watch } from 'vue';

export default {
    name: 'CatalogFilter',

    props: {
        manufacturers: { type: Array,  default: () => [] },
        sizes:         { type: Array,  default: () => [] },
        priceMin:      { type: Number, default: 0 },
        priceMax:      { type: Number, default: 100000 },
    },

    emits: ['apply'],

    setup(props, { emit }) {
        const filter = reactive({
            article:      '',
            manufacturer: '',
            size:         '',
            price_from:   props.priceMin,
            price_to:     props.priceMax,
        });

        // Когда данные из API пришли — обновляем диапазон цен
        watch(() => props.priceMin, (val) => filter.price_from = val);
        watch(() => props.priceMax, (val) => filter.price_to   = val);

        function apply() {
            const result = {};
            if (filter.article)                       result.article      = filter.article.trim();
            if (filter.manufacturer)                  result.manufacturer = filter.manufacturer;
            if (filter.size)                          result.size         = filter.size;
            if (filter.price_from > props.priceMin)   result.price_from   = filter.price_from;
            if (filter.price_to   < props.priceMax)   result.price_to     = filter.price_to;
            emit('apply', result);
        }

        function reset() {
            filter.article      = '';
            filter.manufacturer = '';
            filter.size         = '';
            filter.price_from   = props.priceMin;
            filter.price_to     = props.priceMax;
            emit('apply', {});
        }

        return { filter, apply, reset };
    },

    template: `
        <div class="catalog-filter">
            <h3 class="catalog-filter__title">Фильтр</h3>

            <!--div class="catalog-filter__field">
                <label>Артикул</label>
                <input
                    type="text"
                    v-model="filter.article"
                    placeholder="Введите артикул"
                    @keyup.enter="apply"
                />
            </div-->

            <div class="catalog-filter__field">
                <label>Производитель</label>
                <select v-model="filter.manufacturer">
                    <option value="">Все</option>
                    <option
                        v-for="m in manufacturers"
                        :key="m.ID"
                        :value="m.ID"
                    >{{ m.VALUE }}</option>
                </select>
            </div>

            <div class="catalog-filter__field">
                <label>Материал полки</label>
                <select v-model="filter.size">
                    <option value="">Все</option>
                    <option
                        v-for="s in sizes"
                        :key="s.ID"
                        :value="s.ID"
                    >{{ s.VALUE }}</option>
                </select>
            </div>

            <div class="catalog-filter__field">
                <label>Цена</label>
                <div class="catalog-filter__price-display">
                    <span>{{ filter.price_from }} ₽</span>
                    <span>{{ filter.price_to }} ₽</span>
                </div>
                <div class="catalog-filter__range">
                    <input
                        type="range"
                        :min="priceMin"
                        :max="priceMax"
                        v-model.number="filter.price_from"
                        @change="filter.price_from = Math.min(filter.price_from, filter.price_to)"
                    />
                    <input
                        type="range"
                        :min="priceMin"
                        :max="priceMax"
                        v-model.number="filter.price_to"
                        @change="filter.price_to = Math.max(filter.price_to, filter.price_from)"
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