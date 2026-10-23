const { runArtisan, getProductStock } = require('./helpers/db_helper');

console.log('Testing db_helper...');
const count = runArtisan('\\App\\Models\\Warehouse::count()');
console.log('Warehouse count:', count);

const company = runArtisan('\\App\\Models\\Company::select("id","trade_name")->first()');
console.log('First company:', company);
