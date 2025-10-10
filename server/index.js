require('dotenv').config();
const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');

const app = express();
const port = parseInt(process.env.APP_PORT || process.env.PORT || '3000', 10);

app.use(cors({ origin: true, credentials: true }));
app.use(express.json({ limit: '10mb' }));

const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    port: parseInt(process.env.DB_PORT || '3306', 10),
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
});

async function ensureSchema() {
    const createTableQuery = `
        CREATE TABLE IF NOT EXISTS inventory_state (
            id TINYINT UNSIGNED PRIMARY KEY,
            data JSON NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    `;

    await pool.execute(createTableQuery);
    await pool.execute(
        'INSERT INTO inventory_state (id, data) VALUES (1, JSON_ARRAY()) ON DUPLICATE KEY UPDATE id = id'
    );
}

async function getInventory() {
    await ensureSchema();
    const [rows] = await pool.execute('SELECT data FROM inventory_state WHERE id = 1');
    if (rows.length === 0) {
        return [];
    }

    const { data } = rows[0];
    if (!data) {
        return [];
    }

    if (typeof data === 'string') {
        try {
            return JSON.parse(data);
        } catch (error) {
            console.warn('Contenu JSON invalide dans la base de données. Réinitialisation.', error);
            return [];
        }
    }

    return data;
}

async function saveInventory(inventory) {
    await ensureSchema();
    await pool.execute(
        'INSERT INTO inventory_state (id, data) VALUES (1, CAST(? AS JSON)) ON DUPLICATE KEY UPDATE data = VALUES(data)',
        [JSON.stringify(inventory)]
    );
}

app.get('/api/health', async (req, res) => {
    try {
        await ensureSchema();
        res.json({ status: 'ok' });
    } catch (error) {
        console.error('Erreur de santé API :', error);
        res.status(500).json({ status: 'error', message: 'Base de données inaccessible.' });
    }
});

app.get('/api/inventory', async (req, res) => {
    try {
        const inventory = await getInventory();
        res.json({ inventory });
    } catch (error) {
        console.error('Erreur lors de la récupération de l\'inventaire :', error);
        res.status(500).json({ error: 'Impossible de récupérer l\'inventaire.' });
    }
});

app.put('/api/inventory', async (req, res) => {
    const { inventory } = req.body || {};

    if (!Array.isArray(inventory)) {
        return res.status(400).json({ error: 'Le corps de la requête doit contenir un tableau "inventory".' });
    }

    try {
        await saveInventory(inventory);
        res.json({ success: true });
    } catch (error) {
        console.error('Erreur lors de la sauvegarde de l\'inventaire :', error);
        res.status(500).json({ error: 'Impossible de sauvegarder l\'inventaire.' });
    }
});

app.use((req, res) => {
    res.status(404).json({ error: 'Route non trouvée.' });
});

app.listen(port, () => {
    console.log(`API Gestion Stocks démarrée sur le port ${port}`);
});
