const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const projectRoot = path.resolve(__dirname, '../..');
const tempPhpFile = path.resolve(__dirname, 'temp_tinker.php');

const phpBinary = fs.existsSync('C:\\ServBay\\packages\\php\\current\\php.exe')
    ? 'C:\\ServBay\\packages\\php\\current\\php.exe'
    : (fs.existsSync('C:\\ServBay\\bin\\php.cmd') ? 'C:\\ServBay\\bin\\php.cmd' : 'php');

/**
 * Execute a PHP snippet bootstrapped with Laravel and return parsed JSON
 */
function runArtisan(phpExpression) {
    const phpScript = `<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

try {
    $result = (${phpExpression});
    echo json_encode(['success' => true, 'data' => $result]);
} catch (\\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
`;
    try {
        fs.writeFileSync(tempPhpFile, phpScript);
        const options = { cwd: projectRoot, timeout: 15000 };
        if (phpBinary.endsWith('.cmd')) {
            options.shell = true;
        }
        const output = execFileSync(phpBinary, [tempPhpFile], options).toString();
        
        const jsonStart = output.indexOf('{"success":');
        if (jsonStart !== -1) {
            const parsed = JSON.parse(output.substring(jsonStart));
            if (parsed.success) {
                return parsed.data;
            } else {
                console.error('PHP execution failed:', parsed.error);
                return null;
            }
        }
        return JSON.parse(output);
    } catch (e) {
        console.error('runArtisan error:', e.message);
        return null;
    } finally {
        if (fs.existsSync(tempPhpFile)) {
            try { fs.unlinkSync(tempPhpFile); } catch(err) {}
        }
    }
}

/**
 * Get current stock for a product in a warehouse
 */
function getProductStock(productId, warehouseId = 1) {
    return runArtisan(`[
        'product_qty' => (float)(\\App\\Models\\Product::where('id', ${productId})->value('qty') ?? 0),
        'warehouse_qty' => (float)(\\App\\Models\\Product_Warehouse::where('product_id', ${productId})->where('warehouse_id', ${warehouseId})->value('qty') ?? 0)
    ]`);
}

module.exports = {
    runArtisan,
    getProductStock,
    projectRoot
};
