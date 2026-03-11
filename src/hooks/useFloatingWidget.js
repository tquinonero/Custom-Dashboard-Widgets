import { useState, useEffect, useRef, useCallback } from '@wordpress/element';

const STORAGE_KEY_POSITION = 'cdw_floating_position';
const STORAGE_KEY_VISIBLE = 'cdw_floating_visible';
const DEFAULT_POSITION = { x: 20, y: 20 };

export default function useFloatingWidget(shortcutKey = 'C') {
    const [isVisible, setIsVisible] = useState(() => {
        try {
            return localStorage.getItem(STORAGE_KEY_VISIBLE) === 'true';
        } catch (e) {
            return false;
        }
    });

    const [position, setPosition] = useState(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY_POSITION);
            return saved ? JSON.parse(saved) : DEFAULT_POSITION;
        } catch (e) {
            return DEFAULT_POSITION;
        }
    });

    const [isDragging, setIsDragging] = useState(false);
    const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 });
    const widgetRef = useRef(null);

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY_VISIBLE, isVisible);
        } catch (e) { /* ignore */ }
    }, [isVisible]);

    useEffect(() => {
        try {
            localStorage.setItem(STORAGE_KEY_POSITION, JSON.stringify(position));
        } catch (e) { /* ignore */ }
    }, [position]);

    useEffect(() => {
        const handleKeyDown = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toUpperCase() === shortcutKey) {
                e.preventDefault();
                setIsVisible(prev => !prev);
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [shortcutKey]);

    const handleMouseDown = useCallback((e) => {
        if (e.target.closest('.cdw-command-header') && !e.target.closest('button')) {
            setIsDragging(true);
            const rect = widgetRef.current.getBoundingClientRect();
            setDragOffset({
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            });
        }
    }, []);

    useEffect(() => {
        if (!isDragging) return;

        const handleMouseMove = (e) => {
            setPosition({
                x: e.clientX - dragOffset.x,
                y: e.clientY - dragOffset.y
            });
        };

        const handleMouseUp = () => {
            setIsDragging(false);
        };

        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);

        return () => {
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
        };
    }, [isDragging, dragOffset]);

    const toggle = useCallback(() => {
        setIsVisible(prev => !prev);
    }, []);

    const close = useCallback(() => {
        setIsVisible(false);
    }, []);

    return {
        isVisible,
        position,
        isDragging,
        widgetRef,
        toggle,
        close,
        handleMouseDown,
    };
}
