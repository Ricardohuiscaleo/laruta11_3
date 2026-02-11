import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Heart, Volume2, VolumeX, RotateCcw, Trophy, Zap } from 'lucide-react';

const GalagaGame = () => {
  const canvasRef = useRef(null);
  const gameLoopRef = useRef(null);
  const audioContextRef = useRef(null);
  
  const [gameState, setGameState] = useState('playing');
  const [score, setScore] = useState(0);
  const [lives, setLives] = useState(3);
  const [level, setLevel] = useState(1);
  const [soundEnabled, setSoundEnabled] = useState(true);
  const [keys, setKeys] = useState({});
  
  // Game objects
  const gameObjects = useRef({
    player: { x: 385, y: 550, width: 30, height: 20, speed: 5 },
    bullets: [],
    enemies: [],
    enemyBullets: [],
    particles: []
  });

  const enemyTypes = {
    bee: { color: '#ffff00', points: 50, speed: 1 },
    butterfly: { color: '#ff00ff', points: 80, speed: 1.2 },
    boss: { color: '#ff0000', points: 150, speed: 0.8 }
  };

  // Audio functions
  const playSound = useCallback((frequency, duration, type = 'square', volume = 0.1) => {
    if (!soundEnabled || !audioContextRef.current) return;
    
    const oscillator = audioContextRef.current.createOscillator();
    const gainNode = audioContextRef.current.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContextRef.current.destination);
    
    oscillator.frequency.setValueAtTime(frequency, audioContextRef.current.currentTime);
    oscillator.type = type;
    
    gainNode.gain.setValueAtTime(0, audioContextRef.current.currentTime);
    gainNode.gain.linearRampToValueAtTime(volume, audioContextRef.current.currentTime + 0.01);
    gainNode.gain.exponentialRampToValueAtTime(0.001, audioContextRef.current.currentTime + duration);
    
    oscillator.start(audioContextRef.current.currentTime);
    oscillator.stop(audioContextRef.current.currentTime + duration);
  }, [soundEnabled]);

  const playShootSound = useCallback(() => playSound(800, 0.1, 'square', 0.05), [playSound]);
  const playExplosionSound = useCallback(() => {
    playSound(150, 0.3, 'sawtooth', 0.1);
    setTimeout(() => playSound(100, 0.2, 'square', 0.08), 50);
  }, [playSound]);
  const playHitSound = useCallback(() => playSound(200, 0.2, 'sawtooth', 0.08), [playSound]);

  // Initialize audio context
  useEffect(() => {
    audioContextRef.current = new (window.AudioContext || window.webkitAudioContext)();
    return () => {
      if (audioContextRef.current) {
        audioContextRef.current.close();
      }
    };
  }, []);

  // Create enemy formation
  const createEnemyFormation = useCallback(() => {
    const enemies = [];
    const startX = 100;
    const startY = 50;
    const spacing = 60;
    
    // Boss enemies
    for (let i = 0; i < 4; i++) {
      enemies.push({
        x: startX + i * spacing * 1.5,
        y: startY,
        width: 25,
        height: 20,
        type: 'boss',
        inFormation: true,
        formationX: startX + i * spacing * 1.5,
        formationY: startY,
        angle: 0,
        diving: false,
        divePhase: 0
      });
    }
    
    // Butterfly enemies
    for (let row = 0; row < 2; row++) {
      for (let i = 0; i < 8; i++) {
        enemies.push({
          x: startX + i * spacing,
          y: startY + 60 + row * 40,
          width: 20,
          height: 15,
          type: 'butterfly',
          inFormation: true,
          formationX: startX + i * spacing,
          formationY: startY + 60 + row * 40,
          angle: 0,
          diving: false,
          divePhase: 0
        });
      }
    }
    
    // Bee enemies
    for (let row = 0; row < 2; row++) {
      for (let i = 0; i < 10; i++) {
        enemies.push({
          x: startX + i * 50,
          y: startY + 140 + row * 35,
          width: 18,
          height: 12,
          type: 'bee',
          inFormation: true,
          formationX: startX + i * 50,
          formationY: startY + 140 + row * 35,
          angle: 0,
          diving: false,
          divePhase: 0
        });
      }
    }
    
    gameObjects.current.enemies = enemies;
  }, []);

  // Shooting
  const shoot = useCallback(() => {
    if (gameState !== 'playing') return;
    
    gameObjects.current.bullets.push({
      x: gameObjects.current.player.x + gameObjects.current.player.width / 2 - 2,
      y: gameObjects.current.player.y,
      width: 4,
      height: 10,
      speed: 8,
      color: '#00ff00'
    });
    
    playShootSound();
  }, [gameState, playShootSound]);

  // Create explosion
  const createExplosion = useCallback((x, y, color) => {
    for (let i = 0; i < 8; i++) {
      gameObjects.current.particles.push({
        x: x,
        y: y,
        vx: (Math.random() - 0.5) * 6,
        vy: (Math.random() - 0.5) * 6,
        life: 30,
        color: color
      });
    }
  }, []);

  // Game update loop
  const update = useCallback(() => {
    if (gameState !== 'playing') return;
    
    const { player, bullets, enemies, enemyBullets, particles } = gameObjects.current;
    
    // Move player
    if (keys['ArrowLeft'] && player.x > 0) {
      player.x -= player.speed;
    }
    if (keys['ArrowRight'] && player.x < 770) {
      player.x += player.speed;
    }
    
    // Update bullets
    gameObjects.current.bullets = bullets.filter(bullet => {
      bullet.y -= bullet.speed;
      return bullet.y > -bullet.height;
    });
    
    // Update enemy bullets
    gameObjects.current.enemyBullets = enemyBullets.filter(bullet => {
      bullet.y += bullet.speed;
      return bullet.y < 600;
    });
    
    // Update enemies
    enemies.forEach(enemy => {
      if (enemy.inFormation) {
        enemy.angle += 0.02;
        enemy.x = enemy.formationX + Math.sin(enemy.angle) * 10;
        
        if (Math.random() < 0.001) {
          enemy.diving = true;
          enemy.inFormation = false;
          enemy.divePhase = 0;
        }
      } else if (enemy.diving) {
        enemy.divePhase += 0.05;
        enemy.x += Math.sin(enemy.divePhase * 3) * 3;
        enemy.y += enemyTypes[enemy.type].speed * 2;
        
        if (enemy.y > 600) {
          enemy.diving = false;
          enemy.inFormation = true;
          enemy.x = enemy.formationX;
          enemy.y = enemy.formationY;
        }
      }
      
      // Enemy shooting
      if (Math.random() < 0.002) {
        enemyBullets.push({
          x: enemy.x + enemy.width / 2 - 2,
          y: enemy.y + enemy.height,
          width: 4,
          height: 8,
          speed: 3,
          color: '#ff0000'
        });
      }
    });
    
    // Update particles
    gameObjects.current.particles = particles.filter(particle => {
      particle.x += particle.vx;
      particle.y += particle.vy;
      particle.life--;
      return particle.life > 0;
    });
    
    // Collision detection - bullets vs enemies
    bullets.forEach((bullet, bulletIndex) => {
      enemies.forEach((enemy, enemyIndex) => {
        if (bullet.x < enemy.x + enemy.width &&
            bullet.x + bullet.width > enemy.x &&
            bullet.y < enemy.y + enemy.height &&
            bullet.y + bullet.height > enemy.y) {
          
          createExplosion(enemy.x + enemy.width/2, enemy.y + enemy.height/2, enemyTypes[enemy.type].color);
          setScore(prev => prev + enemyTypes[enemy.type].points);
          bullets.splice(bulletIndex, 1);
          enemies.splice(enemyIndex, 1);
          playExplosionSound();
        }
      });
    });
    
    // Collision detection - enemy bullets vs player
    enemyBullets.forEach((bullet, bulletIndex) => {
      if (bullet.x < player.x + player.width &&
          bullet.x + bullet.width > player.x &&
          bullet.y < player.y + player.height &&
          bullet.y + bullet.height > player.y) {
        
        createExplosion(player.x + player.width/2, player.y + player.height/2, '#00ff00');
        enemyBullets.splice(bulletIndex, 1);
        setLives(prev => {
          const newLives = prev - 1;
          if (newLives <= 0) {
            setGameState('gameOver');
          }
          return newLives;
        });
        playHitSound();
      }
    });
    
    // Check if all enemies destroyed
    if (enemies.length === 0) {
      setLevel(prev => prev + 1);
      createEnemyFormation();
    }
  }, [gameState, keys, createExplosion, playExplosionSound, playHitSound, createEnemyFormation]);

  // Render game
  const render = useCallback(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    const { player, bullets, enemies, enemyBullets, particles } = gameObjects.current;
    
    // Clear canvas
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, 800, 600);
    
    // Draw stars
    ctx.fillStyle = '#ffffff';
    for (let i = 0; i < 50; i++) {
      const x = (i * 37) % 800;
      const y = (i * 23) % 600;
      ctx.fillRect(x, y, 1, 1);
    }
    
    if (gameState === 'playing') {
      // Draw player
      ctx.fillStyle = '#00ff00';
      ctx.fillRect(player.x, player.y, player.width, player.height);
      
      // Draw bullets
      bullets.forEach(bullet => {
        ctx.fillStyle = bullet.color;
        ctx.fillRect(bullet.x, bullet.y, bullet.width, bullet.height);
      });
      
      // Draw enemies
      enemies.forEach(enemy => {
        ctx.fillStyle = enemyTypes[enemy.type].color;
        ctx.fillRect(enemy.x, enemy.y, enemy.width, enemy.height);
      });
      
      // Draw enemy bullets
      enemyBullets.forEach(bullet => {
        ctx.fillStyle = bullet.color;
        ctx.fillRect(bullet.x, bullet.y, bullet.width, bullet.height);
      });
      
      // Draw particles
      particles.forEach(particle => {
        ctx.fillStyle = particle.color;
        ctx.globalAlpha = particle.life / 30;
        ctx.fillRect(particle.x, particle.y, 2, 2);
        ctx.globalAlpha = 1;
      });
    }
  }, [gameState]);

  // Game loop
  useEffect(() => {
    const gameLoop = () => {
      update();
      render();
      gameLoopRef.current = requestAnimationFrame(gameLoop);
    };
    
    gameLoop();
    
    return () => {
      if (gameLoopRef.current) {
        cancelAnimationFrame(gameLoopRef.current);
      }
    };
  }, [update, render]);

  // Input handling
  useEffect(() => {
    const handleKeyDown = (e) => {
      setKeys(prev => ({ ...prev, [e.code]: true }));
      
      if (e.code === 'Space') {
        e.preventDefault();
        shoot();
      }
    };
    
    const handleKeyUp = (e) => {
      setKeys(prev => ({ ...prev, [e.code]: false }));
    };
    
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);
    
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('keyup', handleKeyUp);
    };
  }, [shoot]);

  // Initialize game
  useEffect(() => {
    createEnemyFormation();
  }, [createEnemyFormation]);

  const restartGame = () => {
    setGameState('playing');
    setScore(0);
    setLives(3);
    setLevel(1);
    gameObjects.current.bullets = [];
    gameObjects.current.enemyBullets = [];
    gameObjects.current.particles = [];
    gameObjects.current.player.x = 385;
    gameObjects.current.player.y = 550;
    createEnemyFormation();
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-black text-white font-mono">
      {/* UI Header */}
      <div className="flex justify-between items-center w-[800px] mb-4 text-lg font-bold">
        <div className="flex items-center gap-2">
          <Trophy className="text-yellow-400" size={20} />
          <span>SCORE: {score}</span>
        </div>
        <div className="flex items-center gap-2">
          <Heart className="text-red-400" size={20} />
          <span>LIVES: {lives}</span>
        </div>
        <div>LEVEL: {level}</div>
      </div>

      {/* Game Canvas */}
      <div className="relative">
        <canvas
          ref={canvasRef}
          width={800}
          height={600}
          className="border-2 border-green-400 bg-black"
        />
        
        {/* Game Over Overlay */}
        {gameState === 'gameOver' && (
          <div className="absolute inset-0 bg-black bg-opacity-80 flex flex-col items-center justify-center">
            <h2 className="text-4xl font-bold text-red-400 mb-4">GAME OVER</h2>
            <p className="text-xl mb-4">SCORE FINAL: {score}</p>
            <button
              onClick={restartGame}
              className="flex items-center gap-2 px-6 py-3 bg-green-600 hover:bg-green-700 rounded-lg font-bold"
            >
              <RotateCcw size={20} />
              REINICIAR
            </button>
          </div>
        )}
      </div>

      {/* Controls */}
      <div className="flex items-center justify-between w-[800px] mt-4">
        <div className="text-sm text-green-400">
          ← → MOVER | ESPACIO <Zap className="inline" size={16} /> DISPARAR
        </div>
        
        <div className="flex gap-2">
          <button
            onClick={() => setSoundEnabled(!soundEnabled)}
            className="p-2 bg-gray-700 hover:bg-gray-600 rounded"
          >
            {soundEnabled ? <Volume2 size={20} /> : <VolumeX size={20} />}
          </button>
          
          {gameState === 'gameOver' && (
            <button
              onClick={restartGame}
              className="flex items-center gap-1 px-3 py-2 bg-green-600 hover:bg-green-700 rounded"
            >
              <RotateCcw size={16} />
              R
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default GalagaGame;