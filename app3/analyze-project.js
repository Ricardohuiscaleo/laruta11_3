#!/usr/bin/env node

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuración de extensiones por categoría
const FILE_CATEGORIES = {
  'Frontend': ['.jsx', '.js', '.astro', '.html', '.css', '.scss', '.sass', '.less'],
  'Backend PHP': ['.php'],
  'Database': ['.sql'],
  'Config': ['.json', '.mjs', '.config.js', '.env', '.env.example', '.gitignore', '.htaccess'],
  'Documentation': ['.md', '.txt'],
  'Media': ['.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.mp3', '.wav'],
  'Shell Scripts': ['.sh'],
  'Other': []
};

// Extensiones que se consideran código
const CODE_EXTENSIONS = ['.jsx', '.js', '.astro', '.php', '.sql', '.html', '.css', '.scss', '.sass', '.less', '.json', '.mjs', '.sh'];

// Directorios a ignorar
const IGNORE_DIRS = ['node_modules', '.git', '.astro', 'dist', 'build'];

class ProjectAnalyzer {
  constructor(rootPath) {
    this.rootPath = rootPath;
    this.stats = {
      totalFiles: 0,
      totalLines: 0,
      categories: {},
      extensions: {},
      directories: {},
      largestFiles: [],
      emptyFiles: 0
    };
    
    // Inicializar categorías
    Object.keys(FILE_CATEGORIES).forEach(category => {
      this.stats.categories[category] = { files: 0, lines: 0 };
    });
  }

  shouldIgnoreDirectory(dirName) {
    return IGNORE_DIRS.includes(dirName) || dirName.startsWith('.');
  }

  getFileCategory(extension) {
    for (const [category, extensions] of Object.entries(FILE_CATEGORIES)) {
      if (extensions.includes(extension)) {
        return category;
      }
    }
    return 'Other';
  }

  countLines(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf8');
      const lines = content.split('\n').length;
      return lines;
    } catch (error) {
      console.warn(`Error leyendo archivo ${filePath}: ${error.message}`);
      return 0;
    }
  }

  analyzeFile(filePath, relativePath) {
    const extension = path.extname(filePath).toLowerCase();
    const category = this.getFileCategory(extension);
    const lines = CODE_EXTENSIONS.includes(extension) ? this.countLines(filePath) : 0;
    const stats = fs.statSync(filePath);
    
    // Actualizar estadísticas generales
    this.stats.totalFiles++;
    this.stats.totalLines += lines;
    
    // Actualizar por categoría
    this.stats.categories[category].files++;
    this.stats.categories[category].lines += lines;
    
    // Actualizar por extensión
    if (!this.stats.extensions[extension]) {
      this.stats.extensions[extension] = { files: 0, lines: 0 };
    }
    this.stats.extensions[extension].files++;
    this.stats.extensions[extension].lines += lines;
    
    // Actualizar por directorio
    const dir = path.dirname(relativePath);
    const topDir = dir.split('/')[0] || 'root';
    if (!this.stats.directories[topDir]) {
      this.stats.directories[topDir] = { files: 0, lines: 0 };
    }
    this.stats.directories[topDir].files++;
    this.stats.directories[topDir].lines += lines;
    
    // Archivos más grandes
    if (lines > 0) {
      this.stats.largestFiles.push({
        path: relativePath,
        lines: lines,
        size: stats.size
      });
    }
    
    // Archivos vacíos
    if (lines === 0 && CODE_EXTENSIONS.includes(extension)) {
      this.stats.emptyFiles++;
    }
  }

  analyzeDirectory(dirPath, relativePath = '') {
    try {
      const items = fs.readdirSync(dirPath);
      
      for (const item of items) {
        const fullPath = path.join(dirPath, item);
        const itemRelativePath = relativePath ? path.join(relativePath, item) : item;
        const stats = fs.statSync(fullPath);
        
        if (stats.isDirectory()) {
          if (!this.shouldIgnoreDirectory(item)) {
            this.analyzeDirectory(fullPath, itemRelativePath);
          }
        } else if (stats.isFile()) {
          this.analyzeFile(fullPath, itemRelativePath);
        }
      }
    } catch (error) {
      console.warn(`Error analizando directorio ${dirPath}: ${error.message}`);
    }
  }

  generateReport() {
    // Ordenar archivos más grandes
    this.stats.largestFiles.sort((a, b) => b.lines - a.lines);
    this.stats.largestFiles = this.stats.largestFiles.slice(0, 10);
    
    console.log('🔍 ANÁLISIS DEL PROYECTO RUTA11APP');
    console.log('=' .repeat(50));
    
    // Resumen general
    console.log('\n📊 RESUMEN GENERAL:');
    console.log(`Total de archivos: ${this.stats.totalFiles.toLocaleString()}`);
    console.log(`Total de líneas de código: ${this.stats.totalLines.toLocaleString()}`);
    console.log(`Archivos vacíos: ${this.stats.emptyFiles}`);
    
    // Por categorías
    console.log('\n📁 POR CATEGORÍAS:');
    Object.entries(this.stats.categories)
      .sort((a, b) => b[1].files - a[1].files)
      .forEach(([category, data]) => {
        if (data.files > 0) {
          console.log(`${category.padEnd(15)}: ${data.files.toString().padStart(4)} archivos, ${data.lines.toLocaleString().padStart(6)} líneas`);
        }
      });
    
    // Por directorios principales
    console.log('\n📂 POR DIRECTORIOS:');
    Object.entries(this.stats.directories)
      .sort((a, b) => b[1].files - a[1].files)
      .slice(0, 15)
      .forEach(([dir, data]) => {
        console.log(`${dir.padEnd(20)}: ${data.files.toString().padStart(4)} archivos, ${data.lines.toLocaleString().padStart(6)} líneas`);
      });
    
    // Por extensiones más comunes
    console.log('\n🔧 EXTENSIONES MÁS COMUNES:');
    Object.entries(this.stats.extensions)
      .sort((a, b) => b[1].files - a[1].files)
      .slice(0, 15)
      .forEach(([ext, data]) => {
        if (data.files > 0) {
          const extension = ext || '(sin extensión)';
          console.log(`${extension.padEnd(12)}: ${data.files.toString().padStart(4)} archivos, ${data.lines.toLocaleString().padStart(6)} líneas`);
        }
      });
    
    // Archivos más grandes
    console.log('\n📈 ARCHIVOS MÁS GRANDES:');
    this.stats.largestFiles.forEach((file, index) => {
      console.log(`${(index + 1).toString().padStart(2)}. ${file.path.padEnd(50)} ${file.lines.toLocaleString().padStart(5)} líneas`);
    });
    
    // Estadísticas adicionales
    console.log('\n📋 ESTADÍSTICAS ADICIONALES:');
    const avgLinesPerFile = this.stats.totalLines / this.stats.totalFiles;
    console.log(`Promedio de líneas por archivo: ${avgLinesPerFile.toFixed(1)}`);
    
    const phpFiles = this.stats.extensions['.php']?.files || 0;
    const jsFiles = (this.stats.extensions['.js']?.files || 0) + (this.stats.extensions['.jsx']?.files || 0);
    const astroFiles = this.stats.extensions['.astro']?.files || 0;
    
    console.log(`Archivos PHP: ${phpFiles}`);
    console.log(`Archivos JavaScript/JSX: ${jsFiles}`);
    console.log(`Archivos Astro: ${astroFiles}`);
    console.log(`Archivos SQL: ${this.stats.extensions['.sql']?.files || 0}`);
    
    // Distribución de código
    console.log('\n🎯 DISTRIBUCIÓN DE CÓDIGO:');
    const backendLines = this.stats.categories['Backend PHP'].lines;
    const frontendLines = this.stats.categories['Frontend'].lines;
    const dbLines = this.stats.categories['Database'].lines;
    
    const totalCodeLines = backendLines + frontendLines + dbLines;
    if (totalCodeLines > 0) {
      console.log(`Backend PHP: ${((backendLines / totalCodeLines) * 100).toFixed(1)}%`);
      console.log(`Frontend: ${((frontendLines / totalCodeLines) * 100).toFixed(1)}%`);
      console.log(`Database: ${((dbLines / totalCodeLines) * 100).toFixed(1)}%`);
    }
  }

  saveReport() {
    const reportData = {
      timestamp: new Date().toISOString(),
      summary: {
        totalFiles: this.stats.totalFiles,
        totalLines: this.stats.totalLines,
        emptyFiles: this.stats.emptyFiles
      },
      categories: this.stats.categories,
      directories: this.stats.directories,
      extensions: this.stats.extensions,
      largestFiles: this.stats.largestFiles
    };
    
    const reportPath = path.join(this.rootPath, 'project-analysis.json');
    fs.writeFileSync(reportPath, JSON.stringify(reportData, null, 2));
    console.log(`\n💾 Reporte guardado en: ${reportPath}`);
  }
}

// Ejecutar análisis
const projectPath = process.argv[2] || process.cwd();
console.log(`Analizando proyecto en: ${projectPath}`);

const analyzer = new ProjectAnalyzer(projectPath);
analyzer.analyzeDirectory(projectPath);
analyzer.generateReport();
analyzer.saveReport();