// Loader para el componente SmartAnalysis
(function() {
  'use strict';
  
  // Esperar a que React esté disponible
  function waitForReact(callback) {
    if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined') {
      callback();
    } else {
      setTimeout(() => waitForReact(callback), 100);
    }
  }
  
  waitForReact(() => {
    const { useState, useEffect } = React;
    
    // Componente SmartAnalysis
    function SmartAnalysis({ metrics }) {
      const [analysis, setAnalysis] = useState({ emoji: '🤔', text: 'Analizando métricas...' });
      
      useEffect(() => {
        if (!metrics) return;
        
        // Cargar datos del mes anterior
        fetch('/api/get_previous_month_summary.php?t=' + Date.now())
          .then(r => r.json())
          .then(data => {
            const prevMonth = data.success ? data.data : null;
            const result = generateAnalysis(metrics, prevMonth);
            setAnalysis(result);
          })
          .catch(() => {
            const result = generateAnalysis(metrics, null);
            setAnalysis(result);
          });
      }, [metrics]);
      
      const generateAnalysis = (m, prev) => {
        // Contexto temporal: ajustar expectativas según día del mes
        const isEarlyMonth = m.daysPassed <= 3;
        const isMidMonth = m.daysPassed > 3 && m.daysPassed <= 20;
        const isLateMonth = m.daysPassed > 20;
        
        // PRIORIDAD 1: Día 1-3 del mes - Siempre mostrar análisis de inicio
        if (isEarlyMonth) {
          // Usar datos del mes anterior si están disponibles en métricas
          if (m.previousMonthSales && m.previousMonthSales > 0) {
            const prevMargin = (m.previousMonthMargin || 0).toFixed(1);
            const prevTicket = Math.round(m.previousMonthTicket || 0);
            const prevOrders = m.previousMonthOrders || 0;
            
            return { emoji: '🌅', text: `<strong>Inicio de Mes - Lecciones del Pasado</strong><br><br>Día ${m.daysPassed} del nuevo mes 📅. <strong>Mes anterior:</strong> Cerraste con $${new Intl.NumberFormat('es-CL').format(Math.round(m.previousMonthSales))} (${prevOrders} pedidos, ticket $${new Intl.NumberFormat('es-CL').format(prevTicket)}, margen ${prevMargin}%) 📊. <strong>Aprendizajes:</strong> ${m.previousMonthMargin < 45 ? 'El mes pasado el margen estuvo bajo, cuida los costos este mes 💰' : 'Buen margen el mes pasado, mantenlo 💪'}. ${m.previousMonthTicket < 8500 ? 'Ticket promedio bajo, enfócate en upselling desde ya 🎯' : 'Buen ticket promedio, replica la estrategia ✅'}. Meta diaria: $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))} 🚀.` };
          }
          return { emoji: '🌅', text: `<strong>Inicio de Mes</strong><br><br>Recién arrancando el mes 📅 (día ${m.daysPassed}). Con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos hasta ahora, es normal que los números se vean bajos 📊. Tu meta diaria es $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))} 💰. Los primeros días suelen ser lentos 🐢, pero no te confíes: establece el ritmo desde ya 🎯. Activa marketing temprano 📣, confirma inventario 📦, y asegúrate que el equipo esté motivado 💪. Un buen inicio marca la diferencia para todo el mes 🚀.` };
        }
        
        if (m.monthlyProgress >= 100 && m.dailyPercent >= 100 && m.ticketPercent >= 100) {
          return { emoji: '🚀', text: `<strong>¡Modo Cohete Activado!</strong><br><br>Estás en fuego 🔥🔥🔥 - Meta mensual cumplida ✅ (${m.monthlyProgress}%), ventas diarias superando expectativas 📈 (${m.dailyPercent}%), y ticket promedio por las nubes 💰 (${m.ticketPercent}%). Con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos y un margen del ${m.marginPercent.toFixed(1)}% 💪, estás dominando el juego 🎮. Si sigues así, podrías considerar abrir una segunda sucursal 🏪... o simplemente disfrutar el éxito con una buena cerveza 🍺. Lo que sea que estés haciendo, NO LO CAMBIES 🎯.` };
        }
        // Contexto crítico en días finales
        if (isLateMonth && m.monthlyProgress < 80) {
          const daysLeft = m.daysRemaining;
          const gapPercent = (100 - m.monthlyProgress).toFixed(1);
          return { emoji: '⏰', text: `<strong>Últimos Días del Mes - Situación Urgente</strong><br><br>Estamos en el día ${m.daysPassed} y solo llevas ${m.monthlyProgress}% de tu meta 🚨. Te quedan ${daysLeft} días para cerrar una brecha del ${gapPercent}% ($${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))}) 💸. Esto requiere $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día cuando tu promedio actual es $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales / m.daysPassed))} ⚡. A esta altura del mes, no hay tiempo para estrategias lentas 🐌. Necesitas acción inmediata: promo flash HOY 🔥, contacta TODOS tus clientes frecuentes 📞, ofrece descuentos agresivos (15-25% OFF) 🎯, extiende horarios si es posible 🕐, y considera delivery gratis para cerrar ventas 🚚. Es ahora o nunca ⏳.` };
        }
        
        if (m.monthlyProgress < 70 && m.ticketPercent > 100) {
          const avgTicket = (m.totalSales / (m.totalSales / 11000)) || 11000;
          const ordersNeeded = Math.ceil(m.salesRemaining / avgTicket);
          const ordersPerDay = Math.ceil(ordersNeeded / m.daysRemaining);
          return { emoji: '🤨', text: `<strong>Alto Ticket, Bajo Volumen</strong><br><br>Tienes un problema interesante 🧐: tus clientes están gastando un ${Math.round(m.ticketPercent)}% más de lo esperado (ticket promedio de $${new Intl.NumberFormat('es-CL').format(Math.round(avgTicket))} 💰), lo que significa que cuando alguien entra, compra bien. El tema es que solo llevas ${m.monthlyProgress}% de tu meta mensual con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos. ¿El diagnóstico? 🔍 No es que vendas mal, es que te faltan clientes. Con solo ${Math.ceil(m.totalSales / avgTicket)} pedidos hasta ahora, necesitas urgente subir el volumen 📈. Para llegar a tu meta, necesitas hacer ${ordersNeeded} pedidos más en los próximos ${m.daysRemaining} días, o sea, unos ${ordersPerDay} pedidos diarios vendiendo $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))} por día. La solución no es subir precios (ya están bien ✅), sino traer más gente: activa redes sociales 📱, lanza promos flash ⚡, haz alianzas con apps de delivery 🛵, extiende horarios 🕐 o regala el envío 🎁. Tu producto funciona, solo necesitas más ojos viéndolo 👀.` };
        }
        // Alerta temprana en mitad de mes
        if (isMidMonth && m.monthlyProgress < 50) {
          return { emoji: '⚠️', text: `<strong>Alerta Temprana - Mitad de Mes</strong><br><br>Estamos en el día ${m.daysPassed} (mitad de mes) y solo llevas ${m.monthlyProgress}% de tu meta 📉. Esto es una señal de alerta temprana 🚨. Con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos, necesitas $${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))} más en ${m.daysRemaining} días 📅. La buena noticia: AÚN tienes tiempo para corregir el rumbo 🔄. La mala: cada día que pasa, la presión aumenta ⏰. Analiza QUÉ está fallando 🔍: ¿Poco tráfico? ¿Ticket bajo? ¿Competencia? Ajusta tu estrategia AHORA 🎯, no esperes a fin de mes. Considera: marketing intensivo 📣, revisar precios 💰, mejorar servicio ⭐, o lanzar promociones 🎁. Tienes ${m.daysRemaining} días para cambiar la historia 📖.` };
        }
        
        // Solo mostrar situación crítica si NO es inicio de mes
        if (!isEarlyMonth && m.monthlyProgress < 60 && m.dailyPercent < 80 && m.ticketPercent < 90) {
          return { emoji: '🛑', text: `<strong>Situación Crítica</strong><br><br>Houston, tenemos un problema 🚨. Y no es uno solo: meta mensual apenas al ${m.monthlyProgress}% 🔴, ventas diarias al ${m.dailyPercent}% 📉, y ticket promedio al ${m.ticketPercent}% ⚠️. Estás vendiendo $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} cuando deberías llevar mucho más a esta altura del mes, o sea, te faltan $${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))} 💸 en solo ${m.daysRemaining} días. Para recuperarte, necesitas vender $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))} diarios cuando tu promedio actual es mucho menor. Esto no se arregla solo ⏰. Necesitas acción inmediata: reúne al equipo HOY ☕, revisa si puedes mejorar márgenes sin sacrificar calidad 🔧, lanza una promo agresiva (2x1, descuentos, lo que sea) 🎯, contacta a tus clientes frecuentes con ofertas exclusivas 📞, y considera recortar gastos no esenciales temporalmente 💰. Es momento de tomar decisiones difíciles antes de que sea demasiado tarde 🆘.` };
        }
        if (m.marginPercent > 50 && m.monthlyProgress < 70) {
          return { emoji: '🧐', text: `<strong>El Dilema del Margen</strong><br><br>Tienes un margen brutal del ${m.marginPercent.toFixed(1)}% 💪💰, lo que significa que cuando vendes, ganas bien. Pero aquí está el problema 🤔: las ventas solo van al ${m.monthlyProgress}% de tu meta con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos. ¿Precios muy altos? 💸 ¿Poca demanda? 📉 ¿Competencia más barata? 🏪 Tienes dos opciones: 1️⃣ Bajar precios estratégicamente para mover volumen (sacrificas margen pero ganas en cantidad), o 2️⃣ Invertir fuerte en marketing para justificar tu precio premium 📣. Con ese margen, puedes permitirte promos agresivas y aún así ganar. Considera descuentos del 15-20% para activar demanda 🎯.` };
        }
        if (m.rotacionPercent < 50) {
          return { emoji: '🐌', text: `<strong>Inventario Dormido</strong><br><br>Rotación del ${m.rotacionPercent}% es preocupante 😰. Tienes productos durmiéndose en el almacén 😴📦, lo que significa que tu plata está atrapada en stock que no se mueve 💸. Esto es peligroso porque: 1️⃣ Pierdes liquidez 💰, 2️⃣ Riesgo de vencimiento 📅, 3️⃣ Espacio desperdiciado 📏. Revisa qué no se vende 🔍 y toma acción inmediata: descuentos agresivos (30-50% OFF) 🏷️, crea combos para mover stock lento 🎁, ofrece 2x1 en productos próximos a vencer ⚡, o considera donaciones para el karma (y deducción de impuestos) ✨. Mejor vender barato que botar 🗑️.` };
        }
        if (m.monthlyProgress >= 80 && m.monthlyProgress < 100) {
          const percentRemaining = (100 - m.monthlyProgress).toFixed(1);
          return { emoji: '🎯', text: `<strong>Sprint Final</strong><br><br>Estás tan cerca que casi puedes tocar la meta 🏆: llevas ${m.monthlyProgress}% completado, solo te falta un ${percentRemaining}% más. Con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos, necesitas otros $${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))} 💵 en ${m.daysRemaining} días, o sea, $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))} diarios. Esto es totalmente alcanzable ✅. Es momento del sprint final 🏃: lanza una promo de "últimos días del mes" ⏳, contacta a esos clientes que hace tiempo no compran con una oferta irresistible 📧, haz push agresivo en redes con sentido de urgencia 📣, motiva al equipo con un bonus si llegan a la meta 💪, y considera descuentos por volumen o combos especiales 🎁. Estás a nada de lograrlo, no aflojes ahora 🚀.` };
        }
        if (m.ticketPercent < 80 && m.monthlyProgress >= 70) {
          const ticketGap = (100 - m.ticketPercent).toFixed(1);
          const potentialExtra = Math.round(m.totalSales * (ticketGap / 100));
          const estimatedOrders = Math.ceil(m.totalSales / 8500);
          return { emoji: '💸', text: `<strong>Volumen Alto, Ticket Bajo</strong><br><br>Tienes tráfico, eso es bueno 👍: unos ${estimatedOrders} pedidos hasta ahora. El problema es que cada cliente está gastando menos de lo esperado (ticket al ${m.ticketPercent}% del objetivo 📊). Haz las cuentas 🧮: si logras que cada cliente gaste solo un ${ticketGap}% más, estarías generando $${new Intl.NumberFormat('es-CL').format(potentialExtra)} adicionales 💰. ¿Cómo lo haces? Upselling inteligente 🎯: ofrece combos con "agrega X por solo $Y más" 🍔, entrena al cajero para sugerir productos complementarios ("¿quieres papas con eso?" 🍟), pon los items premium bien visibles en la caja 👀, crea promos de "lleva 2 y ahorra" 🎁, y destaca los especiales del día ⭐. No necesitas más clientes, necesitas que los que ya tienes gasten un poco más 📈. Es la forma más fácil de crecer sin invertir en marketing 🚀.` };
        }
        // Contexto según fase del mes (solo si no es inicio)
        if (isMidMonth) {
          return { emoji: '📊', text: `<strong>Mitad de Mes - Ritmo Estable</strong><br><br>Día ${m.daysPassed}, llevas ${m.monthlyProgress}% de tu meta 📈. Con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos y ritmo diario al ${m.dailyPercent}% ⚡, vas por buen camino 👍. Margen del ${m.marginPercent.toFixed(1)}% saludable 💪. Necesitas $${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día 💰 para cerrar bien. Estás en la zona de control 🎮: mantén la consistencia 📅, cuida la calidad 🌟, y prepara el sprint final 🏃. Quedan ${m.daysRemaining} días, suficiente para ajustar si es necesario 🔧.` };
        } else {
          return { emoji: '📊', text: `<strong>Recta Final - Mantén el Ritmo</strong><br><br>Día ${m.daysPassed}, llevas ${m.monthlyProgress}% de tu meta 📈. Con $${new Intl.NumberFormat('es-CL').format(Math.round(m.totalSales))} vendidos, necesitas $${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))} más en ${m.daysRemaining} días 📅. Ritmo diario ${m.dailyPercent}% ⚡, margen ${m.marginPercent.toFixed(1)}% 💪. Estás en la recta final 🏁: cada día cuenta doble ahora ⏰. Mantén el foco 🎯, no bajes la guardia 🛡️, y asegura cada venta 💰. Si mantienes el ritmo actual, cerrarás bien ✅. Si puedes acelerar, mejor 🚀.` };
        }
      }
      
      return React.createElement('div', {
        style: { background: '#ffffff', border: '2px solid #000000', borderRadius: '12px', padding: '20px', marginBottom: '24px' }
      }, [
        React.createElement('div', {
          key: 'header',
          style: { display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px' }
        }, [
          React.createElement('div', { key: 'emoji', style: { fontSize: '24px' } }, analysis.emoji),
          React.createElement('div', { key: 'title', style: { fontSize: '16px', fontWeight: '700', color: '#000000' } }, 'Análisis del Negocio')
        ]),
        React.createElement('div', {
          key: 'content',
          style: { color: '#000000', fontSize: '14px', lineHeight: '1.6' },
          dangerouslySetInnerHTML: { __html: analysis.text }
        })
      ]);
    }
    
    // Función global para renderizar
    window.renderSmartAnalysis = function(metrics) {
      const root = document.getElementById('smart-analysis-root');
      if (root && metrics) {
        ReactDOM.render(React.createElement(SmartAnalysis, { metrics }), root);
      }
    };
  });
})();
