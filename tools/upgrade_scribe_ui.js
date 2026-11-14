// Node script to update CoreHealthScribeElement.js and verify
const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, '../sdks/js-scribe-sdk/src/CoreHealthScribeElement.js');
let content = fs.readFileSync(filePath, 'utf8');

console.log('Current file size:', content.length);

