export default {
    name: 'CheckoutPropertiesForm',
    props: {
        properties: { type: Array, default: () => [] },
        modelValue: { type: Object, default: () => ({}) },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    methods: {
        fieldKeys(property) {
            const keys = [];
            if (property.code) {
                keys.push(String(property.code));
            }
            if (property.id) {
                keys.push(String(property.id));
            }
            return keys;
        },
        normalizeValue(value) {
            if (Array.isArray(value)) {
                const found = value.map((item) => this.normalizeValue(item)).find((item) => item !== '');
                return found || '';
            }
            return String(value ?? '');
        },
        isEmptyValue(value) {
            return this.normalizeValue(value).trim() === '';
        },
        updateField(property, value) {
            const next = { ...this.modelValue };
            const normalized = this.normalizeValue(value);
            for (const key of this.fieldKeys(property)) {
                next[key] = normalized;
            }
            this.$emit('update:modelValue', next);
        },
        fieldValue(property) {
            for (const key of this.fieldKeys(property)) {
                const current = this.normalizeValue(this.modelValue[key]);
                if (!this.isEmptyValue(current)) {
                    return current;
                }
            }
            return this.normalizeValue(property.value);
        },
        flushToModel() {
            const next = { ...this.modelValue };
            for (const property of this.properties) {
                const keys = this.fieldKeys(property);
                const el = document.getElementById(`prop-${property.id}`);
                let value = el ? this.normalizeValue(el.value) : '';
                if (this.isEmptyValue(value)) {
                    value = keys.map((key) => this.normalizeValue(next[key])).find((item) => !this.isEmptyValue(item)) || '';
                }
                if (this.isEmptyValue(value)) {
                    value = this.normalizeValue(property.value);
                }
                if (this.isEmptyValue(value)) {
                    continue;
                }
                value = value.trim();
                for (const key of keys) {
                    next[key] = value;
                }
            }
            this.$emit('update:modelValue', next);
            return next;
        },
    },
    template: `
        <section class="checkout_section" :class="{ 'checkout_section--disabled': disabled }">
            <h2 class="checkout_section__title">Данные получателя</h2>
            <div v-if="disabled" class="checkout_option__meta">Выберите тип покупателя, местоположение, доставку и способ оплаты, чтобы увидеть поля получателя.</div>
            <div v-else-if="!properties.length" class="checkout_option__meta">Для выбранных условий дополнительные поля не требуются.</div>
            <div v-for="property in properties" :key="property.id" class="checkout_field">
                <label :for="'prop-' + property.id">
                    {{ property.name }}<span v-if="property.required"> *</span>
                </label>
                <select
                    v-if="property.type === 'ENUM'"
                    :id="'prop-' + property.id"
                    :value="fieldValue(property)"
                    @change="updateField(property, $event.target.value)"
                    @input="updateField(property, $event.target.value)"
                >
                    <option value="">Выберите</option>
                    <option v-for="opt in property.options" :key="opt.value" :value="opt.value">{{ opt.name }}</option>
                </select>
                <textarea
                    v-else-if="property.type === 'TEXT'"
                    :id="'prop-' + property.id"
                    rows="3"
                    :value="fieldValue(property)"
                    @change="updateField(property, $event.target.value)"
                    @input="updateField(property, $event.target.value)"
                ></textarea>
                <input
                    v-else
                    :id="'prop-' + property.id"
                    type="text"
                    :value="fieldValue(property)"
                    @change="updateField(property, $event.target.value)"
                    @input="updateField(property, $event.target.value)"
                />
            </div>
        </section>
    `,
};
