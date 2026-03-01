import { useState, useEffect } from 'react';
import { Star, Quote, ExternalLink } from 'lucide-react';
import { LoaderIcon } from './ui/LoaderIcon.jsx';

export default function GoogleReviews() {
    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const fetchReviews = async () => {
            try {
                const response = await fetch('https://app.laruta11.cl/api/get_google_reviews.php');
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

    return (
        <section className="py-24 bg-ruta-dark relative overflow-hidden">
            {/* Background Decor */}
            <div className="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
            <div className="absolute top-1/2 right-0 w-64 h-64 bg-ruta-yellow/5 rounded-full blur-[100px] -translate-y-1/2"></div>

            <div className="container mx-auto px-6 relative z-10">
                <div className="text-center mb-16">
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
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {data.reviews.slice(0, 3).map((review, index) => (
                        <div
                            key={index}
                            className="group p-8 rounded-[2.5rem] bg-white/5 border border-white/10 hover:border-ruta-yellow/30 transition-all duration-500 backdrop-blur-xl relative"
                        >
                            <Quote className="absolute top-6 right-8 w-12 h-12 text-white/5 group-hover:text-ruta-yellow/10 transition-colors" />

                            <div className="flex items-center gap-4 mb-6">
                                {review.profile_photo ? (
                                    <img src={review.profile_photo} alt={review.author} className="w-12 h-12 rounded-full border border-white/20" />
                                ) : (
                                    <div className="w-12 h-12 rounded-full bg-ruta-yellow/20 flex items-center justify-center text-ruta-yellow font-bold">
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

                            <p className="text-ruta-white/70 text-sm leading-relaxed mb-6 italic line-clamp-4">
                                "{review.text}"
                            </p>

                            <div className="text-[10px] text-ruta-white/30 uppercase tracking-widest font-bold">
                                {review.time_description}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="mt-16 text-center">
                    <a
                        href="https://maps.app.goo.gl/8RM68ErBdwgl3pkUE"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 text-ruta-yellow font-bold hover:text-white transition-colors group"
                    >
                        Ver todas las reseñas en Google
                        <ExternalLink size={16} className="group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" />
                    </a>
                </div>
            </div>
        </section>
    );
}
