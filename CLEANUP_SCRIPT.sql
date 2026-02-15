-- ============================================
-- SCRIPT DE LIMPIEZA DE TABLAS VACÍAS
-- La Ruta 11 - Database Cleanup
-- ============================================
-- IMPORTANTE: Hacer backup antes de ejecutar
-- ============================================

-- 1. Tablas obsoletas (reemplazadas)
DROP TABLE IF EXISTS ventas;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS order_extras;

-- 2. Tablas sin uso
DROP TABLE IF EXISTS winners;
DROP TABLE IF EXISTS search_analytics;

-- 3. Sistemas implementados pero nunca usados
DROP TABLE IF EXISTS user_notifications;
DROP TABLE IF EXISTS user_coupons;
DROP TABLE IF EXISTS user_orders;
DROP TABLE IF EXISTS user_order_items;
DROP TABLE IF EXISTS cash_register_sessions;
DROP TABLE IF EXISTS app_visits;

-- 4. Tablas de concurso no usadas (las usadas son: concurso_tracking, concurso_registros, concurso_state)
DROP TABLE IF EXISTS concurso_matches;
DROP TABLE IF EXISTS concurso_pagos;
DROP TABLE IF EXISTS concurso_participants;

-- 5. Tablas TUU no usadas (la usada es: tuu_orders con 979 registros)
DROP TABLE IF EXISTS tuu_pagos_online;
DROP TABLE IF EXISTS tuu_payments;
DROP TABLE IF EXISTS tuu_remote_payments;
DROP TABLE IF EXISTS tuu_reports;
DROP TABLE IF EXISTS tuu_sync_control;

-- 6. Auditoría no usada
DROP TABLE IF EXISTS rl6_credit_audit;

-- ============================================
-- TOTAL: 22 tablas eliminadas
-- ============================================
