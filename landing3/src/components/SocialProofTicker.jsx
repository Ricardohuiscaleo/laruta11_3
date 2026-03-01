import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { TrendingUp, Users, PhoneCall } from 'lucide-react';

export default function SocialProofTicker() {
    const [stats, setStats] = useState(null);
    const [currentIndex, setCurrentIndex] = useState(0);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const response = await fetch('https://app.laruta11.cl/api/get_google_performance.php');
                const result = await response.json();
                if (result.success) {
                    setStats(result.data);
                }
            } catch (error) {
                console.error('Error fetching social proof:', error);
            }
        };
        fetchStats();
    }, []);

    const displayStats = stats || { views: 1250, calls: 154, clicks: 89 };

    const messages = [
        {
            icon: <Users className="w-4 h-4 text-ruta-yellow" />,
            text: `+${displayStats.views.toLocaleString()} personas nos buscaron en Google Maps este mes`,
            id: 'views'
        },
        {
            icon: <PhoneCall className="w-4 h-4 text-ruta-yellow" />,
            text: `${displayStats.calls} clientes nos llamaron directo desde Google`,
            id: 'calls'
        },
        {
            icon: <TrendingUp className="w-4 h-4 text-ruta-yellow" />,
            text: "¡Somos el Foodtruck #1 en búsquedas locales!",
            id: 'rank'
        }
    ];

    useEffect(() => {
        const timer = setInterval(() => {
            setCurrentIndex((prev) => (prev + 1) % messages.length);
        }, 5000);
        return () => clearInterval(timer);
    }, [messages.length]);

    return (
        <div className="bg-ruta-black/40 backdrop-blur-md border border-white/5 rounded-full px-6 py-2 flex items-center gap-3 overflow-hidden max-w-lg mx-auto md:mx-0">
            <div className="flex-shrink-0 relative">
                <div className="absolute inset-0 bg-ruta-yellow blur-sm opacity-30 animate-pulse"></div>
                <div className="relative w-2 h-2 rounded-full bg-ruta-yellow"></div>
            </div>

            <div className="relative h-6 flex-grow overflow-hidden">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={currentIndex}
                        initial={{ y: 20, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        exit={{ y: -20, opacity: 0 }}
                        transition={{ duration: 0.5, ease: "easeOut" }}
                        className="flex items-center gap-2 text-ruta-white/80 text-sm font-medium whitespace-nowrap"
                    >
                        {messages[currentIndex].icon}
                        <span>{messages[currentIndex].text}</span>
                    </motion.div>
                </AnimatePresence>
            </div>
        </div>
    );
}
