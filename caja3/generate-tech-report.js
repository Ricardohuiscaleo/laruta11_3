#!/usr/bin/env node

// Script para generar informe técnico en deploy
import fs from 'fs';
import path from 'path';

const generateReport = () => {
  const stats = {
    totalFiles: 0,
    totalLines: 0,
    categories: {
      'Frontend': { files: 0, lines: 0 },
      'Backend PHP': { files: 0, lines: 0 },
      'Database': { files: 0, lines: 0 }
    },
    largestFiles: [],
    generated_at: new Date().toISOString()
  };

  const analyzeDirectory = (dir) => {
    const items = fs.readdirSync(dir);
    items.forEach(item => {
      const fullPath = path.join(dir, item);
      if (fs.statSync(fullPath).isDirectory() && !['node_modules', '.git'].includes(item)) {
        analyzeDirectory(fullPath);
      } else if (fs.statSync(fullPath).isFile()) {
        const ext = path.extname(item);
        const lines = ext === '.php' || ext === '.jsx' ? 
          fs.readFileSync(fullPath, 'utf8').split('\n').length : 0;
        
        stats.totalFiles++;
        stats.totalLines += lines;
        
        if (ext === '.php') stats.categories['Backend PHP'].files++;
        if (ext === '.jsx') stats.categories['Frontend'].files++;
      }
    });
  };

  analyzeDirectory(process.cwd());
  
  // Guardar en archivo JSON
  fs.writeFileSync('./api/tech-report-cache.json', JSON.stringify(stats, null, 2));
  console.log('✅ Informe técnico generado');
};

generateReport();