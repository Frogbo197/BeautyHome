const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 5000;

// Middleware
app.use(cors());
app.use(express.json());

// Tạo connection pool đến MySQL
const pool = mysql.createPool({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Test database connection
app.get('/api/test-db', async (req, res) => {
  try {
    const connection = await pool.getConnection();
    const [rows] = await connection.execute('SELECT 1 + 1 AS solution');
    connection.release();
    
    res.json({ 
      message: 'Kết nối database thành công!',
      data: rows 
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Routes cơ bản
app.get('/', (req, res) => {
  res.json({ message: 'Backend cho website bán bản vẽ thiết kế' });
});

// Khởi động server
app.listen(PORT, () => {
  console.log(`Server đang chạy trên port ${PORT}`);
  console.log(`Database: ${process.env.DB_NAME}`);
});