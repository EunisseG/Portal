import Quill from 'quill';
import 'quill/dist/quill.snow.css';

/*
 * Editor de texto enriquecido (Quill) para el cuerpo de los tickets.
 *
 * Uso en Blade (dentro de un componente Livewire):
 *   <div wire:ignore x-data="richEditor('body', 'Escribe...')"> <div x-ref="editor"></div> </div>
 *
 * El HTML se envía a la propiedad Livewire indicada sin hacer petición en cada
 * tecla ($set diferido). El servidor siempre lo sanitiza antes de guardarlo.
 */
const TOOLBAR = [
    [{ font: [] }, { size: ['small', false, 'large', 'huge'] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'bullet' }, { list: 'ordered' }, { indent: '-1' }, { indent: '+1' }],
    [{ align: '' }, { align: 'center' }, { align: 'right' }],
    ['link'],
    ['clean'],
];

/** Convierte el contenido del editor a HTML semántico (listas ul/ol reales). */
function toHtml(quill) {
    if (quill.getText().trim() === '') {
        return '';
    }

    // Quill 2.0.3 convierte cada espacio en &nbsp; en getSemanticHTML, lo que
    // impide el ajuste de línea; se devuelven a espacios normales.
    return quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
}

/** Carga un valor que puede ser HTML (del editor) o texto plano (plantillas). */
function load(quill, value) {
    if (! value) {
        quill.setText('');
    } else if (/<\/?[a-z][\s\S]*>/i.test(value)) {
        quill.clipboard.dangerouslyPasteHTML(value);
    } else {
        quill.setText(value);
    }
}

window.richEditor = (property, placeholder = '', label = 'Mensaje') => {
    // Quill no debe vivir en el estado reactivo de Alpine (los proxies lo rompen),
    // por eso la instancia queda en el cierre de la función.
    let quill = null;
    let lastSent = null;

    return {
        init() {
            quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                placeholder,
                modules: { toolbar: TOOLBAR },
            });

            // Accesibilidad: el área editable se anuncia como cuadro de texto multilínea.
            quill.root.setAttribute('role', 'textbox');
            quill.root.setAttribute('aria-multiline', 'true');
            quill.root.setAttribute('aria-label', label);

            const initial = this.$wire.$get(property);
            load(quill, initial);
            lastSent = initial;

            quill.on('text-change', () => {
                lastSent = toHtml(quill);
                this.$wire.$set(property, lastSent, false);
            });

            // Cambios desde el servidor (limpiar tras enviar, respuestas predefinidas).
            this.$wire.$watch(property, (value) => {
                if (value === lastSent) {
                    return;
                }
                lastSent = value;
                load(quill, value);
            });
        },
    };
};
