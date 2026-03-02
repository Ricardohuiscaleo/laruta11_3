import { WebSocketServer } from 'ws'
import mysql from 'mysql2/promise'
import dotenv from 'dotenv'

dotenv.config()

const pool = mysql.createPool({
    host: process.env.APP_DB_HOST || 'localhost',
    database: process.env.APP_DB_NAME || 'ruta11_db',
    user: process.env.APP_DB_USER || 'root',
    password: process.env.APP_DB_PASS || '',
    waitForConnections: true,
    connectionLimit: 10
})

const wss = new WebSocketServer({ port: 8080 })

console.log('🚀 WebSocket server running on ws://localhost:8080')

wss.on('connection', (ws) => {
    console.log('✅ Cliente conectado')

    const sendStats = async () => {
        try {
            const [ventas] = await pool.query(`
                SELECT 
                    COUNT(*) as pedidos_mes,
                    COALESCE(SUM(product_price), 0) as ventas_mes
                FROM tuu_orders 
                WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
                AND payment_status = 'paid'
            `)

            const [hoy] = await pool.query(`
                SELECT COUNT(*) as pedidos_hoy
                FROM tuu_orders 
                WHERE DATE(created_at) = CURRENT_DATE()
            `)

            const [inventario] = await pool.query(`
                SELECT 
                    COUNT(*) as items_activos,
                    COALESCE(SUM(current_stock * cost_per_unit), 0) as total_inventario
                FROM ingredients 
                WHERE is_active = 1
            `)

            const [items_criticos] = await pool.query(`
                SELECT name as nombre, current_stock as stock_actual, unit as unidad
                FROM ingredients 
                WHERE current_stock <= min_stock_level 
                AND is_active = 1
                ORDER BY current_stock ASC
                LIMIT 10
            `)

            const ticket_promedio = ventas[0].pedidos_mes > 0 
                ? ventas[0].ventas_mes / ventas[0].pedidos_mes 
                : 0

            ws.send(JSON.stringify({
                ventas_mes: ventas[0].ventas_mes,
                ventas_mes_formateado: '$' + ventas[0].ventas_mes.toLocaleString('es-CL'),
                pedidos_mes: ventas[0].pedidos_mes,
                pedidos_hoy: hoy[0].pedidos_hoy,
                items_activos: inventario[0].items_activos,
                total_inventario: inventario[0].total_inventario,
                total_inventario_formateado: '$' + inventario[0].total_inventario.toLocaleString('es-CL'),
                ticket_promedio: ticket_promedio,
                ticket_promedio_formateado: '$' + Math.round(ticket_promedio).toLocaleString('es-CL'),
                items_criticos_data: items_criticos,
                timestamp: new Date().toISOString()
            }))
        } catch (error) {
            console.error('❌ Error fetching stats:', error)
            ws.send(JSON.stringify({ error: error.message }))
        }
    }

    sendStats()
    const interval = setInterval(sendStats, 3000)

    ws.on('close', () => {
        console.log('❌ Cliente desconectado')
        clearInterval(interval)
    })

    ws.on('error', (error) => {
        console.error('❌ WebSocket error:', error)
        clearInterval(interval)
    })
})
