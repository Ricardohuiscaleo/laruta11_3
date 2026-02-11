#!/usr/bin/env node

import fs from 'fs';
import path from 'path';

// Script para análisis detallado de archivos específicos
class DetailedAnalyzer {
  constructor(rootPath) {
    this.rootPath = rootPath;
  }

  analyzeAPIEndpoints() {
    const apiPath = path.join(this.rootPath, 'api');
    const endpoints = [];
    
    const scanDirectory = (dir, prefix = '') => {
      try {
        const items = fs.readdirSync(dir);
        items.forEach(item => {
          const fullPath = path.join(dir, item);
          const stats = fs.statSync(fullPath);
          
          if (stats.isDirectory() && !item.startsWith('.')) {
            scanDirectory(fullPath, prefix + item + '/');
          } else if (item.endsWith('.php')) {
            const relativePath = prefix + item;
            const lines = this.countLines(fullPath);
            endpoints.push({ path: relativePath, lines, fullPath });
          }
        });
      } catch (error) {
        console.warn(`Error escaneando ${dir}: ${error.message}`);
      }
    };
    
    if (fs.existsSync(apiPath)) {
      scanDirectory(apiPath);
    }
    
    return endpoints.sort((a, b) => b.lines - a.lines);
  }

  analyzeComponents() {
    const componentsPath = path.join(this.rootPath, 'src', 'components');
    const components = [];
    
    if (fs.existsSync(componentsPath)) {
      const items = fs.readdirSync(componentsPath);
      items.forEach(item => {
        const fullPath = path.join(componentsPath, item);
        const stats = fs.statSync(fullPath);
        
        if (stats.isFile() && (item.endsWith('.jsx') || item.endsWith('.js'))) {
          const lines = this.countLines(fullPath);
          components.push({ name: item, lines, path: fullPath });
        }
      });
    }
    
    return components.sort((a, b) => b.lines - a.lines);
  }

  analyzePages() {
    const pagesPath = path.join(this.rootPath, 'src', 'pages');
    const pages = [];
    
    const scanPages = (dir, prefix = '') => {
      try {
        const items = fs.readdirSync(dir);
        items.forEach(item => {
          const fullPath = path.join(dir, item);
          const stats = fs.statSync(fullPath);
          
          if (stats.isDirectory()) {
            scanPages(fullPath, prefix + item + '/');
          } else if (item.endsWith('.astro')) {
            const lines = this.countLines(fullPath);
            pages.push({ path: prefix + item, lines, fullPath });
          }
        });
      } catch (error) {
        console.warn(`Error escaneando páginas ${dir}: ${error.message}`);
      }
    };
    
    if (fs.existsSync(pagesPath)) {
      scanPages(pagesPath);
    }
    
    return pages.sort((a, b) => b.lines - a.lines);
  }

  countLines(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf8');
      return content.split('\n').length;
    } catch (error) {
      return 0;
    }
  }

  analyzeComplexity() {
    const complexFiles = [];
    
    // Analizar archivos principales
    const mainFiles = [
      'src/components/MenuApp.jsx',
      'src/components/CheckoutApp.jsx',
      'src/components/AdminPanel.jsx',
      'api/get_productos.php',
      'api/registrar_venta.php'
    ];
    
    mainFiles.forEach(file => {
      const fullPath = path.join(this.rootPath, file);
      if (fs.existsSync(fullPath)) {
        const content = fs.readFileSync(fullPath, 'utf8');
        const lines = content.split('\n').length;
        const functions = (content.match(/function\s+\w+|const\s+\w+\s*=\s*\(|\w+\s*:\s*function/g) || []).length;
        const classes = (content.match(/class\s+\w+|export\s+class\s+\w+/g) || []).length;
        
        complexFiles.push({
          file,
          lines,
          functions,
          classes,
          complexity: functions + classes * 2
        });
      }
    });
    
    return complexFiles.sort((a, b) => b.complexity - a.complexity);
  }

  generateDetailedReport() {
    console.log('\n🔬 ANÁLISIS DETALLADO DEL PROYECTO');
    console.log('=' .repeat(60));
    
    // API Endpoints más grandes
    console.log('\n🔌 TOP 15 API ENDPOINTS (por líneas):');
    const endpoints = this.analyzeAPIEndpoints();
    endpoints.slice(0, 15).forEach((endpoint, index) => {
      console.log(`${(index + 1).toString().padStart(2)}. ${endpoint.path.padEnd(40)} ${endpoint.lines.toString().padStart(4)} líneas`);
    });
    
    // Componentes React más grandes
    console.log('\n⚛️  COMPONENTES REACT/JSX:');
    const components = this.analyzeComponents();
    components.forEach((component, index) => {
      console.log(`${(index + 1).toString().padStart(2)}. ${component.name.padEnd(30)} ${component.lines.toString().padStart(4)} líneas`);
    });
    
    // Páginas Astro más grandes
    console.log('\n🚀 PÁGINAS ASTRO:');
    const pages = this.analyzePages();
    pages.slice(0, 10).forEach((page, index) => {
      console.log(`${(index + 1).toString().padStart(2)}. ${page.path.padEnd(40)} ${page.lines.toString().padStart(4)} líneas`);
    });
    
    // Análisis de complejidad
    console.log('\n🧠 ANÁLISIS DE COMPLEJIDAD:');
    const complexity = this.analyzeComplexity();
    complexity.forEach((file, index) => {
      console.log(`${(index + 1).toString().padStart(2)}. ${path.basename(file.file).padEnd(25)} ${file.lines.toString().padStart(4)} líneas, ${file.functions.toString().padStart(2)} funciones, ${file.classes.toString().padStart(1)} clases`);
    });
    
    // Estadísticas por módulos
    console.log('\n📊 ESTADÍSTICAS POR MÓDULOS:');
    const moduleStats = this.getModuleStats();
    Object.entries(moduleStats).forEach(([module, stats]) => {
      console.log(`${module.padEnd(20)}: ${stats.files.toString().padStart(3)} archivos, ${stats.lines.toString().padStart(5)} líneas`);
    });
  }

  getModuleStats() {
    const modules = {
      'Auth System': 0,
      'Payment System': 0,
      'Menu System': 0,
      'Admin Panel': 0,
      'Job Tracker': 0,
      'Analytics': 0,
      'Food Trucks': 0
    };
    
    // Contar archivos por módulo basado en nombres
    const apiPath = path.join(this.rootPath, 'api');
    if (fs.existsSync(apiPath)) {
      const endpoints = this.analyzeAPIEndpoints();
      
      endpoints.forEach(endpoint => {
        const path = endpoint.path.toLowerCase();
        if (path.includes('auth') || path.includes('login') || path.includes('register')) {
          modules['Auth System'] += endpoint.lines;
        } else if (path.includes('tuu') || path.includes('payment') || path.includes('pago')) {
          modules['Payment System'] += endpoint.lines;
        } else if (path.includes('producto') || path.includes('menu') || path.includes('ingredient')) {
          modules['Menu System'] += endpoint.lines;
        } else if (path.includes('admin') || path.includes('dashboard')) {
          modules['Admin Panel'] += endpoint.lines;
        } else if (path.includes('job') || path.includes('tracker') || path.includes('kanban')) {
          modules['Job Tracker'] += endpoint.lines;
        } else if (path.includes('analytic') || path.includes('stats') || path.includes('kpi')) {
          modules['Analytics'] += endpoint.lines;
        } else if (path.includes('food_truck') || path.includes('truck')) {
          modules['Food Trucks'] += endpoint.lines;
        }
      });
    }
    
    // Convertir a formato con archivos y líneas
    const result = {};
    Object.entries(modules).forEach(([module, lines]) => {
      result[module] = { files: Math.ceil(lines / 100), lines }; // Estimación de archivos
    });
    
    return result;
  }
}

// Ejecutar análisis detallado
const projectPath = process.argv[2] || process.cwd();
const analyzer = new DetailedAnalyzer(projectPath);
analyzer.generateDetailedReport();