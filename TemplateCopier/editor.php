<?php

$templateId = (string) ($_GET['id'] ?? '');
if (!preg_match('/^template_[A-Za-z0-9.]+$/', $templateId)) {
    http_response_code(400);
    exit('Invalid template id.');
}

$blueprintPath = __DIR__ . '/uploads/blueprints/' . $templateId . '.json';
if (!is_file($blueprintPath)) {
    http_response_code(404);
    exit('Template not found.');
}

$template = json_decode((string) file_get_contents($blueprintPath), true);
if (!is_array($template) || empty($template['pages']) || !is_array($template['pages'])) {
    http_response_code(422);
    exit('Template data is invalid.');
}

$safeTemplate = json_encode(
    $template,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$sourceName = htmlspecialchars((string) ($template['source']['original_name'] ?? 'Untitled template'), ENT_QUOTES, 'UTF-8');
$safeId = htmlspecialchars($templateId, ENT_QUOTES, 'UTF-8');
$sourcePdfName = basename((string) ($template['source']['saved_file'] ?? ''));
$sourcePdfUrl = preg_match('/^template_[A-Za-z0-9.]+\.pdf$/', $sourcePdfName)
    && is_file(__DIR__ . '/uploads/' . $sourcePdfName)
        ? 'uploads/' . rawurlencode($sourcePdfName)
        : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit <?= $sourceName ?> | Template Copier</title>
    <style>
        :root{color-scheme:light;--ink:#1c302b;--muted:#667873;--line:#d6e0dc;--paper:#fff;--surface:#f0f4f1;--green:#087f68;--green-dark:#075b4d;--coral:#de7155}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--surface);color:var(--ink);font:14px/1.45 system-ui,sans-serif}
        button,input,select{font:inherit}button{cursor:pointer}.topbar{height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:0 22px;background:#fff;border-bottom:1px solid var(--line)}
        .brand{font:700 20px Georgia,serif}.file-title{color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.actions{display:flex;gap:8px;align-items:center}.button{min-height:38px;padding:8px 12px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}.button.primary{border-color:var(--green);background:var(--green);color:#fff}.button:hover{border-color:var(--green)}
        .workspace{display:grid;grid-template-columns:minmax(0,1fr) 310px;min-height:calc(100vh - 64px)}.stage{min-width:0;display:flex;flex-direction:column;align-items:center;padding:24px;gap:16px}.stagebar{width:min(100%,980px);display:flex;justify-content:space-between;align-items:center;gap:12px}.stagebar select{max-width:260px}.stagebar select,.field input,.field select{width:100%;min-height:36px;padding:7px 9px;border:1px solid var(--line);background:#fff;color:var(--ink)}
        .canvas-wrap{width:min(100%,980px);height:calc(100vh - 158px);min-height:420px;overflow:auto;display:grid;place-items:center;padding:28px;background:#e2e9e5;border:1px solid var(--line)}#designCanvas{display:block;max-width:100%;max-height:100%;width:auto;height:auto;background:#fff;box-shadow:0 8px 30px #183a3022;touch-action:none}
        .panel{padding:22px;background:#fff;border-left:1px solid var(--line)}.panel h1{font:700 22px Georgia,serif;margin:0 0 5px}.panel .hint{margin:0 0 20px;color:var(--muted)}.field{display:grid;gap:5px;margin:0 0 13px}.field label{color:var(--muted);font-size:12px;font-weight:650}.field input[type=color]{padding:3px;height:38px}.field input:disabled,.field select:disabled{background:#f3f5f4;color:#899691}.field-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.element-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:18px 0;padding-top:16px;border-top:1px solid var(--line)}.status{min-height:24px;color:var(--green-dark);font-size:13px}.status.error{color:#b33f2d}.selected-label{min-height:20px;margin:14px 0;color:var(--coral);font-size:12px;font-weight:700}.download{display:block;margin-top:16px;color:var(--green-dark)}
        @media(max-width:850px){.workspace{grid-template-columns:1fr}.stage{padding:16px}.canvas-wrap{height:62vh;min-height:340px}.panel{border-left:0;border-top:1px solid var(--line)}.topbar{padding:0 14px}.brand{font-size:17px}.file-title{display:none}}
        @media(max-width:460px){.topbar{height:auto;min-height:60px;flex-wrap:wrap;padding:10px}.actions{width:100%}.actions .button{flex:1}.workspace{min-height:calc(100vh - 106px)}.stagebar{align-items:stretch;flex-direction:column}.canvas-wrap{padding:10px;height:55vh}.panel{padding:18px}}
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">Template Copier</div>
        <div class="file-title" title="<?= $sourceName ?>"><?= $sourceName ?></div>
        <div class="actions">
            <a class="button" href="index.php">New PDF</a>
            <a class="button" href="uploads/blueprints/<?= rawurlencode($templateId) ?>.json" download>Download JSON</a>
            <button class="button primary" id="saveButton" type="button">Save changes</button>
        </div>
    </header>
    <main class="workspace">
        <section class="stage" aria-label="Template canvas">
            <div class="stagebar">
                <strong><?= (int) count($template['pages']) ?> page<?= count($template['pages']) === 1 ? '' : 's' ?></strong>
                <select id="pageSelect" aria-label="Select page"></select>
                <label><input id="originalBackgroundToggle" type="checkbox" <?= $sourcePdfUrl !== '' ? 'checked' : 'disabled' ?>> Original PDF background</label>
            </div>
            <div class="canvas-wrap"><svg id="designCanvas" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Editable template page"></svg></div>
        </section>
        <aside class="panel">
            <h1>Design elements</h1>
            <p class="hint">The original PDF artwork stays in the background, with its text hidden. Select an editable text element to replace or restyle it.</p>
            <div class="selected-label" id="selectedLabel">No element selected</div>
            <div class="field"><label for="textField">Text or image description</label><input id="textField" data-property="text" disabled></div>
            <div class="field"><label for="roleField">Element role</label><input id="roleField" data-property="role" disabled></div>
            <div class="field-row">
                <div class="field"><label for="xField">X position</label><input id="xField" data-property="x" type="number" step="1" disabled></div>
                <div class="field"><label for="yField">Y position</label><input id="yField" data-property="y" type="number" step="1" disabled></div>
            </div>
            <div class="field-row">
                <div class="field"><label for="widthField">Width</label><input id="widthField" data-property="width" type="number" min="1" step="1" disabled></div>
                <div class="field"><label for="heightField">Height</label><input id="heightField" data-property="height" type="number" min="1" step="1" disabled></div>
            </div>
            <div class="field-row">
                <div class="field"><label for="fontSizeField">Font size</label><input id="fontSizeField" data-property="fontSize" type="number" min="1" step="1" disabled></div>
                <div class="field"><label for="colorField">Text / stroke</label><input id="colorField" data-property="color" type="color" value="#111111" disabled></div>
            </div>
            <div class="field-row">
                <div class="field"><label for="fillField">Fill color</label><input id="fillField" data-property="fill" type="color" value="#ffffff" disabled></div>
                <div class="field"><label for="fontFamilyField">Font family</label><select id="fontFamilyField" data-property="fontFamily" disabled><option>Arial</option><option>Georgia</option><option>Times New Roman</option><option>Verdana</option><option>Helvetica</option><option>serif</option></select></div>
            </div>
            <div class="element-actions">
                <button class="button" id="addTextButton" type="button">Add text</button>
                <button class="button" id="addShapeButton" type="button">Add shape</button>
                <button class="button" id="deleteButton" type="button" disabled>Delete selected</button>
            </div>
            <div class="status" id="saveStatus" role="status"></div>
            <a class="download" href="uploads/<?= rawurlencode((string) ($template['source']['saved_file'] ?? '')) ?>" target="_blank" rel="noopener">Open original PDF</a>
        </aside>
    </main>
    <script>
        const template = <?= $safeTemplate ?>;
        const templateId = <?= json_encode($safeId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const sourcePdfUrl = <?= json_encode($sourcePdfUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        template.pages.forEach(page => {
            page.width = Number(page.width) || 612;
            page.height = Number(page.height) || 792;
            page.background = page.background || '#ffffff';
            page.elements = (page.elements || []).map((element, index) => {
                const normalizedType = ['text', 'rectangle', 'ellipse', 'line', 'image'].includes(element.type)
                    ? element.type
                    : (Object.prototype.hasOwnProperty.call(element, 'text') ? 'text' : 'rectangle');
                return {
                    ...element,
                    id: element.id || `legacy_${page.page || 1}_${index + 1}`,
                    type: normalizedType,
                    role: element.role || element.detected_type || element.type || '',
                    text: element.text || '',
                    description: element.description || '',
                    x: Number(element.x) || 0,
                    y: Number(element.y) || 0,
                    width: Number(element.width) || 300,
                    height: Number(element.height) || 18,
                    fontSize: Number(element.fontSize) || 12,
                    fontFamily: element.fontFamily || 'Arial',
                    fontWeight: element.fontWeight || 'normal',
                    fontStyle: element.fontStyle || 'normal',
                    color: /^#[0-9a-f]{6}$/i.test(element.color || '') ? element.color : '#111111',
                    alignment: element.alignment || 'left',
                    fill: element.fill || 'none',
                    stroke: element.stroke || 'none',
                    strokeWidth: Number(element.strokeWidth) || 1,
                    rotation: Number(element.rotation) || 0,
                    editable: true,
                };
            });

            const minX = page.elements.reduce((low, element) => Math.min(low, Number(element.x) || 0), 0);
            const minY = page.elements.reduce((low, element) => Math.min(low, Number(element.y) || 0), 0);
            const offsetX = minX < 0 ? Math.abs(minX) + 8 : 0;
            const offsetY = minY < 0 ? Math.abs(minY) + 8 : 0;
            if (offsetX || offsetY) {
                page.elements.forEach(element => {
                    element.x = (Number(element.x) || 0) + offsetX;
                    element.y = (Number(element.y) || 0) + offsetY;
                });
            }
        });
        const canvas = document.getElementById('designCanvas');
        const pageSelect = document.getElementById('pageSelect');
        const controls = [...document.querySelectorAll('[data-property]')];
        const selectedLabel = document.getElementById('selectedLabel');
        const deleteButton = document.getElementById('deleteButton');
        const saveStatus = document.getElementById('saveStatus');
        const originalBackgroundToggle = document.getElementById('originalBackgroundToggle');
        const svgNamespace = 'http://www.w3.org/2000/svg';
        const originalPageImages = new Map();
        const nativeTextLayouts = new Map();
        const backgroundRequests = new Map();
        let pdfJsPromise = null;
        let originalPdfPromise = null;
        let pageIndex = 0;
        let selectedElementId = null;
        let dragState = null;

        template.pages.forEach((page, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = `Page ${page.page || index + 1}`;
            pageSelect.append(option);
        });

        function currentPage() {
            return template.pages[pageIndex];
        }

        function svgNode(name, attributes = {}) {
            const node = document.createElementNS(svgNamespace, name);
            Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));
            return node;
        }

        function makeElementNode(element) {
            const group = svgNode('g', { 'data-id': element.id, cursor: 'move' });
            const transform = Number(element.rotation) ? `rotate(${element.rotation} ${element.x + element.width / 2} ${element.y + element.height / 2})` : '';
            if (transform) group.setAttribute('transform', transform);

            if (element.type === 'text') {
                const text = svgNode('text', {
                    x: Number(element.x),
                    y: Number(element.y) + Number(element.fontSize),
                    fill: element.color || '#111111',
                    'font-size': element.fontSize || 16,
                    'font-family': element.fontFamily || 'Arial',
                    'font-weight': element.fontWeight || 'normal',
                    'font-style': element.fontStyle || 'normal',
                    'text-anchor': 'start',
                });
                const nativeLayout = nativeTextLayouts.get(`${pageIndex}:${element.id}`);
                if (nativeLayout) {
                    const offsetX = Number(element.x) - nativeLayout.originX;
                    const offsetY = Number(element.y) - nativeLayout.originY;
                    const fontScale = Number(element.fontSize) / nativeLayout.baseFontSize || 1;
                    nativeLayout.runs.forEach(run => {
                        const span = svgNode('tspan', {
                            x: run.x + offsetX,
                            y: run.baseline + offsetY,
                            'font-size': run.fontSize * fontScale,
                            'font-family': element.fontFamily || run.fontFamily,
                            'font-weight': element.fontWeight || 'normal',
                            textLength: run.width * fontScale,
                            lengthAdjust: 'spacingAndGlyphs',
                        });
                        span.textContent = run.text;
                        text.append(span);
                    });
                } else {
                    text.textContent = element.text || '';
                }
                group.append(text);
            } else if (element.type === 'ellipse') {
                group.append(svgNode('ellipse', {
                    cx: Number(element.x) + Number(element.width) / 2,
                    cy: Number(element.y) + Number(element.height) / 2,
                    rx: Number(element.width) / 2,
                    ry: Number(element.height) / 2,
                    fill: element.fill || 'none',
                    stroke: element.stroke || element.color || 'none',
                    'stroke-width': element.strokeWidth || 1,
                }));
            } else if (element.type === 'line') {
                group.append(svgNode('line', {
                    x1: element.x, y1: element.y,
                    x2: Number(element.x) + Number(element.width),
                    y2: Number(element.y) + Number(element.height),
                    stroke: element.stroke || element.color || '#222222',
                    'stroke-width': element.strokeWidth || 1,
                }));
            } else {
                group.append(svgNode('rect', {
                    x: element.x, y: element.y, width: element.width, height: element.height,
                    fill: element.type === 'image' ? '#e9efec' : element.fill || 'none',
                    stroke: element.stroke || (element.type === 'image' ? '#80938c' : element.color || 'none'),
                    'stroke-width': element.strokeWidth || 1,
                    'stroke-dasharray': element.type === 'image' ? '5 4' : 'none',
                }));
                if (element.type === 'image') {
                    const label = svgNode('text', {
                        x: Number(element.x) + 8,
                        y: Number(element.y) + Math.min(Number(element.height) / 2 + 4, 24),
                        fill: '#53665f',
                        'font-size': 12,
                    });
                    label.textContent = element.description || 'Image placeholder';
                    group.append(label);
                }
            }

            if (element.id === selectedElementId) {
                group.append(svgNode('rect', {
                    x: Number(element.x) - 3,
                    y: Number(element.y) - 3,
                    width: Number(element.width) + 6,
                    height: Number(element.height) + 6,
                    fill: 'none',
                    stroke: '#de7155',
                    'stroke-width': 1.5,
                    'stroke-dasharray': '4 3',
                    'pointer-events': 'none',
                }));
            }
            return group;
        }

        function selectedElement() {
            return currentPage().elements.find(element => element.id === selectedElementId) || null;
        }

        function setControls() {
            const element = selectedElement();
            selectedLabel.textContent = element ? `${element.role || element.type} · ${element.type}` : 'No element selected';
            deleteButton.disabled = !element;
            controls.forEach(control => {
                const key = control.dataset.property;
                control.disabled = !element || ((key === 'fontSize' || key === 'fontFamily') && element.type !== 'text');
                if (!element) return;
                let value = element[key] ?? '';
                if (control.type === 'color' && (!value || value === 'none' || !/^#[0-9a-f]{6}$/i.test(value))) value = '#ffffff';
                if (control.type === 'checkbox') {
                    control.checked = Boolean(value);
                } else {
                    control.value = value;
                }
            });
        }

        async function renderOriginalPage(pageIndexToRender) {
            if (!pdfJsPromise) {
                pdfJsPromise = import('https://cdn.jsdelivr.net/npm/pdfjs-dist@5.4.149/legacy/build/pdf.min.mjs')
                    .then(pdfjsLib => {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@5.4.149/legacy/build/pdf.worker.min.mjs';
                        return pdfjsLib;
                    });
            }
            const pdfjsLib = await pdfJsPromise;
            if (!originalPdfPromise) {
                originalPdfPromise = pdfjsLib.getDocument(sourcePdfUrl).promise;
            }
            const pdf = await originalPdfPromise;
            const pdfPage = await pdf.getPage(pageIndexToRender + 1);
            const viewport = pdfPage.getViewport({ scale: 1.5 });
            const coordinateViewport = pdfPage.getViewport({ scale: 1 });
            const [operatorList, textContent] = await Promise.all([
                pdfPage.getOperatorList(),
                pdfPage.getTextContent(),
            ]);
            const textOperators = new Set([
                pdfjsLib.OPS.showText,
                pdfjsLib.OPS.showSpacedText,
                pdfjsLib.OPS.nextLineShowText,
                pdfjsLib.OPS.nextLineSetSpacingShowText,
            ]);
            const filteredOperationIndexes = new Set();
            operatorList.fnArray.forEach((operator, index) => {
                if (!textOperators.has(operator)) filteredOperationIndexes.add(index);
            });
            const pageCanvas = document.createElement('canvas');
            pageCanvas.width = Math.ceil(viewport.width);
            pageCanvas.height = Math.ceil(viewport.height);
            await pdfPage.render({
                canvasContext: pageCanvas.getContext('2d'),
                viewport,
                filteredOperationIndexes,
            }).promise;
            const nativeItems = textContent.items
                .filter(item => typeof item.str === 'string' && item.str.trim())
                .map(item => {
                    const transform = pdfjsLib.Util.transform(coordinateViewport.transform, item.transform);
                    return {
                        text: item.str,
                        x: transform[4],
                        baseline: transform[5],
                        width: item.width,
                        fontSize: Math.hypot(transform[2], transform[3]),
                        fontFamily: textContent.styles[item.fontName]?.fontFamily || 'serif',
                    };
                });
            return {
                image: pageCanvas.toDataURL('image/png'),
                width: coordinateViewport.width,
                height: coordinateViewport.height,
                nativeItems,
            };
        }

        function normalizeMatchText(text) {
            return String(text || '')
                .normalize('NFKC')
                .replace(/[\u2018\u2019\u02bc]/g, "'")
                .replace(/[\u201c\u201d]/g, '"')
                .replace(/\s+/g, '')
                .toLocaleLowerCase();
        }

        function alignTextToNativeItems(page, pageIndexToAlign, nativeItems) {
            const claimedItems = new Set();
            page.elements.filter(element => element.type === 'text').forEach(element => {
                const target = normalizeMatchText(element.text);
                if (!target) return;

                let match = null;
                for (let start = 0; start < nativeItems.length && !match; start++) {
                    if (claimedItems.has(start)) continue;
                    let combined = '';
                    for (let end = start; end < nativeItems.length && end < start + 40; end++) {
                        if (claimedItems.has(end)) break;
                        combined += normalizeMatchText(nativeItems[end].text);
                        if (combined === target) {
                            match = nativeItems.slice(start, end + 1).map((item, offset) => ({ item, index: start + offset }));
                            break;
                        }
                        if (!target.startsWith(combined)) break;
                    }
                }
                if (!match) return;

                match.forEach(({ index }) => claimedItems.add(index));
                const runs = match.map(({ item }) => item);
                const minX = Math.min(...runs.map(run => run.x));
                const minY = Math.min(...runs.map(run => run.baseline - run.fontSize));
                const maxX = Math.max(...runs.map(run => run.x + run.width));
                const maxY = Math.max(...runs.map(run => run.baseline));
                const baseFontSize = Math.max(...runs.map(run => run.fontSize));
                if (!element.nativeAligned) {
                    element.x = minX;
                    element.y = minY;
                    element.width = Math.max(1, maxX - minX);
                    element.height = Math.max(1, maxY - minY);
                    element.fontSize = baseFontSize;
                    element.fontFamily = runs[0].fontFamily;
                    element.fontWeight = 'normal';
                    element.alignment = 'left';
                    element.nativeAligned = true;
                }
                nativeTextLayouts.set(`${pageIndexToAlign}:${element.id}`, {
                    originX: minX,
                    originY: minY,
                    baseFontSize,
                    runs,
                });
            });
        }

        function cacheOriginalPage(pageIndexToCache, pageData) {
            originalPageImages.set(pageIndexToCache, pageData);
            const page = template.pages[pageIndexToCache];
            page.width = pageData.width;
            page.height = pageData.height;
            alignTextToNativeItems(page, pageIndexToCache, pageData.nativeItems);
        }

        function getVisibleElements(page, showOriginalBackground) {
            if (!showOriginalBackground) {
                return page.elements;
            }
            return page.elements.filter(element => {
                const type = String(element.type || '').toLowerCase();
                const role = String(element.role || '').toLowerCase();
                if (type === 'text') return true;
                if (type === 'image') return false;
                if (type === 'rectangle' || type === 'ellipse' || type === 'line') {
                    return /title|subtitle|heading|body|caption|label|author|date|page|number|quote|bullet|list|text/.test(role) || element.text;
                }
                return false;
            });
        }

        function render() {
            const page = currentPage();
            canvas.setAttribute('viewBox', `0 0 ${page.width} ${page.height}`);
            canvas.setAttribute('width', page.width);
            canvas.setAttribute('height', page.height);
            canvas.replaceChildren(svgNode('rect', { x: 0, y: 0, width: page.width, height: page.height, fill: page.background || '#ffffff' }));
            const showOriginalBackground = originalBackgroundToggle.checked && Boolean(sourcePdfUrl);
            if (showOriginalBackground) {
                const pageData = originalPageImages.get(pageIndex);
                if (pageData) {
                    canvas.append(svgNode('image', {
                        x: 0,
                        y: 0,
                        width: page.width,
                        height: page.height,
                        href: pageData.image,
                        preserveAspectRatio: 'none',
                    }));
                } else if (!backgroundRequests.has(pageIndex)) {
                    saveStatus.classList.remove('error');
                    saveStatus.textContent = 'Loading original PDF page…';
                    const requestedPage = pageIndex;
                    const request = renderOriginalPage(requestedPage)
                        .then(pageData => {
                            cacheOriginalPage(requestedPage, pageData);
                            saveStatus.classList.remove('error');
                            saveStatus.textContent = '';
                            if (pageIndex === requestedPage && originalBackgroundToggle.checked) render();
                        })
                        .catch(error => {
                            originalBackgroundToggle.checked = false;
                            saveStatus.classList.add('error');
                            saveStatus.textContent = error.message;
                            render();
                        })
                        .finally(() => backgroundRequests.delete(requestedPage));
                    backgroundRequests.set(pageIndex, request);
                }
            }
            const visibleElements = getVisibleElements(page, showOriginalBackground);
            if (selectedElementId && !visibleElements.some(element => element.id === selectedElementId)) {
                selectedElementId = null;
            }
            visibleElements.forEach(element => {
                canvas.append(makeElementNode(element));
            });
            setControls();
        }

        pageSelect.addEventListener('change', () => {
            pageIndex = Number(pageSelect.value);
            selectedElementId = null;
            render();
        });
        originalBackgroundToggle.addEventListener('change', () => {
            saveStatus.textContent = '';
            render();
        });

        canvas.addEventListener('pointerdown', event => {
            const group = event.target.closest('[data-id]');
            if (!group) {
                selectedElementId = null;
                render();
                return;
            }
            selectedElementId = group.dataset.id;
            const element = selectedElement();
            if (!element) return;
            const point = canvas.createSVGPoint();
            point.x = event.clientX;
            point.y = event.clientY;
            const position = point.matrixTransform(canvas.getScreenCTM().inverse());
            dragState = { id: element.id, x: Number(element.x), y: Number(element.y), pointerX: position.x, pointerY: position.y };
            canvas.setPointerCapture(event.pointerId);
            render();
        });

        canvas.addEventListener('pointermove', event => {
            if (!dragState) return;
            const point = canvas.createSVGPoint();
            point.x = event.clientX;
            point.y = event.clientY;
            const position = point.matrixTransform(canvas.getScreenCTM().inverse());
            const element = currentPage().elements.find(item => item.id === dragState.id);
            if (!element) return;
            element.x = Math.round(dragState.x + position.x - dragState.pointerX);
            element.y = Math.round(dragState.y + position.y - dragState.pointerY);
            render();
        });
        canvas.addEventListener('pointerup', () => { dragState = null; });
        canvas.addEventListener('pointercancel', () => { dragState = null; });

        controls.forEach(control => {
            control.addEventListener('input', () => {
                const element = selectedElement();
                if (!element) return;
                const key = control.dataset.property;
                element[key] = control.type === 'checkbox'
                    ? control.checked
                    : control.type === 'number' ? Number(control.value) : control.value;
                if (key === 'text') nativeTextLayouts.delete(`${pageIndex}:${element.id}`);
                if (key === 'text' && element.type === 'image') element.description = control.value;
                render();
            });
        });

        document.getElementById('addTextButton').addEventListener('click', () => {
            const page = currentPage();
            const element = { id: `added_${Date.now()}`, type: 'text', role: 'text', text: 'New text', x: 40, y: 40, width: 220, height: 30, fontSize: 20, fontFamily: 'Arial', fontWeight: 'normal', fontStyle: 'normal', color: '#1c302b', alignment: 'left', fill: 'none', stroke: 'none', strokeWidth: 1, editable: true };
            page.elements.push(element);
            selectedElementId = element.id;
            render();
        });

        document.getElementById('addShapeButton').addEventListener('click', () => {
            const page = currentPage();
            const element = { id: `added_${Date.now()}`, type: 'rectangle', role: 'shape', text: '', x: 40, y: 40, width: 120, height: 70, fontSize: 16, fontFamily: 'Arial', color: '#087f68', fill: 'none', stroke: '#087f68', strokeWidth: 2, editable: true };
            page.elements.push(element);
            selectedElementId = element.id;
            render();
        });

        deleteButton.addEventListener('click', () => {
            const page = currentPage();
            page.elements = page.elements.filter(element => element.id !== selectedElementId);
            selectedElementId = null;
            render();
        });

        document.getElementById('saveButton').addEventListener('click', async event => {
            const button = event.currentTarget;
            button.disabled = true;
            saveStatus.classList.remove('error');
            saveStatus.textContent = 'Aligning PDF text…';
            try {
                if (sourcePdfUrl) {
                    for (let index = 0; index < template.pages.length; index++) {
                        if (originalPageImages.has(index)) continue;
                        const pendingRequest = backgroundRequests.get(index);
                        if (pendingRequest) await pendingRequest;
                        if (!originalPageImages.has(index)) {
                            cacheOriginalPage(index, await renderOriginalPage(index));
                        }
                    }
                }
                saveStatus.textContent = 'Saving…';
                const response = await fetch('save-template.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: templateId, template }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Save failed.');
                saveStatus.textContent = 'Saved';
            } catch (error) {
                saveStatus.classList.add('error');
                saveStatus.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });

        render();
    </script>
</body>
</html>