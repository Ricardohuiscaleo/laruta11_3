#!/usr/bin/env node

import fs from 'fs';
import path from 'path';

// Script rápido para estadísticas básicas
const quickStats = () => {
  const startTime = Date.now();
  
  let totalFiles = 0;
  let totalLines = 0;
  let phpFiles = 0;
  let jsxFiles = 0;
  let astroFiles = 0;
  
  const countInDirectory = (dir) => {
    try {
      const items = fs.readdirSync(dir);
      items.forEach(item => {
        const fullPath = path.join(dir, item);
        const stats = fs.statSync(fullPath);
        
        if (stats.isDirectory() && !item.startsWith('.') && item !== 'node_modules') {
          countInDirectory(fullPath);
        } else if (stats.isFile()) {
          totalFiles++;
          const ext = path.extname(item).toLowerCase();
          
          if (['.php', '.jsx', '.js', '.astro', '.sql', '.html', '.css'].includes(ext)) {
            const content = fs.readFileSync(fullPath, 'utf8');
            const lines = content.split('\n').length;
            totalLines += lines;
            
            if (ext === '.php') phpFiles++;
            else if (ext === '.jsx') jsxFiles++;
            else if (ext === '.astro') astroFiles++;
          }
        }
      });
    } catch (error) {
      // Ignorar errores de permisos
    }
  };
  
  countInDirectory(process.cwd());
  
  const endTime = Date.now();
  const duration = ((endTime - startTime) / 1000).toFixed(2);
  
  console.log(`⚡ ESTADÍSTICAS RÁPIDAS (${duration}s)`);
  console.log('─'.repeat(35));
  console.log(`📁 Total archivos: ${totalFiles}`);
  console.log(`📝 Líneas de código: ${totalLines.toLocaleString()}`);
  console.log(`🐘 Archivos PHP: ${phpFiles}`);
  console.log(`⚛️  Componentes JSX: ${jsxFiles}`);
  console.log(`🚀 Páginas Astro: ${astroFiles}`);
  console.log(`📊 Promedio: ${Math.round(totalLines / totalFiles)} líneas/archivo`);
};

quickStats();