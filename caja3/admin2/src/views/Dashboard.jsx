import React, { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from "../components/ui/card"
import { Progress } from "../components/ui/progress"
import { Separator } from "../components/ui/separator"
import {
    TrendingUp, ShoppingCart, DollarSign, Package, Wifi, WifiOff, Target, ArrowUpRight, ArrowDownRight, Users,
    CreditCard, Building2, Banknote, Bike, AlertTriangle, Calendar, MapPin, BarChart3, Clock, TrendingDown,
    Zap, ListOrdered, Truck, HandCoins
} from "lucide-react"

export function Dashboard() {
    const [data, setData] = useState(null)
    const [loading, setLoading] = useState(true)
    const [wsConnected, setWsConnected] = useState(false)

    const formatCLP = (val) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(val);
    const formatCompact = (val) => {
        if (val >= 1000000) return `$${(val / 1000000).toFixed(2)}M`;
        if (val >= 1000) return `$${(val / 1000).toFixed(0)}k`;
        return formatCLP(val);
    };

    const fetchData = () => {
        fetch('/api/get_modern_dashboard_hub.php')
            .then(res => res.json())
            .then(res => {
                if (res && res.success) setData(res.metrics);
                setLoading(false);
            })
            .catch(err => {
                console.error("Error fetching hub stats:", err);
                setLoading(false);
            })
    }

    useEffect(() => {
        fetchData();
        const wsUrl = import.meta.env.PROD ? 'wss://caja.laruta11.cl:8080' : 'ws://localhost:8080';
        const ws = new WebSocket(wsUrl);
        ws.onopen = () => setWsConnected(true);
        ws.onmessage = () => fetchData();
        ws.onclose = () => setWsConnected(false);
        return () => ws.close();
    }, [])

    if (loading) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[60vh] space-y-4">
                <div className="h-12 w-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <p className="text-muted-foreground font-medium animate-pulse">Sincronizando métricas con PHP 8.5...</p>
            </div>
        )
    }

    const { ventas, compras, margen, inventario, plan_compras, breakeven, goals, payment_methods, addresses } = data;

    const MetricCard = ({ title, value, subValue, icon: Icon, trend, color, footer }) => (
        <Card className="overflow-hidden border-slate-200/60 shadow-sm hover:shadow-md transition-all">
            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle className="text-[10px] font-black uppercase tracking-widest text-muted-foreground/70">{title}</CardTitle>
                <div className={`p-1.5 rounded-lg ${color.bg} ${color.text}`}>
                    <Icon className="h-4 w-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-black tracking-tight">{value}</div>
                {subValue && <div className="text-[11px] font-bold text-muted-foreground mt-0.5">{subValue}</div>}
                {trend !== undefined && (
                    <div className={`flex items-center gap-1 mt-2 text-[10px] font-bold ${trend >= 0 ? 'text-emerald-600' : 'text-red-500'}`}>
                        {trend >= 0 ? <ArrowUpRight className="h-3 w-3" /> : <ArrowDownRight className="h-3 w-3" />}
                        {Math.abs(trend)}% {trend >= 0 ? 'Crecimiento' : 'Descenso'}
                    </div>
                )}
                {footer && <div className="mt-4 pt-3 border-t border-slate-100">{footer}</div>}
            </CardContent>
        </Card>
    );

    return (
        <div className="p-1 space-y-8 animate-in fade-in duration-700">
            {/* --- TOP BAR --- */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-black tracking-tighter text-slate-900">Dashboard Estratégico</h1>
                    <div className="flex items-center gap-3 mt-1.5">
                        <div className={`flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase border ${wsConnected ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-500 border-slate-200'}`}>
                            {wsConnected ? <><Zap className="h-3 w-3 animate-pulse" /> Tiempo real activo</> : <><WifiOff className="h-3 w-3" /> Modo estático</>}
                        </div>
                        <span className="text-[10px] uppercase font-bold text-muted-foreground/60 tracking-wider">Lógica de turnos: 17:30 - 04:00</span>
                    </div>
                </div>
                <div className="bg-white border rounded-xl p-2 flex items-center gap-4 shadow-sm">
                    <div className="text-right">
                        <p className="text-[9px] uppercase font-bold text-muted-foreground">Periodo Actual</p>
                        <p className="text-xs font-black text-slate-800">Marzo 2026</p>
                    </div>
                    <Separator orientation="vertical" className="h-8" />
                    <Calendar className="h-5 w-5 text-blue-600" />
                </div>
            </div>

            {/* --- PRIMARY METRICS GRID --- */}
            <div className="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
                <MetricCard
                    title="Ventas Mes Actual"
                    value={formatCLP(ventas.current)}
                    subValue={`${ventas.orders} Pedidos | Ticket: ${formatCLP(ventas.ticket)}`}
                    icon={DollarSign}
                    trend={ventas.growth}
                    color={{ bg: "bg-blue-50", text: "text-blue-600" }}
                    footer={<div className="text-[10px] font-bold text-slate-600">Top Ingresos: <span className="text-blue-600">{ventas.top_item}</span></div>}
                />
                <MetricCard
                    title="Inversión Inventario"
                    value={formatCLP(compras.total)}
                    subValue={`${compras.count} Compras este mes`}
                    icon={ShoppingCart}
                    color={{ bg: "bg-orange-50", text: "text-orange-600" }}
                    footer={<div className="text-[10px] font-bold text-slate-600 flex justify-between"><span>Items Críticos: <span className="text-red-500">{compras.items_criticos}</span></span> <span>{compras.top_provider}</span></div>}
                />
                <MetricCard
                    title="Margen Bruto"
                    value={formatCLP(margen.bruto)}
                    subValue={`Ventas - CMV (Compras)`}
                    icon={TrendingUp}
                    color={{ bg: "bg-emerald-50", text: "text-emerald-600" }}
                    footer={<div className="text-[10px] font-bold text-slate-600 flex justify-between"><span>Margen %:</span> <span className="text-emerald-600 font-black">{margen.percent}%</span></div>}
                />
                <MetricCard
                    title="Estado Inventario"
                    value={formatCLP(inventario.value)}
                    subValue={`${inventario.count} Items activos`}
                    icon={Package}
                    color={{ bg: "bg-purple-50", text: "text-purple-600" }}
                    footer={<div className="text-[10px] font-bold text-slate-600 flex justify-between"><span>Rotación Mes:</span> <span className="font-black">{inventario.rotation}x</span></div>}
                />
            </div>

            {/* --- STRATEGIC ROW 1 --- */}
            <div className="grid gap-6 grid-cols-1 lg:grid-cols-3">
                {/* Breakeven Card */}
                <Card className="lg:col-span-2 shadow-sm border-slate-200/60 overflow-hidden">
                    <CardHeader className="bg-slate-50/50 pb-3 border-b flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-sm font-black flex items-center gap-2 uppercase tracking-tight">
                                <Target className="h-4 w-4 text-red-500" />
                                Punto de Equilibrio (Rentabilidad)
                            </CardTitle>
                            <p className="text-[10px] text-muted-foreground font-bold">Falta vender para cubrir sueldos y costos fijos</p>
                        </div>
                        <div className={`px-2 py-1 rounded text-[10px] font-black uppercase ${breakeven.progress >= 100 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`}>
                            {breakeven.progress >= 100 ? '✅ Cubierto' : '🚨 En Riesgo'}
                        </div>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div className="space-y-4">
                                <div className="flex items-end gap-3">
                                    <div className="text-4xl font-black tracking-tighter text-slate-900">{formatCLP(breakeven.needed)}</div>
                                    <div className="text-[10px] font-bold text-muted-foreground mb-1.5 uppercase">Restante</div>
                                </div>
                                <div className="space-y-2">
                                    <div className="flex justify-between text-[11px] font-bold uppercase">
                                        <span className="text-muted-foreground">Progreso Mensual</span>
                                        <span className="text-slate-900">{breakeven.progress}%</span>
                                    </div>
                                    <Progress value={breakeven.progress} className={`h-3 ${breakeven.progress >= 100 ? 'bg-green-100' : 'bg-slate-100'}`} />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="p-2 bg-slate-50 rounded-lg border border-slate-100 text-center">
                                        <p className="text-[9px] font-bold uppercase text-muted-foreground">Sueldos</p>
                                        <p className="text-xs font-black">{formatCompact(breakeven.salaries)}</p>
                                    </div>
                                    <div className="p-2 bg-slate-50 rounded-lg border border-slate-100 text-center">
                                        <p className="text-[9px] font-bold uppercase text-muted-foreground">Margen Prom.</p>
                                        <p className="text-xs font-black text-emerald-600">{breakeven.margin}%</p>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-slate-900 p-5 rounded-2xl text-white flex flex-col justify-between relative overflow-hidden group">
                                <div className="relative z-10">
                                    <p className="text-[10px] font-black uppercase text-slate-400 mb-1">Liquidez Inmediata (Flujo)</p>
                                    <div className={`text-3xl font-black ${breakeven.liquidity >= 0 ? 'text-green-400' : 'text-red-400'}`}>
                                        {formatCLP(breakeven.liquidity)}
                                    </div>
                                    <p className="text-[10px] font-medium text-slate-400 mt-2 font-mono">
                                        {formatCompact(ventas.current)} - {formatCompact(compras.total)} - {formatCompact(breakeven.salaries)}
                                    </p>
                                </div>
                                <HandCoins className="absolute -right-4 -bottom-4 h-24 w-24 text-white/5 group-hover:scale-110 transition-transform" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Purchase Plan Card */}
                <Card className="shadow-sm border-slate-200/60 flex flex-col">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm font-black flex items-center gap-2 uppercase">
                            <Truck className="h-4 w-4 text-blue-600" />
                            Plan de Compras
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex-1 space-y-5">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                                <Clock className="h-6 w-6" />
                            </div>
                            <div>
                                <p className="text-[10px] font-bold uppercase text-muted-foreground">Próxima reposición</p>
                                <p className="text-lg font-black">{plan_compras.next_refresh} Items</p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <span className="text-[10px] font-bold uppercase text-muted-foreground">Costo Est.</span>
                                <div className="font-extrabold text-slate-800">{formatCLP(plan_compras.est_cost)}</div>
                            </div>
                            <div className="space-y-1">
                                <span className="text-[10px] font-bold uppercase text-muted-foreground">Urgentes</span>
                                <div className="font-extrabold text-red-600">{plan_compras.urgent} Items</div>
                            </div>
                        </div>
                        <div className="p-3 bg-amber-50 border border-amber-100 rounded-xl">
                            <div className="flex justify-between items-center mb-1">
                                <span className="text-[10px] font-black text-amber-800 uppercase">Días restantes</span>
                                <span className="text-xs font-black text-amber-900">{plan_compras.days} Días</span>
                            </div>
                            <Progress value={(plan_compras.days / 7) * 100} className="h-1.5 bg-amber-200" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* --- GOALS / RITMO SECTION --- */}
            <div className="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
                <Card className="p-4 bg-slate-50 border-slate-200/60 shadow-none">
                    <CardTitle className="text-[10px] font-black uppercase text-muted-foreground mb-3 flex items-center gap-1.5">
                        <BarChart3 className="h-3 w-3" /> Meta Diaria
                    </CardTitle>
                    <div className="space-y-2">
                        <div className="flex justify-between items-baseline">
                            <span className="text-xl font-black">{formatCompact(goals.daily_actual)}</span>
                            <span className="text-[10px] font-bold text-slate-400">/ {formatCompact(goals.daily_target)}</span>
                        </div>
                        <Progress value={(goals.daily_actual / goals.daily_target) * 100} className="h-2" />
                    </div>
                </Card>
                <Card className="p-4 bg-slate-50 border-slate-200/60 shadow-none">
                    <CardTitle className="text-[10px] font-black uppercase text-muted-foreground mb-3 flex items-center gap-1.5">
                        <TrendingUp className="h-3 w-3" /> Meta Mensual
                    </CardTitle>
                    <div className="space-y-2">
                        <div className="flex justify-between items-baseline">
                            <span className="text-xl font-black">{formatCompact(goals.monthly_total)}</span>
                            <span className="text-[10px] font-bold text-slate-400">/ {formatCompact(goals.monthly_target)}</span>
                        </div>
                        <Progress value={(goals.monthly_total / goals.monthly_target) * 100} className="h-2" />
                    </div>
                </Card>
                <Card className="p-4 bg-slate-50 border-slate-200/60 shadow-none">
                    <CardTitle className="text-[10px] font-black uppercase text-muted-foreground mb-3 flex items-center gap-1.5">
                        <Clock className="h-3 w-3" /> Ritmo Sugerido
                    </CardTitle>
                    <div className="text-xl font-black">{formatCLP(goals.ritmo)}<span className="text-[10px] text-slate-400 ml-1">/ día</span></div>
                    <p className="text-[9px] font-bold text-emerald-600 mt-1 uppercase tracking-tighter">Mantener para objetivo</p>
                </Card>
                <Card className="p-4 bg-slate-900 border-none shadow-lg shadow-blue-900/10">
                    <CardTitle className="text-[10px] font-black uppercase text-blue-400 mb-3 flex items-center gap-1.5">
                        <Zap className="h-3 w-3" /> Salud Estratégica
                    </CardTitle>
                    <div className="text-2xl font-black text-white">Excelente</div>
                    <div className="flex gap-1 mt-2">
                        {[1, 2, 3, 4, 5].map(i => <div key={i} className={`h-1 flex-1 rounded-full ${i <= 4 ? 'bg-blue-500' : 'bg-slate-700'}`}></div>)}
                    </div>
                </Card>
            </div>

            {/* --- ANALYTICS SECTION --- */}
            <div className="grid gap-6 grid-cols-1 lg:grid-cols-2">
                {/* Payment Methods */}
                <Card className="shadow-sm border-slate-200/60 overflow-hidden">
                    <CardHeader className="bg-slate-50/50 pb-3 border-b">
                        <CardTitle className="text-sm font-black flex items-center gap-2 uppercase tracking-tight">
                            <CreditCard className="h-4 w-4 text-indigo-500" />
                            Desglose por Método de Pago
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                            {payment_methods?.map((pm, i) => {
                                const icons = { webpay: CreditCard, transfer: Building2, card: CreditCard, cash: Banknote, pedidosya: Bike };
                                const PMIcon = icons[pm.payment_method] || DollarSign;
                                const colors = { webpay: "text-blue-600", transfer: "text-indigo-600", cash: "text-emerald-600", pedidosya: "text-red-500" };
                                return (
                                    <div key={i} className="p-3 bg-white border border-slate-100 rounded-xl hover:border-slate-300 transition-all group">
                                        <div className="flex justify-between mb-2">
                                            <PMIcon className={`h-4 w-4 ${colors[pm.payment_method] || 'text-slate-400'}`} />
                                            <span className="text-[9px] font-black text-slate-300 uppercase tracking-tighter">#{i + 1}</span>
                                        </div>
                                        <p className="text-[10px] font-black uppercase text-slate-400">{pm.payment_method}</p>
                                        <p className="text-sm font-black text-slate-900 group-hover:scale-105 transition-transform">{formatCLP(pm.total)}</p>
                                    </div>
                                )
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Top Addresses */}
                <Card className="shadow-sm border-slate-200/60 overflow-hidden">
                    <CardHeader className="bg-slate-50/50 pb-3 border-b">
                        <CardTitle className="text-sm font-black flex items-center gap-2 uppercase tracking-tight">
                            <MapPin className="h-4 w-4 text-rose-500" />
                            Ventas por Dirección (Top 10)
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-slate-100 max-h-[320px] overflow-auto">
                            {addresses?.map((addr, i) => (
                                <div key={i} className="px-5 py-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                    <div className="flex items-center gap-3">
                                        <span className="h-6 w-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-500">{i + 1}</span>
                                        <span className="text-xs font-bold text-slate-700 truncate max-w-[200px]">{addr.address}</span>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-black text-slate-900">{formatCLP(addr.total)}</p>
                                        <p className="text-[9px] font-bold text-muted-foreground uppercase">{addr.count} pedidos</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="text-center pt-8 border-t border-slate-100 flex flex-col items-center gap-4">
                <div className="flex items-center gap-2 text-xs font-bold text-muted-foreground bg-slate-100 px-4 py-1.5 rounded-full">
                    <Wifi className="h-3 w-3" /> IA-Dashboard Engine v8.5 Active
                </div>
                <div className="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden flex gap-1 px-1 py-[2px]">
                    <div className="h-full w-1/3 bg-blue-500/20 rounded-full"></div>
                    <div className="h-full w-1/4 bg-blue-500/40 rounded-full"></div>
                    <div className="h-full w-1/2 bg-blue-500 rounded-full"></div>
                </div>
            </div>
        </div>
    )
}
