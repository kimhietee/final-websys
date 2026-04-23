/* ============================================================
   KIM INVENTORIES — data.js
   Sample product data for static prototyping.
   
   FUTURE PHP MIGRATION NOTES:
   - Remove this entire file once connected to MySQL.
   - Replace PRODUCTS_DATA with SELECT queries via PHP + PDO.
   - All helper functions (getStockStats, getCategoryTotals)
     should become SQL aggregations server-side.
   - Low stock threshold (10) should be stored in a config table.
   ============================================================ */

// ─── Constants ───────────────────────────────────────────────────────────────
const LOW_STOCK_THRESHOLD = 10;  // Future: read from DB config

// ─── Sample Data ─────────────────────────────────────────────────────────────
// Quantities are designed so:
//   Total  = 500 units
//   Low Stock products (qty < 10) = 3
//   Out of Stock = 0
//   Category breakdown mirrors the Stock Overview chart in the design
let PRODUCTS_DATA = [
  // Beverages  (~55 total)
  { id:  1, name: 'Americano',        category: 'Beverages',   price:  85, quantity: 20, unit: 'cup'    },
  { id:  2, name: 'Café Latte',       category: 'Beverages',   price: 110, quantity: 15, unit: 'cup'    },
  { id:  3, name: 'Green Tea',        category: 'Beverages',   price:  70, quantity:  8, unit: 'cup'    }, // LOW STOCK
  { id:  4, name: 'Sparkling Water',  category: 'Beverages',   price:  40, quantity: 12, unit: 'bottle' },

  // Pastries  (~305 total)
  { id:  5, name: 'Croissant',        category: 'Pastries',    price:  65, quantity: 80, unit: 'piece'  },
  { id:  6, name: 'Cinnamon Roll',    category: 'Pastries',    price:  75, quantity: 75, unit: 'piece'  },
  { id:  7, name: 'Blueberry Muffin', category: 'Pastries',    price:  55, quantity:  5, unit: 'piece'  }, // LOW STOCK
  { id:  8, name: 'Chocolate Cake',   category: 'Pastries',    price:  90, quantity: 85, unit: 'slice'  },
  { id:  9, name: 'Butter Cookie',    category: 'Pastries',    price:  45, quantity: 60, unit: 'piece'  },

  // Light Meals  (~100 total)
  { id: 10, name: 'Club Sandwich',    category: 'Light Meals', price: 150, quantity: 50, unit: 'plate'  },
  { id: 11, name: 'Pasta Salad',      category: 'Light Meals', price: 120, quantity:  3, unit: 'plate'  }, // LOW STOCK
  { id: 12, name: 'Caesar Salad',     category: 'Light Meals', price: 130, quantity: 47, unit: 'plate'  },

  // Others  (~40 total)
  { id: 13, name: 'Mixed Nuts',       category: 'Others',      price:  90, quantity: 25, unit: 'pack'   },
  { id: 14, name: 'Energy Bar',       category: 'Others',      price:  45, quantity: 15, unit: 'piece'  }
];

// Next ID counter for Add Product
let NEXT_PRODUCT_ID = 15;

// ─── Status Helper ───────────────────────────────────────────────────────────

/** Returns 'out-of-stock' | 'low-stock' | 'in-stock' */
function getProductStatus(quantity) {
  if (quantity === 0)                    return 'out-of-stock';
  if (quantity < LOW_STOCK_THRESHOLD)    return 'low-stock';
  return 'in-stock';
}

/** Returns HTML badge string for a given status */
function statusBadgeHtml(quantity) {
  const status = getProductStatus(quantity);
  const map = {
    'in-stock':    ['badge-in-stock',  'In Stock'],
    'low-stock':   ['badge-low-stock', 'Low Stock'],
    'out-of-stock':['badge-out-stock', 'Out of Stock']
  };
  const [cls, label] = map[status];
  return `<span class="badge-status ${cls}">${label}</span>`;
}

// ─── Aggregation Helpers ─────────────────────────────────────────────────────

/** Compute summary stats for Stat Cards */
function getStockStats() {
  const totalStock     = PRODUCTS_DATA.reduce((s, p) => s + p.quantity, 0);
  const totalValue     = PRODUCTS_DATA.reduce((s, p) => s + p.price * p.quantity, 0);
  const lowStockCount  = PRODUCTS_DATA.filter(p => getProductStatus(p.quantity) === 'low-stock').length;
  const outStockCount  = PRODUCTS_DATA.filter(p => getProductStatus(p.quantity) === 'out-of-stock').length;
  const inStockCount   = PRODUCTS_DATA.filter(p => getProductStatus(p.quantity) === 'in-stock').length;
  return { totalStock, totalValue, lowStockCount, outStockCount, inStockCount };
}

/** Totals per category  →  { Beverages: 55, Pastries: 305, ... } */
function getCategoryTotals() {
  const totals = {};
  PRODUCTS_DATA.forEach(p => {
    totals[p.category] = (totals[p.category] || 0) + p.quantity;
  });
  return totals;
}

/** Format Philippine Peso */
function formatPeso(amount) {
  return '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 0 });
}

// ─── CRUD Stubs (Future: replace with PHP API fetch calls) ───────────────────

function addProduct(product) {
  product.id = NEXT_PRODUCT_ID++;
  PRODUCTS_DATA.push(product);
}

function updateProduct(id, updates) {
  const idx = PRODUCTS_DATA.findIndex(p => p.id === id);
  if (idx !== -1) PRODUCTS_DATA[idx] = Object.assign({}, PRODUCTS_DATA[idx], updates);
}

function deleteProduct(id) {
  PRODUCTS_DATA = PRODUCTS_DATA.filter(p => p.id !== id);
}
