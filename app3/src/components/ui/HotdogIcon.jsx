import React from 'react';

const HotdogIcon = ({ size = 24, color = "currentColor", ...props }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="none"
    stroke={color}
    strokeWidth="2.0"
    strokeLinecap="round"
    strokeLinejoin="round"
    {...props}
  >
    <path d="M2 15 C 2 11, 5 10, 12 10 s 10 1, 10 5 s -4 5, -10 5 S 2 19, 2 15 Z" />
    <path d="M1 15 c 0 -2.5, 3 -4, 11 -4 s 11 1.5, 11 4" />
    <path d="M5 10 q 3.5 -4, 7 0 t 7 0" />
  </svg>
);

export default HotdogIcon;