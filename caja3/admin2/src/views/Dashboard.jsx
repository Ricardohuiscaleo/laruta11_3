import React, { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from "../components/ui/card"
import { Progress } from "../components/ui/progress"
import {
    TrendingUp,
    ShoppingCart,
    DollarSign,
    Package,
    Wifi,
    WifiOff,
    Target,
    ArrowUpRight,
    ArrowDownRight,
    Users
} from "lucide-react"

export function Dashboard() {
    const [data, setData] = useState(null)
    const [loading, setLoading] = useState(true)
    const [wsConnected, setWsConnected] = useState(false)

    const formatCLP = (val) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(val);

    useEffect(() => {
        // Cargar datos iniciales desde el nuevo HUB API
        const fetchData = () => {
            fetch('/api/get_modern_dashboard_hub.php')
                .then(res => res.json())
                .then(res => {
                    if (res && res.success) {
                        setData(res)
                    }
                    setLoading(false)
                })
                .catch(err => {
                    console.error("Error fetching hub stats:", err)
                    setLoading(false)
                })
        }

        fetchData()

        // WebSocket para actualizaciones en tiempo real (mantener compatibilidad)
        const wsUrl = import.meta.env.PROD
            ? 'wss://caja.laruta11.cl:8080'
            : 'ws://localhost:8080'

        const ws = new WebSocket(wsUrl)

        ws.onopen = () => {
            setWsConnected(true)
        }

        ws.onmessage = (event) => {
            const wsData = JSON.parse(event.data)
            if (!wsData.error) {
                // Si el WS envía datos básicos, refrescar el HUB para métricas completas
                fetchData()
            }
        }

        ws.onclose = () => setWsConnected(false)
        return () => ws.close()
    }, [])

    if (loading) {
        return <div className="p-8 text-center text-muted-foreground animate-pulse">Analizando métricas estratégicas...</div>
    }

    const metrics = data?.metrics || {}
    const inventory = data?.inventory || {}

    const kpiCards = [
        {
            title: "Ventas (Netas)",
            value: formatCLP(metrics.sales?.current_net || 0),
            description: `${metrics.sales?.orders_count || 0} pedidos pagados`,
            icon: DollarSign,
            trend: metrics.financial?.growth_percent,
            color: "text-emerald-600",
        },
        {
            title: "Punto de Equilibrio",
            value: formatCLP(metrics.goals?.breakeven_sales || 0),
            description: `Meta para cubrir sueldos`,
            icon: Target,
            color: "text-blue-600",
        },
        {
            title: "Cumplimiento Meta",
            value: `${metrics.goals?.monthly_completion || 0}%`,
            description: `Progreso del mes actual`,
            icon: TrendingUp,
            color: "text-orange-600",
        },
        {
            title: "Ventas Hoy",
            value: formatCLP(metrics.sales?.today_net || 0),
            description: `${metrics.sales?.today_count || 0} pedidos hoy`,
            icon: ShoppingCart,
            color: "text-purple-600",
        },
    ]

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-sm">
                    {wsConnected ? (
                        <div className="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 rounded-full border border-green-200">
                            <Wifi className="h-3.5 w-3.5 animate-pulse" />
                            <span className="font-semibold">Tiempo real activo</span>
                        </div>
                    ) : (
                        <div className="flex items-center gap-1.5 px-2.5 py-1 bg-gray-50 text-gray-500 rounded-full border border-gray-200">
                            <WifiOff className="h-3.5 w-3.5" />
                            <span className="font-medium">Modo estático</span>
                        </div>
                    )}
                </div>
                <div className="text-xs text-muted-foreground bg-slate-100 px-3 py-1 rounded-md font-mono">
                    Última actualización: {data?.timestamp}
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {kpiCards.map((card) => (
                    <Card key={card.title} className="shadow-sm border-slate-200/60 bg-white/50 backdrop-blur-sm overflow-hidden relative group transition-all hover:shadow-md hover:border-slate-300">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-bold uppercase tracking-wider text-muted-foreground/80">
                                {card.title}
                            </CardTitle>
                            <card.icon className={`h-4 w-4 ${card.color}`} />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-black text-slate-900">{card.value}</div>
                            <div className="flex items-center gap-2 mt-1">
                                {card.trend !== undefined && (
                                    <span className={`text-[10px] font-bold flex items-center px-1.5 py-0.5 rounded ${card.trend >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                        {card.trend >= 0 ? <ArrowUpRight className="h-3 w-3 mr-0.5" /> : <ArrowDownRight className="h-3 w-3 mr-0.5" />}
                                        {Math.abs(card.trend)}%
                                    </span>
                                )}
                                <p className="text-[11px] text-muted-foreground font-medium">
                                    {card.description}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                <Card className="col-span-4 shadow-sm border-slate-200/60">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Rendimiento Estratégico</CardTitle>
                            <p className="text-xs text-muted-foreground">Comparativa de objetivos y ritmo actual</p>
                        </div>
                        <div className={`px-3 py-1.5 rounded-lg text-sm font-black ${metrics.goals?.progression_percent >= 100 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                            {metrics.goals?.progression_percent}% Ritmo
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-2">
                            <div className="flex justify-between text-xs font-bold">
                                <span>Progreso Mensual</span>
                                <span>{metrics.goals?.monthly_completion}%</span>
                            </div>
                            <Progress value={metrics.goals?.monthly_completion} className="h-3 bg-slate-100" />
                            <p className="text-[10px] text-muted-foreground">Vendido: {formatCLP(metrics.sales?.current_net)} / Meta Seguridad: {formatCLP(metrics.goals?.safe_target)}</p>
                        </div>

                        <div className="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                            <div className="space-y-1">
                                <span className="text-[10px] uppercase font-bold text-muted-foreground">Promedio Diario Real</span>
                                <div className="text-lg font-black text-slate-800">{formatCLP(metrics.goals?.daily_average)}</div>
                            </div>
                            <div className="space-y-1">
                                <span className="text-[10px] uppercase font-bold text-muted-foreground">Meta Diaria (Ideal)</span>
                                <div className="text-lg font-black text-slate-800 text-blue-600">{formatCLP(metrics.goals?.daily_goal)}</div>
                            </div>
                            <div className="space-y-1">
                                <span className="text-[10px] uppercase font-bold text-muted-foreground">Margen Operativo Bruto</span>
                                <div className="text-lg font-black text-emerald-600">{metrics.financial?.margin_percent}%</div>
                            </div>
                            <div className="space-y-1">
                                <span className="text-[10px] uppercase font-bold text-muted-foreground">Días Transcurridos</span>
                                <div className="text-lg font-black text-slate-800">{metrics.goals?.days_passed} / {metrics.goals?.days_total}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="col-span-3 shadow-sm border-slate-200/60 flex flex-col">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5 text-red-500" />
                            Alertas de Inventario
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex-1 overflow-auto">
                        <div className="space-y-3">
                            {inventory.items?.map((item, i) => (
                                <div key={i} className="flex items-center justify-between p-2 rounded-lg bg-red-50/50 border border-red-100 transition-colors hover:bg-red-50">
                                    <div className="flex flex-col">
                                        <span className="text-sm font-bold text-slate-800">{item.name}</span>
                                        <span className="text-[10px] text-muted-foreground uppercase font-semibold">Min: {item.min_stock_level} {item.unit}</span>
                                    </div>
                                    <span className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-black">
                                        {item.current_stock}
                                    </span>
                                </div>
                            ))}
                            {(!inventory.items || inventory.items.length === 0) && (
                                <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                                    <div className="text-4xl mb-2">✅</div>
                                    <p className="text-sm font-medium">Stock saludable</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card className="shadow-sm border-slate-200/60 bg-blue-600 text-white text-center p-6 bg-gradient-to-r from-blue-600 to-blue-700 overflow-hidden relative">
                <div className="relative z-10 flex flex-col items-center gap-2">
                    <Target className="h-8 w-8 opacity-50" />
                    <h3 className="text-xl font-black">Próximo Hito: {formatCLP(metrics.goals?.safe_target)}</h3>
                    <p className="text-blue-100 text-sm font-medium italic opacity-90 max-w-md">
                        Alcanzar esta cifra asegura la cobertura total de sueldos y costos fijos de este mes.
                    </p>
                </div>
                <div className="absolute top-0 right-0 -mr-16 -mt-16 bg-white/10 w-64 h-64 rounded-full blur-3xl"></div>
                <div className="absolute bottom-0 left-0 -ml-16 -mb-16 bg-blue-400/20 w-48 h-48 rounded-full blur-2xl"></div>
            </Card>
        </div>
    )
}
