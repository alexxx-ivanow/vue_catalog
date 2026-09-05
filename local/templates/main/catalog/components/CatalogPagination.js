export default {
    name: 'CatalogPagination',

    props: {
        currentPage: { type: Number, required: true },
        totalPages:  { type: Number, required: true },
    },

    emits: ['change'],

    setup(props, { emit }) {
        function go(page) {
            if (page < 1 || page > props.totalPages) return;
            if (page === props.currentPage) return;
            emit('change', page);
        }

        return { go };
    },

    template: `
        <div v-if="totalPages > 1" class="catalog-pagination">

            <button
                class="catalog-pagination__btn"
                :disabled="currentPage === 1"
                @click="go(currentPage - 1)"
            >←</button>

            <template v-for="page in totalPages" :key="page">
                <!-- Показываем первую, последнюю и страницы рядом с текущей -->
                <template v-if="
                    page === 1 ||
                    page === totalPages ||
                    Math.abs(page - currentPage) <= 1
                ">
                    <button
                        class="catalog-pagination__btn"
                        :class="{ 'catalog-pagination__btn--active': page === currentPage }"
                        @click="go(page)"
                    >{{ page }}</button>
                </template>

                <!-- Многоточие слева -->
                <span
                    v-else-if="page === currentPage - 2"
                    class="catalog-pagination__dots"
                >...</span>

                <!-- Многоточие справа -->
                <span
                    v-else-if="page === currentPage + 2"
                    class="catalog-pagination__dots"
                >...</span>
            </template>

            <button
                class="catalog-pagination__btn"
                :disabled="currentPage === totalPages"
                @click="go(currentPage + 1)"
            >→</button>

        </div>
    `,
};