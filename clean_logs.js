const fs = require('fs');
const path = require('path');

const directories = [
    path.join(__dirname, 'client', 'src'),
    path.join(__dirname, 'server', 'app'),
    path.join(__dirname, 'server', 'routes')
];

function cleanLogsInDirectory(dir) {
    const files = fs.readdirSync(dir);
    
    files.forEach(file => {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            cleanLogsInDirectory(fullPath);
        } else if (fullPath.match(/\.(tsx?|jsx?|php)$/)) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = content;
            
            if (fullPath.endsWith('.php')) {
                // Remove dd() and dump() that are on their own line
                modified = modified.replace(/^\s*(dd|dump)\(.*?\);?\s*$/gm, '');
            } else {
                // Remove console.log, console.error, console.warn on their own line
                modified = modified.replace(/^\s*console\.(log|error|warn|info|debug)\(.*?\);?\s*$/gm, '');
            }
            
            if (content !== modified) {
                fs.writeFileSync(fullPath, modified, 'utf8');
                console.log(`Cleaned logs in: ${fullPath}`);
            }
        }
    });
}

directories.forEach(dir => {
    if (fs.existsSync(dir)) {
        cleanLogsInDirectory(dir);
    }
});
console.log('Log cleanup complete.');
