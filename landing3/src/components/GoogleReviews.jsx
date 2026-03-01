import { useState, useEffect, useRef } from 'react';
import { Star, Quote, ExternalLink } from 'lucide-react';
import { LoaderIcon } from './ui/LoaderIcon.jsx';
import { motion } from 'framer-motion';

export default function GoogleReviews() {
    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const scrollRef = useRef(null);

    useEffect(() => {
        const fetchReviews = async () => {
            try {
                // Añadimos timestamp para evitar cache del navegador y forzar actualización tras cambio en PHP
                const response = await fetch(`https://app.laruta11.cl/api/get_google_reviews.php?t=${Date.now()}`);
                const result = await response.json();
                if (result.success) {
                    setData(result);
                }
            } catch (error) {
                console.error('Error fetching Google reviews:', error);
            } finally {
                setIsLoading(false);
            }
        };

        fetchReviews();
    }, []);

    if (isLoading) {
        return (
            <div className="flex justify-center items-center py-20 bg-ruta-dark">
                <LoaderIcon size={40} className="text-ruta-yellow" />
            </div>
        );
    }

    if (!data || !data.reviews || data.reviews.length === 0) {
        return null;
    }

    // Duplicamos las reseñas para el efecto de scroll infinito suave
    const infiniteReviews = [...data.reviews, ...data.reviews];

    return (
        <section className="py-24 bg-ruta-dark relative overflow-hidden">
            {/* Background Decor */}
            <div className="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
            <div className="absolute top-1/2 right-0 w-64 h-64 bg-ruta-yellow/5 rounded-full blur-[100px] -translate-y-1/2"></div>
            <div className="absolute top-1/4 left-0 w-48 h-48 bg-ruta-orange/5 rounded-full blur-[80px]"></div>

            <div className="container mx-auto px-6 relative z-20">
                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true }}
                    transition={{ duration: 0.8 }}
                    className="text-center mb-16"
                >
                    <div className="flex items-center justify-center gap-2 mb-4">
                        <div className="flex">
                            {[...Array(5)].map((_, i) => (
                                <Star key={i} size={18} className="fill-ruta-yellow text-ruta-yellow" />
                            ))}
                        </div>
                        <span className="text-white font-bold text-xl">{data.rating}</span>
                        <span className="text-ruta-white/40 text-sm">({data.total_ratings} reseñas en Google)</span>
                    </div>
                    <h2 className="text-3xl md:text-5xl font-extrabold text-white tracking-tighter mb-4">
                        Lo que dicen <span className="text-transparent bg-clip-text bg-gradient-to-r from-ruta-yellow to-ruta-orange">nuestros clientes</span>
                    </h2>
                </motion.div>
            </div>

            {/* Infinite Scroll Wrapper */}
            <div className="relative w-full overflow-hidden py-10">
                {/* Gradient Fades for edges */}
                <div className="absolute left-0 top-0 bottom-0 w-20 md:w-40 bg-gradient-to-r from-ruta-dark to-transparent z-10"></div>
                <div className="absolute right-0 top-0 bottom-0 w-20 md:w-40 bg-gradient-to-l from-ruta-dark to-transparent z-10"></div>

                <motion.div
                    className="flex gap-8 px-4"
                    animate={{ x: [0, -1800] }}
                    transition={{
                        x: {
                            repeat: Infinity,
                            repeatType: "loop",
                            duration: 40,
                            ease: "linear"
                        }
                    }}
                    style={{ width: "fit-content" }}
                >
                    {infiniteReviews.map((review, index) => (
                        <div
                            key={index}
                            className="w-[350px] md:w-[400px] flex-shrink-0 p-8 rounded-[2.5rem] bg-white/5 border border-white/10 hover:border-ruta-yellow/30 hover:bg-white/[0.08] transition-all duration-500 backdrop-blur-xl relative group shadow-2xl"
                        >
                            <Quote className="absolute top-6 right-8 w-12 h-12 text-white/5 group-hover:text-ruta-yellow/10 transition-colors" />

                            <div className="flex items-center gap-4 mb-6">
                                {review.profile_photo ? (
                                    <img
                                        src={review.profile_photo}
                                        alt={review.author}
                                        className="w-12 h-12 rounded-full border border-white/20 object-cover"
                                    />
                                ) : (
                                    <div className="w-12 h-12 rounded-full bg-ruta-yellow/20 flex items-center justify-center text-ruta-yellow font-bold border border-ruta-yellow/30">
                                        {review.author[0]}
                                    </div>
                                )}
                                <div>
                                    <h4 className="font-bold text-white text-sm">{review.author}</h4>
                                    <div className="flex gap-0.5">
                                        {[...Array(review.rating)].map((_, i) => (
                                            <Star key={i} size={12} className="fill-ruta-yellow text-ruta-yellow" />
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="h-[100px] overflow-hidden relative">
                                <p className="text-ruta-white/70 text-sm leading-relaxed italic">
                                    "{review.text}"
                                </p>
                                {review.text.length > 150 && (
                                    <div className="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-black/20 to-transparent"></div>
                                )}
                            </div>

                            <div className="mt-6 flex items-center justify-between">
                                <div className="text-[10px] text-ruta-white/30 uppercase tracking-widest font-bold">
                                    {review.time_description}
                                </div>
                                <div className="flex gap-1 h-3">
                                    <span className="w-1.5 h-1.5 rounded-full bg-ruta-yellow/40"></span>
                                    <span className="w-1.5 h-1.5 rounded-full bg-ruta-yellow/20"></span>
                                </div>
                            </div>
                        </div>
                    ))}
                </motion.div>
            </div>

            <div className="container mx-auto px-6 mt-12 text-center relative z-20">
                <motion.div
                    initial={{ opacity: 0 }}
                    whileInView={{ opacity: 1 }}
                    viewport={{ once: true }}
                    transition={{ delay: 0.5 }}
                >
                    <a
                        href="https://www.google.com/maps/place/?q=place_id:ChIJx1qbNL6pWpERZwHfDe5eN1o"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 text-ruta-yellow font-bold hover:text-white transition-colors group"
                    >
                        Ver todas las reseñas en Google
                        <ExternalLink size={16} className="group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" />
                    </a>
                </motion.div>
            </div>
        </section>
    );
}
