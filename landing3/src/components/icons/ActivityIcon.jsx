"use client";

import { cn } from "../lib/utils";
import { motion, useAnimation, useReducedMotion } from "framer-motion";
import { forwardRef, useCallback, useImperativeHandle, useRef } from "react";

const ActivityIcon = forwardRef(
    (
        {
            onMouseEnter,
            onMouseLeave,
            className,
            size = 24,
            duration = 1,
            isAnimated = true,
            ...props
        },
        ref,
    ) => {
        const controls = useAnimation();
        const reduced = useReducedMotion();
        const isControlled = useRef(false);

        useImperativeHandle(ref, () => {
            isControlled.current = true;
            return {
                startAnimation: () =>
                    reduced ? controls.start("normal") : controls.start("animate"),
                stopAnimation: () => controls.start("normal"),
            };
        });

        const handleEnter = useCallback(
            (e) => {
                if (!isAnimated || reduced) return;
                if (!isControlled.current) controls.start("animate");
                else onMouseEnter?.(e);
            },
            [controls, reduced, isAnimated, onMouseEnter],
        );

        const handleLeave = useCallback(
            (e) => {
                if (!isControlled.current) {
                    controls.start("normal");
                } else {
                    onMouseLeave?.(e);
                }
            },
            [controls, onMouseLeave],
        );

        const activityVariants = {
            normal: {
                strokeDasharray: "none",
                strokeDashoffset: 0,
                opacity: 1,
            },
            animate: {
                strokeDasharray: "60 120",
                strokeDashoffset: [0, -180],
                transition: {
                    duration: 1.4 * duration,
                    ease: "linear",
                    repeat: Infinity,
                    repeatType: "loop",
                },
            },
        };

        return (
            <motion.div
                className={cn("inline-flex items-center justify-center", className)}
                onMouseEnter={handleEnter}
                onMouseLeave={handleLeave}
                {...props}
            >
                <motion.svg
                    xmlns="http://www.w3.org/2000/svg"
                    width={size}
                    height={size}
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    animate={controls}
                    initial="normal"
                >
                    <motion.path
                        d="M2 12h2.49a2 2 0 0 0 1.92-1.46l2.35-8.36a.25.25 0 0 1 .48 0l5.52 19.64a.25.25 0 0 0 .48 0l2.35-8.36A2 2 0 0 1 19.52 12H22"
                        variants={activityVariants}
                    />
                </motion.svg>
            </motion.div>
        );
    },
);

ActivityIcon.displayName = "ActivityIcon";
export { ActivityIcon };
