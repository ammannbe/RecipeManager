{{--
    Recipe edit page: an info box while the form differs from the data it was loaded or saved with,
    and a blue bar beside every changed field. Compares $wire.data with a snapshot taken after the
    fields initialised (and again after each save); Filament's unsavedChangesAlerts() warns on leaving.
--}}
<div
    wire:ignore
    data-unsaved-changes
    x-data="{
        initial: null,
        changed: false,
        timer: null,
        snapshot() {
            return JSON.stringify($wire.data ?? {})
        },
        valueAt(data, path) {
            return JSON.stringify(path.split('.').slice(1).reduce((value, key) => value?.[key], data) ?? null)
        },
        pathOf(element) {
            for (const attribute of element.attributes) {
                if (attribute.name.startsWith('wire:model')) {
                    return attribute.value
                }
            }

            return element.getAttribute('x-data')?.match(/\$entangle\('([^']+)'/)?.[1] ?? null
        },
        mark() {
            if (this.initial === null) {
                return
            }

            const current = this.snapshot()
            const root = $wire.$el

            this.changed = current !== this.initial
            root.querySelectorAll('[data-unsaved-field]').forEach((wrapper) => wrapper.removeAttribute('data-unsaved-field'))

            if (! this.changed) {
                return
            }

            const before = JSON.parse(this.initial)
            const after = JSON.parse(current)

            root.querySelectorAll('[data-field-wrapper] input, [data-field-wrapper] textarea, [data-field-wrapper] select, [data-field-wrapper] [x-data]').forEach((element) => {
                const path = this.pathOf(element)

                if (path?.startsWith('data.') && this.valueAt(before, path) !== this.valueAt(after, path)) {
                    element.closest('[data-field-wrapper]')?.setAttribute('data-unsaved-field', '')
                }
            })
        },
        init() {
            // After the fields (e.g. the rich editor) initialised.
            setTimeout(() => {
                this.initial = this.snapshot()
                this.$watch('$wire.data', () => {
                    clearTimeout(this.timer)
                    this.timer = setTimeout(() => this.mark(), 150)
                })
                this.$watch('$wire.savedDataHash', () => {
                    this.initial = this.snapshot()
                    this.mark()
                })
            }, 300)
        },
    }"
>
    <style>
        [data-unsaved-field] {
            border-inline-start: 3px solid var(--info-500);
            padding-inline-start: 0.5rem;
            margin-inline-start: calc(-0.5rem - 3px);
        }
    </style>

    {{-- x-show only on the outer box: showing it removes the inline display, which would drop the flex row. --}}
    <div x-cloak x-show="changed" role="status">
        <div style="display: flex; flex-direction: row; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid var(--info-200); background: var(--info-50); color: var(--info-700); font-size: 0.875rem; line-height: 1.25rem">
            <x-filament::icon icon="heroicon-o-information-circle" style="width: 1.25rem; height: 1.25rem; flex: none; display: inline-block" />
            <span style="flex: 1 1 auto">
                <strong>{{ __('Unsaved changes') }}</strong>:
                {{ __('Changed fields are marked blue. Save to keep them.') }}
            </span>
        </div>
    </div>
</div>
