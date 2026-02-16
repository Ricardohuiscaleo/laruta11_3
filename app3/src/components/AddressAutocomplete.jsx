import React, { useState, useEffect, useRef } from 'react';
import { MapPin } from 'lucide-react';

const AddressAutocomplete = ({ value, onChange, placeholder = "Escribe tu dirección..." }) => {
  const [suggestions, setSuggestions] = useState([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const debounceTimer = useRef(null);
  const inputRef = useRef(null);

  const API_KEY = 'AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A';

  const fetchSuggestions = async (input) => {
    if (!input || input.length < 3) {
      setSuggestions([]);
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(
        `https://maps.googleapis.com/maps/api/place/autocomplete/json?input=${encodeURIComponent(input)}&components=country:cl&language=es&key=${API_KEY}`,
        { mode: 'cors' }
      );
      
      // Como la API tiene CORS, usamos un proxy PHP
      const proxyResponse = await fetch('/api/location/autocomplete_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ input, country: 'cl' })
      });

      const data = await proxyResponse.json();
      
      if (data.predictions) {
        setSuggestions(data.predictions);
        setShowSuggestions(true);
      }
    } catch (error) {
      console.error('Error fetching suggestions:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (e) => {
    const newValue = e.target.value;
    onChange(newValue);

    // Debounce para no hacer requests en cada tecla
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
    }

    debounceTimer.current = setTimeout(() => {
      fetchSuggestions(newValue);
    }, 300);
  };

  const handleSelectSuggestion = (suggestion) => {
    onChange(suggestion.description);
    setSuggestions([]);
    setShowSuggestions(false);
  };

  // Cerrar sugerencias al hacer clic fuera
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (inputRef.current && !inputRef.current.contains(e.target)) {
        setShowSuggestions(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="relative" ref={inputRef}>
      <div className="relative">
        <input
          type="text"
          value={value}
          onChange={handleInputChange}
          onFocus={() => suggestions.length > 0 && setShowSuggestions(true)}
          className="w-full px-3 py-2 pl-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
          placeholder={placeholder}
          autoComplete="off"
        />
        <MapPin className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={16} />
        {isLoading && (
          <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-orange-500"></div>
          </div>
        )}
      </div>

      {/* Sugerencias */}
      {showSuggestions && suggestions.length > 0 && (
        <div className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
          {suggestions.map((suggestion) => (
            <button
              key={suggestion.place_id}
              type="button"
              onClick={() => handleSelectSuggestion(suggestion)}
              className="w-full px-4 py-3 text-left hover:bg-orange-50 transition-colors border-b border-gray-100 last:border-b-0"
            >
              <div className="flex items-start gap-2">
                <MapPin size={16} className="text-orange-500 mt-1 flex-shrink-0" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-900 truncate">
                    {suggestion.structured_formatting?.main_text || suggestion.description}
                  </p>
                  <p className="text-xs text-gray-500 truncate">
                    {suggestion.structured_formatting?.secondary_text || ''}
                  </p>
                </div>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

export default AddressAutocomplete;
