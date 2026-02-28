import { useState, useCallback, useEffect } from 'react';

/**
 * Hook for creating resizable panels
 * @param {object} options
 * @param {number} options.initialSize - Initial width/height
 * @param {number} options.minSize - Minimum size
 * @param {number} options.maxSize - Maximum size
 * @param {'horizontal'|'vertical'} options.direction - Resize direction
 */
export function useResizable({ initialSize, minSize = 50, maxSize = 600, direction = 'horizontal' }) {
  const [size, setSize] = useState(initialSize);
  const [isResizing, setIsResizing] = useState(false);

  const startResize = useCallback((e) => {
    e.preventDefault();
    setIsResizing(true);
  }, []);

  useEffect(() => {
    if (!isResizing) return;

    const handleMouseMove = (e) => {
      if (direction === 'horizontal') {
        // For left sidebar: use clientX directly
        // For right sidebar: calculate from right edge
        // This will be handled by the component using the hook
      }
    };

    const handleMouseUp = () => {
      setIsResizing(false);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);

    return () => {
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
    };
  }, [isResizing, direction, minSize, maxSize]);

  const updateSize = useCallback((newSize) => {
    setSize(Math.min(maxSize, Math.max(minSize, newSize)));
  }, [minSize, maxSize]);

  return {
    size,
    isResizing,
    startResize,
    setIsResizing,
    updateSize,
  };
}
