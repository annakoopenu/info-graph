/**
 * Metadata cloud layout — positions dots using a seeded PRNG
 * so positions are stable across page loads for the same items.
 */
(function () {
    'use strict';

    const container = document.getElementById('cloud-container');
    if (!container) return;

    const dots = Array.from(container.querySelectorAll('.cloud-dot'));
    if (dots.length === 0) return;

    // Simple seeded PRNG (mulberry32)
    function mulberry32(seed) {
        return function () {
            seed |= 0;
            seed = (seed + 0x6D2B79F5) | 0;
            let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
            t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }

    // Layout config
    const padding = 12; // px between dots
    const containerRect = container.getBoundingClientRect();
    const W = container.clientWidth;
    const H = Math.max(400, dots.length * 18); // Scale height with items
    container.style.height = H + 'px';

    // Place each dot using its data-id as seed
    const placed = []; // [{x, y, w, h}]

    dots.forEach(function (dot) {
        const id = parseInt(dot.dataset.id, 10) || 1;
        const rating = parseInt(dot.dataset.rating, 10) || 50;
        const rand = mulberry32(id * 2654435761); // spread seeds

        // Scale dot size by rating (min 60px, max 140px)
        const size = 60 + (rating / 100) * 80;
        dot.style.width = size + 'px';
        dot.style.height = size + 'px';
        dot.style.fontSize = Math.max(10, 10 + (rating / 100) * 4) + 'px';

        // Try to find a non-overlapping position (up to 80 attempts)
        let bestX = 0, bestY = 0, foundSpot = false;
        for (let attempt = 0; attempt < 80; attempt++) {
            const x = rand() * Math.max(0, W - size - padding);
            const y = rand() * Math.max(0, H - size - padding);

            let overlaps = false;
            for (const p of placed) {
                if (
                    x < p.x + p.w + padding &&
                    x + size + padding > p.x &&
                    y < p.y + p.h + padding &&
                    y + size + padding > p.y
                ) {
                    overlaps = true;
                    break;
                }
            }
            if (!overlaps) {
                bestX = x;
                bestY = y;
                foundSpot = true;
                break;
            }
            // Keep last attempt as fallback
            bestX = x;
            bestY = y;
        }

        placed.push({ x: bestX, y: bestY, w: size, h: size });
        dot.style.left = bestX + 'px';
        dot.style.top = bestY + 'px';
    });
})();
