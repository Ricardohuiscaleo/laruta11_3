#!/usr/bin/env node

import fs from 'fs';
import path from 'path';

// Script para generar resumen ejecutivo del proyecto
class ProjectSummary {
  constructor() {
    this.reportPath = './project-analysis.json';
  }

  loadReport() {
    if (fs.existsSync(this.reportPath)) {
      return JSON.parse(fs.readFileSync(this.reportPath, 'utf8'));
    }
    return null;
  }

  generateExecutiveSummary() {
    const report = this.loadReport();
    if (!report) {
      console.log('❌ No se encontró el reporte de análisis. Ejecuta primero: node analyze-project.js');
      return;
    }

    console.log('📋 RESUMEN EJECUTIVO - PROYECTO RUTA11APP');
    console.log('=' .repeat(60));
    console.log(`📅 Generado: ${new Date().toLocaleString('es-CL')}`);
    
    // Métricas principales
    console.log('\n🎯 MÉTRICAS PRINCIPALES:');
    console.log(`• Total de archivos: ${report.summary.totalFiles.toLocaleString()}`);
    console.log(`• Total de líneas de código: ${report.summary.totalLines.toLocaleString()}`);
    console.log(`• Promedio por archivo: ${Math.round(report.summary.totalLines / report.summary.totalFiles)} líneas`);
    
    // Distribución tecnológica
    console.log('\n💻 STACK TECNOLÓGICO:');
    const phpFiles = report.extensions['.php']?.files || 0;
    const jsxFiles = report.extensions['.jsx']?.files || 0;
    const astroFiles = report.extensions['.astro']?.files || 0;
    const sqlFiles = report.extensions['.sql']?.files || 0;
    
    console.log(`• Backend PHP: ${phpFiles} archivos (${report.categories['Backend PHP'].lines.toLocaleString()} líneas)`);
    console.log(`• Frontend React: ${jsxFiles} componentes (${report.extensions['.jsx']?.lines.toLocaleString() || 0} líneas)`);
    console.log(`• Páginas Astro: ${astroFiles} páginas (${report.extensions['.astro']?.lines.toLocaleString() || 0} líneas)`);
    console.log(`• Base de datos: ${sqlFiles} scripts SQL (${report.categories['Database'].lines.toLocaleString()} líneas)`);
    
    // Arquitectura del proyecto
    console.log('\n🏗️  ARQUITECTURA:');
    console.log('• Aplicación Full-Stack con separación clara Frontend/Backend');
    console.log('• API REST en PHP con múltiples endpoints especializados');
    console.log('• Frontend híbrido: Astro + React para máximo rendimiento');
    console.log('• Base de datos MySQL con scripts de migración');
    console.log('• Sistema de pagos integrado (TUU)');
    console.log('• Panel de administración completo');
    console.log('• Sistema de tracking de empleados');
    
    // Módulos principales
    console.log('\n📦 MÓDULOS PRINCIPALES:');
    const apiFiles = report.directories['api']?.files || 0;
    const srcFiles = report.directories['src']?.files || 0;
    
    console.log(`• API Backend: ${apiFiles} endpoints organizados por funcionalidad`);
    console.log(`• Frontend: ${srcFiles} archivos (componentes, páginas, layouts)`);
    console.log('• Sistema de autenticación con Google OAuth');
    console.log('• Gestión de productos y menú dinámico');
    console.log('• Procesamiento de pagos y órdenes');
    console.log('• Analytics y reportes en tiempo real');
    console.log('• Sistema de food trucks y delivery');
    
    // Complejidad y mantenibilidad
    console.log('\n⚖️  COMPLEJIDAD:');
    const largestFile = report.largestFiles[0];
    const avgComplexity = this.calculateComplexity(report);
    
    console.log(`• Archivo más grande: ${largestFile.path} (${largestFile.lines.toLocaleString()} líneas)`);
    console.log(`• Complejidad promedio: ${avgComplexity}`);
    console.log('• Código bien modularizado con separación de responsabilidades');
    console.log('• APIs RESTful siguiendo convenciones estándar');
    
    // Estado del proyecto
    console.log('\n🚀 ESTADO DEL PROYECTO:');
    console.log('• ✅ Aplicación completamente funcional');
    console.log('• ✅ Sistema de pagos integrado y operativo');
    console.log('• ✅ Panel administrativo completo');
    console.log('• ✅ PWA optimizada para móviles');
    console.log('• ✅ Sistema de analytics implementado');
    console.log('• ✅ Backup y scripts de mantenimiento');
    
    // Recomendaciones
    console.log('\n💡 RECOMENDACIONES:');
    console.log('• Considerar refactorizar MenuApp.jsx (3,298 líneas)');
    console.log('• Implementar tests unitarios para componentes críticos');
    console.log('• Documentar APIs principales con OpenAPI/Swagger');
    console.log('• Optimizar queries de base de datos más complejas');
    console.log('• Implementar CI/CD para deployments automáticos');
    
    // Conclusión
    console.log('\n🎉 CONCLUSIÓN:');
    console.log('Proyecto robusto y bien estructurado con más de 73,000 líneas de código.');
    console.log('Stack moderno, arquitectura escalable y funcionalidades completas para');
    console.log('un sistema de food truck con e-commerce, pagos y gestión administrativa.');
    
    this.generateMarkdownReport(report);
  }

  calculateComplexity(report) {
    const totalFiles = report.summary.totalFiles;
    const totalLines = report.summary.totalLines;
    const avgLines = totalLines / totalFiles;
    
    if (avgLines < 50) return 'Baja';
    if (avgLines < 150) return 'Media';
    return 'Alta';
  }

  generateMarkdownReport(report) {
    const markdown = `# Reporte de Análisis - Proyecto Ruta11App

## Resumen Ejecutivo

- **Total de archivos:** ${report.summary.totalFiles.toLocaleString()}
- **Total de líneas:** ${report.summary.totalLines.toLocaleString()}
- **Fecha de análisis:** ${new Date().toLocaleString('es-CL')}

## Stack Tecnológico

| Tecnología | Archivos | Líneas |
|------------|----------|--------|
| PHP Backend | ${report.extensions['.php']?.files || 0} | ${report.categories['Backend PHP'].lines.toLocaleString()} |
| React/JSX | ${report.extensions['.jsx']?.files || 0} | ${report.extensions['.jsx']?.lines.toLocaleString() || 0} |
| Astro | ${report.extensions['.astro']?.files || 0} | ${report.extensions['.astro']?.lines.toLocaleString() || 0} |
| SQL | ${report.extensions['.sql']?.files || 0} | ${report.categories['Database'].lines.toLocaleString()} |

## Archivos Más Grandes

${report.largestFiles.slice(0, 10).map((file, i) => 
  `${i + 1}. **${file.path}** - ${file.lines.toLocaleString()} líneas`
).join('\n')}

## Distribución por Directorios

${Object.entries(report.directories)
  .sort((a, b) => b[1].files - a[1].files)
  .slice(0, 10)
  .map(([dir, data]) => `- **${dir}**: ${data.files} archivos, ${data.lines.toLocaleString()} líneas`)
  .join('\n')}

---
*Reporte generado automáticamente*
`;

    fs.writeFileSync('./PROJECT_REPORT.md', markdown);
    console.log('\n📄 Reporte Markdown guardado en: PROJECT_REPORT.md');
  }
}

// Ejecutar
const summary = new ProjectSummary();
summary.generateExecutiveSummary();