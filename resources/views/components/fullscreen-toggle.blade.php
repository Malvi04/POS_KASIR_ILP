<div x-data="{
        isFullscreen: false,
        isSupported: true,
        showHint: false,
        hintTimeout: null,
        bottomOffset: 20, // default 20px (bottom-5)
        checkTimer: null,

        // Dragging & Custom Positioning State
        isDragging: false,
        hasDragged: false,
        dragStartX: 0,
        dragStartY: 0,
        initialX: 0,
        initialY: 0,
        posX: null,
        posY: null,

        init() {
            this.checkFullscreen();
            document.addEventListener('fullscreenchange', () => this.checkFullscreen());
            document.addEventListener('webkitfullscreenchange', () => this.checkFullscreen());
            document.addEventListener('mozfullscreenchange', () => this.checkFullscreen());
            document.addEventListener('MSFullscreenChange', () => this.checkFullscreen());

            // Check if Fullscreen API is available
            this.isSupported = !!(
                document.fullscreenEnabled ||
                document.webkitFullscreenEnabled ||
                document.mozFullScreenEnabled ||
                document.msFullscreenEnabled
            );

            // Restore custom user dragged position if previously saved
            try {
                const saved = localStorage.getItem('pos_fullscreen_pos');
                if (saved) {
                    const p = JSON.parse(saved);
                    if (typeof p.x === 'number' && typeof p.y === 'number') {
                        // Validate within viewport
                        if (p.x >= 0 && p.x <= window.innerWidth - 48 && p.y >= 0 && p.y <= window.innerHeight - 48) {
                            this.posX = p.x;
                            this.posY = p.y;
                        }
                    }
                }
            } catch (e) {}

            // Auto-detect other floating buttons in the bottom-right corner and dynamically offset
            this.adjustAutoPosition();
            this.checkTimer = setInterval(() => this.adjustAutoPosition(), 1200);

            window.addEventListener('resize', () => {
                this.adjustAutoPosition();
                if (this.posX !== null && this.posY !== null) {
                    this.posX = Math.min(this.posX, window.innerWidth - 60);
                    this.posY = Math.min(this.posY, window.innerHeight - 60);
                }
            }, { passive: true });
        },

        adjustAutoPosition() {
            if (this.posX !== null && this.posY !== null) return; // Custom drag position overrides auto-stacking

            // Detect any fixed/absolute floating buttons in the bottom-right region (except this container)
            let maxBottom = 20; // default 20px
            const selfEl = this.$el;
            const elements = document.querySelectorAll('button, a, div[role=\'button\']');

            elements.forEach(el => {
                if (selfEl.contains(el)) return;
                const rect = el.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0) return;

                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return;

                // Check if element is positioned at bottom right (within 120px from right and 150px from bottom)
                const isBottomRight = (window.innerWidth - rect.right <= 120) && (window.innerHeight - rect.bottom <= 140);
                const isFloating = (style.position === 'fixed' || style.position === 'absolute') && (rect.width >= 36 && rect.height >= 36);

                if (isBottomRight && isFloating) {
                    const elBottomSpace = window.innerHeight - rect.top + 14;
                    if (elBottomSpace > maxBottom) {
                        maxBottom = Math.min(elBottomSpace, window.innerHeight - 80);
                    }
                }
            });

            this.bottomOffset = maxBottom;
        },

        checkFullscreen() {
            this.isFullscreen = !!(
                document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.mozFullScreenElement ||
                document.msFullscreenElement
            );
        },

        toggleFullscreen() {
            if (this.isFullscreen) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        },

        enterFullscreen() {
            const el = document.documentElement;
            if (el.requestFullscreen) {
                el.requestFullscreen().catch(err => console.warn('Request fullscreen failed:', err));
            } else if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
            } else if (el.mozRequestFullScreen) {
                el.mozRequestFullScreen();
            } else if (el.msRequestFullscreen) {
                el.msRequestFullscreen();
            }
            this.flashHint();
        },

        exitFullscreen() {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(err => console.warn('Exit fullscreen failed:', err));
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            this.flashHint();
        },

        flashHint() {
            this.showHint = true;
            if (this.hintTimeout) clearTimeout(this.hintTimeout);
            this.hintTimeout = setTimeout(() => {
                this.showHint = false;
            }, 2500);
        },

        // Drag to Reposition Handling
        startDrag(e) {
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            const rect = this.$el.getBoundingClientRect();
            this.initialX = rect.left;
            this.initialY = rect.top;
            this.dragStartX = clientX;
            this.dragStartY = clientY;
            this.isDragging = true;
            this.hasDragged = false;

            const onMove = (moveEvt) => {
                if (!this.isDragging) return;
                const curX = moveEvt.touches ? moveEvt.touches[0].clientX : moveEvt.clientX;
                const curY = moveEvt.touches ? moveEvt.touches[0].clientY : moveEvt.clientY;
                const deltaX = curX - this.dragStartX;
                const deltaY = curY - this.dragStartY;

                if (Math.abs(deltaX) > 4 || Math.abs(deltaY) > 4) {
                    this.hasDragged = true;
                }

                let newX = this.initialX + deltaX;
                let newY = this.initialY + deltaY;

                // Restrict within viewport bounds
                const btnW = 48;
                const btnH = 48;
                newX = Math.max(12, Math.min(newX, window.innerWidth - btnW - 12));
                newY = Math.max(12, Math.min(newY, window.innerHeight - btnH - 12));

                this.posX = newX;
                this.posY = newY;
            };

            const onEnd = () => {
                if (!this.isDragging) return;
                this.isDragging = false;
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onEnd);
                window.removeEventListener('touchmove', onMove);
                window.removeEventListener('touchend', onEnd);

                if (this.hasDragged && this.posX !== null && this.posY !== null) {
                    try {
                        localStorage.setItem('pos_fullscreen_pos', JSON.stringify({ x: this.posX, y: this.posY }));
                    } catch (err) {}
                }
            };

            window.addEventListener('mousemove', onMove, { passive: false });
            window.addEventListener('mouseup', onEnd);
            window.addEventListener('touchmove', onMove, { passive: false });
            window.addEventListener('touchend', onEnd);
        },

        handleClick() {
            if (this.hasDragged) {
                this.hasDragged = false;
                return;
            }
            this.toggleFullscreen();
        }
     }"
     @keydown.window="
        // Shortcut: F11 or Ctrl+Shift+F (or Cmd+Shift+F on Mac)
        if ($event.key === 'F11') {
            $event.preventDefault();
            toggleFullscreen();
        } else if (($event.ctrlKey || $event.metaKey) && $event.shiftKey && ($event.key === 'F' || $event.key === 'f')) {
            $event.preventDefault();
            toggleFullscreen();
        }
     "
     x-show="isSupported"
     x-cloak
     :style="posX !== null && posY !== null
        ? `position: fixed; left: ${posX}px; top: ${posY}px; z-index: 80;`
        : `position: fixed; right: 20px; bottom: ${bottomOffset}px; z-index: 80;`"
     class="flex flex-col items-end pointer-events-none select-none transition-all duration-300 ease-out">

    <!-- Mini Feedback Toast / Shortcut Tooltip -->
    <div x-show="showHint"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
         class="mb-2 px-3 py-1.5 rounded-xl bg-slate-900/90 text-white text-[11px] font-bold shadow-lg backdrop-blur-xs flex items-center gap-1.5 border border-slate-700/50 pointer-events-auto">
        <span class="material-symbols-rounded text-[14px] text-amber-400" x-text="isFullscreen ? 'fullscreen' : 'fullscreen_exit'"></span>
        <span x-text="isFullscreen ? 'Layar Penuh Aktif (Tekan F11 / Esc untuk keluar)' : 'Keluar dari Layar Penuh'"></span>
    </div>

    <!-- Floating Action Button (FAB) (Draggable & Clickable) -->
    <div class="relative group pointer-events-auto">
        <button type="button"
                @mousedown="startDrag($event)"
                @touchstart="startDrag($event)"
                @click="handleClick()"
                :title="isFullscreen ? 'Keluar Layar Penuh (F11 / Esc / Geser untuk pindahkan)' : 'Layar Penuh / Full Screen (F11 / Geser untuk pindahkan)'"
                :aria-label="isFullscreen ? 'Keluar Layar Penuh' : 'Layar Penuh'"
                class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl flex items-center justify-center transition-all duration-200 cursor-grab active:cursor-grabbing shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 border touch-none"
                :class="isFullscreen
                    ? 'bg-amber-600 hover:bg-amber-500 text-white border-amber-400 ring-2 ring-amber-300/40 shadow-amber-900/20'
                    : 'bg-white/95 hover:bg-white text-slate-700 hover:text-[#0052cc] border-slate-200/90 backdrop-blur-md ring-1 ring-slate-900/5'">
            
            <!-- Icon Fullscreen -->
            <template x-if="!isFullscreen">
                <span class="material-symbols-rounded text-[22px] sm:text-[24px]">fullscreen</span>
            </template>

            <!-- Icon Exit Fullscreen -->
            <template x-if="isFullscreen">
                <span class="material-symbols-rounded text-[22px] sm:text-[24px]">fullscreen_exit</span>
            </template>
        </button>

        <!-- Tooltip on Hover -->
        <div class="absolute right-full mr-2.5 top-1/2 -translate-y-1/2 hidden group-hover:flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-900/95 text-white text-[11px] font-bold shadow-lg whitespace-nowrap pointer-events-none backdrop-blur-xs border border-slate-700/50 z-10">
            <span x-text="isFullscreen ? 'Keluar Layar Penuh' : 'Layar Penuh'"></span>
            <kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] font-mono text-slate-300 border border-slate-700 ml-1">F11</kbd>
        </div>
    </div>
</div>
