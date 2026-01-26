#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const DOCS_DIR = __dirname;
const INPUT_FILE = path.join(DOCS_DIR, 'PROGETTO_ONESIFORO_ONESIBOX.md');
const OUTPUT_MD = path.join(DOCS_DIR, 'PROGETTO_ONESIFORO_ONESIBOX_WITH_IMAGES.md');
const OUTPUT_DOCX = path.join(DOCS_DIR, 'PROGETTO_ONESIFORO_ONESIBOX.docx');
const DIAGRAMS_DIR = path.join(DOCS_DIR, 'diagrams');

// Ensure diagrams directory exists
if (!fs.existsSync(DIAGRAMS_DIR)) {
    fs.mkdirSync(DIAGRAMS_DIR, { recursive: true });
}

console.log('Reading markdown file...');
let content = fs.readFileSync(INPUT_FILE, 'utf-8');

// Find all mermaid code blocks
const mermaidRegex = /```mermaid\n([\s\S]*?)```/g;
let match;
let diagramIndex = 0;
const replacements = [];

console.log('Extracting and rendering Mermaid diagrams...');

while ((match = mermaidRegex.exec(content)) !== null) {
    diagramIndex++;
    const mermaidCode = match[1];
    const fullMatch = match[0];
    const mmdFile = path.join(DIAGRAMS_DIR, `diagram_${diagramIndex}.mmd`);
    const pngFile = path.join(DIAGRAMS_DIR, `diagram_${diagramIndex}.png`);
    const relativePngPath = `diagrams/diagram_${diagramIndex}.png`;

    // Write mermaid code to temp file
    fs.writeFileSync(mmdFile, mermaidCode);

    try {
        // Render diagram using mmdc
        console.log(`  Rendering diagram ${diagramIndex}...`);
        execSync(`mmdc -i "${mmdFile}" -o "${pngFile}" -w 1200 -b transparent --quiet`, {
            timeout: 60000,
            stdio: 'pipe'
        });

        // Store replacement
        replacements.push({
            original: fullMatch,
            replacement: `![Diagramma ${diagramIndex}](${relativePngPath})`
        });

        console.log(`  ✓ Diagram ${diagramIndex} rendered successfully`);
    } catch (error) {
        console.error(`  ✗ Error rendering diagram ${diagramIndex}: ${error.message}`);
        // Keep original mermaid block if rendering fails
        replacements.push({
            original: fullMatch,
            replacement: fullMatch
        });
    }

    // Clean up temp mmd file
    try {
        fs.unlinkSync(mmdFile);
    } catch (e) {}
}

console.log(`\nTotal diagrams found: ${diagramIndex}`);

// Apply replacements
let modifiedContent = content;
for (const { original, replacement } of replacements) {
    modifiedContent = modifiedContent.replace(original, replacement);
}

// Write modified markdown
fs.writeFileSync(OUTPUT_MD, modifiedContent);
console.log(`\nModified markdown saved to: ${OUTPUT_MD}`);

// Convert to DOCX using pandoc
console.log('\nConverting to DOCX with pandoc...');
try {
    execSync(`pandoc "${OUTPUT_MD}" -o "${OUTPUT_DOCX}" --from=markdown --to=docx --toc --toc-depth=3 --standalone`, {
        cwd: DOCS_DIR,
        stdio: 'inherit'
    });
    console.log(`\n✓ DOCX file created: ${OUTPUT_DOCX}`);
} catch (error) {
    console.error(`\n✗ Error converting to DOCX: ${error.message}`);
    process.exit(1);
}

// Clean up intermediate markdown
try {
    fs.unlinkSync(OUTPUT_MD);
    console.log('Cleaned up intermediate files.');
} catch (e) {}

console.log('\nDone!');
