import React, { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { MapPin } from 'lucide-react';

const AddressAutocomplete = ({ value, onChange, placeholder = "Escribe tu dirección...", className = "", onDeliveryFee = null }) => {
  const [suggestions, setSuggestions] = useState([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [rect, setRect] = useState(null);
  const debounceTimer = useRef(null);
  const inputRef = useRef(null);
  const wrapperRef = useRef(null);

  const updateRect = () => {
    if (inputRef.current) setRect(inputRef.current.getBoundingClientRect());
  };

  const fetchSuggestions = async (input) => {
    if (!input || input.length < 3) { setSuggestions([]); return; }
    setIsLoading(true);
    try {
      const res = await fetch('/api/location/autocomplete_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ input, country: 'cl' })
      });
      const data = await res.json();
      if (data.predictions?.length) {
        setSuggestions(data.predictions);
        setShowSuggestions(true);
        updateRect();
      } else {
        setSuggestions([]);
        setShowSuggestions(false);
      }
    } catch (e) {
      console.error('Error fetching suggestions:', e);
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (e) => {
    onChange(e.target.value);
    if (debounceTimer.current) clearTimeout(debounceTimer.current);
    debounceTimer.current = setTimeout(() => fetchSuggestions(e.target.value), 300);
  };

  const handleBlur = () => {
    if (onDeliveryFee && value && value.length > 5) {
      fetch('/api/location/get_delivery_fee.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ address: value })
      })
        .then(r => r.json())
        .then(data => { if (data.success) onDeliveryFee(data); })
        .catch(() => {});
    }
  };

  const handleSelect = (suggestion) => {
    onChange(suggestion.description);
    setSuggestions([]);
    setShowSuggestions(false);
    // Calcular tarifa dinámica si hay callback
    if (onDeliveryFee) {
      fetch('/api/location/get_delivery_fee.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ address: suggestion.description })
      })
        .then(r => r.json())
        .then(data => { if (data.success) onDeliveryFee(data); })
        .catch(() => {});
    }
  };

  useEffect(() => {
    const onClickOutside = (e) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target)) {
        setShowSuggestions(false);
      }
    };
    document.addEventListener('mousedown', onClickOutside);
    window.addEventListener('scroll', updateRect, true);
    window.addEventListener('resize', updateRect);
    return () => {
      document.removeEventListener('mousedown', onClickOutside);
      window.removeEventListener('scroll', updateRect, true);
      window.removeEventListener('resize', updateRect);
    };
  }, []);

  const dropdown = showSuggestions && suggestions.length > 0 && rect ? createPortal(
    <div style={{
      position: 'fixed',
      top: rect.bottom + 4,
      left: rect.left,
      width: rect.width,
      zIndex: 999999,
      background: 'white',
      border: '1px solid #e5e7eb',
      borderRadius: '10px',
      boxShadow: '0 8px 24px rgba(0,0,0,0.18)',
      maxHeight: '220px',
      overflowY: 'auto',
    }}>
      {suggestions.map((s) => (
        <button
          key={s.place_id}
          type="button"
          onMouseDown={(e) => { e.preventDefault(); handleSelect(s); }}
          style={{ display: 'block', width: '100%', textAlign: 'left', padding: '10px 14px', background: 'none', border: 'none', borderBottom: '1px solid #f3f4f6', cursor: 'pointer' }}
          onMouseEnter={e => e.currentTarget.style.background = '#fff7ed'}
          onMouseLeave={e => e.currentTarget.style.background = 'none'}
        >
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: '8px' }}>
            <MapPin size={14} style={{ color: '#f97316', marginTop: '2px', flexShrink: 0 }} />
            <div style={{ minWidth: 0 }}>
              <p style={{ margin: 0, fontSize: '13px', fontWeight: 600, color: '#111827', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {s.structured_formatting?.main_text || s.description}
              </p>
              {s.structured_formatting?.secondary_text && (
                <p style={{ margin: 0, fontSize: '11px', color: '#6b7280', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {s.structured_formatting.secondary_text}
                </p>
              )}
            </div>
          </div>
        </button>
      ))}
    </div>,
    document.body
  ) : null;

  return (
    <div ref={wrapperRef}>
      <div style={{ position: 'relative' }} ref={inputRef}>
        <input
          type="text"
          value={value}
          onChange={handleInputChange}
          onFocus={() => { if (suggestions.length > 0) { setShowSuggestions(true); updateRect(); } }}
          onBlur={handleBlur}
          className={className || "w-full px-3 py-2 pl-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"}
          placeholder={placeholder}
          autoComplete="off"
        />
        <MapPin style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} size={16} />
        {isLoading && (
          <div style={{ position: 'absolute', right: '10px', top: '50%', transform: 'translateY(-50%)' }}>
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-orange-500" />
          </div>
        )}
      </div>
      {dropdown}
    </div>
  );
};

export default AddressAutocomplete;
