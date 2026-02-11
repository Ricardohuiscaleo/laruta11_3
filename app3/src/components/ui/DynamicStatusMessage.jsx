import React, { useState, useEffect } from 'react';

const DynamicStatusMessage = ({ isActive = true, user = null, statusData = null }) => {
  const [displayText, setDisplayText] = useState('');
  const [currentMessage, setCurrentMessage] = useState('');
  const [charIndex, setCharIndex] = useState(0);
  const [currentTime, setCurrentTime] = useState('');
  const [usedIndices, setUsedIndices] = useState([]);

  const generateJoke = (hour, minute) => {
    const userName = user ? user.nombre.split(' ')[0] : '';
    const greeting = userName ? `${userName}, ` : '';
    
    const morningJokes = [
      `${greeting}abrimos a las 18:00 🍔☀️`,
      `${greeting}reserva tu Completo Tradicional 🌭📝`,
      `${greeting}antojo de Hamburguesa Italiana? 🍔😋`,
      `${greeting}Tomahawk Cheddar te espera ⏰🥩`,
      `${greeting}Pizza Familiar ideal para hoy 🍕☕`,
    ];

    const afternoonJokes = [
      `${greeting}prueba la Hamburguesa Triple XXXL 🍔💪`,
      `${greeting}Papas Fritas recién hechas 🍟🔥`,
      `${greeting}Pizza Familiar para compartir 🍕❤️`,
      `${greeting}la Gorda es un clásico 🥪👌`,
      `${greeting}Salchipapa perfecta 🌭🍟✨`,
    ];

    const eveningJokes = [
      `${greeting}Churrasco Premium recién hecho 🥩🔥`,
      `${greeting}doble carne en Hamburguesa Doble 🍔🍔😋`,
      `${greeting}Pizza Familiar + bebida fría 🍕🥤😊`,
      `${greeting}Completo Tocino con extra queso 🌭🥓🧀`,
      `${greeting}Pichanga Familiar para todos 🍖🎉`,
    ];

    const lateNightJokes = [
      `${greeting}Barros Luco clásico chileno 🥪🇨🇱`,
      `${greeting}Cheeseburger con queso fundido 🍔🧀🔥`,
      `${greeting}Pizza + Papas combo perfecto 🍕🍟👌`,
      `${greeting}Lomito de Cerdo jugoso 🥪😋`,
      `${greeting}Hamburguesa Italiana antes de cerrar 🍔⏰`,
    ];

    const weekendJokes = [
      `${greeting}Pizza Familiar ideal para viernes 🍕🎉`,
      `${greeting}Combo Gorda para compartir 🍔🍟👥`,
      `${greeting}Papas Fritas todo el fin de semana 🍟✨`,
      `${greeting}la Tortuga perfecta para domingo 🥪😌`,
    ];

    const productJokes = [
      `${greeting}Hamburguesa Italiana 🍔💛`,
      `${greeting}Papas Fritas 🍟😍`,
      `${greeting}Completo Tradicional 🌭👌`,
      `${greeting}Hamburguesa Triple XXXL 🍔💪`,
      `${greeting}Salchipapa 🍟🌭`,
      `${greeting}Completo Tocino 🌭🥓`,
      `${greeting}Tomahawk Cheddar 🥩👑`,
      `${greeting}la Gorda 🥪😋`,
      `${greeting}Pizza Familiar 🍕❤️`,
      `${greeting}Combo Gorda 🍔🍟`,
      `${greeting}Hamburguesa Doble 🍔🍔`,
      `${greeting}Pichanga Familiar 🍖🎉`,
      `${greeting}la Tortuga 🥪✨`,
      `${greeting}Churrasco Premium 🥩🔥`,
      `${greeting}Papa Italiana 🍟🌭`,
      `${greeting}Barros Luco 🥪🇨🇱`,
      `${greeting}Ave Italiana 🍗👌`,
      `${greeting}Hass de Filete Pollo 🌭🍗`,
      `${greeting}Cheeseburger 🍔🧀`,
      `${greeting}Lomito de Cerdo 🥪🐷`,
      `${greeting}papas crujientes 🍟🔥`,
      `${greeting}combos siempre 🍔🥤`,
      `${greeting}Papa Pollito 🍟🍗`,
      `${greeting}Combo Completo Familiar 🌭👨‍👩‍👧‍👦`,
      `${greeting}cada producto su historia 🌭💫`,
    ];

    let templates;
    const dayOfWeek = new Date().getDay();
    const isWeekend = dayOfWeek === 5 || dayOfWeek === 6 || dayOfWeek === 0;

    if (isWeekend && hour >= 18) {
      templates = weekendJokes;
    } else if (hour >= 5 && hour < 12) {
      templates = morningJokes;
    } else if (hour >= 12 && hour < 17) {
      templates = afternoonJokes;
    } else if (hour >= 18 && hour < 23) {
      templates = eveningJokes;
    } else if (hour >= 23 || hour < 3) {
      templates = lateNightJokes;
    } else {
      templates = productJokes;
    }

    let availableIndices = templates.map((_, i) => i).filter(i => !usedIndices.includes(i));
    if (availableIndices.length === 0) {
      setUsedIndices([]);
      availableIndices = templates.map((_, i) => i);
    }
    const randomIndex = availableIndices[Math.floor(Math.random() * availableIndices.length)];
    setUsedIndices(prev => [...prev, randomIndex].slice(-Math.floor(templates.length / 2)));
    return templates[randomIndex];
  };

  const getMessage = () => {
    const now = new Date();
    const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
    const hours = chileTime.getHours();
    const minutes = chileTime.getMinutes();

    if (hours >= 3 && hours < 5) {
      return `⏰ Abrimos a las 18:00 - Descansa un poco 😴`;
    }

    // Usar statusData si está disponible
    const isOpen = statusData ? (isActive && statusData.is_open) : false;
    
    // Estado intermedio: abre hoy
    if (statusData && statusData.status === 'opens_today' && statusData.next_open_time) {
      const userName = user ? user.nombre.split(' ')[0] + ', ' : '';
      return `${userName}abrimos a las ${statusData.next_open_time} 🕐✨`;
    }

    if (isOpen) {
      return generateJoke(hours, minutes);
    } else {
      const userName = user ? user.nombre.split(' ')[0] : '';
      return generateJoke(hours, minutes);
    }
  };

  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      const chileTime = new Date(now.toLocaleString('en-US', { timeZone: 'America/Santiago' }));
      const hours = chileTime.getHours().toString().padStart(2, '0');
      const minutes = chileTime.getMinutes().toString().padStart(2, '0');
      setCurrentTime(`${hours}:${minutes}`);
    };

    updateTime();
    const timeInterval = setInterval(updateTime, 1000);

    return () => clearInterval(timeInterval);
  }, []);

  useEffect(() => {
    const updateMessage = () => {
      const newMessage = getMessage();
      setCurrentMessage(newMessage);
      setCharIndex(0);
      setDisplayText('');
    };

    updateMessage();
    const interval = setInterval(updateMessage, 10000); // 10 segundos

    return () => clearInterval(interval);
  }, [isActive, user, statusData]);

  useEffect(() => {
    if (charIndex < currentMessage.length) {
      const timeout = setTimeout(() => {
        setDisplayText(currentMessage.slice(0, charIndex + 1));
        setCharIndex(charIndex + 1);
      }, 40);
      return () => clearTimeout(timeout);
    }
  }, [charIndex, currentMessage]);

  if (!displayText && !currentTime) return null;

  if (!isActive || (statusData && statusData.status === 'closed')) {
    return (
      <div className="flex items-center gap-2 bg-yellow-400 text-black font-extrabold text-[10px] sm:text-xs px-2 py-0.5 rounded animate-pulse">
        <span>{currentTime}</span>
        <span>•</span>
        <span>🚨 CERRADO POR HOY 🚨</span>
      </div>
    );
  }

  return (
    <div className="flex items-center gap-2 text-black font-bold text-[10px] sm:text-xs whitespace-nowrap overflow-hidden">
      <span className="text-orange-600 font-extrabold">{currentTime}</span>
      <span className="text-gray-400">•</span>
      <span>
        {Array.from(displayText).map((char, index) => {
          const isEmoji = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/u.test(char);
          const isTyping = index === charIndex - 1;
          
          return (
            <span
              key={index}
              className={isEmoji ? '' : (isTyping ? 'text-orange-500 font-extrabold' : 'text-black')}
            >
              {char}
            </span>
          );
        })}
      </span>
    </div>
  );
};

export default DynamicStatusMessage;
