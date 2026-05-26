import Quill from 'quill';

export function merosRenderQuillContent(deltaOps) {
    let html = '';
    
    deltaOps = typeof deltaOps === 'string' ? JSON.parse(deltaOps) : deltaOps;
    
    deltaOps.forEach(op => {
        let text = op.insert;
        const attrs = op.attributes || {};
        
        // Escape HTML entities in text to prevent XSS
        text = text.replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;")
                   .replace(/"/g, "&quot;")
                   .replace(/'/g, "&#039;");

        if (attrs.bold) {
            text = `<strong>${text}</strong>`;
        }
        if (attrs.underline) {
            text = `<u>${text}</u>`;
        }
        if (attrs.link) {
            // Sanitize URL if necessary
            const safeUrl = attrs.link.replace(/"/g, '&quot;');
            text = `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${text}</a>`;
        }
        if (attrs.italic) {
            text = `<em>${text}</em>`;
        }
        
        // Handle newlines if your delta has them
        if (text === '\n') {
            text = '<br>'; 
        }

        html += text;
    });

    // Wrap in a single paragraph if desired, or return raw
    return `<p>${html}</p>`; 
}

export function merosHydrateQuillContent(container, content = null) {
    if (!container || !container._quill) {
        return;
    }

    if (!content) {

        const richTextPayloads = Alpine.store('formBuilder').richTextPayloads || [];
        const rtId = container.dataset.rtId;

        if (!rtId) {
            return;
        }

        const payload = richTextPayloads.find(payload => Number(payload.rt_id) === Number(rtId));

        if (payload && payload.content) {
            container._quill.setContents(JSON.parse(payload.content));
        }
    }

    else {
        console.log('Hydrating quill with content', content);
        container._quill.setContents(JSON.parse(content));
    }
}

export function initRichTextEditors(richTextPayloads = []) {
    if (richTextPayloads === []) {
        richTextPayloads = Alpine.store('formBuilder').richTextPayloads || [];
    }

    const richTextEditors = document.querySelectorAll('.meros-rich-textarea');

    richTextEditors.forEach(editor => {
        if (editor._quill) {
            return;
        }

        const isGroupDescription = editor.classList.contains('meros-form-group-description');

        const quill = new Quill(editor, {
            theme: 'snow',
            modules: {
                toolbar: ['bold', 'italic', 'underline', 'link']
            }
        });

        editor._quill = quill;

        merosHydrateQuillContent(editor);

        quill.root.addEventListener('blur', () => {            
            const deltaOps = quill.getContents().ops;

            if (deltaOps?.length === 0 || (deltaOps?.length === 1 && deltaOps[0].insert === '\n')) {
                Alpine.store('formBuilder').updateActiveFieldProperty('description', '');
            }
            
            if (isGroupDescription) {
                Alpine.store('formBuilder').updateActiveGroupProperty('description', JSON.stringify(deltaOps));
            }
        });
    });
}
