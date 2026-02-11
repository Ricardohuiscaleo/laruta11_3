import { useState, useEffect } from 'react';

export default function SmartAnalysis({ metrics }) {
  const [analysis, setAnalysis] = useState({ emoji: '🤔', text: 'Analizando métricas...' });

  useEffect(() => {
    if (!metrics) return;

    const result = generateAnalysis(metrics);
    setAnalysis(result);
  }, [metrics]);

  const generateAnalysis = (m) => {
    // Escenario 0: Inicio de mes (primeros 3 días) - Analizar mes ANTERIOR
    const today = new Date();
    const dayOfMonth = today.getDate();
    if (dayOfMonth <= 3 && m.totalSales < 100000) {
      // Obtener nombre del mes anterior
      const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
      const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
      const lastMonthName = monthNames[lastMonth.getMonth()];
      
      // Analizar datos del mes anterior (si están disponibles en m.previousMonthSales)
      if (m.previousMonthSales && m.previousMonthSales > 0) {
        const metaMensual = m.salesRemaining + m.totalSales; // Meta del mes actual
        const cumplimientoAnterior = (m.previousMonthSales / metaMensual) * 100;
        
        // Analizar por semanas si hay datos disponibles
        let weeklyAnalysis = '';
        if (m.previousMonthWeeks && m.previousMonthWeeks.length > 0) {
          const weeks = m.previousMonthWeeks;
          const avgWeekly = m.previousMonthSales / weeks.length;
          
          // Identificar mejor y peor semana
          const bestWeek = weeks.reduce((max, w) => w.sales > max.sales ? w : max, weeks[0]);
          const worstWeek = weeks.reduce((min, w) => w.sales < min.sales ? w : min, weeks[0]);
          
          // Analizar tendencia (primera vs última semana)
          const firstWeek = weeks[0];
          const lastWeek = weeks[weeks.length - 1];
          const trend = lastWeek.sales > firstWeek.sales ? 'creciente 📈' : 'decreciente 📉';
          
          weeklyAnalysis = `<br><br><strong>📅 Análisis Semanal de ${lastMonthName}:</strong><br>`;
          weeklyAnalysis += `• <strong>Semana ${bestWeek.week}</strong>: Mejor semana con $${new Intl.NumberFormat('es-CL').format(Math.round(bestWeek.sales))} 🏆<br>`;
          weeklyAnalysis += `• <strong>Semana ${worstWeek.week}</strong>: Más baja con $${new Intl.NumberFormat('es-CL').format(Math.round(worstWeek.sales))} ⚠️<br>`;
          weeklyAnalysis += `• <strong>Tendencia</strong>: ${trend}<br>`;
          weeklyAnalysis += `• <strong>Promedio semanal</strong>: $${new Intl.NumberFormat('es-CL').format(Math.round(avgWeekly))}<br><br>`;
          
          // Recomendaciones específicas
          weeklyAnalysis += `<strong>💡 Recomendaciones para ${monthNames[today.getMonth()]}:</strong><br>`;
          
          if (trend === 'decreciente 📉') {
            weeklyAnalysis += `• 🎯 <strong>Semana 1-2</strong>: Arranca fuerte con promos de lanzamiento. Meta: $${new Intl.NumberFormat('es-CL').format(Math.round(bestWeek.sales))}/semana<br>`;
            weeklyAnalysis += `• 🔥 <strong>Semana 3</strong>: Mitad de mes, lanza combos especiales para mantener momentum<br>`;
            weeklyAnalysis += `• 💪 <strong>Semana 4</strong>: Cierre agresivo con descuentos flash y 2x1 para recuperar<br>`;
          } else {
            weeklyAnalysis += `• ✅ <strong>Semana 1-2</strong>: Mantén el ritmo inicial. Meta: $${new Intl.NumberFormat('es-CL').format(Math.round(avgWeekly))}/semana<br>`;
            weeklyAnalysis += `• 🚀 <strong>Semana 3</strong>: Acelera con marketing digital y promos mid-month<br>`;
            weeklyAnalysis += `• 🎯 <strong>Semana 4</strong>: Cierra con todo, apunta a superar $${new Intl.NumberFormat('es-CL').format(Math.round(bestWeek.sales))}<br>`;
          }
        }
        
        if (cumplimientoAnterior >= 100) {
          return {
            emoji: '🎉',
            text: `<strong>¡Excelente cierre de ${lastMonthName}!</strong><br>Cerraste con <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.previousMonthSales))}</strong> (${cumplimientoAnterior.toFixed(0)}% de meta). ${weeklyAnalysis}Meta diaria: <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> 🚀`
          };
        } else if (cumplimientoAnterior >= 80) {
          return {
            emoji: '👍',
            text: `<strong>Buen cierre de ${lastMonthName}</strong><br>Lograste <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.previousMonthSales))}</strong> (${cumplimientoAnterior.toFixed(0)}% de meta). ${weeklyAnalysis}Meta diaria: <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> 💪`
          };
        } else {
          const diferencia = metaMensual - m.previousMonthSales;
          return {
            emoji: '📊',
            text: `<strong>Análisis de ${lastMonthName}</strong><br>Cerraste en <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.previousMonthSales))}</strong> (${cumplimientoAnterior.toFixed(0)}% de meta). Faltaron $${new Intl.NumberFormat('es-CL').format(Math.round(diferencia))}. ${weeklyAnalysis}Meta diaria: <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> 🎯`
          };
        }
      }
      
      // Fallback si no hay datos del mes anterior
      return {
        emoji: '📅',
        text: `<strong>Inicio de ${monthNames[today.getMonth()]}</strong><br>Es ${dayOfMonth === 1 ? 'el primer día' : 'el día ' + dayOfMonth} del mes. La meta es <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining + m.totalSales))}</strong>, vendiendo <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> en promedio. 🚀 ¡Que empiece el mes con todo!`
      };
    }
    
    // Escenario 1: Todo va excelente
    if (m.monthlyProgress >= 100 && m.dailyPercent >= 100 && m.ticketPercent >= 100) {
      return {
        emoji: '🚀',
        text: `<strong>¡Modo Cohete Activado!</strong><br>Estás en fuego 🔥 - Meta mensual cumplida, ventas diarias superando expectativas y ticket promedio por las nubes. Si sigues así, podrías considerar abrir una segunda sucursal... o simplemente disfrutar el éxito 🍾`
      };
    }
    
    // Escenario 2: Meta mensual en riesgo pero buen ticket
    if (m.monthlyProgress < 70 && m.ticketPercent > 100) {
      return {
        emoji: '🤨',
        text: `<strong>Situación Curiosa</strong><br>Tus clientes gastan bien ($${Math.round(m.ticketPercent)}% sobre objetivo 💰), pero... ¿dónde están todos? 👀 Necesitas <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> los próximos ${m.daysRemaining} días. Hora de activar el marketing 📣`
      };
    }
    
    // Escenario 3: Buen ritmo diario pero meta mensual atrasada
    if (m.dailyPercent >= 90 && m.monthlyProgress < 70) {
      return {
        emoji: '⏱️',
        text: `<strong>Carrera Contra el Tiempo</strong><br>Ritmo diario decente (${m.dailyPercent}%), pero el mes te quedó corto. Faltan <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))}</strong> en ${m.daysRemaining} días. Necesitas acelerar a <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> 🏃`
      };
    }
    
    // Escenario 4: Margen excelente pero ventas bajas
    if (m.marginPercent > 50 && m.monthlyProgress < 70) {
      return {
        emoji: '🧐',
        text: `<strong>El Dilema del Margen</strong><br>Margen brutal del ${m.marginPercent.toFixed(1)}% 💪, pero las ventas no acompañan (${m.monthlyProgress}% de meta). ¿Precios muy altos? ¿Poca demanda? Considera promociones estratégicas para mover volumen 📦`
      };
    }
    
    // Escenario 5: Rotación de inventario baja
    if (m.rotacionPercent < 50) {
      return {
        emoji: '🐌',
        text: `<strong>Inventario Dormido</strong><br>Rotación del ${m.rotacionPercent}% es preocupante. Tienes productos durmiéndose en el almacén 😴 Revisa qué no se vende y considera: descuentos, combos o... donaciones (para el karma ✨)`
      };
    }
    
    // Escenario 6: Todo mal
    if (m.monthlyProgress < 60 && m.dailyPercent < 80 && m.ticketPercent < 90) {
      return {
        emoji: '🛑',
        text: `<strong>Código Rojo</strong><br>Houston, tenemos un problema. Meta mensual al ${m.monthlyProgress}%, ventas diarias flojas y ticket bajo objetivo. Necesitas <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> urgente. Recomendación: reunión de emergencia ☕ + plan de acción 📝`
      };
    }
    
    // Escenario 7: Casi llegando a la meta
    if (m.monthlyProgress >= 80 && m.monthlyProgress < 100) {
      return {
        emoji: '🎯',
        text: `<strong>¡Casi Ahí!</strong><br>Estás al ${m.monthlyProgress}% de la meta. Solo faltan <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.salesRemaining))}</strong> en ${m.daysRemaining} días. Eso es <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong>. ¡Un último empujón y lo logras! 💪`
      };
    }
    
    // Escenario 8: Ticket bajo pero buen volumen
    if (m.ticketPercent < 80 && m.monthlyProgress >= 70) {
      return {
        emoji: '💸',
        text: `<strong>Volumen vs Valor</strong><br>Vendes bien pero el ticket promedio está bajo (${m.ticketPercent}%). Estrategia: upselling 🍔➕🍟, combos atractivos o sugerir extras. Cada $500 extra por pedido suma mucho al mes 📈`
      };
    }
    
    // Escenario default: Situación normal
    return {
      emoji: '📊',
      text: `<strong>Operación Normal</strong><br>Meta mensual al ${m.monthlyProgress}%, ritmo diario ${m.dailyPercent}%. Necesitas mantener <strong>$${new Intl.NumberFormat('es-CL').format(Math.round(m.dailyNeeded))}/día</strong> para cerrar el mes. Margen del ${m.marginPercent.toFixed(1)}% es saludable. Sigue así 👍`
    };
  };

  return (
    <div style={{
      background: '#ffffff',
      border: '2px solid #000000',
      borderRadius: '12px',
      padding: '20px',
      marginBottom: '24px'
    }}>
      <div style={{
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
        marginBottom: '12px'
      }}>
        <div style={{ fontSize: '24px' }}>{analysis.emoji}</div>
        <div style={{
          fontSize: '16px',
          fontWeight: '700',
          color: '#000000'
        }}>
          Análisis del Negocio
        </div>
      </div>
      <div
        style={{
          color: '#000000',
          fontSize: '14px',
          lineHeight: '1.6'
        }}
        dangerouslySetInnerHTML={{ __html: analysis.text }}
      />
    </div>
  );
}
