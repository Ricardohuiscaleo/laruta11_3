import React, { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { TrendingUp, ShoppingCart, DollarSign, Package, Wifi, WifiOff } from "lucide-react"

export function Dashboard() {
    const [stats, setStats] = useState(null)
    const [loading, setLoading] = useState(true)
    const [wsConnected, setWsConnected] = useState(false)

    useEffect(() => {
        // Fallback: cargar datos iniciales con fetch
        fetch('/api/get_dashboard_stats.php')
            .then(res => res.json())
            .then(data => {
                setStats(data)
                setLoading(false)
            })
            .catch(err => {
                console.error("Error fetching stats:", err)
                setLoading(false)
            })

        // WebSocket para actualizaciones en tiempo real
        const ws = new WebSocket('ws://localhost:8080')
        
        ws.onopen = () => {
            console.log('✅ WebSocket conectado')
            setWsConnected(true)
        }
        
        ws.onmessage = (event) => {
            const data = JSON.parse(event.data)
            if (!data.error) {
                setStats(data)
                setLoading(false)
            }
        }
        
        ws.onerror = (error) => {
            console.error('❌ WebSocket error:', error)
            setWsConnected(false)
        }
        
        ws.onclose = () => {
            console.log('❌ WebSocket desconectado')
            setWsConnected(false)
        }

        return () => ws.close()
    }, [])

    if (loading) {
        return <div className="p-8 text-center text-muted-foreground">Cargando estadísticas...</div>
    }

    const cards = [
        {
            title: "Ventas del Mes",
            value: stats?.ventas_mes_formateado || "$0",
            description: `${stats?.pedidos_mes || 0} pedidos realizados`,
            icon: DollarSign,
            color: "text-emerald-600",
        },
        {
            title: "Inversión Inventario",
            value: stats?.total_inventario_formateado || "$0",
            description: `${stats?.items_activos || 0} productos activos`,
            icon: Package,
            color: "text-blue-600",
        },
        {
            title: "Ticket Promedio",
            value: stats?.ticket_promedio_formateado || "$0",
            description: "Basado en ventas recientes",
            icon: TrendingUp,
            color: "text-orange-600",
        },
        {
            title: "Pedidos Hoy",
            value: stats?.pedidos_hoy || "0",
            description: "Ventas del día actual",
            icon: ShoppingCart,
            color: "text-purple-600",
        },
    ]

    return (
        <div className="space-y-6">
            {/* Indicador de conexión */}
            <div className="flex items-center gap-2 text-sm">
                {wsConnected ? (
                    <>
                        <Wifi className="h-4 w-4 text-green-600" />
                        <span className="text-green-600 font-medium">Tiempo real activo</span>
                    </>
                ) : (
                    <>
                        <WifiOff className="h-4 w-4 text-gray-400" />
                        <span className="text-gray-400">Modo estático</span>
                    </>
                )}
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {cards.map((card) => (
                    <Card key={card.title} className="shadow-sm border-slate-200/60 bg-white/50 backdrop-blur-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {card.title}
                            </CardTitle>
                            <card.icon className={`h-4 w-4 ${card.color}`} />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{card.value}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {card.description}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                <Card className="col-span-4 shadow-sm border-slate-200/60">
                    <CardHeader>
                        <CardTitle>Resumen Operativo</CardTitle>
                    </CardHeader>
                    <CardContent className="h-[300px] flex items-center justify-center text-muted-foreground italic">
                        Gráficos impulsados por Chart.js próximamente...
                    </CardContent>
                </Card>
                <Card className="col-span-3 shadow-sm border-slate-200/60">
                    <CardHeader>
                        <CardTitle>Items Críticos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {stats?.items_criticos_data?.slice(0, 5).map((item, i) => (
                                <div key={i} className="flex items-center justify-between text-sm">
                                    <span className="font-medium text-slate-700">{item.nombre}</span>
                                    <span className="px-2 py-1 bg-red-100 text-red-700 rounded-md text-xs font-bold">
                                        {item.stock_actual} {item.unidad}
                                    </span>
                                </div>
                            ))}
                            {(!stats?.items_criticos_data || stats?.items_criticos_data.length === 0) && (
                                <p className="text-sm text-center text-muted-foreground">No hay items en nivel crítico.</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    )
}
