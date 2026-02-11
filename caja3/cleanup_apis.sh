#!/bin/bash
# Script de limpieza de APIs no utilizadas
# Generado automáticamente

echo "🧹 Limpiando APIs no utilizadas..."

# Crear backup
mkdir -p backup_apis
echo "📦 Creando backup..."

# Backup y eliminar: S3Manager.php
cp "api/S3Manager.php" "backup_apis/" 2>/dev/null
rm "api/S3Manager.php"
# Backup y eliminar: activos_update.php
cp "api/activos_update.php" "backup_apis/" 2>/dev/null
rm "api/activos_update.php"
# Backup y eliminar: activos_update_v2.php
cp "api/activos_update_v2.php" "backup_apis/" 2>/dev/null
rm "api/activos_update_v2.php"
# Backup y eliminar: actualizar_ingredientes.php
cp "api/actualizar_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/actualizar_ingredientes.php"
# Backup y eliminar: actualizar_peso_ingredientes.php
cp "api/actualizar_peso_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/actualizar_peso_ingredientes.php"
# Backup y eliminar: auth/add_session_token.php
cp "api/auth/add_session_token.php" "backup_apis/" 2>/dev/null
rm "api/auth/add_session_token.php"
# Backup y eliminar: auth/check_database.php
cp "api/auth/check_database.php" "backup_apis/" 2>/dev/null
rm "api/auth/check_database.php"
# Backup y eliminar: auth/debug_cookies.php
cp "api/auth/debug_cookies.php" "backup_apis/" 2>/dev/null
rm "api/auth/debug_cookies.php"
# Backup y eliminar: auth/debug_register.php
cp "api/auth/debug_register.php" "backup_apis/" 2>/dev/null
rm "api/auth/debug_register.php"
# Backup y eliminar: auth/debug_session.php
cp "api/auth/debug_session.php" "backup_apis/" 2>/dev/null
rm "api/auth/debug_session.php"
# Backup y eliminar: auth/gmail/auto_refresh.php
cp "api/auth/gmail/auto_refresh.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/auto_refresh.php"
# Backup y eliminar: auth/gmail/callback.php
cp "api/auth/gmail/callback.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/callback.php"
# Backup y eliminar: auth/gmail/gmail_setup.php
cp "api/auth/gmail/gmail_setup.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/gmail_setup.php"
# Backup y eliminar: auth/gmail/refresh_token.php
cp "api/auth/gmail/refresh_token.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/refresh_token.php"
# Backup y eliminar: auth/gmail/send_email.php
cp "api/auth/gmail/send_email.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/send_email.php"
# Backup y eliminar: auth/gmail/setup.php
cp "api/auth/gmail/setup.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/setup.php"
# Backup y eliminar: auth/gmail/test_gmail.php
cp "api/auth/gmail/test_gmail.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/test_gmail.php"
# Backup y eliminar: auth/gmail/test_send.php
cp "api/auth/gmail/test_send.php" "backup_apis/" 2>/dev/null
rm "api/auth/gmail/test_send.php"
# Backup y eliminar: auth/google/jobs_callback.php
cp "api/auth/google/jobs_callback.php" "backup_apis/" 2>/dev/null
rm "api/auth/google/jobs_callback.php"
# Backup y eliminar: auth/google/jobs_login.php
cp "api/auth/google/jobs_login.php" "backup_apis/" 2>/dev/null
rm "api/auth/google/jobs_login.php"
# Backup y eliminar: auth/google/tracker_callback.php
cp "api/auth/google/tracker_callback.php" "backup_apis/" 2>/dev/null
rm "api/auth/google/tracker_callback.php"
# Backup y eliminar: auth/google/tracker_login.php
cp "api/auth/google/tracker_login.php" "backup_apis/" 2>/dev/null
rm "api/auth/google/tracker_login.php"
# Backup y eliminar: auth/jobs_check_session.php
cp "api/auth/jobs_check_session.php" "backup_apis/" 2>/dev/null
rm "api/auth/jobs_check_session.php"
# Backup y eliminar: auth/setup_manual_auth.php
cp "api/auth/setup_manual_auth.php" "backup_apis/" 2>/dev/null
rm "api/auth/setup_manual_auth.php"
# Backup y eliminar: auth/test_columns.php
cp "api/auth/test_columns.php" "backup_apis/" 2>/dev/null
rm "api/auth/test_columns.php"
# Backup y eliminar: auth/test_register.php
cp "api/auth/test_register.php" "backup_apis/" 2>/dev/null
rm "api/auth/test_register.php"
# Backup y eliminar: auth/test_session.php
cp "api/auth/test_session.php" "backup_apis/" 2>/dev/null
rm "api/auth/test_session.php"
# Backup y eliminar: auth/tracker_check_session.php
cp "api/auth/tracker_check_session.php" "backup_apis/" 2>/dev/null
rm "api/auth/tracker_check_session.php"
# Backup y eliminar: auth/tracker_logout.php
cp "api/auth/tracker_logout.php" "backup_apis/" 2>/dev/null
rm "api/auth/tracker_logout.php"
# Backup y eliminar: categorias_hardcoded.php
cp "api/categorias_hardcoded.php" "backup_apis/" 2>/dev/null
rm "api/categorias_hardcoded.php"
# Backup y eliminar: check_all_connections.php
cp "api/check_all_connections.php" "backup_apis/" 2>/dev/null
rm "api/check_all_connections.php"
# Backup y eliminar: check_files.php
cp "api/check_files.php" "backup_apis/" 2>/dev/null
rm "api/check_files.php"
# Backup y eliminar: check_order_8.php
cp "api/check_order_8.php" "backup_apis/" 2>/dev/null
rm "api/check_order_8.php"
# Backup y eliminar: check_quota.php
cp "api/check_quota.php" "backup_apis/" 2>/dev/null
rm "api/check_quota.php"
# Backup y eliminar: check_tables.php
cp "api/check_tables.php" "backup_apis/" 2>/dev/null
rm "api/check_tables.php"
# Backup y eliminar: check_ventas_structure.php
cp "api/check_ventas_structure.php" "backup_apis/" 2>/dev/null
rm "api/check_ventas_structure.php"
# Backup y eliminar: corregir_recetas.php
cp "api/corregir_recetas.php" "backup_apis/" 2>/dev/null
rm "api/corregir_recetas.php"
# Backup y eliminar: costos_fijos_update.php
cp "api/costos_fijos_update.php" "backup_apis/" 2>/dev/null
rm "api/costos_fijos_update.php"
# Backup y eliminar: costos_fijos_update_v2.php
cp "api/costos_fijos_update_v2.php" "backup_apis/" 2>/dev/null
rm "api/costos_fijos_update_v2.php"
# Backup y eliminar: create_backup.php
cp "api/create_backup.php" "backup_apis/" 2>/dev/null
rm "api/create_backup.php"
# Backup y eliminar: create_order.php
cp "api/create_order.php" "backup_apis/" 2>/dev/null
rm "api/create_order.php"
# Backup y eliminar: create_productos_table.php
cp "api/create_productos_table.php" "backup_apis/" 2>/dev/null
rm "api/create_productos_table.php"
# Backup y eliminar: cron/refresh_gmail_token.php
cp "api/cron/refresh_gmail_token.php" "backup_apis/" 2>/dev/null
rm "api/cron/refresh_gmail_token.php"
# Backup y eliminar: cross_reference_system.php
cp "api/cross_reference_system.php" "backup_apis/" 2>/dev/null
rm "api/cross_reference_system.php"
# Backup y eliminar: debug_analisis.php
cp "api/debug_analisis.php" "backup_apis/" 2>/dev/null
rm "api/debug_analisis.php"
# Backup y eliminar: debug_db_connection.php
cp "api/debug_db_connection.php" "backup_apis/" 2>/dev/null
rm "api/debug_db_connection.php"
# Backup y eliminar: debug_ingredientes.php
cp "api/debug_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/debug_ingredientes.php"
# Backup y eliminar: debug_metrics.php
cp "api/debug_metrics.php" "backup_apis/" 2>/dev/null
rm "api/debug_metrics.php"
# Backup y eliminar: debug_order.php
cp "api/debug_order.php" "backup_apis/" 2>/dev/null
rm "api/debug_order.php"
# Backup y eliminar: debug_proyeccion.php
cp "api/debug_proyeccion.php" "backup_apis/" 2>/dev/null
rm "api/debug_proyeccion.php"
# Backup y eliminar: debug_recetas.php
cp "api/debug_recetas.php" "backup_apis/" 2>/dev/null
rm "api/debug_recetas.php"
# Backup y eliminar: delete_image_from_gallery.php
cp "api/delete_image_from_gallery.php" "backup_apis/" 2>/dev/null
rm "api/delete_image_from_gallery.php"
# Backup y eliminar: delete_ingrediente.php
cp "api/delete_ingrediente.php" "backup_apis/" 2>/dev/null
rm "api/delete_ingrediente.php"
# Backup y eliminar: delete_product_image.php
cp "api/delete_product_image.php" "backup_apis/" 2>/dev/null
rm "api/delete_product_image.php"
# Backup y eliminar: delete_proyeccion.php
cp "api/delete_proyeccion.php" "backup_apis/" 2>/dev/null
rm "api/delete_proyeccion.php"
# Backup y eliminar: delete_proyeccion_v2.php
cp "api/delete_proyeccion_v2.php" "backup_apis/" 2>/dev/null
rm "api/delete_proyeccion_v2.php"
# Backup y eliminar: diagnostico.php
cp "api/diagnostico.php" "backup_apis/" 2>/dev/null
rm "api/diagnostico.php"
# Backup y eliminar: diagnostico_html.php
cp "api/diagnostico_html.php" "backup_apis/" 2>/dev/null
rm "api/diagnostico_html.php"
# Backup y eliminar: expand_image_url_field.php
cp "api/expand_image_url_field.php" "backup_apis/" 2>/dev/null
rm "api/expand_image_url_field.php"
# Backup y eliminar: fix_categorias.php
cp "api/fix_categorias.php" "backup_apis/" 2>/dev/null
rm "api/fix_categorias.php"
# Backup y eliminar: fix_ingredientes.php
cp "api/fix_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/fix_ingredientes.php"
# Backup y eliminar: fix_ingredientes_id.php
cp "api/fix_ingredientes_id.php" "backup_apis/" 2>/dev/null
rm "api/fix_ingredientes_id.php"
# Backup y eliminar: fix_truncated_urls.php
cp "api/fix_truncated_urls.php" "backup_apis/" 2>/dev/null
rm "api/fix_truncated_urls.php"
# Backup y eliminar: food_trucks/get_exact_coordinates.php
cp "api/food_trucks/get_exact_coordinates.php" "backup_apis/" 2>/dev/null
rm "api/food_trucks/get_exact_coordinates.php"
# Backup y eliminar: food_trucks/update_locations.php
cp "api/food_trucks/update_locations.php" "backup_apis/" 2>/dev/null
rm "api/food_trucks/update_locations.php"
# Backup y eliminar: generar_analisis.php
cp "api/generar_analisis.php" "backup_apis/" 2>/dev/null
rm "api/generar_analisis.php"
# Backup y eliminar: get_analisis.php
cp "api/get_analisis.php" "backup_apis/" 2>/dev/null
rm "api/get_analisis.php"
# Backup y eliminar: get_analytics.php
cp "api/get_analytics.php" "backup_apis/" 2>/dev/null
rm "api/get_analytics.php"
# Backup y eliminar: get_bebida.php
cp "api/get_bebida.php" "backup_apis/" 2>/dev/null
rm "api/get_bebida.php"
# Backup y eliminar: get_categorias_from_ingredientes.php
cp "api/get_categorias_from_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/get_categorias_from_ingredientes.php"
# Backup y eliminar: get_combined_transactions.php
cp "api/get_combined_transactions.php" "backup_apis/" 2>/dev/null
rm "api/get_combined_transactions.php"
# Backup y eliminar: get_dashboard_kpis.php
cp "api/get_dashboard_kpis.php" "backup_apis/" 2>/dev/null
rm "api/get_dashboard_kpis.php"
# Backup y eliminar: get_ingrediente.php
cp "api/get_ingrediente.php" "backup_apis/" 2>/dev/null
rm "api/get_ingrediente.php"
# Backup y eliminar: get_ingredientes.php
cp "api/get_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/get_ingredientes.php"
# Backup y eliminar: get_ingredientes_fixed.php
cp "api/get_ingredientes_fixed.php" "backup_apis/" 2>/dev/null
rm "api/get_ingredientes_fixed.php"
# Backup y eliminar: get_pending_orders.php
cp "api/get_pending_orders.php" "backup_apis/" 2>/dev/null
rm "api/get_pending_orders.php"
# Backup y eliminar: get_pos_status.php
cp "api/get_pos_status.php" "backup_apis/" 2>/dev/null
rm "api/get_pos_status.php"
# Backup y eliminar: get_proyeccion.php
cp "api/get_proyeccion.php" "backup_apis/" 2>/dev/null
rm "api/get_proyeccion.php"
# Backup y eliminar: get_proyeccion_ejemplo.php
cp "api/get_proyeccion_ejemplo.php" "backup_apis/" 2>/dev/null
rm "api/get_proyeccion_ejemplo.php"
# Backup y eliminar: get_proyecciones.php
cp "api/get_proyecciones.php" "backup_apis/" 2>/dev/null
rm "api/get_proyecciones.php"
# Backup y eliminar: get_recetas.php
cp "api/get_recetas.php" "backup_apis/" 2>/dev/null
rm "api/get_recetas.php"
# Backup y eliminar: get_recetas_fixed.php
cp "api/get_recetas_fixed.php" "backup_apis/" 2>/dev/null
rm "api/get_recetas_fixed.php"
# Backup y eliminar: get_recetas_simple.php
cp "api/get_recetas_simple.php" "backup_apis/" 2>/dev/null
rm "api/get_recetas_simple.php"
# Backup y eliminar: get_server_time.php
cp "api/get_server_time.php" "backup_apis/" 2>/dev/null
rm "api/get_server_time.php"
# Backup y eliminar: get_unsplash_background.php
cp "api/get_unsplash_background.php" "backup_apis/" 2>/dev/null
rm "api/get_unsplash_background.php"
# Backup y eliminar: get_user_detail.php
cp "api/get_user_detail.php" "backup_apis/" 2>/dev/null
rm "api/get_user_detail.php"
# Backup y eliminar: get_users.php
cp "api/get_users.php" "backup_apis/" 2>/dev/null
rm "api/get_users.php"
# Backup y eliminar: guardar_analisis.php
cp "api/guardar_analisis.php" "backup_apis/" 2>/dev/null
rm "api/guardar_analisis.php"
# Backup y eliminar: ia_analisis.php
cp "api/ia_analisis.php" "backup_apis/" 2>/dev/null
rm "api/ia_analisis.php"
# Backup y eliminar: init_ia_tables.php
cp "api/init_ia_tables.php" "backup_apis/" 2>/dev/null
rm "api/init_ia_tables.php"
# Backup y eliminar: jobs/analyze_text.php
cp "api/jobs/analyze_text.php" "backup_apis/" 2>/dev/null
rm "api/jobs/analyze_text.php"
# Backup y eliminar: jobs/debug_keywords.php
cp "api/jobs/debug_keywords.php" "backup_apis/" 2>/dev/null
rm "api/jobs/debug_keywords.php"
# Backup y eliminar: jobs/fix_completed_at.php
cp "api/jobs/fix_completed_at.php" "backup_apis/" 2>/dev/null
rm "api/jobs/fix_completed_at.php"
# Backup y eliminar: jobs/fix_scores.php
cp "api/jobs/fix_scores.php" "backup_apis/" 2>/dev/null
rm "api/jobs/fix_scores.php"
# Backup y eliminar: jobs/get_application_data.php
cp "api/jobs/get_application_data.php" "backup_apis/" 2>/dev/null
rm "api/jobs/get_application_data.php"
# Backup y eliminar: jobs/get_application_summary.php
cp "api/jobs/get_application_summary.php" "backup_apis/" 2>/dev/null
rm "api/jobs/get_application_summary.php"
# Backup y eliminar: jobs/get_keywords.php
cp "api/jobs/get_keywords.php" "backup_apis/" 2>/dev/null
rm "api/jobs/get_keywords.php"
# Backup y eliminar: jobs/start_application.php
cp "api/jobs/start_application.php" "backup_apis/" 2>/dev/null
rm "api/jobs/start_application.php"
# Backup y eliminar: jobs/submit_application.php
cp "api/jobs/submit_application.php" "backup_apis/" 2>/dev/null
rm "api/jobs/submit_application.php"
# Backup y eliminar: jobs/test_jobs_table.php
cp "api/jobs/test_jobs_table.php" "backup_apis/" 2>/dev/null
rm "api/jobs/test_jobs_table.php"
# Backup y eliminar: jobs/test_keywords.php
cp "api/jobs/test_keywords.php" "backup_apis/" 2>/dev/null
rm "api/jobs/test_keywords.php"
# Backup y eliminar: jobs/test_score_calculation.php
cp "api/jobs/test_score_calculation.php" "backup_apis/" 2>/dev/null
rm "api/jobs/test_score_calculation.php"
# Backup y eliminar: jobs/track_input.php
cp "api/jobs/track_input.php" "backup_apis/" 2>/dev/null
rm "api/jobs/track_input.php"
# Backup y eliminar: jobs/update_answers.php
cp "api/jobs/update_answers.php" "backup_apis/" 2>/dev/null
rm "api/jobs/update_answers.php"
# Backup y eliminar: jobs/update_time_elapsed.php
cp "api/jobs/update_time_elapsed.php" "backup_apis/" 2>/dev/null
rm "api/jobs/update_time_elapsed.php"
# Backup y eliminar: list_models.php
cp "api/list_models.php" "backup_apis/" 2>/dev/null
rm "api/list_models.php"
# Backup y eliminar: migrate_personal_data.php
cp "api/migrate_personal_data.php" "backup_apis/" 2>/dev/null
rm "api/migrate_personal_data.php"
# Backup y eliminar: notifications/send_notification.php
cp "api/notifications/send_notification.php" "backup_apis/" 2>/dev/null
rm "api/notifications/send_notification.php"
# Backup y eliminar: proyeccion.php
cp "api/proyeccion.php" "backup_apis/" 2>/dev/null
rm "api/proyeccion.php"
# Backup y eliminar: proyeccion_financiera.php
cp "api/proyeccion_financiera.php" "backup_apis/" 2>/dev/null
rm "api/proyeccion_financiera.php"
# Backup y eliminar: proyeccion_v2.php
cp "api/proyeccion_v2.php" "backup_apis/" 2>/dev/null
rm "api/proyeccion_v2.php"
# Backup y eliminar: proyeccion_v3.php
cp "api/proyeccion_v3.php" "backup_apis/" 2>/dev/null
rm "api/proyeccion_v3.php"
# Backup y eliminar: proyecciones.php
cp "api/proyecciones.php" "backup_apis/" 2>/dev/null
rm "api/proyecciones.php"
# Backup y eliminar: proyecciones_v2.php
cp "api/proyecciones_v2.php" "backup_apis/" 2>/dev/null
rm "api/proyecciones_v2.php"
# Backup y eliminar: quota_info.php
cp "api/quota_info.php" "backup_apis/" 2>/dev/null
rm "api/quota_info.php"
# Backup y eliminar: raw_data.php
cp "api/raw_data.php" "backup_apis/" 2>/dev/null
rm "api/raw_data.php"
# Backup y eliminar: registrar_venta.php
cp "api/registrar_venta.php" "backup_apis/" 2>/dev/null
rm "api/registrar_venta.php"
# Backup y eliminar: reload_page.php
cp "api/reload_page.php" "backup_apis/" 2>/dev/null
rm "api/reload_page.php"
# Backup y eliminar: reset_ingredientes.php
cp "api/reset_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/reset_ingredientes.php"
# Backup y eliminar: save_bebida.php
cp "api/save_bebida.php" "backup_apis/" 2>/dev/null
rm "api/save_bebida.php"
# Backup y eliminar: save_config.php
cp "api/save_config.php" "backup_apis/" 2>/dev/null
rm "api/save_config.php"
# Backup y eliminar: save_image_url.php
cp "api/save_image_url.php" "backup_apis/" 2>/dev/null
rm "api/save_image_url.php"
# Backup y eliminar: save_ingrediente.php
cp "api/save_ingrediente.php" "backup_apis/" 2>/dev/null
rm "api/save_ingrediente.php"
# Backup y eliminar: save_proyeccion.php
cp "api/save_proyeccion.php" "backup_apis/" 2>/dev/null
rm "api/save_proyeccion.php"
# Backup y eliminar: save_proyeccion_v2.php
cp "api/save_proyeccion_v2.php" "backup_apis/" 2>/dev/null
rm "api/save_proyeccion_v2.php"
# Backup y eliminar: seed_dashboard_data.php
cp "api/seed_dashboard_data.php" "backup_apis/" 2>/dev/null
rm "api/seed_dashboard_data.php"
# Backup y eliminar: seed_data.php
cp "api/seed_data.php" "backup_apis/" 2>/dev/null
rm "api/seed_data.php"
# Backup y eliminar: setup_analytics_tables.php
cp "api/setup_analytics_tables.php" "backup_apis/" 2>/dev/null
rm "api/setup_analytics_tables.php"
# Backup y eliminar: setup_app_db.php
cp "api/setup_app_db.php" "backup_apis/" 2>/dev/null
rm "api/setup_app_db.php"
# Backup y eliminar: setup_categorias.php
cp "api/setup_categorias.php" "backup_apis/" 2>/dev/null
rm "api/setup_categorias.php"
# Backup y eliminar: setup_dashboard_tables.php
cp "api/setup_dashboard_tables.php" "backup_apis/" 2>/dev/null
rm "api/setup_dashboard_tables.php"
# Backup y eliminar: setup_ia_tables.php
cp "api/setup_ia_tables.php" "backup_apis/" 2>/dev/null
rm "api/setup_ia_tables.php"
# Backup y eliminar: setup_orders_table.php
cp "api/setup_orders_table.php" "backup_apis/" 2>/dev/null
rm "api/setup_orders_table.php"
# Backup y eliminar: setup_proyecciones_simple.php
cp "api/setup_proyecciones_simple.php" "backup_apis/" 2>/dev/null
rm "api/setup_proyecciones_simple.php"
# Backup y eliminar: setup_proyecciones_v2.php
cp "api/setup_proyecciones_v2.php" "backup_apis/" 2>/dev/null
rm "api/setup_proyecciones_v2.php"
# Backup y eliminar: setup_real_db.php
cp "api/setup_real_db.php" "backup_apis/" 2>/dev/null
rm "api/setup_real_db.php"
# Backup y eliminar: setup_user_columns.php
cp "api/setup_user_columns.php" "backup_apis/" 2>/dev/null
rm "api/setup_user_columns.php"
# Backup y eliminar: setup_user_tables.php
cp "api/setup_user_tables.php" "backup_apis/" 2>/dev/null
rm "api/setup_user_tables.php"
# Backup y eliminar: setup_ventas_tables.php
cp "api/setup_ventas_tables.php" "backup_apis/" 2>/dev/null
rm "api/setup_ventas_tables.php"
# Backup y eliminar: simple_fix.php
cp "api/simple_fix.php" "backup_apis/" 2>/dev/null
rm "api/simple_fix.php"
# Backup y eliminar: simple_update_ventas.php
cp "api/simple_update_ventas.php" "backup_apis/" 2>/dev/null
rm "api/simple_update_ventas.php"
# Backup y eliminar: sincronizar_ingredientes.php
cp "api/sincronizar_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/sincronizar_ingredientes.php"
# Backup y eliminar: sync_example.php
cp "api/sync_example.php" "backup_apis/" 2>/dev/null
rm "api/sync_example.php"
# Backup y eliminar: test/find_config.php
cp "api/test/find_config.php" "backup_apis/" 2>/dev/null
rm "api/test/find_config.php"
# Backup y eliminar: test_api.php
cp "api/test_api.php" "backup_apis/" 2>/dev/null
rm "api/test_api.php"
# Backup y eliminar: test_connection.php
cp "api/test_connection.php" "backup_apis/" 2>/dev/null
rm "api/test_connection.php"
# Backup y eliminar: test_cors.php
cp "api/test_cors.php" "backup_apis/" 2>/dev/null
rm "api/test_cors.php"
# Backup y eliminar: test_dashboard.php
cp "api/test_dashboard.php" "backup_apis/" 2>/dev/null
rm "api/test_dashboard.php"
# Backup y eliminar: test_gemini.php
cp "api/test_gemini.php" "backup_apis/" 2>/dev/null
rm "api/test_gemini.php"
# Backup y eliminar: test_job_demo.php
cp "api/test_job_demo.php" "backup_apis/" 2>/dev/null
rm "api/test_job_demo.php"
# Backup y eliminar: test_jobs_table.php
cp "api/test_jobs_table.php" "backup_apis/" 2>/dev/null
rm "api/test_jobs_table.php"
# Backup y eliminar: test_kanban_tables.php
cp "api/test_kanban_tables.php" "backup_apis/" 2>/dev/null
rm "api/test_kanban_tables.php"
# Backup y eliminar: test_keywords.php
cp "api/test_keywords.php" "backup_apis/" 2>/dev/null
rm "api/test_keywords.php"
# Backup y eliminar: test_s3.php
cp "api/test_s3.php" "backup_apis/" 2>/dev/null
rm "api/test_s3.php"
# Backup y eliminar: test_update_costos.php
cp "api/test_update_costos.php" "backup_apis/" 2>/dev/null
rm "api/test_update_costos.php"
# Backup y eliminar: test_ventas_v2.php
cp "api/test_ventas_v2.php" "backup_apis/" 2>/dev/null
rm "api/test_ventas_v2.php"
# Backup y eliminar: tracker/debug_kanban_status.php
cp "api/tracker/debug_kanban_status.php" "backup_apis/" 2>/dev/null
rm "api/tracker/debug_kanban_status.php"
# Backup y eliminar: tracker/generate_interview_pdf.php
cp "api/tracker/generate_interview_pdf.php" "backup_apis/" 2>/dev/null
rm "api/tracker/generate_interview_pdf.php"
# Backup y eliminar: tracker/generate_pdf_dompdf.php
cp "api/tracker/generate_pdf_dompdf.php" "backup_apis/" 2>/dev/null
rm "api/tracker/generate_pdf_dompdf.php"
# Backup y eliminar: tracker/generate_qr_poster.php
cp "api/tracker/generate_qr_poster.php" "backup_apis/" 2>/dev/null
rm "api/tracker/generate_qr_poster.php"
# Backup y eliminar: tracker/generate_simple_pdf.php
cp "api/tracker/generate_simple_pdf.php" "backup_apis/" 2>/dev/null
rm "api/tracker/generate_simple_pdf.php"
# Backup y eliminar: tracker/get_all_candidate_ids.php
cp "api/tracker/get_all_candidate_ids.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_all_candidate_ids.php"
# Backup y eliminar: tracker/get_candidate_detail.php
cp "api/tracker/get_candidate_detail.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_candidate_detail.php"
# Backup y eliminar: tracker/get_candidates.php
cp "api/tracker/get_candidates.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_candidates.php"
# Backup y eliminar: tracker/get_dashboard_stats.php
cp "api/tracker/get_dashboard_stats.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_dashboard_stats.php"
# Backup y eliminar: tracker/get_interview.php
cp "api/tracker/get_interview.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_interview.php"
# Backup y eliminar: tracker/get_interview_stats.php
cp "api/tracker/get_interview_stats.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_interview_stats.php"
# Backup y eliminar: tracker/get_interviews_status.php
cp "api/tracker/get_interviews_status.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_interviews_status.php"
# Backup y eliminar: tracker/get_kanban.php
cp "api/tracker/get_kanban.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_kanban.php"
# Backup y eliminar: tracker/get_keywords.php
cp "api/tracker/get_keywords.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_keywords.php"
# Backup y eliminar: tracker/get_notification_status.php
cp "api/tracker/get_notification_status.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_notification_status.php"
# Backup y eliminar: tracker/get_qr_locations.php
cp "api/tracker/get_qr_locations.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_qr_locations.php"
# Backup y eliminar: tracker/get_qr_stats.php
cp "api/tracker/get_qr_stats.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_qr_stats.php"
# Backup y eliminar: tracker/get_stats.php
cp "api/tracker/get_stats.php" "backup_apis/" 2>/dev/null
rm "api/tracker/get_stats.php"
# Backup y eliminar: tracker/insert_sample_questions.php
cp "api/tracker/insert_sample_questions.php" "backup_apis/" 2>/dev/null
rm "api/tracker/insert_sample_questions.php"
# Backup y eliminar: tracker/interview.php
cp "api/tracker/interview.php" "backup_apis/" 2>/dev/null
rm "api/tracker/interview.php"
# Backup y eliminar: tracker/manage_questions.php
cp "api/tracker/manage_questions.php" "backup_apis/" 2>/dev/null
rm "api/tracker/manage_questions.php"
# Backup y eliminar: tracker/manage_users.php
cp "api/tracker/manage_users.php" "backup_apis/" 2>/dev/null
rm "api/tracker/manage_users.php"
# Backup y eliminar: tracker/move_kanban_card.php
cp "api/tracker/move_kanban_card.php" "backup_apis/" 2>/dev/null
rm "api/tracker/move_kanban_card.php"
# Backup y eliminar: tracker/save_interview.php
cp "api/tracker/save_interview.php" "backup_apis/" 2>/dev/null
rm "api/tracker/save_interview.php"
# Backup y eliminar: tracker/save_keywords.php
cp "api/tracker/save_keywords.php" "backup_apis/" 2>/dev/null
rm "api/tracker/save_keywords.php"
# Backup y eliminar: tracker/send_candidate_email.php
cp "api/tracker/send_candidate_email.php" "backup_apis/" 2>/dev/null
rm "api/tracker/send_candidate_email.php"
# Backup y eliminar: tracker/setup_questions_table.php
cp "api/tracker/setup_questions_table.php" "backup_apis/" 2>/dev/null
rm "api/tracker/setup_questions_table.php"
# Backup y eliminar: tracker/sync_kanban.php
cp "api/tracker/sync_kanban.php" "backup_apis/" 2>/dev/null
rm "api/tracker/sync_kanban.php"
# Backup y eliminar: tracker/sync_kanban_status.php
cp "api/tracker/sync_kanban_status.php" "backup_apis/" 2>/dev/null
rm "api/tracker/sync_kanban_status.php"
# Backup y eliminar: tracker/test.php
cp "api/tracker/test.php" "backup_apis/" 2>/dev/null
rm "api/tracker/test.php"
# Backup y eliminar: tracker/track_qr_view.php
cp "api/tracker/track_qr_view.php" "backup_apis/" 2>/dev/null
rm "api/tracker/track_qr_view.php"
# Backup y eliminar: tracker/update_kanban_status.php
cp "api/tracker/update_kanban_status.php" "backup_apis/" 2>/dev/null
rm "api/tracker/update_kanban_status.php"
# Backup y eliminar: tuu/create_payment.php
cp "api/tuu/create_payment.php" "backup_apis/" 2>/dev/null
rm "api/tuu/create_payment.php"
# Backup y eliminar: tuu/create_payment_fallback.php
cp "api/tuu/create_payment_fallback.php" "backup_apis/" 2>/dev/null
rm "api/tuu/create_payment_fallback.php"
# Backup y eliminar: tuu/create_payment_minimal.php
cp "api/tuu/create_payment_minimal.php" "backup_apis/" 2>/dev/null
rm "api/tuu/create_payment_minimal.php"
# Backup y eliminar: tuu/create_payment_real.php
cp "api/tuu/create_payment_real.php" "backup_apis/" 2>/dev/null
rm "api/tuu/create_payment_real.php"
# Backup y eliminar: tuu/create_payment_working.php
cp "api/tuu/create_payment_working.php" "backup_apis/" 2>/dev/null
rm "api/tuu/create_payment_working.php"
# Backup y eliminar: tuu/get_config.php
cp "api/tuu/get_config.php" "backup_apis/" 2>/dev/null
rm "api/tuu/get_config.php"
# Backup y eliminar: tuu/get_devices.php
cp "api/tuu/get_devices.php" "backup_apis/" 2>/dev/null
rm "api/tuu/get_devices.php"
# Backup y eliminar: tuu/payment_form.php
cp "api/tuu/payment_form.php" "backup_apis/" 2>/dev/null
rm "api/tuu/payment_form.php"
# Backup y eliminar: tuu/save_config.php
cp "api/tuu/save_config.php" "backup_apis/" 2>/dev/null
rm "api/tuu/save_config.php"
# Backup y eliminar: tuu/sync_reports.php
cp "api/tuu/sync_reports.php" "backup_apis/" 2>/dev/null
rm "api/tuu/sync_reports.php"
# Backup y eliminar: tuu/test_connection.php
cp "api/tuu/test_connection.php" "backup_apis/" 2>/dev/null
rm "api/tuu/test_connection.php"
# Backup y eliminar: update_default_values.php
cp "api/update_default_values.php" "backup_apis/" 2>/dev/null
rm "api/update_default_values.php"
# Backup y eliminar: update_existing_db.php
cp "api/update_existing_db.php" "backup_apis/" 2>/dev/null
rm "api/update_existing_db.php"
# Backup y eliminar: update_ingrediente.php
cp "api/update_ingrediente.php" "backup_apis/" 2>/dev/null
rm "api/update_ingrediente.php"
# Backup y eliminar: update_ingredientes_table.php
cp "api/update_ingredientes_table.php" "backup_apis/" 2>/dev/null
rm "api/update_ingredientes_table.php"
# Backup y eliminar: update_order_status.php
cp "api/update_order_status.php" "backup_apis/" 2>/dev/null
rm "api/update_order_status.php"
# Backup y eliminar: update_peso_ingredientes.php
cp "api/update_peso_ingredientes.php" "backup_apis/" 2>/dev/null
rm "api/update_peso_ingredientes.php"
# Backup y eliminar: update_receta.php
cp "api/update_receta.php" "backup_apis/" 2>/dev/null
rm "api/update_receta.php"
# Backup y eliminar: update_sueldo_base.php
cp "api/update_sueldo_base.php" "backup_apis/" 2>/dev/null
rm "api/update_sueldo_base.php"
# Backup y eliminar: update_tables_structure.php
cp "api/update_tables_structure.php" "backup_apis/" 2>/dev/null
rm "api/update_tables_structure.php"
# Backup y eliminar: upload_image.php
cp "api/upload_image.php" "backup_apis/" 2>/dev/null
rm "api/upload_image.php"
# Backup y eliminar: users/add_address.php
cp "api/users/add_address.php" "backup_apis/" 2>/dev/null
rm "api/users/add_address.php"
# Backup y eliminar: users/delete_account.php
cp "api/users/delete_account.php" "backup_apis/" 2>/dev/null
rm "api/users/delete_account.php"
# Backup y eliminar: users/delete_address.php
cp "api/users/delete_address.php" "backup_apis/" 2>/dev/null
rm "api/users/delete_address.php"
# Backup y eliminar: users/get_profile.php
cp "api/users/get_profile.php" "backup_apis/" 2>/dev/null
rm "api/users/get_profile.php"
# Backup y eliminar: users/toggle_favorite.php
cp "api/users/toggle_favorite.php" "backup_apis/" 2>/dev/null
rm "api/users/toggle_favorite.php"
# Backup y eliminar: users/update_profile.php
cp "api/users/update_profile.php" "backup_apis/" 2>/dev/null
rm "api/users/update_profile.php"
# Backup y eliminar: ventas_get_all.php
cp "api/ventas_get_all.php" "backup_apis/" 2>/dev/null
rm "api/ventas_get_all.php"
# Backup y eliminar: ventas_update.php
cp "api/ventas_update.php" "backup_apis/" 2>/dev/null
rm "api/ventas_update.php"
# Backup y eliminar: ventas_update_direct.php
cp "api/ventas_update_direct.php" "backup_apis/" 2>/dev/null
rm "api/ventas_update_direct.php"
# Backup y eliminar: ventas_update_simple.php
cp "api/ventas_update_simple.php" "backup_apis/" 2>/dev/null
rm "api/ventas_update_simple.php"
# Backup y eliminar: ventas_update_v2.php
cp "api/ventas_update_v2.php" "backup_apis/" 2>/dev/null
rm "api/ventas_update_v2.php"

echo "✅ Limpieza completada. Backup en: backup_apis/"
echo "📊 Archivos eliminados: 220"
